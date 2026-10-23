<?php

namespace App\Exports;

use App\Models\Event;
use App\Services\AttendanceGrid;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * The people present in a report period, one row each, with a column per day (or session)
 * showing when they checked in. Used as-is for CSV downloads; AttendanceWorkbook wraps it
 * with a title block and styling for Excel.
 */
class AttendanceExport implements FromCollection, WithHeadings, WithMapping
{
    protected ?array $grid = null;

    private int $rowNumber = 0;

    public function __construct(protected Event $event, protected int|string $day = 'all', protected ?string $area = null, protected ?int $session = null) {}

    public function grid(): array
    {
        return $this->grid ??= app(AttendanceGrid::class)->build($this->event, $this->day, $this->session, $this->area);
    }

    /** The people present, in name order; map() looks up each person's day columns. */
    public function collection(): Collection
    {
        return $this->grid()['rows']->pluck('person');
    }

    public function headings(): array
    {
        $grid = $this->grid();

        return [
            '#', 'Name', 'Phone', 'Area', 'Category', 'Gender', 'Email',
            ...$grid['slots']->pluck('label')->all(),
            ...($grid['multiDay'] ? ['Days attended'] : []),
            'Check-in method',
        ];
    }

    public function map($person): array
    {
        $grid = $this->grid();
        $row = $grid['rows']->firstWhere('person.id', $person->id);

        return [
            ++$this->rowNumber,
            $person->name,
            $person->phone,
            $row->area,
            $person->category,
            $person->gender,
            $person->email,
            ...array_values($row->cells),
            ...($grid['multiDay'] ? [$row->daysAttended.' of '.$grid['totalDays']] : []),
            $row->method,
        ];
    }

    /** 1-based column numbers of the day/session columns. */
    protected function dayColumns(): array
    {
        return range(8, 7 + $this->grid()['slots']->count());
    }
}
