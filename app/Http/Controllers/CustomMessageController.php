<?php

namespace App\Http\Controllers;

use App\Imports\CustomMessageRecipientsImport;
use App\Jobs\SendCustomAttendeeMessageJob;
use App\Models\CustomMessage;
use App\Models\CustomMessageRecipient;
use App\Models\Event;
use App\Models\Participant;
use App\Services\CustomMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class CustomMessageController extends Controller
{
    public function __construct(private readonly CustomMessageService $messages) {}

    public function index(Event $event): View
    {
        $this->authorize('manageMessages', $event);

        $messages = $event->customMessages()
            ->withCount([
                'recipients as sent_count' => fn ($query) => $query->where('status', CustomMessageRecipient::STATUS_SENT),
                'recipients as failed_count' => fn ($query) => $query->where('status', CustomMessageRecipient::STATUS_FAILED),
                'recipients as skipped_count' => fn ($query) => $query->where('status', CustomMessageRecipient::STATUS_SKIPPED),
                'recipients as pending_count' => fn ($query) => $query->where('status', CustomMessageRecipient::STATUS_PENDING),
            ])
            ->latest()
            ->paginate(15);

        return view('events.messages.index', compact('event', 'messages'));
    }

    public function create(Event $event): View
    {
        $this->authorize('manageMessages', $event);

        $registrants = $event->registrations()
            ->whereIn('status', ['confirmed', 'pending', 'waitlisted', 'awaiting_confirmation'])
            ->with('participant')
            ->get()
            ->pluck('participant')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        return view('events.messages.create', compact('event', 'registrants'));
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageMessages', $event);

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'mode' => ['required', Rule::in(CustomMessageService::MODES)],
            'participant_ids' => ['array'],
            'participant_ids.*' => ['integer'],
            'recipients_file' => ['nullable', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $recipients = collect();

        if (! empty($validated['participant_ids'])) {
            Participant::query()
                ->where('company_id', $event->company_id)
                ->whereIn('id', $validated['participant_ids'])
                ->get()
                ->each(function (Participant $participant) use ($recipients): void {
                    $recipients->push([
                        'participant_id' => $participant->id,
                        'name' => $participant->name,
                        'email' => $participant->email,
                        'phone' => $participant->phone,
                    ]);
                });
        }

        if ($request->hasFile('recipients_file')) {
            $import = new CustomMessageRecipientsImport;
            Excel::import($import, $request->file('recipients_file'));

            foreach ($import->rows as $row) {
                if ($row['name'] === '') {
                    continue;
                }
                $recipients->push([
                    'participant_id' => null,
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                ]);
            }
        }

        $recipients = $this->deduplicate($recipients);

        abort_if($recipients->isEmpty(), 422, 'Add at least one recipient before sending.');

        $message = $event->customMessages()->create([
            'company_id' => $event->company_id,
            'created_by' => $request->user()->id,
            'subject' => $validated['subject'] ?? null,
            'body' => $validated['body'],
            'mode' => $validated['mode'],
            'recipient_count' => $recipients->count(),
        ]);

        foreach ($recipients as $recipient) {
            $channel = $this->messages->determineChannel($validated['mode'], $recipient['email'], $recipient['phone']);

            $row = $message->recipients()->create([
                'participant_id' => $recipient['participant_id'],
                'name' => $recipient['name'],
                'email' => $recipient['email'],
                'phone' => $recipient['phone'],
                'channel' => $channel,
                'status' => $channel ? CustomMessageRecipient::STATUS_PENDING : CustomMessageRecipient::STATUS_SKIPPED,
            ]);

            if ($channel) {
                SendCustomAttendeeMessageJob::dispatch($row->id);
            }
        }

        return redirect()->route('events.messages.show', [$event, $message])
            ->with('success', "Message queued for {$recipients->count()} recipients.");
    }

    public function show(Event $event, CustomMessage $message): View
    {
        $this->authorize('manageMessages', $event);
        abort_unless($message->event_id === $event->id, 404);

        $recipients = $message->recipients()->orderBy('name')->paginate(50);

        return view('events.messages.show', compact('event', 'message', 'recipients'));
    }

    /** @param Collection<int, array{participant_id: ?int, name: string, email: ?string, phone: ?string}> $recipients */
    private function deduplicate(Collection $recipients): Collection
    {
        $seen = [];

        return $recipients->filter(function (array $recipient) use (&$seen): bool {
            $key = $recipient['email'] ? strtolower(trim($recipient['email']))
                : preg_replace('/\D+/', '', $recipient['phone'] ?? '');

            if ($key === '' || $key === null) {
                return true;
            }
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        })->values();
    }
}
