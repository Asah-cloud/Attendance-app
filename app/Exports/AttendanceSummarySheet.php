<?php

namespace App\Exports;

use App\Models\Event;
use App\Services\AttendanceGrid;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * First sheet of the Excel download: the headline numbers, how many came each day, and
 * the same split by area, category, gender and country.
 */
class AttendanceSummarySheet implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
{
    /** @var array<int, array{row: int, width: int, rows: int}> Table header rows, for styling. */
    private array $tables = [];

    /** @var array<int, int> Section heading rows. */
    private array $headings = [];

    private int $widest = 2;

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

        $lines = [
            [$this->event->title],
            [collect(['Attendance summary: '.$grid['periodLabel'], AttendanceGrid::filterLabel($grid['filters']), 'Generated '.now()->format('j M Y, g:i A')])->filter()->join('  ·  ')],
            // Blank rows must hold a value: an empty array is read as "no rows" and dropped.
            [''],
        ];
        $section = function (string $heading, array $header, array $body) use (&$lines): void {
            $this->headings[] = count($lines) + 1;
            $lines[] = [$heading];
            $this->tables[] = ['row' => count($lines) + 1, 'width' => count($header), 'rows' => count($body)];
            $this->widest = max($this->widest, count($header));
            $lines[] = $header;
            array_push($lines, ...$body);
            $lines[] = [''];
        };

        $section('Overview', ['', 'Number', 'How it is counted'], [
            ['Expected', $grid['totalExpected'], 'Confirmed attendees (support staff not included)'],
            [$grid['presentLabel'], $rows->count(), $grid['day'] === 'all' ? 'Checked in on at least one day' : 'Checked in during this period'],
            [$grid['absentLabel'], $grid['absent']->count(), 'Expected but no check-in'],
            ...($grid['day'] === 'all' ? [['Days with check-ins', $grid['totalDays'], 'Of '.$grid['eventDays'].' event day(s); days with no check-ins are left out']] : []),
            [$grid['day'] === 'all' ? 'Average daily turnout' : 'Turnout', $grid['turnout'].'%', $grid['day'] === 'all' ? 'Average of each day\'s present ÷ expected' : 'Present ÷ expected'],
        ]);

        if ($slots->count() > 1) {
            $section('Present each day', ['Day', 'Present', 'Absent', 'Turnout'], $slots->map(fn ($slot) => [
                $slot['label'], $grid['slotStats'][$slot['key']]['present'], max(0, $grid['totalExpected'] - $grid['slotStats'][$slot['key']]['present']), $grid['slotStats'][$slot['key']]['rate'].'%',
            ])->all());
        }

        $byArea = $rows->groupBy('area')->sortByDesc(fn ($group) => $group->count());
        $section('By area', ['Area', ...($slots->count() > 1 ? $slots->pluck('label')->all() : []), 'People present'], $byArea->map(fn ($group, $area) => [
            $area,
            ...($slots->count() > 1 ? $slots->map(fn ($slot) => $present($group, $slot))->all() : []),
            $group->count(),
        ])->values()->all());

        foreach (['category' => 'By category', 'gender' => 'By gender'] as $field => $heading) {
            $section($heading, [ucfirst($field), 'People present'], $rows->countBy(fn ($row) => $row->person->{$field} ?: 'Unspecified')->sortDesc()
                ->map(fn ($count, $label) => [$label, $count])->values()->all());
        }

        $section('By country', ['Country (from phone number)', 'People present'], $rows->countBy('country')->sortDesc()
            ->map(fn ($count, $label) => [$label, $count])->values()->all());

        return $lines;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('0F172A');
            $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('64748B');
            $sheet->getRowDimension(1)->setRowHeight(24);
            foreach ($this->headings as $row) {
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('1E3A8A');
            }
            foreach ($this->tables as $table) {
                $last = Coordinate::stringFromColumnIndex($table['width']);
                $header = $sheet->getStyle("A{$table['row']}:{$last}{$table['row']}");
                $header->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
                $end = $table['row'] + max(1, $table['rows']);
                $sheet->getStyle("A{$table['row']}:{$last}{$end}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');
                if ($table['width'] > 1) {
                    $sheet->getStyle("B{$table['row']}:{$last}{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            }
            $sheet->getColumnDimension('A')->setWidth(34);
            for ($column = 2; $column <= $this->widest; $column++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(16);
            }
        }];
    }
}
