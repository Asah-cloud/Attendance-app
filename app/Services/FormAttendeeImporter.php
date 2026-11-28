<?php

namespace App\Services;

use App\Models\EventRegistration;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormResponse;
use App\Models\Participant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Adds people who answered a custom form to the event's attendee list, using the
 * form's mapping of which question holds the name, phone, email and so on.
 *
 * Responses come from a public form, so they are matched like public registrations
 * (untrusted): an existing person's details are only filled in where blank, never
 * overwritten. No confirmation message is sent; the person just joins the list.
 */
class FormAttendeeImporter
{
    /** Attendee details a form question can supply, with the words used to guess which question it is. */
    public const TARGETS = [
        'name' => ['label' => 'Name', 'hint' => 'Full name, or first name if the surname is asked separately'],
        'last_name' => ['label' => 'Surname', 'hint' => 'Only if the form asks for it separately'],
        'phone' => ['label' => 'Phone'],
        'email' => ['label' => 'Email'],
        'gender' => ['label' => 'Gender'],
        'category' => ['label' => 'Category'],
        'country' => ['label' => 'Country'],
        'room_group' => ['label' => 'Area'],
    ];

    /** Question types that can hold a person's details (a tick box cannot). */
    public const MAPPABLE_TYPES = ['text', 'textarea', 'number', 'date', 'select', 'radio'];

    public const ADDED = 'added';

    public const EXISTING = 'existing';

    public const NO_NAME = 'no_name';

    public const UNPAID = 'unpaid';

    public const CONFLICT = 'conflict';

    public const ALREADY = 'already';

    public function __construct(private readonly ParticipantRegistrationService $participants) {}

    /** @return Collection<int, FormField> */
    public function mappableFields(Form $form): Collection
    {
        return $form->fields()->where('is_active', true)->whereIn('field_type', self::MAPPABLE_TYPES)->get();
    }

    /**
     * The saved mapping, or a guess from the question labels when none has been saved.
     *
     * @return array<string, ?string> target => field_key
     */
    public function mapping(Form $form): array
    {
        if ($form->attendee_mapping) {
            return array_merge(array_fill_keys(array_keys(self::TARGETS), null), $form->attendee_mapping);
        }

        return $this->guessMapping($this->mappableFields($form));
    }

    /** @return array<string, ?string> */
    public function guessMapping(Collection $fields): array
    {
        $label = fn (FormField $field) => ' '.trim(preg_replace('/[^a-z]+/', ' ', mb_strtolower($field->label))).' ';
        $has = fn (FormField $field, array $words) => collect($words)->contains(fn ($word) => str_contains($label($field), ' '.$word.' '));
        $find = fn (array $words, array $not = []) => $fields->first(fn (FormField $field) => $has($field, $words) && ! $has($field, $not))?->field_key;

        // Names of things other than the person ("church name", "name of school") are not the person's name.
        $notPerson = ['church', 'company', 'organisation', 'organization', 'school', 'institution', 'group', 'team', 'department', 'business', 'association', 'society', 'emergency', 'kin', 'parent', 'guardian', 'spouse', 'father', 'mother'];
        $lastName = $find(['surname', 'last name', 'family name', 'lastname'], $notPerson);
        $firstName = $lastName ? $find(['first name', 'firstname', 'given name', 'other names', 'other name', 'forename', 'forenames'], $notPerson) : null;

        $guess = [
            'name' => $firstName ?? $find(['full name', 'fullname', 'name', 'names'], [...$notPerson, 'surname', 'last']),
            'last_name' => $firstName ? $lastName : null,
            'phone' => $find(['phone', 'telephone', 'mobile', 'contact', 'tel', 'whatsapp', 'cell'], ['emergency', 'kin', 'parent', 'guardian', 'spouse']),
            'email' => $find(['email', 'e mail', 'mail'], ['emergency', 'kin', 'parent', 'guardian', 'spouse']),
            'gender' => $find(['gender', 'sex']),
            'category' => $find(['category', 'designation', 'role', 'position']),
            'country' => $find(['country', 'nationality']),
            'room_group' => $find(['area', 'district', 'zone', 'region', 'branch', 'circuit', 'conference', 'diocese']),
        ];

        // One question supplies one detail: a later guess cannot reuse a question already taken.
        $used = [];
        foreach ($guess as $target => $key) {
            if ($key !== null && in_array($key, $used, true)) {
                $guess[$target] = null;
            } elseif ($key !== null) {
                $used[] = $key;
            }
        }

        return $guess;
    }

