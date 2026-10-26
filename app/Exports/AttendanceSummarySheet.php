<?php

namespace App\Exports;

use App\Exports\Concerns\WritesSummarySections;
use App\Models\Event;
use App\Services\AttendanceGrid;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * First sheet of the Excel download: the headline numbers, how many came each day, and
 * the same split by area, category, gender and country.
 */
class AttendanceSummarySheet implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
{
    use WritesSummarySections;

    public function __construct(private readonly Event $event, private readonly array $grid) {}

    public function title(): string
    {
        return 'Summary';
    }

    public function array(): array
    {
        $grid = $this->grid;
        $slots = $grid['slots'];
        $rows = $grid['rows'];
        $present = fn ($group, $slot) => $group->filter(fn ($row) => $row->cells[$slot['key']] !== AttendanceGrid::NOT_PRESENT && $row->cells[$slot['key']] !== '')->count();

        $this->summaryTitle($this->event->title, collect(['Attendance summary: '.$grid['periodLabel'], AttendanceGrid::filterLabel($grid['filters']), 'Generated '.now()->format('j M Y, g:i A')])->filter()->join('  ·  '));

        $this->section('Overview', ['', 'Number', 'How it is counted'], [
            ['Expected', $grid['totalExpected'], 'Confirmed attendees (support staff not included)'],
            [$grid['presentLabel'], $rows->count(), $grid['day'] === 'all' ? 'Checked in on at least one day' : 'Checked in during this period'],
            [$grid['absentLabel'], $grid['absent']->count(), 'Expected but no check-in'],
            ...($grid['day'] === 'all' ? [['Days with check-ins', $grid['totalDays'], 'Of '.$grid['eventDays'].' event day(s); days with no check-ins are left out']] : []),
            [$grid['day'] === 'all' ? 'Average daily turnout' : 'Turnout', $grid['turnout'].'%', $grid['day'] === 'all' ? 'Average of each day\'s present ÷ expected' : 'Present ÷ expected'],
        ]);

        if ($slots->count() > 1) {
            $this->section('Present each day', ['Day', 'Present', 'Absent', 'Turnout'], $slots->map(fn ($slot) => [
                $slot['label'], $grid['slotStats'][$slot['key']]['present'], max(0, $grid['totalExpected'] - $grid['slotStats'][$slot['key']]['present']), $grid['slotStats'][$slot['key']]['rate'].'%',
            ])->all());
        }

        $byArea = $rows->groupBy('area')->sortByDesc(fn ($group) => $group->count());
        $this->section('By area', ['Area', ...($slots->count() > 1 ? $slots->pluck('label')->all() : []), 'People present'], $byArea->map(fn ($group, $area) => [
            $area,
            ...($slots->count() > 1 ? $slots->map(fn ($slot) => $present($group, $slot))->all() : []),
            $group->count(),
        ])->values()->all());

        foreach (['category' => 'By category', 'gender' => 'By gender'] as $field => $heading) {
            $this->section($heading, [ucfirst($field), 'People present'], $rows->countBy(fn ($row) => $row->person->{$field} ?: 'Unspecified')->sortDesc()
                ->map(fn ($count, $label) => [$label, $count])->values()->all());
        }

        $this->section('By country', ['Country (from phone number)', 'People present'], $rows->countBy('country')->sortDesc()
            ->map(fn ($count, $label) => [$label, $count])->values()->all());

        return $this->lines;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => fn (AfterSheet $event) => $this->styleSummary($event->sheet->getDelegate())];
    }
}
