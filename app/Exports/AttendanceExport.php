<?php

namespace App\Exports;

use App\Models\Event;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceExport implements FromCollection, WithHeadings, WithMapping
{
    protected $event;

    protected $day;

    // 1. Accept BOTH the event and the day
    public function __construct(Event $event, $day = 'all')
    {
        $this->event = $event;
        $this->day = $day;
    }

    public function collection(): Enumerable
    {
        return $this->event->confirmedParticipants()
            ->whereHas('attendances', function ($query) {
                $query->where('event_id', $this->event->id);

                // 2. Filter by day if it's not 'all'
                if ($this->day !== 'all') {
                    $query->where('day', $this->day);
                }
            })
            ->with(['attendances' => function ($q) {
                $q->where('event_id', $this->event->id);
                if ($this->day !== 'all') {
                    $q->where('day', $this->day);
                }
            }])
            ->get();
    }

    public function headings(): array
    {
        // 3. Match your headings to your map data
        return ['Name', 'Phone', 'Category', 'Gender', 'Email', 'Date/Time Marked', 'Day Number'];
    }

    public function map($user): array
    {
        // Get the specific attendance record for this event/day
        $attendance = $user->attendances->first();

        return [
            $user->name,
            $user->phone,
            $user->category,
            $user->gender,
            $user->email, // <-- added email to the export
            $attendance ? $attendance->created_at->format('d-m-Y h:i A') : 'N/A',
            $attendance ? $attendance->day : $this->day,
        ];
    }
}
