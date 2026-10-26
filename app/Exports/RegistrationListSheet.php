<?php

namespace App\Exports;

use App\Exports\Concerns\KeepsTextAsText;
use App\Exports\Concerns\StylesReportSheet;
use App\Models\Event;
use App\Services\RegistrationReport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/** Everyone in the registration report, one row each. */
class RegistrationListSheet implements FromArray, WithCustomStartCell, WithCustomValueBinder, WithEvents, WithHeadings, WithTitle
{
    use KeepsTextAsText, StylesReportSheet;

    public function __construct(private readonly Event $event, private readonly array $report) {}

    public function title(): string
    {
        return 'People';
    }

    public function startCell(): string
    {
        return 'A'.$this->headerRow;
    }

    public function headings(): array
    {
        return ['#', 'Name', 'Phone', 'Email', 'Country', 'Area', 'Category', 'Gender', 'Status', 'Registered', 'Checked in'];
    }

    public function array(): array
    {
        return $this->report['rows']->values()->map(fn ($row, $index) => [
            $index + 1, $row->person->name, $row->person->phone, $row->person->email, $row->country, $row->area, $row->category, $row->gender,
            $row->statusLabel, $row->registration->registered_at?->format('j M Y'),
            RegistrationReport::checkedInLabel($row, $this->report['eventDays']),
        ])->all();
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            $this->writeTitleBlock($sheet, $this->event->title, 'Registrations: '.RegistrationReport::filterLabel($this->report['filters']).'  ·  '.$this->report['rows']->count().' people  ·  Generated '.now()->format('j M Y, g:i A'));
            $this->styleTable($sheet, $this->headerRow, count($this->headings()), [1]);
        }];
    }
}
