<?php

namespace App\Http\Controllers;

use App\Imports\CustomMessageRecipientsImport;
use App\Jobs\SendCustomAttendeeMessageJob;
use App\Models\CustomMessage;
use App\Models\CustomMessageRecipient;
use App\Models\Event;
use App\Models\Participant;
use App\Services\CustomMessageService;
use App\Services\PhoneNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class CustomMessageController extends Controller
{
    public function __construct(private readonly CustomMessageService $messages) {}

    public function index(Request $request, Event $event): View
    {
        $this->authorize('manageMessages', $event);

        $filter = in_array($request->query('filter'), ['email', 'sms', 'failed', 'today'], true) ? $request->query('filter') : 'all';
        $search = trim((string) $request->query('q', ''));

        $query = $event->customMessages()->withCount($this->statusCounts());

        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(fn ($where) => $where
                ->whereRaw('LOWER(subject) LIKE ?', [$like])
                ->orWhereRaw('LOWER(email_body) LIKE ?', [$like])
                ->orWhereRaw('LOWER(sms_body) LIKE ?', [$like]));
        }

        match ($filter) {
            'email' => $query->whereNotNull('email_body')->where('email_body', '!=', ''),
            'sms' => $query->whereNotNull('sms_body')->where('sms_body', '!=', ''),
            'failed' => $query->whereHas('recipients', fn ($recipients) => $recipients->where('status', CustomMessageRecipient::STATUS_FAILED)),
            'today' => $query->where('created_at', '>=', now()->startOfDay()),
            default => null,
        };

        $messages = $query->latest()->paginate(20)->withQueryString();

        $counts = [
            'all' => $event->customMessages()->count(),
            'email' => $event->customMessages()->whereNotNull('email_body')->where('email_body', '!=', '')->count(),
            'sms' => $event->customMessages()->whereNotNull('sms_body')->where('sms_body', '!=', '')->count(),
            'failed' => $event->customMessages()->whereHas('recipients', fn ($recipients) => $recipients->where('status', CustomMessageRecipient::STATUS_FAILED))->count(),
            'today' => $event->customMessages()->where('created_at', '>=', now()->startOfDay())->count(),
        ];

        $selected = $request->filled('message')
            ? $event->customMessages()->withCount($this->statusCounts())->with('creator')->find($request->query('message'))
            : null;

        return view('events.messages.index', [
            'event' => $event,
            'messages' => $messages,
            'counts' => $counts,
            'filter' => $filter,
            'search' => $search,
            'selected' => $selected,
            'recipientGroups' => $selected ? $this->groupedRecipients($selected) : collect(),
            'composeConfig' => $this->composeConfig($event, $this->registrantsFor($event)),
        ]);
    }

    public function create(Event $event): View
    {
        $this->authorize('manageMessages', $event);

        $composeConfig = $this->composeConfig($event, $this->registrantsFor($event));

        return view('events.messages.create', compact('event', 'composeConfig'));
    }

    public function edit(Event $event, CustomMessage $message): View
    {
        $this->authorize('manageMessages', $event);
        abort_unless($message->event_id === $event->id, 404);

        $composeConfig = $this->composeConfig($event, $this->registrantsFor($event));
        $selectedParticipantIds = $message->recipients()->whereNotNull('participant_id')->distinct()->pluck('participant_id')->all();
        $seedRecipients = $message->recipients()->whereNull('participant_id')->get()
            ->unique(fn (CustomMessageRecipient $recipient) => strtolower(trim((string) $recipient->email)).'|'.preg_replace('/\D+/', '', (string) $recipient->phone))
            ->map(fn (CustomMessageRecipient $recipient) => ['id' => $recipient->id, 'name' => $recipient->name, 'email' => $recipient->email, 'phone' => $recipient->phone])->values();

        return view('events.messages.create', compact('event', 'composeConfig', 'message', 'selectedParticipantIds', 'seedRecipients'));
    }

    public function resend(Request $request, Event $event, CustomMessage $message): RedirectResponse
    {
        $this->authorize('manageMessages', $event);
        abort_unless($message->event_id === $event->id, 404);

        return $this->send($request, $event, $message);
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageMessages', $event);

        return $this->send($request, $event);
    }

    private function send(Request $request, Event $event, ?CustomMessage $source = null): RedirectResponse
    {

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'email_body' => ['nullable', 'required_without:sms_body', 'string', 'max:5000'],
            'sms_body' => ['nullable', 'required_without:email_body', 'string', 'max:1000'],
            'mode' => ['required', Rule::in(CustomMessageService::MODES)],
            'participant_ids' => ['array'],
            'participant_ids.*' => ['integer'],
            'recipient_keys' => ['array'],
            'recipient_keys.*' => ['integer'],
            'recipients_file' => ['nullable', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,csv,txt'],
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

        if ($source && $request->has('recipient_keys')) {
            $selectedIds = collect($validated['recipient_keys'] ?? [])->map(fn ($id) => (int) $id)->all();
            $allowedIds = $source->recipients()->whereNull('participant_id')->pluck('id')->all();
            abort_unless(empty(array_diff($selectedIds, $allowedIds)), 422);
            $source->recipients()->whereNull('participant_id')->whereIn('id', $selectedIds)->get()
                ->unique(fn (CustomMessageRecipient $recipient) => strtolower(trim((string) $recipient->email)).'|'.preg_replace('/\D+/', '', (string) $recipient->phone))
                ->each(fn (CustomMessageRecipient $recipient) => $recipients->push([
                    'participant_id' => null, 'name' => $recipient->name, 'email' => $recipient->email, 'phone' => $recipient->phone,
                ]));
        }

        $recipients = $this->deduplicate($recipients);

        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages([
                'participant_ids' => $source ? 'Select at least one recipient to resend this message to.' : 'No recipients found. Tick at least one registrant, or upload a file with a Name in the first column of each row.',
            ]);
        }

        $message = $event->customMessages()->create([
            'company_id' => $event->company_id,
            'created_by' => $request->user()->id,
            'subject' => $validated['subject'] ?? null,
            'email_body' => $validated['email_body'] ?? null,
            'sms_body' => $validated['sms_body'] ?? null,
            'mode' => $validated['mode'],
            'recipient_count' => $recipients->count(),
        ]);

        if ($request->hasFile('attachments')) {
            $stored = [];
            foreach ($request->file('attachments') as $file) {
                $path = $file->storeAs("custom-message-attachments/{$message->id}", Str::uuid().'-'.$file->getClientOriginalName(), 'local');
                $stored[] = ['path' => $path, 'name' => $file->getClientOriginalName(), 'mime' => $file->getClientMimeType()];
            }
            $message->update(['attachments' => $stored]);
        } elseif ($source && ! empty($source->attachments)) {
            $message->update(['attachments' => $source->attachments]);
        }

        $hasEmailBody = filled($validated['email_body'] ?? null);
        $hasSmsBody = filled($validated['sms_body'] ?? null);

        foreach ($recipients as $recipient) {
            $channels = $this->messages->determineChannels($validated['mode'], $recipient['email'], $recipient['phone'], $hasEmailBody, $hasSmsBody);

            if (empty($channels)) {
                $message->recipients()->create([
                    'participant_id' => $recipient['participant_id'],
                    'name' => $recipient['name'],
                    'email' => $recipient['email'],
                    'phone' => $recipient['phone'],
                    'channel' => null,
                    'status' => CustomMessageRecipient::STATUS_SKIPPED,
                ]);

                continue;
            }

            foreach ($channels as $channel) {
                $row = $message->recipients()->create([
                    'participant_id' => $recipient['participant_id'],
                    'name' => $recipient['name'],
                    'email' => $recipient['email'],
                    'phone' => $recipient['phone'],
                    'channel' => $channel,
                    'status' => CustomMessageRecipient::STATUS_PENDING,
                ]);

                SendCustomAttendeeMessageJob::dispatch($row->id);
            }
        }

        return redirect()->route('events.messages.index', ['event' => $event, 'message' => $message->id])
            ->with('success', ($source ? 'Resend queued' : 'Message queued')." for {$recipients->count()} recipients.");
    }

    private function registrantsFor(Event $event): Collection
    {
        return $event->registrations()
            ->whereIn('status', ['confirmed', 'pending', 'waitlisted', 'awaiting_confirmation'])
            ->with('participant')->get()->pluck('participant')->filter()->unique('id')->sortBy('name')->values();
    }

    public function show(Event $event, CustomMessage $message): RedirectResponse
    {
        $this->authorize('manageMessages', $event);
        abort_unless($message->event_id === $event->id, 404);

        return redirect()->route('events.messages.index', ['event' => $event, 'message' => $message->id]);
    }

    public function progress(Event $event, CustomMessage $message): JsonResponse
    {
        $this->authorize('manageMessages', $event);
        abort_unless($message->event_id === $event->id, 404);

        $counts = $message->recipients()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'sent' => (int) ($counts[CustomMessageRecipient::STATUS_SENT] ?? 0),
            'failed' => (int) ($counts[CustomMessageRecipient::STATUS_FAILED] ?? 0),
            'skipped' => (int) ($counts[CustomMessageRecipient::STATUS_SKIPPED] ?? 0),
            'pending' => (int) ($counts[CustomMessageRecipient::STATUS_PENDING] ?? 0),
        ]);
    }

    public function retryFailed(Event $event, CustomMessage $message): RedirectResponse
    {
        $this->authorize('manageMessages', $event);
        abort_unless($message->event_id === $event->id, 404);

        $failed = $message->recipients()->where('status', CustomMessageRecipient::STATUS_FAILED)->get();

        foreach ($failed as $recipient) {
            $recipient->update(['status' => CustomMessageRecipient::STATUS_PENDING, 'error_message' => null]);
            SendCustomAttendeeMessageJob::dispatch($recipient->id);
        }

        return redirect()->route('events.messages.index', ['event' => $event, 'message' => $message->id])
            ->with('success', $failed->isEmpty() ? 'Nothing to retry.' : "Retrying {$failed->count()} failed ".Str::plural('delivery', $failed->count()).'.');
    }

    /** @return array<string, \Closure> */
    private function statusCounts(): array
    {
        return [
            'recipients as sent_count' => fn ($query) => $query->where('status', CustomMessageRecipient::STATUS_SENT),
            'recipients as failed_count' => fn ($query) => $query->where('status', CustomMessageRecipient::STATUS_FAILED),
            'recipients as skipped_count' => fn ($query) => $query->where('status', CustomMessageRecipient::STATUS_SKIPPED),
            'recipients as pending_count' => fn ($query) => $query->where('status', CustomMessageRecipient::STATUS_PENDING),
        ];
    }

    /** One entry per person, each holding that person's per-channel delivery rows. */
    private function groupedRecipients(CustomMessage $message): Collection
    {
        return $message->recipients()
            ->orderBy('name')
            ->get()
            ->groupBy(fn (CustomMessageRecipient $recipient) => $recipient->participant_id
                ? 'participant:'.$recipient->participant_id
                : ($recipient->email ? 'email:'.strtolower(trim($recipient->email))
                    : ($recipient->phone ? 'phone:'.preg_replace('/\D+/', '', $recipient->phone)
                        : 'recipient:'.$recipient->id)))
            ->map(fn (Collection $channels) => $channels->sortBy(fn (CustomMessageRecipient $recipient) => $recipient->channel === 'mail' ? 0 : 1)->values())
            ->values();
    }

    /**
     * Data the compose panel needs in the browser to preview the message and show which
     * channel each recipient would get. The routing rules are mirrored in the panel's
     * JavaScript from CustomMessageService::determineChannels().
     *
     * @return array<string, mixed>
     */
    private function composeConfig(Event $event, Collection $registrants): array
    {
        return [
            'organization' => $event->company?->name ?? 'The event team',
            'eventTitle' => $event->title,
            'smsEnabled' => (bool) config('services.arkesel.enabled'),
            'participants' => $registrants->map(fn (Participant $participant) => [
                'id' => (string) $participant->id,
                'name' => $participant->name,
                'email' => $participant->email,
                'phone' => $participant->phone,
                'hasEmail' => filled($participant->email) && ! str_ends_with($participant->email, '@example.invalid'),
                'ghana' => PhoneNumberService::isGhanaNumber($participant->phone),
            ])->values()->all(),
        ];
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
