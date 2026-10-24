<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Participant;
use Illuminate\Support\Collection;

/**
 * The one place attendance reports are counted. Every report page, PDF and spreadsheet reads
 * these numbers, so they always agree. The rules:
 *
 *  - Expected: confirmed attendees (support staff excluded), on every day.
 *  - A day is "held" once it has started and at least one check-in was recorded on it (or it is
 *    today, while the event runs). Days
 *    with no check-ins (an event that ran longer than its check-ins, or a day still to come)
 *    are left out of the columns, of "days attended" and of averages, never counted as absences.
 *  - Days attended: held days on which the person checked in at least once (any session).
 *  - Average daily turnout: the mean of each held day's present ÷ expected.
 *  - Numbered participant staff ("Participant 7") count as present on every held day.
 */
class AttendanceGrid
{
    public const NOT_PRESENT = '—';

    public const FILTERS = ['area', 'category', 'gender'];

    public function __construct(private readonly AttendanceReportData $report) {}

    /**
     * @param  array{area?: ?string, category?: ?string, gender?: ?string}  $filters
     */
    public function build(Event $event, int|string $day, ?int $session = null, array $filters = []): array
    {
        $session = $day === 'all' || (int) $day < 1 ? null : $session;
        $filters = array_filter(array_intersect_key($filters, array_flip(self::FILTERS)), fn ($value) => filled($value));
        ['presentUsers' => $present, 'absentUsers' => $absent, 'totalExpected' => $expected] = $this->report->forPeriod($event, $day, $session);

        if ($filters !== []) {
            $present = $present->filter(fn ($person) => self::matches($person, $filters))->values();
            $absent = $absent->filter(fn ($person) => self::matches($person, $filters))->values();
            $expected = $present->count() + $absent->count();
        }

        $heldDays = $this->heldDays($event);
        $arrivalHeld = $event->has_arrival_session && Attendance::query()->where('event_id', $event->id)->where('day', 0)->exists();
        $isHeld = fn (int $d) => $d === 0 ? $arrivalHeld : $heldDays->contains($d);
        $held = match (true) {
            $day === 'all' => $heldDays->isNotEmpty(),
            default => $isHeld((int) $day),
        };

        if (! $held) {
            // Nothing was checked in for this period: no one can be present or absent yet.
            $present = collect();
            $absent = collect();
        }

        $slots = $this->slots($event, $day, $session, $heldDays, $arrivalHeld);
        $checkIns = Attendance::query()
            ->where('event_id', $event->id)
            ->whereIn('participant_id', $present->pluck('id'))
            ->orderBy('created_at')
            ->get()
            ->groupBy('participant_id');

        $rows = $present->values()->map(function (Participant $person) use ($slots, $checkIns, $heldDays, $isHeld) {
            $isStaff = $person->isNumberedParticipantStaff();
            $mine = $checkIns->get($person->id, collect());
            $cells = [];
            foreach ($slots as $slot) {
                $hit = $mine->first(fn ($a) => (int) $a->day === $slot['day'] && (int) ($a->session ?? 1) === $slot['session']);
                $cells[$slot['key']] = match (true) {
                    $hit !== null => $hit->created_at->format('g:i A'),
                    $isStaff && $slot['day'] >= 1 => 'Staff',
                    $isHeld($slot['day']) => self::NOT_PRESENT,
                    default => '',
                };
            }
            $methods = $mine->where('day', '>=', 0)->pluck('method')->map(fn ($m) => $m === 'self' ? 'self' : 'staff')->unique();

            return (object) [
                'person' => $person,
                'area' => self::area($person),
                'isStaff' => $isStaff,
                'cells' => $cells,
                'checkIns' => $mine,
                'daysAttended' => $isStaff
                    ? $heldDays->count()
                    : $mine->pluck('day')->map(fn ($d) => (int) $d)->filter(fn ($d) => $heldDays->contains($d))->unique()->count(),
                'method' => match (true) {
                    $isStaff => 'Staff check-in (all event days)',
                    $methods->count() > 1 => 'Self & by staff',
                    $methods->first() === 'self' => 'Self check-in',
                    $methods->isEmpty() => '',
                    default => 'By staff',
                },
            ];
        });

        $countIn = fn (Collection $group, array $slot) => $group->filter(fn ($row) => ! in_array($row->cells[$slot['key']], ['', self::NOT_PRESENT], true))->count();
        $slotStats = $slots->mapWithKeys(fn ($slot) => [$slot['key'] => [
            'present' => $countIn($rows, $slot),
            'rate' => $expected ? round($countIn($rows, $slot) / $expected * 100) : 0,
        ]]);

        // One figure per held day (sessions merged), used for turnout.
        $dayStats = $slots->where('day', '>=', 1)->groupBy('day')->map(function ($daySlots, $d) use ($rows, $expected) {
            $present = $rows->filter(fn ($row) => $daySlots->contains(fn ($slot) => ! in_array($row->cells[$slot['key']], ['', self::NOT_PRESENT], true)))->count();

            return ['day' => (int) $d, 'label' => 'Day '.$d, 'present' => $present, 'absent' => max(0, $expected - $present), 'rate' => $expected ? round($present / $expected * 100) : 0];
        })->values();

        $turnout = match (true) {
            ! $held || ! $expected => 0,
            $day === 'all' => $dayStats->isEmpty() ? 0 : (int) round($dayStats->avg('rate')),
            default => (int) round($rows->count() / $expected * 100),
        };

        return [
            'day' => $day,
            'session' => $session,
            'filters' => $filters,
            'held' => $held,
            'heldDays' => $heldDays,
            'slots' => $slots,
            'slotStats' => $slotStats,
            'dayStats' => $dayStats,
            'rows' => $rows,
            'absent' => $absent->values(),
            'totalExpected' => $expected,
            'turnout' => $turnout,
            'multiDay' => $day === 'all' && $heldDays->count() > 1,
            'totalDays' => $heldDays->count(),
            'eventDays' => $event->totalDays(),
            'periodLabel' => $day === 'all' ? 'All days' : $event->attendanceSessionLabel($day, $session ?? 1),
            'presentLabel' => $day === 'all' ? 'Came at least once' : 'Present',
            'absentLabel' => match (true) {
                $day === 'all' => 'Never came',
                $event->status === 'active' && (int) $day === $event->currentDay() => 'Not yet checked in',
                default => 'Absent',
            },
        ];
    }

