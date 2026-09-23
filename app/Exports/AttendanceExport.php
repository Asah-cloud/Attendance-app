<?php

namespace App\Exports;

use App\Models\Event;
use App\Services\AttendanceReportData;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceExport implements FromCollection, WithHeadings, WithMapping
{
    protected $event;

    protected $day;

    protected $area;

    // 1. Accept BOTH the event and the day
    public function __construct(Event $event, $day = 'all', ?string $area = null)
    {
        $this->event = $event;
        $this->day = $day;
        $this->area = $area;
    }

    public function collection(): Enumerable
    {
        $presentUsers = app(AttendanceReportData::class)->forPeriod($this->event, $this->day)['presentUsers'];

        if ($this->area === null) {
            return $presentUsers;
        }

        return $presentUsers
            ->filter(fn ($user) => ($user->room_group ?: ($user->department ?: 'Area not specified')) === $this->area)
            ->values();
    }

    public function headings(): array
    {
        // 3. Match your headings to your map data
        return ['Name', 'Phone', 'Area', 'Category', 'Gender', 'Email', 'Date/Time Marked', 'Attendance Session'];
    }

    public function map($user): array
    {
        // Get the specific attendance record for this event/day
        $attendance = $user->attendances->first();

        return [
            $user->name,
            $user->phone,
            $user->room_group ?: ($user->department ?: 'Area not specified'),
            $user->category,
            $user->gender,
            $user->email, // <-- added email to the export
            $attendance ? $attendance->created_at->format('d-m-Y h:i A') : 'N/A',
            $user->isNumberedParticipantStaff()
                ? 'Staff check-in (all event days)'
                : $this->event->attendanceSessionLabel($attendance ? $attendance->day : $this->day),
        ];
    }
}
