<?php

namespace App\Exports;

use App\Models\Event;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AreaAttendanceSummaryExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Event $event, private readonly int|string $day) {}

    public function collection(): Enumerable
    {
        $participants = $this->day === 'all' || (int) $this->day === 0
            ? $this->event->confirmedParticipants()
            : $this->event->attendanceEligibleParticipants();

        return $participants
            ->whereHas('attendances', function ($query): void {
                $query->where('event_id', $this->event->id);
                if ($this->day !== 'all') {
                    $query->where('day', $this->day);
                }
            })
            ->get()
            ->countBy(fn ($participant) => $participant->room_group ?: ($participant->department ?: 'Area not specified'))
            ->sortDesc()
            ->map(fn ($count, $area) => ['area' => $area, 'present' => $count])
            ->values();
    }

    public function headings(): array
    {
        return ['Area', 'Participants Present'];
    }

    public function map($row): array
    {
        return [$row['area'], $row['present']];
    }
}
