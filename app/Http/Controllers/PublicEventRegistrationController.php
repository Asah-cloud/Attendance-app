<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Notifications\Concerns\NotifiesPerChannel;
use App\Notifications\EventRegistrationSubmitted;
use App\Notifications\RoomAssigned;
use App\Services\ParticipantRegistrationService;
use App\Services\RegistrationLifecycleService;
use App\Services\RoomAllocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicEventRegistrationController extends Controller
{
    public function create(Event $event): View
    {
        abort_unless($event->registration_enabled, 404);
        $event->ensureSystemRegistrationFields();
        $event->loadMissing('company');

        return view('registrations.create', [
            'event' => $event,
            'fields' => $event->registrationFields()->where('is_active', true)->get(),
            'isOpen' => $event->registrationIsOpen(),
        ]);
    }

    public function store(Request $request, Event $event, ParticipantRegistrationService $participants, RegistrationLifecycleService $lifecycle): RedirectResponse
    {
        $event->ensureSystemRegistrationFields();
        $fields = $event->registrationFields()->where('is_active', true)->get();
        $validated = $request->validate($this->rules($fields));

        $registration = DB::transaction(function () use ($event, $validated, $participants): EventRegistration {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);

            if (! $event->registrationIsOpen()) {
                throw ValidationException::withMessages(['registration' => 'Registration is not currently open for this event.']);
            }

            $participant = $participants->resolveParticipant($event, [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'gender' => $validated['gender'],
                'category' => $validated['category'],
            ]);

            if (EventRegistration::query()->where('event_id', $event->id)->where('participant_id', $participant->id)->exists()) {
                throw ValidationException::withMessages(['email' => 'You are already registered for this event.']);
            }

            $confirmedCount = EventRegistration::query()
                ->where('event_id', $event->id)
                ->where('status', EventRegistration::STATUS_CONFIRMED)
                ->count();

            $status = match (true) {
                $event->registration_requires_approval => EventRegistration::STATUS_PENDING,
                $event->registration_capacity !== null && $confirmedCount >= $event->registration_capacity => EventRegistration::STATUS_WAITLISTED,
                default => EventRegistration::STATUS_CONFIRMED,
            };

            return EventRegistration::create([
                'event_id' => $event->id,
                'participant_id' => $participant->id,
                'status' => $status,
                'approved_at' => $status === EventRegistration::STATUS_CONFIRMED ? now() : null,
                'source' => 'public',
                'custom_answers' => $validated['custom'] ?? [],
                'consented_at' => now(),
                'terms_version' => $event->registration_terms_version,
                'accommodation_required' => $event->accommodation_enabled && ($validated['accommodation_required'] ?? false),
                'accessibility_required' => $event->accommodation_enabled && ($validated['accommodation_required'] ?? false) && ($validated['accessibility_required'] ?? false),
                'accommodation_notes' => $event->accommodation_enabled ? ($validated['accommodation_notes'] ?? null) : null,
                'food_required' => $event->food_registration_required && ($validated['food_required'] ?? false),
            ]);
        });

        $registration->load(['event.company', 'participant']);
        $lifecycle->allocateAccommodation($registration);
        NotifiesPerChannel::send($registration->participant, new EventRegistrationSubmitted($registration));

        return $this->afterRegistration($registration);
    }

    public function confirmation(string $code): View
    {
        $registration = EventRegistration::with(['event.company', 'participant', 'roomAssignment.room.floor.block.site'])
            ->where('registration_code', $code)
            ->firstOrFail();

        return view('registrations.confirmation', compact('registration'));
    }

    public function roomSelect(string $code, RoomAllocationService $allocator): View
    {
        $registration = EventRegistration::with(['event.company', 'participant', 'roomAssignment.room.floor.block.site'])
            ->where('registration_code', $code)
            ->firstOrFail();

        abort_unless($this->selfSelectAllowed($registration), 404);

        $rooms = $allocator->selectableRooms($registration)
            ->groupBy(fn ($room) => $room->floor->block->name)
            ->map(fn ($blockRooms) => $blockRooms->groupBy(fn ($room) => $room->floor->name));

        return view('registrations.room-select', compact('registration', 'rooms'));
    }

    public function roomClaim(Request $request, string $code, RoomAllocationService $allocator): RedirectResponse
    {
        $registration = EventRegistration::with(['event.company', 'participant', 'roomAssignment'])
            ->where('registration_code', $code)
            ->firstOrFail();

        abort_unless($this->selfSelectAllowed($registration), 404);

        $data = $request->validate(['room_id' => ['required', 'integer']]);
        $result = $allocator->claim($registration, $data['room_id']);

        if ($result['ok'] && $registration->event->accommodation_published) {
            $assignment = $registration->roomAssignment()
                ->with(['registration.participant', 'registration.event.company', 'room.floor.block.site'])
                ->first();
            if ($assignment && ! $assignment->notification_sent_at) {
                NotifiesPerChannel::send($registration->participant, new RoomAssigned($assignment));
                $assignment->update(['notification_sent_at' => now()]);
            }
        }

        return redirect()->route('registrations.room.select', $registration->registration_code)
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function selfSelectAllowed(EventRegistration $registration): bool
    {
        return $registration->status === EventRegistration::STATUS_CONFIRMED
            && $registration->accommodation_required
            && $registration->event->accommodationSelfSelectOpen();
    }

    public function cancel(string $code, RegistrationLifecycleService $lifecycle): RedirectResponse
    {
        $registration = EventRegistration::where('registration_code', $code)->firstOrFail();

        $lifecycle->cancel($registration);

        return back()->with('success', 'Your registration has been cancelled.');
    }

    public function showConfirm(string $code): View|RedirectResponse
    {
        $registration = EventRegistration::with(['event.company', 'participant'])
            ->where('registration_code', $code)
            ->firstOrFail();

        if ($registration->status !== EventRegistration::STATUS_AWAITING_CONFIRMATION) {
            return redirect()->route('registrations.confirmation', $registration->registration_code);
        }

        $event = $registration->event;
        $fields = $event->registrationFields()->where('is_system', false)->where('is_active', true)->get();

        return view('confirmations.show', compact('registration', 'event', 'fields'));
    }

    public function storeConfirm(Request $request, string $code, RegistrationLifecycleService $lifecycle): RedirectResponse
    {
        $registration = EventRegistration::with('event')
            ->where('registration_code', $code)
            ->firstOrFail();

        if ($registration->status !== EventRegistration::STATUS_AWAITING_CONFIRMATION) {
            return redirect()->route('registrations.confirmation', $registration->registration_code);
        }

        $event = $registration->event;
        $fields = $event->registrationFields()->where('is_system', false)->where('is_active', true)->get();

        $validated = $request->validate(array_merge(
            ['consent' => ['required', 'accepted']],
            $this->customFieldRules($fields)
        ));

        $registration->update([
            'status' => EventRegistration::STATUS_CONFIRMED,
            'approved_at' => now(),
            'custom_answers' => $validated['custom'] ?? [],
            'consented_at' => now(),
            'terms_version' => $event->registration_terms_version,
        ]);

        $lifecycle->notify($registration, 'confirmed');
        $lifecycle->allocateAccommodation($registration);

        return $this->afterRegistration($registration->fresh());
    }

    /** Send a confirmed attendee who needs a room straight to the room picker while self-selection is open. */
    private function afterRegistration(EventRegistration $registration): RedirectResponse
    {
        $registration->loadMissing('event');

        if ($registration->status === EventRegistration::STATUS_CONFIRMED
            && $registration->accommodation_required
            && ! $registration->roomAssignment()->exists()
            && $registration->event->accommodationSelfSelectOpen()) {
            return redirect()->route('registrations.room.select', ['code' => $registration->registration_code, 'new' => 1]);
        }

        return redirect()->route('registrations.confirmation', $registration->registration_code);
    }

    private function rules($fields): array
    {
        $genderField = $fields->firstWhere('field_key', 'gender');
        $categoryField = $fields->firstWhere('field_key', 'category');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'gender' => ['required', Rule::in($genderField->options ?? ['Male', 'Female'])],
            'category' => $categoryField && $categoryField->field_type === 'select'
                ? ['required', Rule::in($categoryField->options ?? [])]
                : ['required', 'string', 'max:100'],
            'consent' => ['required', 'accepted'],
            'accommodation_required' => ['nullable', 'boolean'],
            'accessibility_required' => ['nullable', 'boolean'],
            'accommodation_notes' => ['nullable', 'string', 'max:2000'],
            'food_required' => ['nullable', 'boolean'],
        ];

        return array_merge($rules, $this->customFieldRules($fields->where('is_system', false)));
    }

    private function customFieldRules($fields): array
    {
        $rules = ['custom' => ['nullable', 'array']];

        foreach ($fields as $field) {
            $fieldRules = [$field->is_required ? 'required' : 'nullable'];
            $fieldRules = array_merge($fieldRules, match ($field->field_type) {
                'textarea' => ['string', 'max:2000'],
                'number' => ['numeric'],
                'date' => ['date'],
                'select', 'radio' => [Rule::in($field->options ?? [])],
                'checkbox' => [$field->is_required ? 'accepted' : 'boolean'],
                default => ['string', 'max:255'],
            });
            $rules['custom.'.$field->field_key] = $fieldRules;
        }

        return $rules;
    }
}
