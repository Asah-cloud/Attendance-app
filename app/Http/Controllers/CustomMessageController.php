<?php

namespace App\Http\Controllers;

use App\Imports\CustomMessageRecipientsImport;
use App\Jobs\SendCustomAttendeeMessageJob;
use App\Models\CustomMessage;
use App\Models\CustomMessageRecipient;
use App\Models\Event;
use App\Models\Participant;
use App\Services\CustomMessageSender;
use App\Services\CustomMessageService;
use App\Services\PhoneNumberService;
use App\Support\MergeFields;
use App\Support\Search;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class CustomMessageController extends Controller
{
    private const FILTERS = ['drafts', 'scheduled', 'email', 'sms', 'failed', 'today'];

    public function __construct(private readonly CustomMessageSender $sender) {}

    public function index(Request $request, Event $event): View
    {
        $this->authorize('manageMessages', $event);

        $filter = in_array($request->query('filter'), self::FILTERS, true) ? $request->query('filter') : 'all';
        $search = trim((string) $request->query('q', ''));

        $query = $event->customMessages()->withCount($this->statusCounts());

        Search::apply($query, $search, ['subject', 'email_body', 'sms_body']);

        // Drafts have their own list; every other list is about messages that are scheduled or sent.
        match ($filter) {
            'drafts' => $query->where('status', CustomMessage::STATUS_DRAFT)->latest('updated_at'),
            'scheduled' => $query->where('status', CustomMessage::STATUS_SCHEDULED)->orderBy('scheduled_at'),
            default => $query->where('status', '!=', CustomMessage::STATUS_DRAFT)
                ->orderByRaw('COALESCE(sent_at, scheduled_at, created_at) DESC'),
        };

        match ($filter) {
            'email' => $query->whereNotNull('email_body')->where('email_body', '!=', ''),
            'sms' => $query->whereNotNull('sms_body')->where('sms_body', '!=', ''),
            'failed' => $query->whereHas('recipients', fn ($recipients) => $recipients->where('status', CustomMessageRecipient::STATUS_FAILED)),
            'today' => $query->where('status', CustomMessage::STATUS_SENT)->where('sent_at', '>=', now()->startOfDay()),
            default => null,
        };

        $messages = $query->paginate(20)->withQueryString();

        $sentOrScheduled = fn () => $event->customMessages()->where('status', '!=', CustomMessage::STATUS_DRAFT);
        $counts = [
            'all' => $sentOrScheduled()->count(),
            'drafts' => $event->customMessages()->where('status', CustomMessage::STATUS_DRAFT)->count(),
            'scheduled' => $event->customMessages()->where('status', CustomMessage::STATUS_SCHEDULED)->count(),
            'email' => $sentOrScheduled()->whereNotNull('email_body')->where('email_body', '!=', '')->count(),
            'sms' => $sentOrScheduled()->whereNotNull('sms_body')->where('sms_body', '!=', '')->count(),
            'failed' => $sentOrScheduled()->whereHas('recipients', fn ($recipients) => $recipients->where('status', CustomMessageRecipient::STATUS_FAILED))->count(),
            'today' => $event->customMessages()->where('status', CustomMessage::STATUS_SENT)->where('sent_at', '>=', now()->startOfDay())->count(),
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
            'recipientGroups' => $selected && ! $selected->isPending() ? $this->groupedRecipients($selected) : collect(),
            'planned' => $selected?->isPending() ? $this->plannedRecipients($selected) : ['total' => 0, 'names' => []],
            'composeConfig' => $this->composeConfig($event),
        ]);
    }

    public function create(Event $event): View
    {
        $this->authorize('manageMessages', $event);

        $composeConfig = $this->composeConfig($event);

        return view('events.messages.create', compact('event', 'composeConfig'));
    }

    public function edit(Event $event, CustomMessage $message): View
    {
        $this->authorize('manageMessages', $event);
        abort_unless($message->event_id === $event->id, 404);

        $composeConfig = $this->composeConfig($event);

        if ($message->isPending()) {
            $selectedParticipantIds = $message->draftParticipantIds();
            $seedRecipients = collect($message->draftExtras())
                ->map(fn (array $extra, int $index) => ['id' => $index] + $extra + ['email' => null, 'phone' => null])->values();

            return view('events.messages.create', compact('event', 'composeConfig', 'message', 'selectedParticipantIds', 'seedRecipients'));
        }

        $selectedParticipantIds = $message->recipients()->whereNotNull('participant_id')->distinct()->pluck('participant_id')->all();
        $seedRecipients = $message->recipients()->whereNull('participant_id')->get()
            ->unique(fn (CustomMessageRecipient $recipient) => strtolower(trim((string) $recipient->email)).'|'.preg_replace('/\D+/', '', (string) $recipient->phone))
            ->map(fn (CustomMessageRecipient $recipient) => ['id' => $recipient->id, 'name' => $recipient->name, 'email' => $recipient->email, 'phone' => $recipient->phone])->values();

        return view('events.messages.create', compact('event', 'composeConfig', 'message', 'selectedParticipantIds', 'seedRecipients'));
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageMessages', $event);

        $draftId = $request->integer('draft_id');
        $existing = $draftId ? $event->customMessages()->find($draftId) : null;

        return $this->persist($request, $event, $existing?->isPending() ? $existing : null);
    }

    public function update(Request $request, Event $event, CustomMessage $message): RedirectResponse
    {
        $this->authorize('manageMessages', $event);
        abort_unless($message->event_id === $event->id && $message->isPending(), 404);

        return $this->persist($request, $event, $message);
    }

    public function resend(Request $request, Event $event, CustomMessage $message): RedirectResponse
    {
        $this->authorize('manageMessages', $event);
        abort_unless($message->event_id === $event->id, 404);

        return $this->persist($request, $event, null, $message);
    }

    /** Quiet background save of what a manager is typing, so closing the panel never loses work. */
    public function autosave(Request $request, Event $event): JsonResponse
    {
        $this->authorize('manageMessages', $event);

        $validated = $request->validate([
            'draft_id' => ['nullable', 'integer'],
            'subject' => ['nullable', 'string', 'max:255'],
            'email_body' => ['nullable', 'string', 'max:5000'],
            'sms_body' => ['nullable', 'string', 'max:1000'],
            'mode' => ['nullable', Rule::in(CustomMessageService::MODES)],
            'participant_ids' => ['nullable', 'array', 'max:5000'],
            'participant_ids.*' => ['integer'],
        ]);

        $draft = ! empty($validated['draft_id']) ? $event->customMessages()->find($validated['draft_id']) : null;
        abort_if($draft && ! $draft->isDraft(), 409, 'This message is no longer a draft.');

        $participantIds = $this->companyParticipantIds($event, $validated['participant_ids'] ?? []);
        $hasContent = filled($validated['subject'] ?? null) || filled($validated['email_body'] ?? null)
            || filled($validated['sms_body'] ?? null) || $participantIds !== [];

        if (! $hasContent) {
            return response()->json(['id' => $draft?->id, 'saved' => false]);
        }

        $extras = $draft ? $draft->draftExtras() : [];
        $fields = [
            'subject' => $validated['subject'] ?? null,
            'email_body' => $validated['email_body'] ?? null,
            'sms_body' => $validated['sms_body'] ?? null,
            'mode' => $validated['mode'] ?? 'smart',
            'draft_recipients' => ['participants' => $participantIds, 'extras' => $extras],
            'recipient_count' => count($participantIds) + count($extras),
        ];

        if ($draft) {
            $draft->update($fields);
        } else {
            $draft = $event->customMessages()->create($fields + [
                'company_id' => $event->company_id,
                'created_by' => $request->user()->id,
                'status' => CustomMessage::STATUS_DRAFT,
            ]);
        }

        return response()->json(['id' => $draft->id, 'saved' => true, 'at' => now()->format('H:i')]);
    }

    public function sendNow(Event $event, CustomMessage $message): RedirectResponse
    {
        $this->authorize('manageMessages', $event);
        abort_unless($message->event_id === $event->id && $message->isPending(), 404);

        if (! $this->sender->sendStored($message)) {
            return back()->with('error', 'This message has no recipients left to send to. Open it, pick recipients and try again.');
        }

        return $this->openMessage($event, $message, 'Message queued for '.$message->refresh()->recipient_count.' recipients.');
    }

    public function unschedule(Event $event, CustomMessage $message): RedirectResponse
    {
        $this->authorize('manageMessages', $event);
        abort_unless($message->event_id === $event->id && $message->isScheduled(), 404);

        $message->update(['status' => CustomMessage::STATUS_DRAFT, 'scheduled_at' => null]);

        return redirect()->route('events.messages.index', ['event' => $event, 'filter' => 'drafts', 'message' => $message->id])
            ->with('success', 'Schedule cancelled. The message is back in your drafts.');
    }

    public function destroy(Event $event, CustomMessage $message): RedirectResponse
    {
        $this->authorize('manageMessages', $event);
        abort_unless($message->event_id === $event->id && $message->isPending(), 404);

        Storage::disk('local')->deleteDirectory("custom-message-attachments/{$message->id}");
        $message->delete();

        return redirect()->route('events.messages.index', ['event' => $event, 'filter' => 'drafts'])
            ->with('success', 'Draft deleted.');
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

        return $this->openMessage($event, $message, $failed->isEmpty() ? 'Nothing to retry.' : "Retrying {$failed->count()} failed ".Str::plural('delivery', $failed->count()).'.');
    }

    /**
     * Save, schedule or send the composed message, depending on which button was pressed.
     * $existing is a draft or scheduled message being edited; $source is a sent message being resent.
     */
    private function persist(Request $request, Event $event, ?CustomMessage $existing = null, ?CustomMessage $source = null): RedirectResponse
    {
        $intent = $source ? 'send' : (string) $request->input('intent', 'send');
        abort_unless(in_array($intent, ['send', 'schedule', 'draft'], true), 422);
        $isDraft = $intent === 'draft';

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'email_body' => $isDraft ? ['nullable', 'string', 'max:5000'] : ['nullable', 'required_without:sms_body', 'string', 'max:5000'],
            'sms_body' => $isDraft ? ['nullable', 'string', 'max:1000'] : ['nullable', 'required_without:email_body', 'string', 'max:1000'],
            'mode' => ['required', Rule::in(CustomMessageService::MODES)],
            'scheduled_at' => $intent === 'schedule' ? ['required', 'date'] : ['nullable'],
            'participant_ids' => ['array'],
            'participant_ids.*' => ['integer'],
            'recipient_keys' => ['array'],
            'recipient_keys.*' => ['integer'],
            'recipients_file' => ['nullable', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,csv,txt'],
        ]);

        $scheduledAt = $intent === 'schedule' ? $this->parseScheduleTime($validated['scheduled_at']) : null;

        $participantIds = $this->companyParticipantIds($event, $validated['participant_ids'] ?? []);
        $extras = $this->collectExtras($request, $validated, $source, $existing);
        $recipients = CustomMessageSender::deduplicate($this->recipientRows($event, $participantIds, $extras));

        if ($isDraft) {
            if (! filled($validated['subject'] ?? null) && ! filled($validated['email_body'] ?? null) && ! filled($validated['sms_body'] ?? null) && $recipients->isEmpty()) {
                throw ValidationException::withMessages(['email_body' => 'Write something or pick recipients before saving a draft.']);
            }
        } elseif ($recipients->isEmpty()) {
            throw ValidationException::withMessages([
                'participant_ids' => $source ? 'Select at least one recipient to resend this message to.' : 'No recipients found. Tick at least one registrant, or upload a file with a Name in the first column of each row.',
            ]);
        }

        $fields = [
            'subject' => $validated['subject'] ?? null,
            'email_body' => $validated['email_body'] ?? null,
            'sms_body' => $validated['sms_body'] ?? null,
            'mode' => $validated['mode'],
            'recipient_count' => $recipients->count(),
            'status' => match ($intent) {
                'draft' => CustomMessage::STATUS_DRAFT,
                'schedule' => CustomMessage::STATUS_SCHEDULED,
                default => CustomMessage::STATUS_SENT,
            },
            'scheduled_at' => $scheduledAt,
            'sent_at' => $intent === 'send' ? now() : null,
            // The selection is only kept until the message goes out; after that the delivery rows are the record.
            'draft_recipients' => $intent === 'send' ? null : ['participants' => $participantIds, 'extras' => $extras],
        ];

        if ($existing) {
            $existing->update($fields);
            $message = $existing;
        } else {
            $message = $event->customMessages()->create($fields + [
                'company_id' => $event->company_id,
                'created_by' => $request->user()->id,
            ]);
        }

        $this->storeAttachments($request, $message, $source);

        if ($intent === 'send') {
            $this->sender->deliver($message, $recipients);

            return $this->openMessage($event, $message, ($source ? 'Resend queued' : 'Message queued')." for {$recipients->count()} recipients.");
        }

        if ($intent === 'schedule') {
            return redirect()->route('events.messages.index', ['event' => $event, 'filter' => 'scheduled', 'message' => $message->id])
                ->with('success', 'Scheduled for '.$scheduledAt->timezone(config('app.timezone'))->format('M j, Y \a\t H:i').' ('.config('app.timezone').').');
        }

        return redirect()->route('events.messages.index', ['event' => $event, 'filter' => 'drafts', 'message' => $message->id])
            ->with('success', 'Draft saved. Find it under Drafts whenever you are ready.');
    }

    private function parseScheduleTime(string $input): Carbon
    {
        try {
            $when = Carbon::parse($input, config('app.timezone'));
        } catch (\Throwable) {
            throw ValidationException::withMessages(['scheduled_at' => 'That date and time is not valid.']);
        }

        if ($when->lt(now()->addMinute())) {
            throw ValidationException::withMessages(['scheduled_at' => 'Pick a time at least a minute from now.']);
        }
        if ($when->gt(now()->addYear())) {
            throw ValidationException::withMessages(['scheduled_at' => 'Messages can be scheduled up to a year ahead.']);
        }

        return $when;
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    private function companyParticipantIds(Event $event, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Participant::query()
            ->where('company_id', $event->company_id)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * People who are not registrants: rows from an uploaded list plus any earlier ones kept from
     * the message being edited or resent.
     *
     * @return list<array{name: string, email: ?string, phone: ?string}>
     */
    private function collectExtras(Request $request, array $validated, ?CustomMessage $source, ?CustomMessage $existing): array
    {
        $extras = [];

        if ($request->hasFile('recipients_file')) {
            $import = new CustomMessageRecipientsImport;
            Excel::import($import, $request->file('recipients_file'));

            foreach ($import->rows as $row) {
                if ($row['name'] !== '') {
                    $extras[] = ['name' => $row['name'], 'email' => $row['email'], 'phone' => $row['phone']];
                }
            }
        }

        if ($source && $request->has('recipient_keys')) {
            $selectedIds = collect($validated['recipient_keys'] ?? [])->map(fn ($id) => (int) $id)->all();
            $allowedIds = $source->recipients()->whereNull('participant_id')->pluck('id')->all();
            abort_unless(empty(array_diff($selectedIds, $allowedIds)), 422);

            $source->recipients()->whereNull('participant_id')->whereIn('id', $selectedIds)->get()
                ->unique(fn (CustomMessageRecipient $recipient) => strtolower(trim((string) $recipient->email)).'|'.preg_replace('/\D+/', '', (string) $recipient->phone))
                ->each(function (CustomMessageRecipient $recipient) use (&$extras): void {
                    $extras[] = ['name' => $recipient->name, 'email' => $recipient->email, 'phone' => $recipient->phone];
                });
        }

        if ($existing) {
            $kept = $existing->draftExtras();

            // The edit page lists these people as ticked boxes; when it did, honour what was left ticked.
            // A panel that never showed them (no seed_shown) keeps them all rather than silently dropping them.
            if ($request->boolean('seed_shown')) {
                $selectedIndexes = collect($validated['recipient_keys'] ?? [])->map(fn ($index) => (int) $index)->all();
                abort_unless(empty(array_diff($selectedIndexes, array_keys($kept))), 422);
                $kept = array_values(array_intersect_key($kept, array_flip($selectedIndexes)));
            }

            $extras = array_merge($kept, $extras);
        }

        return $extras;
    }

    /**
     * @param  list<int>  $participantIds
     * @param  list<array{name: string, email: ?string, phone: ?string}>  $extras
     * @return Collection<int, array{participant_id: ?int, name: string, email: ?string, phone: ?string}>
     */
    private function recipientRows(Event $event, array $participantIds, array $extras): Collection
    {
        $rows = collect();

        if ($participantIds !== []) {
            Participant::query()->where('company_id', $event->company_id)->whereIn('id', $participantIds)->get()
                ->each(fn (Participant $participant) => $rows->push([
                    'participant_id' => $participant->id,
                    'name' => $participant->name,
                    'email' => $participant->email,
                    'phone' => $participant->phone,
                ]));
        }

        foreach ($extras as $extra) {
            $rows->push(['participant_id' => null, 'name' => $extra['name'], 'email' => $extra['email'] ?? null, 'phone' => $extra['phone'] ?? null]);
        }

        return $rows;
    }

    private function storeAttachments(Request $request, CustomMessage $message, ?CustomMessage $source): void
    {
        if ($request->hasFile('attachments')) {
            if (! empty($message->attachments)) {
                Storage::disk('local')->delete(array_column($message->attachments, 'path'));
            }

            $stored = [];
            foreach ($request->file('attachments') as $file) {
                $path = $file->storeAs("custom-message-attachments/{$message->id}", Str::uuid().'-'.$file->getClientOriginalName(), 'local');
                $stored[] = ['path' => $path, 'name' => $file->getClientOriginalName(), 'mime' => $file->getClientMimeType()];
            }
            $message->update(['attachments' => $stored]);
        } elseif ($source && ! empty($source->attachments)) {
            $message->update(['attachments' => $source->attachments]);
        }
    }

    /** @return array{total: int, names: list<string>} who a draft or scheduled message is currently set to reach */
    private function plannedRecipients(CustomMessage $message): array
    {
        $names = Participant::query()
            ->where('company_id', $message->company_id)
            ->whereIn('id', $message->draftParticipantIds())
            ->orderBy('name')
            ->pluck('name')
            ->merge(array_column($message->draftExtras(), 'name'))
            ->values();

        return ['total' => $names->count(), 'names' => $names->take(12)->all()];
    }

    private function openMessage(Event $event, CustomMessage $message, string $flash): RedirectResponse
    {
        return redirect()->route('events.messages.index', ['event' => $event, 'message' => $message->id])->with('success', $flash);
    }

    private function registrantsFor(Event $event): Collection
    {
        return $event->registrations()
            ->whereIn('status', ['confirmed', 'pending', 'waitlisted', 'awaiting_confirmation'])
            ->with('participant')->get()->pluck('participant')->filter()->unique('id')->sortBy('name')->values();
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
     * Data the compose panel needs in the browser: who can be picked, the templates on offer,
     * and the rules it mirrors in JavaScript for previews (channel routing from
     * CustomMessageService::determineChannels(), merge fields from MergeFields).
     *
     * @return array<string, mixed>
     */
    private function composeConfig(Event $event): array
    {
        return [
            'organization' => $event->company?->name ?? 'The event team',
            'eventTitle' => $event->title,
            'smsEnabled' => (bool) config('services.arkesel.enabled'),
            'timezone' => config('app.timezone'),
            'tokens' => MergeFields::TOKENS,
            'urls' => [
                'autosave' => route('events.messages.autosave', $event),
                'templates' => route('events.message-templates.store', $event),
            ],
            'templates' => $event->company?->messageTemplates()->orderBy('name')->get(['id', 'name', 'subject', 'email_body', 'sms_body'])
                ->map(fn ($template) => $template->only(['id', 'name', 'subject', 'email_body', 'sms_body']) + ['url' => route('events.message-templates.destroy', [$event, $template->id])])
                ->values()->all() ?? [],
            'participants' => $this->registrantsFor($event)->map(fn (Participant $participant) => [
                'id' => (string) $participant->id,
                'name' => $participant->name,
                'email' => $participant->email,
                'phone' => $participant->phone,
                'hasEmail' => filled($participant->email) && ! str_ends_with($participant->email, '@example.invalid'),
                'ghana' => PhoneNumberService::isGhanaNumber($participant->phone),
            ])->values()->all(),
        ];
    }
}