    public static function area(Participant $person): string
    {
        return $person->room_group ?: ($person->department ?: 'Area not specified');
    }

    /** e.g. "Area: Accra · Gender: Male", or null when nothing is filtered. */
    public static function filterLabel(array $filters): ?string
    {
        return collect($filters)->map(fn ($value, $key) => ucfirst($key).': '.$value)->join(' · ') ?: null;
    }

    public static function matches(Participant $person, array $filters): bool
    {
        return (! isset($filters['area']) || self::area($person) === $filters['area'])
            && (! isset($filters['category']) || ($person->category ?: 'Unspecified') === $filters['category'])
            && (! isset($filters['gender']) || ($person->gender ?: 'Unspecified') === $filters['gender']);
    }

    /**
     * Program days that have started and have at least one check-in, in order.
     *
     * @return Collection<int, int>
     */
    public function heldDays(Event $event): Collection
    {
        $lastStarted = match ($event->status) {
            'upcoming' => 0,
            'active' => $event->currentDay(),
            default => $event->totalDays(),
        };

        if ($lastStarted < 1) {
            return collect();
        }

        return Attendance::query()
            ->where('event_id', $event->id)
            ->whereBetween('day', [1, $lastStarted])
            ->distinct()
            ->pluck('day')
            ->map(fn ($d) => (int) $d)
            // Today is under way while the event runs, even before the first check-in.
            ->when($event->status === 'active', fn ($days) => $days->push($event->currentDay()))
            ->unique()
            ->sort()
            ->values();
    }

    /** @return Collection<int, array{key: string, day: int, session: int, label: string}> */
    private function slots(Event $event, int|string $day, ?int $session, Collection $heldDays, bool $arrivalHeld): Collection
    {
        $days = $day === 'all'
            ? collect($arrivalHeld ? [0] : [])->concat($heldDays)
            : collect([(int) $day]);

        return $days->flatMap(function (int $d) use ($event, $session) {
            if ($d === 0) {
                return [['key' => '0:1', 'day' => 0, 'session' => 1, 'label' => 'Arrival']];
            }
            $sessions = $session !== null
                ? collect([['session' => $session, 'label' => null]])
                : $event->sessionsForDay($d);
            $several = $sessions->count() > 1;

            return $sessions->map(fn ($s) => [
                'key' => $d.':'.$s['session'],
                'day' => $d,
                'session' => (int) $s['session'],
                'label' => $several || $session !== null
                    ? 'Day '.$d.' · '.($s['label'] ?: 'Session '.$s['session'])
                    : 'Day '.$d,
            ]);
        })->values();
    }
}
