<?php

namespace App\Exports;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** The Excel attendance download: Summary, Present (a column per day) and Not present sheets. */
class AttendanceWorkbook implements Export, WithMultipleSheets
{
    public function __construct(private readonly Event $event, private readonly int|string $day = 'all', private readonly ?string $area = null, private readonly ?int $session = null) {}

    public function sheets(): array
    {
        $present = new AttendanceSheet($this->event, $this->day, $this->area, $this->session);
        $grid = $present->grid();

        return [
            new AttendanceSummarySheet($this->event, $grid, $this->area),
            $present,
            new NotPresentSheet($this->event, $grid, $this->area),
        ];
    }
}