    /**
     * Add every response not yet on the attendee list.
     *
     * @return array<string, int> outcome => count
     */
    public function addAll(Form $form): array
    {
        $counts = array_fill_keys([self::ADDED, self::EXISTING, self::NO_NAME, self::UNPAID, self::CONFLICT], 0);

        $form->responses()->whereNull('event_registration_id')->orderBy('id')
            ->each(function (FormResponse $response) use ($form, &$counts): void {
                $response->setRelation('form', $form);
                $outcome = $this->add($response);
                if (isset($counts[$outcome])) {
                    $counts[$outcome]++;
                }
            });

        return $counts;
    }

    /** Add one response to the attendee list; returns what happened (one of the outcome constants). */
    public function add(FormResponse $response): string
    {
        $form = $response->form;
        $event = $form->event;
        $mapping = $this->mapping($form);

        if ($form->collectsPayment() && $response->payment_status !== 'paid') {
            return self::UNPAID;
        }

        $answer = function (string $target) use ($mapping, $response): ?string {
            $key = $mapping[$target] ?? null;
            $value = $key !== null ? ($response->answers[$key] ?? null) : null;
            $value = is_scalar($value) ? trim((string) $value) : '';

            return $value !== '' ? $value : null;
        };

        $name = trim(implode(' ', array_filter([$answer('name'), $answer('last_name')])));
        if ($name === '') {
            return self::NO_NAME;
        }
        $email = $answer('email');

        try {
            return DB::transaction(function () use ($response, $event, $name, $email, $answer): string {
                // A response can be added by the manager's button and by a payment arriving at once.
                $locked = FormResponse::query()->lockForUpdate()->findOrFail($response->id);
                if ($locked->event_registration_id) {
                    return self::ALREADY;
                }

                $details = array_filter([
                    'name' => $name,
                    'email' => $email && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
                    'phone' => $answer('phone'),
                    'gender' => $answer('gender'),
                    'category' => $answer('category'),
                    'country' => $answer('country'),
                    'room_group' => $answer('room_group'),
                ], fn ($value) => $value !== null);

                // A phone or email already on record under a clearly different name is someone else's:
                // a leader often fills the form for others with her own number, and older lists carry
                // wrong numbers. Joining them would put this person on the list under the other name.
                $match = $this->participants->findMatch($event, $details, trusted: false);
                $registration = null;
                $sameContact = null;
                if ($match && ! $this->participants->namesAgree($match->name, $name)) {
                    // Several people can share the number: the one whose name agrees is this person.
                    $phone = $this->participants->normalizePhone($details['phone'] ?? null);
                    $sameContact = Participant::query()->where('company_id', $event->company_id)
                        ->where(fn ($query) => $query
                            ->when($phone, fn ($q) => $q->orWhere('phone', $phone)->orWhere('secondary_phone', $phone))
                            ->when($details['email'] ?? null, fn ($q, $email) => $q->orWhereRaw('lower(email) = ?', [mb_strtolower($email)])))
                        ->get()->first(fn (Participant $candidate) => $this->participants->namesAgree($candidate->name, $name));
                    // Or this person (same name) is on this event's list already, e.g. sent the form
                    // again later with her own number.
                    $registration = $sameContact ? null : EventRegistration::query()->where('event_id', $event->id)->with('participant')->get()
                        ->first(fn (EventRegistration $existing) => $this->participants->nameKey($existing->participant?->name) === $this->participants->nameKey($name));
                    $details['force_new'] = true;
                }

                $participant = $sameContact ?? $registration?->participant ?? $this->participants->resolveParticipant($event, $details, trusted: false);
                $registration ??= EventRegistration::query()->where('event_id', $event->id)->where('participant_id', $participant->id)->first();
                $outcome = $registration ? self::EXISTING : self::ADDED;

                $registration ??= EventRegistration::create([
                    'event_id' => $event->id,
                    'participant_id' => $participant->id,
                    'status' => EventRegistration::STATUS_CONFIRMED,
                    'approved_at' => now(),
                    'source' => 'form',
                    ...$this->participants->eventDetails($details, $participant, null, trusted: false),
                    'food_required' => false,
                ]);

                $locked->update(['event_registration_id' => $registration->id]);
                $response->event_registration_id = $registration->id;

                return $outcome;
            });
        } catch (ValidationException) {
            // The email and phone belong to two different people already on record.
            return self::CONFLICT;
        }
    }

    /**
     * Add a new response straight away when the form is set to do so. Never lets a
     * problem here stop the person's answers from being saved.
     */
    public function addIfAutomatic(FormResponse $response): void
    {
        $response->loadMissing('form.event');
        if (! $response->form?->auto_add_attendees) {
            return;
        }

        try {
            $this->add($response);
        } catch (Throwable $exception) {
            Log::warning('Could not add a form response to the attendee list', ['form_response_id' => $response->id, 'error' => $exception->getMessage()]);
        }
    }
}
