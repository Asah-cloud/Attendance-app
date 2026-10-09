<?php

namespace App\Exports;

use App\Models\Event;
use App\Services\AttendanceReportData;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AreaAttendanceSummaryExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Event $event, private readonly int|string $day, private readonly ?int $session = null) {}

    public function collection(): Enumerable
    {
        return app(AttendanceReportData::class)->forPeriod($this->event, $this->day, $this->session)['presentUsers']
            ->countBy(fn ($participant) => $participant->room_group ?: ($participant->department ?: 'Area not specified'))
            ->sortDesc()
            ->map(fn ($count, $area) => ['area' => $area, 'present' => $count])
            ->values();
    }

    public function headings(): array
    {
        return ['Area', 'People Present'];
    }

    public function map($row): array
    {
        return [$row['area'], $row['present']];
    }
}
