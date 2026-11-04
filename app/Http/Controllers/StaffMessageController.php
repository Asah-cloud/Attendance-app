<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Event;
use App\Models\StaffMessage;
use App\Models\StaffMessageRecipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admins and managers message their ushers, meal-station and audit staff (all of them, those on
 * one event, or one person). Staff read them from the bell in the header; they cannot reply.
 */
class StaffMessageController extends Controller
{
    public function index(Request $request): View
    {
        [$company, $companies] = $this->company($request);

        $messages = StaffMessage::query()->where('company_id', $company->id)
            ->with(['sender', 'event', 'recipients.user'])
            ->withCount(['recipients', 'recipients as read_count' => fn ($query) => $query->whereNotNull('read_at')])
            ->latest('id')->paginate(20)->withQueryString();

        return view('staff-messages.index', [
            'company' => $company,
            'companies' => $companies,
            'messages' => $messages,
            'staff' => StaffMessage::staffIn($company->id)->orderBy('name')->get(['id', 'name']),
            'events' => Event::query()->where('company_id', $company->id)->whereNull('cancelled_at')
                ->withCount(['staff' => fn ($query) => $query->whereHas('roles', fn ($query) => $query->whereIn('name', StaffMessage::STAFF_ROLES))])
                ->orderByDesc('event_date')->limit(30)->get(['id', 'title', 'event_date']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$company] = $this->company($request);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'audience' => ['required', Rule::in([StaffMessage::AUDIENCE_ALL, StaffMessage::AUDIENCE_EVENT, StaffMessage::AUDIENCE_USER])],
            'event_id' => ['nullable', 'required_if:audience,'.StaffMessage::AUDIENCE_EVENT, 'integer',
                Rule::exists('events', 'id')->where('company_id', $company->id)],
            'user_id' => ['nullable', 'required_if:audience,'.StaffMessage::AUDIENCE_USER, 'integer'],
            'urgent' => ['nullable', 'boolean'],
        ]);

        $recipientIds = StaffMessage::staffIn($company->id)
            ->when($data['audience'] === StaffMessage::AUDIENCE_EVENT, fn ($query) => $query->whereHas('events', fn ($query) => $query->whereKey($data['event_id'])))
            ->when($data['audience'] === StaffMessage::AUDIENCE_USER, fn ($query) => $query->whereKey($data['user_id']))
            ->pluck('id');

        if ($recipientIds->isEmpty()) {
            return back()->withInput()->with('error', 'There are no staff accounts to send this to.');
        }

        $message = StaffMessage::create([
            'company_id' => $company->id,
            'sender_id' => $request->user()->id,
            'audience' => $data['audience'],
            'event_id' => $data['audience'] === StaffMessage::AUDIENCE_EVENT ? $data['event_id'] : null,
            'body' => trim($data['body']),
            'urgent' => (bool) ($data['urgent'] ?? false),
        ]);
        $now = now();
        StaffMessageRecipient::insert($recipientIds->map(fn ($id) => [
            'staff_message_id' => $message->id, 'user_id' => $id, 'created_at' => $now, 'updated_at' => $now,
        ])->all());

        return redirect()->route('staff-messages.index', $this->companyQuery($request, $company))
            ->with('success', 'Message sent to '.$recipientIds->count().' staff '.str('member')->plural($recipientIds->count()).'.');
    }

    public function show(Request $request, StaffMessage $message): View
    {
        $this->authorizeCompany($request, $message);

        return view('staff-messages.show', [
            'message' => $message->load(['sender', 'event']),
            'recipients' => $message->recipients()->with('user')->get()
                ->sortBy(fn (StaffMessageRecipient $recipient) => [$recipient->read_at === null ? 0 : 1, $recipient->user?->name])->values(),
        ]);
    }

    public function destroy(Request $request, StaffMessage $message): RedirectResponse
    {
        $this->authorizeCompany($request, $message);
        $message->delete();

        return redirect()->route('staff-messages.index', $request->user()->hasRole('admin') ? ['company_id' => $message->company_id] : [])
            ->with('success', 'Message deleted. Staff will no longer see it.');
    }

    /** What the staff bell needs, polled by the page: unread count, the latest messages, and urgent ones not yet read. */
    public function feed(Request $request): JsonResponse
    {
        $received = fn () => StaffMessageRecipient::query()->where('user_id', $request->user()->id);
        $latest = $received()->with('message.sender')->latest('id')->limit(20)->get()->filter->message;

        return response()->json([
            'unread' => $received()->whereNull('read_at')->count(),
            'items' => $latest->map(fn (StaffMessageRecipient $recipient) => $this->item($recipient))->values(),
            'urgent' => $latest->filter(fn (StaffMessageRecipient $recipient) => $recipient->read_at === null && $recipient->message->urgent)
                ->sortBy('id')->map(fn (StaffMessageRecipient $recipient) => $this->item($recipient))->values(),
        ]);
    }

    /** Marks the given messages read, or every unread one when none are given. */
    public function read(Request $request): JsonResponse
    {
        $ids = $request->validate(['ids' => ['nullable', 'array'], 'ids.*' => ['integer']])['ids'] ?? null;

        StaffMessageRecipient::query()->where('user_id', $request->user()->id)->whereNull('read_at')
            ->when($ids !== null, fn ($query) => $query->whereIn('staff_message_id', $ids))
            ->update(['read_at' => now()]);

        return response()->json(['unread' => StaffMessageRecipient::query()->where('user_id', $request->user()->id)->whereNull('read_at')->count()]);
    }

    /** @return array<string, mixed> */
    private function item(StaffMessageRecipient $recipient): array
    {
        $message = $recipient->message;

        return [
            'id' => $message->id,
            'body' => $message->body,
            'urgent' => $message->urgent,
            'unread' => $recipient->read_at === null,
            'from' => $message->sender?->name ?? 'Your organisation',
            'time' => $message->created_at->timezone(config('app.timezone'))->format('M j, g:i A'),
            'ago' => $message->created_at->diffForHumans(),
        ];
    }

    /** @return array{0: Company, 1: Collection<int, Company>} */
    private function company(Request $request): array
    {
        $companies = collect();
        if ($request->user()->hasRole('admin')) {
            $companies = Company::query()->orderBy('name')->get(['id', 'name']);
            $companyId = $request->integer('company_id') ?: $companies->first()?->id;
            abort_unless($companies->contains('id', $companyId), 404);
        } else {
            $companyId = $request->user()->company_id;
            abort_unless($companyId, 403);
        }

        return [Company::findOrFail($companyId), $companies];
    }

    private function companyQuery(Request $request, Company $company): array
    {
        return $request->user()->hasRole('admin') ? ['company_id' => $company->id] : [];
    }

    private function authorizeCompany(Request $request, StaffMessage $message): void
    {
        abort_unless($request->user()->hasRole('admin') || $message->company_id === $request->user()->company_id, 404);
    }
}
