<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventRegistration;

/**
 * Who has registered for an event, at any time: before it starts ("who is coming") and after
 * ("who came"). Counts everyone registered, not check-ins, so it works on an event that hasn't
 * begun. Support staff are left out, as in the attendance reports. The page and both downloads
 * read this, so they always agree.
 */
class RegistrationReport
{
    public const FILTERS = ['status', 'area', 'category', 'gender', 'country'];

    /** Every registration status, in the order shown. The default filter is "confirmed". */
    public const STATUSES = [
        EventRegistration::STATUS_CONFIRMED => 'Confirmed',
        EventRegistration::STATUS_PENDING => 'Pending approval',
        EventRegistration::STATUS_AWAITING_CONFIRMATION => 'Awaiting confirmation',
        EventRegistration::STATUS_WAITLISTED => 'Waitlisted',
        EventRegistration::STATUS_CANCELLED => 'Cancelled',
        EventRegistration::STATUS_REJECTED => 'Rejected',
    ];

    /** "All statuses" in the status filter. */
    public const ANY_STATUS = 'all';

    /** The groups each report splits people by: heading => row field. */
    public const BREAKDOWNS = ['Category' => 'category', 'Gender' => 'gender', 'Country' => 'country', 'Area' => 'area'];

    /**
     * @param  array{status?: ?string, area?: ?string, category?: ?string, gender?: ?string, country?: ?string}  $filters
     */
    public function build(Event $event, array $filters = []): array
    {
        $filters = self::normalise($filters);
        $daysCheckedIn = Attendance::query()->where('event_id', $event->id)->where('day', '>=', 1)
            ->selectRaw('participant_id, count(distinct day) as days')->groupBy('participant_id')
            ->pluck('days', 'participant_id');

        $everyone = $event->registrations()->with('participant')
            ->whereHas('participant', fn ($query) => $query->where('is_support_staff', false))
            ->get()
            ->map(fn (EventRegistration $registration) => (object) [
                'registration' => $registration,
                'person' => $registration->participant,
                'status' => $registration->status,
                'statusLabel' => self::STATUSES[$registration->status] ?? ucfirst((string) $registration->status),
                'area' => AttendanceGrid::area($registration->participant),
                'country' => AttendanceGrid::country($registration->participant),
                'category' => $registration->participant->category ?: 'Unspecified',
                'gender' => $registration->participant->gender ?: 'Unspecified',
                'daysCheckedIn' => (int) ($daysCheckedIn[$registration->participant_id] ?? 0),
            ])
            ->sortBy(fn ($row) => mb_strtolower($row->person->name))
            ->values();

        $matchesGroups = fn ($row) => collect(self::BREAKDOWNS)->every(fn ($field) => ! isset($filters[$field]) || $row->{$field} === $filters[$field]);
        $inGroups = $everyone->filter($matchesGroups);
        $rows = $inGroups->filter(fn ($row) => $filters['status'] === self::ANY_STATUS || $row->status === $filters['status'])->values();

        return [
            'rows' => $rows,
            'filters' => $filters,
            // Every status, for the people matching the other filters, so the counts show the whole picture.
            'statusCounts' => collect(self::STATUSES)->map(fn ($label, $status) => $inGroups->where('status', $status)->count())->filter(fn ($count, $status) => $count > 0 || $status === EventRegistration::STATUS_CONFIRMED),
            'breakdowns' => collect(self::BREAKDOWNS)->map(fn ($field) => $rows->countBy($field)->sortDesc()),
            'options' => collect(self::BREAKDOWNS)->mapWithKeys(fn ($field) => [$field => $everyone->pluck($field)->unique()->sort()->values()]),
            'checkedIn' => $rows->filter(fn ($row) => $row->daysCheckedIn > 0)->count(),
            'eventDays' => $event->totalDays(),
        ];
    }

    /** The filters with "status" always set, defaulting to confirmed (who is coming). */
    public static function normalise(array $filters): array
    {
        $filters = collect($filters)->only(self::FILTERS)->map(fn ($value) => trim((string) $value))->filter(fn ($value) => $value !== '')->all();
        $status = $filters['status'] ?? EventRegistration::STATUS_CONFIRMED;
        $filters['status'] = $status === self::ANY_STATUS || isset(self::STATUSES[$status]) ? $status : EventRegistration::STATUS_CONFIRMED;

        return $filters;
    }

    /** e.g. "Confirmed · Country: Ghana", for titles and download headers. */
    public static function filterLabel(array $filters): string
    {
        return collect($filters)->map(fn ($value, $key) => $key === 'status'
            ? ($value === self::ANY_STATUS ? 'All statuses' : self::STATUSES[$value])
            : ucfirst($key).': '.$value)->join(' · ');
    }

    /** "Not yet" or "2 of 3 days", for the Checked in column. */
    public static function checkedInLabel(object $row, int $eventDays): string
    {
        return $row->daysCheckedIn ? $row->daysCheckedIn.' of '.$eventDays.' '.($eventDays === 1 ? 'day' : 'days') : 'Not yet';
    }
}
