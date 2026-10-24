<?php

namespace App\Exports;

use App\Exports\Concerns\StylesReportSheet;
use App\Models\Event;
use App\Services\AttendanceGrid;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/** Registered people who did not check in during the report period. */
class NotPresentSheet implements FromArray, WithCustomStartCell, WithEvents, WithHeadings, WithTitle
{
    use StylesReportSheet;

    public function __construct(private readonly Event $event, private readonly array $grid) {}

    public function title(): string
    {
        return $this->grid['absentLabel'];
    }

    public function startCell(): string
    {
        return 'A'.$this->headerRow;
    }

    public function headings(): array
    {
        return ['#', 'Name', 'Phone', 'Area', 'Category', 'Gender', 'Email'];
    }

    public function array(): array
    {
        return $this->grid['absent']->values()->map(fn ($person, $index) => [
            $index + 1, $person->name, $person->phone, AttendanceGrid::area($person), $person->category, $person->gender, $person->email,
        ])->all();
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            $this->writeTitleBlock($sheet, $this->event->title, collect([
                $this->grid['absentLabel'].': '.$this->grid['periodLabel'],
                AttendanceGrid::filterLabel($this->grid['filters']),
                $this->grid['absent']->count().' of '.$this->grid['totalExpected'].' people',
            ])->filter()->join('  ·  '));
            $this->styleTable($sheet, $this->headerRow, 7, [1]);
        }];
    }
}
