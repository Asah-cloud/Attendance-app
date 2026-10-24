<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/** The Advanced report view, built on an all-days AttendanceGrid. */
class AttendanceInsights
{
    public function __construct(private readonly AttendanceGrid $grid) {}

    /** When people checked in: a count per hour for each held day (and Arrival). */
    public function checkInTimes(array $grid): array
    {
        $byDay = $grid['slots']->groupBy('day')->map(function ($slots, $day) use ($grid) {
            $hours = $grid['rows']->reject->isStaff
                ->map(fn ($row) => $row->checkIns->where('day', (int) $day)->first())
                ->filter()
                ->countBy(fn ($checkIn) => (int) $checkIn->created_at->format('G'));

            return ['label' => (int) $day === 0 ? 'Arrival' : 'Day '.$day, 'hours' => $hours, 'peak' => $hours->isEmpty() ? null : $hours->sortDesc()->keys()->first()];
        })->values();

        $all = $byDay->flatMap(fn ($day) => $day['hours']->keys());

        return [
            'days' => $byDay,
            'hours' => $all->isEmpty() ? [] : range($all->min(), $all->max()),
            'max' => max(1, (int) $byDay->max(fn ($day) => $day['hours']->max())),
        ];
    }

    /** Self check-in on the shared link versus check-in by staff, per day (people, not scans). */
    public function methods(array $grid): Collection
    {
        return $grid['slots']->groupBy('day')->map(function ($slots, $day) use ($grid) {
            $firsts = $grid['rows']->reject->isStaff->map(fn ($row) => $row->checkIns->where('day', (int) $day)->first())->filter();
            $self = $firsts->where('method', 'self')->count();

            return ['label' => (int) $day === 0 ? 'Arrival' : 'Day '.$day, 'self' => $self, 'staff' => $firsts->count() - $self, 'total' => $firsts->count()];
        })->values();
    }

    /** People who came, then missed at least one later day. */
    public function dropOffs(array $grid): Collection
    {
        $held = $grid['heldDays'];

        return $grid['rows']->reject->isStaff->map(function ($row) use ($held) {
            $came = $row->checkIns->pluck('day')->map(fn ($d) => (int) $d)->filter(fn ($d) => $held->contains($d))->unique();
            if ($came->isEmpty()) {
                return null;
            }
            $missed = $held->filter(fn ($d) => $d > $came->min() && ! $came->contains($d))->values();

            return $missed->isEmpty() ? null : (object) ['person' => $row->person, 'area' => $row->area, 'came' => $came->sort()->values(), 'missed' => $missed];
        })->filter()->sortByDesc(fn ($row) => $row->missed->count())->values();
    }

    /** Headline numbers for this event and, when chosen, another one to compare against. */
    public function comparison(Event $event, array $grid, ?int $compareId, User $user): array
    {
        $candidates = Event::query()
            ->where('company_id', $event->company_id)
            ->whereKeyNot($event->id)
            ->orderByDesc('event_date')
            ->limit(30)
            ->get()
            ->filter(fn (Event $other) => Gate::forUser($user)->allows('view', $other))
            ->values();
        $other = $compareId ? $candidates->firstWhere('id', $compareId) : null;

        return [
            'candidates' => $candidates,
            'current' => $this->headline($event, $grid),
            'other' => $other ? $this->headline($other, $this->grid->build($other, 'all')) : null,
        ];
    }

    public function headline(Event $event, array $grid): array
    {
        $best = $grid['dayStats']->sortByDesc('rate')->first();

        return [
            'event' => $event,
            'expected' => $grid['totalExpected'],
            'came' => $grid['rows']->count(),
            'never' => $grid['absent']->count(),
            'days' => $grid['totalDays'],
            'turnout' => $grid['turnout'],
            'best' => $best ? $best['label'].' ('.$best['rate'].'%)' : '—',
        ];
    }

    /**
     * Confirmed attendees whose details would make the breakdowns unreliable.
     *
     * @return Collection<int, array{label: string, hint: string, people: Collection}>
     */
    public function dataQuality(Event $event): Collection
    {
        $people = $event->confirmedParticipants()->orderBy('name')->get();
        $digits = fn (?string $phone) => substr(preg_replace('/\D+/', '', (string) $phone), -9);
        $phoneCounts = $people->map(fn ($person) => $digits($person->phone))->filter()->countBy();

        return collect([
            ['label' => 'No area', 'hint' => 'They are grouped under "Area not specified".', 'people' => $people->filter(fn (Participant $p) => blank($p->room_group) && blank($p->department))],
            ['label' => 'No gender', 'hint' => 'They show as "Unspecified" in the gender split.', 'people' => $people->filter(fn (Participant $p) => blank($p->gender))],
            ['label' => 'Unusual gender value', 'hint' => 'Anything other than Male or Female, such as a country typed in the wrong column.', 'people' => $people->filter(fn (Participant $p) => filled($p->gender) && ! in_array(strtolower(trim($p->gender)), ['male', 'female', 'm', 'f'], true))],
            ['label' => 'No category', 'hint' => 'They show as "Unspecified" in the category split.', 'people' => $people->filter(fn (Participant $p) => blank($p->category))],
            ['label' => 'No phone number', 'hint' => 'They cannot use phone check-in or receive SMS.', 'people' => $people->filter(fn (Participant $p) => blank($p->phone))],
            ['label' => 'Phone number shared by several people', 'hint' => 'Phone check-in can only find one of them.', 'people' => $people->filter(fn (Participant $p) => filled($digits($p->phone)) && $phoneCounts[$digits($p->phone)] > 1)],
        ])->map(fn ($issue) => $issue + ['count' => $issue['people']->count(), 'listable' => true])
            ->when($this->dominantCategory($people), fn ($issues, $dominant) => $issues->push([
                'label' => 'Category is the same for almost everyone',
                'hint' => "{$dominant['count']} of {$people->count()} are \"{$dominant['value']}\", so the category split says little. Ministers, wives, guests or similar groups would make it useful.",
                'people' => collect(),
                'count' => $dominant['count'],
                'listable' => false,
            ]));
    }

    /** @return array{value: string, count: int}|null when one category covers 90%+ of 10 or more people. */
    private function dominantCategory(Collection $people): ?array
    {
        $top = $people->countBy(fn ($p) => $p->category ?: 'Unspecified')->sortDesc();
        $count = (int) $top->first();

        return $people->count() >= 10 && $count / $people->count() >= 0.9 ? ['value' => (string) $top->keys()->first(), 'count' => $count] : null;
    }
}
