<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParticipantRegistrationService
{
    public function register(Event $event, array $data, string $source): array
    {
        return DB::transaction(function () use ($event, $data, $source): array {
            $participant = $this->resolveParticipant($event, $data);

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

    public function resolveParticipant(Event $event, array $data): Participant
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

        $user = $this->findExistingUser($lookupEmails, $phone, $memberId);

        if ($user && $user->company_id !== null && $event->company_id !== null && $user->company_id !== $event->company_id) {
            throw ValidationException::withMessages([
                'email' => 'These details belong to a participant in another company.',
            ]);
        }

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
            'name' => $data['name'] ?? $user->name,
            'email' => $email ?? $user->email,
            'phone' => $user->phone ?? $phone,
            'member_id' => $user->member_id ?? $memberId,
            'category' => $this->usableString($data['category'] ?? null) ?? $user->category,
            'gender' => $this->usableString($data['gender'] ?? null) ?? $user->gender,
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

    private function findExistingUser(array $emails, ?string $phone, ?string $memberId): ?Participant
    {
        $matches = collect([
            $emails !== [] ? Participant::query()->whereIn('email', $emails)->first() : null,
            $phone ? Participant::query()->where('phone', $phone)->first() : null,
            $memberId ? Participant::query()->where('member_id', $memberId)->first() : null,
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
