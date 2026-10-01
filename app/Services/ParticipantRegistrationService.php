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

            $registration = EventRegistration::query()->updateOrCreate(
                ['event_id' => $event->id, 'participant_id' => $participant->id],
                [
                    'status' => EventRegistration::STATUS_CONFIRMED,
                    'approved_at' => now(),
                    'cancelled_at' => null,
                    'source' => $source,
                ]
            );

            return [$participant, $registration];
        });
    }

    /**
     * Find or create the participant these registration details belong to.
     *
     * When $trusted is false (unauthenticated public registration), an existing
     * match's profile fields are only filled in when blank, never overwritten —
     * matching by phone/email alone isn't proof the submitter owns that profile.
     */
    public function resolveParticipant(Event $event, array $data, bool $trusted = true): Participant
    {
        [$phone, $secondaryPhone] = $this->splitPhones($data['phone'] ?? null);
        $email = $this->usableEmail($data['email'] ?? null);
        $memberId = $this->usableString($data['member_id'] ?? null);
        $gender = $this->usableString($data['gender'] ?? null);
        $lookupEmails = collect($data['lookup_emails'] ?? [])
            ->prepend($email)
            ->filter()
            ->map(fn ($value) => strtolower((string) $value))
            ->unique()
            ->values()
            ->all();

        // Scoped to this event's company: the same person attending events run by two
        // different companies gets an independent participant record in each, rather
        // than being treated as one shared identity (or blocked outright).
        $user = $this->findExistingUser($lookupEmails, $phone, $secondaryPhone, $memberId, $event->company_id, $trusted, $gender);

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
            // Unlike contact/profile fields, room_group is specific to each event (which area/
            // room a person is in this time), so it's never carried over from a prior event —
            // a blank submission clears it rather than falling back to the participant's history.
            'room_group' => $this->usableString($data['room_group'] ?? null),
            'company_id' => $user->company_id ?? $event->company_id,
        ])->save();

        return $user;
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

    private function findExistingUser(array $emails, ?string $phone, ?string $secondaryPhone, ?string $memberId, ?int $companyId, bool $trusted, ?string $gender): ?Participant
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
        $memberMatch = $memberId
            ? Participant::query()->where('company_id', $companyId)->where('member_id', $memberId)->first()
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
