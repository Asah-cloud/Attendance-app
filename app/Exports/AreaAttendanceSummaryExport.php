<?php

namespace App\Exports;

use App\Exports\Concerns\StylesReportSheet;
use App\Models\Event;
use App\Services\AttendanceGrid;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/** People present per area, with a count for each day when the period covers several. */
class AreaAttendanceSummaryExport implements FromCollection, WithCustomStartCell, WithEvents, WithHeadings, WithMapping, WithTitle
{
    use StylesReportSheet;

    private ?array $grid = null;

    public function __construct(private readonly Event $event, private readonly int|string $day, private readonly ?int $session = null, private readonly array $filters = []) {}

    private function grid(): array
    {
        return $this->grid ??= app(AttendanceGrid::class)->build($this->event, $this->day, $this->session, $this->filters);
    }

    private function perDay(): bool
    {
        return $this->grid()['slots']->count() > 1;
    }

    public function collection(): Collection
    {
        $slots = $this->grid()['slots'];

        return $this->grid()['rows']->groupBy('area')
            ->map(fn ($group, $area) => [
                'area' => $area,
                'slots' => $slots->map(fn ($slot) => $group->filter(fn ($row) => ! in_array($row->cells[$slot['key']], ['', AttendanceGrid::NOT_PRESENT], true))->count())->all(),
                'present' => $group->count(),
            ])
            ->sortByDesc('present')
            ->values();
    }

    public function title(): string
    {
        return 'Areas';
    }

    public function startCell(): string
    {
        return 'A'.$this->headerRow;
    }

    public function headings(): array
    {
        return ['Area', ...($this->perDay() ? $this->grid()['slots']->pluck('label')->all() : []), 'People present'];
    }

    public function map($row): array
    {
        return [$row['area'], ...($this->perDay() ? $row['slots'] : []), $row['present']];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            $columns = count($this->headings());
            $this->writeTitleBlock($sheet, $this->event->title, 'People present by area: '.$this->grid()['periodLabel'].'  ·  Generated '.now()->format('j M Y, g:i A'));
            $this->styleTable($sheet, $this->headerRow, $columns, range(2, $columns));
            $total = Coordinate::stringFromColumnIndex($columns);
            $sheet->getStyle($total.($this->headerRow + 1).':'.$total.$sheet->getHighestDataRow())->getFont()->setBold(true);
        }];
    }
}
