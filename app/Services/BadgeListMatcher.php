<?php

namespace App\Services;

use App\Imports\UsersImport;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Support\Collection;

/**
 * Finds the people on an uploaded list among an event's registrations, so Badge Studio can
 * select exactly them for printing. Nobody is added or changed.
 */
class BadgeListMatcher
{
    public function __construct(private readonly ParticipantRegistrationService $people) {}

    /**
     * @param  array<int, array<int, mixed>>  $rows  The sheet's rows, first row first.
     * @return array{ids: list<int>, rows: int, problems: list<array{row: int, name: string, reason: string}>}
     */
    public function match(Event $event, array $rows, bool $staffMode): array
    {
        $registrations = $event->registrations()->with('participant')->get();
        $columns = UsersImport::headingColumns($rows[0] ?? []);
        $start = $columns ? 1 : 0;

        $ids = [];
        $problems = [];
        $count = 0;
        foreach (array_slice($rows, $start) as $offset => $row) {
            [$name, $phone, $email] = $this->person($row, $columns);
            if ($name === '' && $phone === null && $email === null) {
                continue;
            }
            $count++;
            $rowNumber = $start + $offset + 1;
            $label = $name !== '' ? $name : ($phone ?? $email);
            [$registration, $reason] = $this->find($registrations, $name, $phone, $email);
            $reason ??= match (true) {
                (bool) $registration->participant->is_support_staff !== $staffMode => $staffMode ? 'An attendee, not event staff: print from attendee badges' : 'Event staff: print from staff badges',
                $registration->status !== EventRegistration::STATUS_CONFIRMED => 'Registration is '.str_replace('_', ' ', $registration->status).', not confirmed',
                default => null,
            };
            if ($reason !== null) {
                $problems[] = ['row' => $rowNumber, 'name' => $label, 'reason' => $reason];

                continue;
            }
            $ids[$registration->id] = $registration->id;
        }

        return ['ids' => array_values($ids), 'rows' => $count, 'problems' => $problems];
    }

    /**
     * The name, phone and email in a row: from the heading columns, else column B in the import's
     * fixed layout (an ID or number in column A), else column A for a plain list of names.
     *
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private function person(array $row, ?array $columns): array
    {
        $cell = fn (?int $index) => $index === null ? '' : trim((string) ($row[$index] ?? ''));
        if ($columns) {
            [$name, $phone, $email] = [$cell($columns['name']), $cell($columns['phone'] ?? null), $cell($columns['email'] ?? null)];
        } elseif ($cell(1) !== '' && ($cell(0) === '' || is_numeric($cell(0)))) {
            [$name, $phone, $email] = [$cell(1), $cell(5), $cell(6)];
        } else {
            [$name, $phone, $email] = [$cell(0), '', ''];
        }
        $phone = $this->people->normalizePhone($phone) ?: null;

        return [$name, $phone, filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : null];
    }

    /**
     * The one registration this person matches: by phone, then email, then name.
     *
     * @param  Collection<int, EventRegistration>  $registrations
     * @return array{0: ?EventRegistration, 1: ?string} The registration, or why none was chosen.
     */
    private function find(Collection $registrations, string $name, ?string $phone, ?string $email): array
    {
        $tries = [
            [$phone, fn (EventRegistration $r) => in_array($phone, [$this->people->normalizePhone($r->participant->phone), $this->people->normalizePhone($r->participant->secondary_phone)], true)],
            [$email, fn (EventRegistration $r) => strtolower((string) $r->participant->email) === $email],
            [$name ?: null, fn (EventRegistration $r) => $this->people->sameName($r->participant->name, $name)],
        ];
        foreach ($tries as [$value, $test]) {
            if ($value === null) {
                continue;
            }
            $found = $registrations->filter($test);
            // Someone both cancelled and re-registered: the confirmed one is meant.
            if ($found->count() > 1) {
                $found = $found->where('status', EventRegistration::STATUS_CONFIRMED)->whenEmpty(fn () => $found);
            }
            if ($found->count() === 1) {
                return [$found->first(), null];
            }
            if ($found->count() > 1) {
                return [null, $found->count().' people on this event match: add a phone number or email to the list to choose'];
            }
        }

        return [null, 'Not registered for this event'];
    }
}
