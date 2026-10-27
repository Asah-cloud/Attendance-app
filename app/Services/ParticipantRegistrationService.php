<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParticipantRegistrationService
{
    public function register(Event $event, array $data, string $source, bool $trusted = true): array
    {
        return DB::transaction(function () use ($event, $data, $source, $trusted): array {
            $participant = $this->resolveParticipant($event, $data, $trusted);
            $existing = EventRegistration::query()->where('event_id', $event->id)->where('participant_id', $participant->id)->first();

            $registration = EventRegistration::query()->updateOrCreate(
                ['event_id' => $event->id, 'participant_id' => $participant->id],
                [
                    'status' => EventRegistration::STATUS_CONFIRMED,
                    'approved_at' => now(),
                    'cancelled_at' => null,
                    'source' => $source,
                    ...$this->eventDetails($data, $participant, $existing, $trusted),
                ]
            );

            return [$participant, $registration];
        });
    }

    /**
     * Category and area for this event's registration. They come from what was submitted or
     * imported for this event; when nothing was given, a new registration starts from the
     * person's latest values and an existing one keeps its own. An untrusted public form
     * cannot change an existing registration's category.
     *
     * @return array{category: ?string, room_group: ?string}
     */
    public function eventDetails(array $data, Participant $participant, ?EventRegistration $existing, bool $trusted = true): array
    {
        $category = $this->usableString($data['category'] ?? null);
        $roomGroup = $this->usableString($data['room_group'] ?? null);

        return [
            'category' => ($trusted || ! $existing ? $category : null) ?? $existing?->category ?? $participant->getRawOriginal('category'),
            // A row for this event with a blank area means "no area for this event".
            'room_group' => array_key_exists('room_group', $data) ? $roomGroup : ($existing?->room_group ?? $participant->getRawOriginal('room_group')),
        ];
    }

    /**
     * Find or create the participant these registration details belong to.
     *
     * When $trusted is false (unauthenticated public registration), an existing
     * match's profile fields are only filled in when blank, never overwritten —
     * matching by phone/email alone isn't proof the submitter owns that profile.
     *
     * $data['force_new'] skips the lookup and always creates a new participant, for an
     * import row the manager has said is a different person from the record it matches.
     */
    public function resolveParticipant(Event $event, array $data, bool $trusted = true): Participant
    {
        [$phone, $secondaryPhone] = $this->splitPhones($data['phone'] ?? null);
        $email = $this->usableEmail($data['email'] ?? null);
        $memberId = $this->usableString($data['member_id'] ?? null);
        $gender = $this->usableString($data['gender'] ?? null);

        $user = empty($data['force_new']) ? $this->findMatch($event, $data, $trusted) : null;

        if (! $user) {
            return Participant::create([
                'name' => $data['name'],
                'email' => $email
                        ?? $this->usableString($data['generated_email'] ?? null)
                        ?? null,
                'phone' => $phone,
                'secondary_phone' => $secondaryPhone,
                'member_id' => $memberId,
                'category' => $this->usableString($data['category'] ?? null) ?? 'Member',
                'gender' => $gender,
                'room_group' => $this->usableString($data['room_group'] ?? null),
                'country' => $this->usableString($data['country'] ?? null),
                'company_id' => $event->company_id,
            ]);
        }

        $user->fill([
            'name' => $trusted ? ($data['name'] ?? $user->name) : ($user->name ?? $data['name'] ?? null),
            'email' => $trusted ? ($email ?? $user->email) : ($user->email ?? $email),
            'phone' => $user->phone ?? $phone,
            'secondary_phone' => $user->secondary_phone ?? $secondaryPhone,
            'member_id' => $user->member_id ?? $memberId,
            'category' => $trusted
                ? ($this->usableString($data['category'] ?? null) ?? $user->category)
                : ($user->category ?? $this->usableString($data['category'] ?? null)),
            'gender' => $trusted
                ? ($gender ?? $user->gender)
                : ($user->gender ?? $gender),
            'country' => $trusted
                ? ($this->usableString($data['country'] ?? null) ?? $user->country)
                : ($user->country ?? $this->usableString($data['country'] ?? null)),
            // Unlike contact/profile fields, room_group is specific to each event (which area/
            // room a person is in this time), so it's never carried over from a prior event —
            // a blank submission clears it rather than falling back to the participant's history.
            'room_group' => $this->usableString($data['room_group'] ?? null),
            'company_id' => $user->company_id ?? $event->company_id,
        ])->save();

        return $user;
    }

    /**
     * The existing participant resolveParticipant() would update for these details, without
     * changing anything - so an import can show a manager who a row is about to overwrite.
     */
    public function findMatch(Event $event, array $data, bool $trusted = true): ?Participant
    {
        [$phone, $secondaryPhone] = $this->splitPhones($data['phone'] ?? null);
        $lookupEmails = collect($data['lookup_emails'] ?? [])
            ->prepend($this->usableEmail($data['email'] ?? null))
            ->filter()
            ->map(fn ($value) => strtolower((string) $value))
            ->unique()
            ->values()
            ->all();

        // Scoped to this event's company: the same person attending events run by two
        // different companies gets an independent participant record in each, rather
        // than being treated as one shared identity (or blocked outright).
        return $this->findExistingUser(
            $lookupEmails, $phone, $secondaryPhone, $this->usableString($data['member_id'] ?? null),
            $event->company_id, $trusted, $this->usableString($data['gender'] ?? null), $data['name'] ?? null,
        );
    }

    /** Names compared ignoring case, spacing and punctuation ("Kumi-Asare" = "kumi asare"). */
    public function sameName(?string $a, ?string $b): bool
    {
        $normalize = fn (?string $value) => trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower((string) $value)) ?? '');

        return $normalize($a) !== '' && $normalize($a) === $normalize($b);
    }

    public function normalizePhone(?string $phone): ?string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone ?? '') ?? '';

        // The "00" international dialing prefix in front of the Ghana country code (e.g.
        // "00233247207499") is otherwise left as part of the digits, so the same number
        // written as "00233..." and "+233..."/"0..." would normalize to different stored
        // values and fail to match each other. Scoped to "00233" specifically — a foreign
        // number dialed as "00<their country code>..." is left untouched.
        if (str_starts_with($phone, '00233')) {
            $phone = substr($phone, 2);
        }

        if (str_starts_with($phone, '233')) {
            $phone = substr($phone, 3);
        }

        $phone = ltrim($phone, '0');

        return $phone !== '' ? $phone : null;
    }

    /**
     * A spreadsheet/PDF phone cell sometimes holds two numbers for the same person (e.g.
     * "0244123456/0201234567"). Split only on explicit multi-number separators — never on
     * bare whitespace or '-', which commonly appear inside a single formatted number — then
     * normalize each side independently and keep at most the first two.
     *
     * @return array{0: ?string, 1: ?string} [primary, secondary]
     */
    private function splitPhones(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [null, null];
        }

        $numbers = collect(preg_split('/[\/,;&\n]+|\s+(?:or|and)\s+/i', $raw))
            ->map(fn ($part) => $this->normalizePhone($part))
            ->filter()
            ->unique()
            ->values();

        return [$numbers->get(0), $numbers->get(1)];
    }

    private function findExistingUser(array $emails, ?string $phone, ?string $secondaryPhone, ?string $memberId, ?int $companyId, bool $trusted, ?string $gender, ?string $name): ?Participant
    {
        $phones = array_values(array_filter([$phone, $secondaryPhone]));

        $contactMatches = collect([
            $this->findByEmail($emails, $companyId, $gender),
            $this->findByPhone($phones, $companyId, $gender),
        ])->filter()->unique('id')->values();

        if ($contactMatches->count() > 1) {
            throw ValidationException::withMessages([
                'email' => 'The supplied email and phone belong to different participant records. Contact the organizer.',
            ]);
        }

        $contactMatch = $contactMatches->first();
        // An imported member ID is usually just the row number in column A, and a later list
        // for the same event numbers its rows from 1 again. So an ID alone only re-finds the
        // same person when the name agrees too; a different name is a new person who happens
        // to share the row number, not a rename of whoever had it before.
        $memberMatch = $memberId
            ? Participant::query()->where('company_id', $companyId)->where('member_id', $memberId)->get()
                ->first(fn (Participant $candidate) => $this->sameName($candidate->name, $name))
            : null;

        // Spreadsheet/PDF IDs are often row numbers local to one list, not permanent
        // organization-wide identities. For a trusted manager import, a matching email
        // or phone is therefore stronger evidence than a colliding imported member ID.
        if ($trusted && $contactMatch) {
            return $contactMatch;
        }

        if ($contactMatch && $memberMatch && $contactMatch->id !== $memberMatch->id) {
            throw ValidationException::withMessages([
                'email' => 'The supplied contact details and member ID belong to different participant records. Contact the organizer.',
            ]);
        }

        return $contactMatch ?? $memberMatch;
    }

    /**
     * A household often shares one email address (commonly the husband's) across its
     * members, so an email match alone isn't proof of identity the way it is for an
     * individual's own address — same disambiguation as findByPhone() below, and for the
     * same reason: a spouse of a different, known gender is a distinct person, not a match.
     */
    private function findByEmail(array $emails, ?int $companyId, ?string $gender): ?Participant
    {
        if ($emails === []) {
            return null;
        }

        $candidates = Participant::query()->where('company_id', $companyId)->whereIn('email', $emails)->get();

        if ($gender === null) {
            return $candidates->first();
        }

        return $candidates->first(fn (Participant $candidate) => $candidate->gender === null || $candidate->gender === $gender);
    }

    /**
     * A household often registers several people under one shared phone number, so a phone
     * match alone isn't proof of identity the way it is for an individual's own number. When
     * this row states a gender, prefer a same-company candidate on that phone whose gender
     * agrees (or is still unknown) over one on record as a different gender — the latter is
     * treated as a distinct family member sharing the line, not the same person, and gets
     * its own participant record instead of silently overwriting theirs.
     */
    private function findByPhone(array $phones, ?int $companyId, ?string $gender): ?Participant
    {
        if ($phones === []) {
            return null;
        }

        $candidates = Participant::query()->where('company_id', $companyId)
            ->where(fn ($query) => $query->whereIn('phone', $phones)->orWhereIn('secondary_phone', $phones))
            ->get();

        if ($gender === null) {
            return $candidates->first();
        }

        return $candidates->first(fn (Participant $candidate) => $candidate->gender === null || $candidate->gender === $gender);
    }

    private function usableEmail(?string $email): ?string
    {
        $email = $this->usableString($email);

        return $email && ! str_ends_with($email, '@example.invalid') ? strtolower($email) : null;
    }

    private function usableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
