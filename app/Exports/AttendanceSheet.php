<?php

namespace App\Exports;

use App\Exports\Concerns\StylesReportSheet;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/** The "Present" sheet of the Excel download: AttendanceExport with a title block and styling. */
class AttendanceSheet extends AttendanceExport implements WithCustomStartCell, WithEvents, WithTitle
{
    use StylesReportSheet;

    public function title(): string
    {
        return 'Present';
    }

    public function startCell(): string
    {
        return 'A'.$this->headerRow;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $grid = $this->grid();
            $sheet = $event->sheet->getDelegate();
            $this->writeTitleBlock(
                $sheet,
                $this->event->title,
                collect([
                    'Present: '.$grid['periodLabel'],
                    $this->area,
                    $grid['rows']->count().' of '.$grid['totalExpected'].' people',
                    'Generated '.now()->format('j M Y, g:i A'),
                ])->filter()->join('  ·  ')
            );
            $columns = count($this->headings());
            $this->styleTable($sheet, $this->headerRow, $columns, [1, $columns - 1], $this->dayColumns());
        }];
    }
}
