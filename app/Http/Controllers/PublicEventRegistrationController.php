<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Notifications\EventRegistrationSubmitted;
use App\Services\ParticipantRegistrationService;
use App\Services\RegistrationLifecycleService;
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

    public function store(Request $request, Event $event, ParticipantRegistrationService $participants): RedirectResponse
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
            ]);
        });

        $registration->load(['event.company', 'participant']);
        $registration->participant->notify(new EventRegistrationSubmitted($registration));

        return redirect()->route('registrations.confirmation', $registration->registration_code);
    }

    public function confirmation(string $code): View
    {
        $registration = EventRegistration::with(['event.company', 'participant'])
            ->where('registration_code', $code)
            ->firstOrFail();

        return view('registrations.confirmation', compact('registration'));
    }

    public function cancel(string $code, RegistrationLifecycleService $lifecycle): RedirectResponse
    {
        $registration = EventRegistration::where('registration_code', $code)->firstOrFail();

        $lifecycle->cancel($registration);

        return back()->with('success', 'Your registration has been cancelled.');
    }

    private function rules($fields): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'category' => ['required', 'string', 'max:100'],
            'consent' => ['required', 'accepted'],
            'custom' => ['nullable', 'array'],
        ];

        foreach ($fields->where('is_system', false) as $field) {
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
