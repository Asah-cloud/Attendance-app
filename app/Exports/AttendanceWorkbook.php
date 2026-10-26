<?php

namespace App\Exports;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** The Excel attendance download: Summary, Present (a column per day) and Not present sheets. */
class AttendanceWorkbook implements Export, WithMultipleSheets
{
    /** @param  array{area?: ?string, category?: ?string, gender?: ?string, country?: ?string}  $filters */
    public function __construct(private readonly Event $event, private readonly int|string $day = 'all', private readonly ?int $session = null, private readonly array $filters = []) {}

    public function sheets(): array
    {
        $present = new AttendanceSheet($this->event, $this->day, $this->session, $this->filters);
        $grid = $present->grid();

        return [
            new AttendanceSummarySheet($this->event, $grid),
            $present,
            new NotPresentSheet($this->event, $grid),
        ];
    }
}
