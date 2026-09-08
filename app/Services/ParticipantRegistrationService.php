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
        $phone = $this->normalizePhone($data['phone'] ?? null);
        $email = $this->usableEmail($data['email'] ?? null);
        $memberId = $this->usableString($data['member_id'] ?? null);
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
        $user = $this->findExistingUser($lookupEmails, $phone, $memberId, $event->company_id);

        if (! $user) {
            return Participant::create([
                'name' => $data['name'],
                'email' => $email
                        ?? $this->usableString($data['generated_email'] ?? null)
                        ?? null,
                'phone' => $phone,
                'member_id' => $memberId,
                'category' => $this->usableString($data['category'] ?? null) ?? 'Member',
                'gender' => $this->usableString($data['gender'] ?? null),
                'company_id' => $event->company_id,
            ]);
        }

        $user->fill([
            'name' => $trusted ? ($data['name'] ?? $user->name) : ($user->name ?? $data['name'] ?? null),
            'email' => $trusted ? ($email ?? $user->email) : ($user->email ?? $email),
            'phone' => $user->phone ?? $phone,
            'member_id' => $user->member_id ?? $memberId,
            'category' => $trusted
                ? ($this->usableString($data['category'] ?? null) ?? $user->category)
                : ($user->category ?? $this->usableString($data['category'] ?? null)),
            'gender' => $trusted
                ? ($this->usableString($data['gender'] ?? null) ?? $user->gender)
                : ($user->gender ?? $this->usableString($data['gender'] ?? null)),
            'company_id' => $user->company_id ?? $event->company_id,
        ])->save();

        return $user;
    }

    public function normalizePhone(?string $phone): ?string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone ?? '') ?? '';

        if (str_starts_with($phone, '233')) {
            $phone = substr($phone, 3);
        }

        $phone = ltrim($phone, '0');

        return $phone !== '' ? $phone : null;
    }

    private function findExistingUser(array $emails, ?string $phone, ?string $memberId, ?int $companyId): ?Participant
    {
        $matches = collect([
            $emails !== [] ? Participant::query()->where('company_id', $companyId)->whereIn('email', $emails)->first() : null,
            $phone ? Participant::query()->where('company_id', $companyId)->where('phone', $phone)->first() : null,
            $memberId ? Participant::query()->where('company_id', $companyId)->where('member_id', $memberId)->first() : null,
        ])->filter()->unique('id')->values();

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'email' => 'The supplied email and phone belong to different participant records. Contact the organizer.',
            ]);
        }

        return $matches->first();
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
