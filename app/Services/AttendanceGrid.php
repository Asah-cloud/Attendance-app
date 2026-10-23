<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Participant;
use Illuminate\Support\Collection;

/**
 * Turns the attendance for a report period into one row per person and one column per
 * day (or per session when a day has several), so downloads show "Day 1 | Day 2" side by
 * side instead of squeezing every check-in into a single column.
 */
class AttendanceGrid
{
    public const NOT_PRESENT = '—';

    public function __construct(private readonly AttendanceReportData $report) {}

    /**
     * @return array{
     *     slots: Collection<int, array{key: string, day: int, session: int, label: string}>,
     *     rows: Collection<int, object>,
     *     absent: Collection<int, Participant>,
     *     totalExpected: int,
     *     multiDay: bool,
     *     periodLabel: string,
     * }
     */
    public function build(Event $event, int|string $day, ?int $session = null, ?string $area = null): array
    {
        $session = $day === 'all' || (int) $day < 1 ? null : $session;
        ['presentUsers' => $present, 'absentUsers' => $absent, 'totalExpected' => $totalExpected] = $this->report->forPeriod($event, $day, $session);

        if ($area !== null) {
            $present = $present->filter(fn ($person) => self::area($person) === $area)->values();
            $absent = $absent->filter(fn ($person) => self::area($person) === $area)->values();
            $totalExpected = $present->count() + $absent->count();
        }

        $slots = $this->slots($event, $day, $session);
        $checkIns = Attendance::query()
            ->where('event_id', $event->id)
            ->whereIn('participant_id', $present->pluck('id'))
            ->orderBy('created_at')
            ->get()
            ->groupBy('participant_id');
        $lastDayHeld = $this->lastDayHeld($event);

        $rows = $present->values()->map(function (Participant $person) use ($slots, $checkIns, $lastDayHeld) {
            $isStaff = $person->isNumberedParticipantStaff();
            $mine = $checkIns->get($person->id, collect());
            $cells = [];
            foreach ($slots as $slot) {
                $hit = $mine->first(fn ($a) => (int) $a->day === $slot['day'] && (int) ($a->session ?? 1) === $slot['session']);
                $cells[$slot['key']] = match (true) {
                    $hit !== null => $hit->created_at->format('g:i A'),
                    $isStaff && $slot['day'] >= 1 && $slot['day'] <= $lastDayHeld => 'Staff',
                    $slot['day'] > $lastDayHeld => '',
                    default => self::NOT_PRESENT,
                };
            }
            $daysAttended = $isStaff
                ? $lastDayHeld
                : $mine->where('day', '>=', 1)->pluck('day')->unique()->count();
            $methods = $mine->where('day', '>=', 0)->pluck('method')->map(fn ($m) => $m === 'self' ? 'self' : 'staff')->unique();

            return (object) [
                'person' => $person,
                'area' => self::area($person),
                'cells' => $cells,
                'daysAttended' => $daysAttended,
                'method' => match (true) {
                    $isStaff => 'Staff check-in (all event days)',
                    $methods->count() > 1 => 'Self & by staff',
                    $methods->first() === 'self' => 'Self check-in',
                    $methods->isEmpty() => '',
                    default => 'By staff',
                },
            ];
        });

        return [
            'slots' => $slots,
            'rows' => $rows,
            'absent' => $absent->values(),
            'totalExpected' => $totalExpected,
            'multiDay' => $day === 'all' && $event->totalDays() > 1,
            'totalDays' => $event->totalDays(),
            'periodLabel' => $day === 'all' ? 'All days' : $event->attendanceSessionLabel($day, $session ?? 1),
        ];
    }

    public static function area(Participant $person): string
    {
        return $person->room_group ?: ($person->department ?: 'Area not specified');
    }

    /** @return Collection<int, array{key: string, day: int, session: int, label: string}> */
    private function slots(Event $event, int|string $day, ?int $session): Collection
    {
        $days = $day === 'all'
            ? collect($event->has_arrival_session ? [0] : [])->concat(range(1, $event->totalDays()))
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

    /** The last program day that has started; later days are left blank, not marked absent. */
    private function lastDayHeld(Event $event): int
    {
        return match (true) {
            $event->status === 'upcoming' => 0,
            $event->status === 'active' => $event->currentDay(),
            default => $event->totalDays(),
        };
    }
}
