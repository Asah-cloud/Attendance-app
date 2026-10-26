<?php

namespace App\Exports;

use App\Models\Event;
use App\Services\RegistrationReport;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** The Excel registration download: a Summary sheet and a People sheet, for the chosen filters. */
class RegistrationReportWorkbook implements Export, WithMultipleSheets
{
    public function __construct(private readonly Event $event, private readonly array $filters = []) {}

    public function sheets(): array
    {
        $report = app(RegistrationReport::class)->build($this->event, $this->filters);

        return [
            new RegistrationSummarySheet($this->event, $report),
            new RegistrationListSheet($this->event, $report),
        ];
    }
}
