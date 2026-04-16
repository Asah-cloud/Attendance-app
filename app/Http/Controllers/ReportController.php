<?php

namespace App\Http\Controllers;

use App\Exports\AttendanceExport;
use App\Models\Event;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function show(Event $event, Request $request)
{
    $selectedDay = $request->query('day', 1);

    if ($selectedDay === 'all') {
        // 1. Get users who attended at least ONCE during the entire event
        $presentUsers = $event->users()
            ->whereHas('attendances', function ($query) use ($event) {
                $query->where('event_id', $event->id);
            })
            ->with(['attendances' => function($q) use ($event) {
                $q->where('event_id', $event->id)->orderBy('day', 'asc');
            }])
            ->get();

        // 2. Absent users are those who never showed up on ANY day
        $presentIds = $presentUsers->pluck('id');
        $absentUsers = $event->users()
            ->whereNotIn('users.id', $presentIds)
            ->get();
            
    } else {
        // Standard single-day logic (Fixed for PostgreSQL ambiguity)
        $presentUsers = $event->users()
            ->whereHas('attendances', function ($query) use ($event, $selectedDay) {
                $query->where('event_id', $event->id)
                      ->where('day', $selectedDay);
            })
            ->with(['attendances' => function($q) use ($event, $selectedDay) {
                $q->where('event_id', $event->id)->where('day', $selectedDay);
            }])
            ->get();

        $presentIds = $presentUsers->pluck('id');
        $absentUsers = $event->users()
            ->whereNotIn('users.id', $presentIds)
            ->get();
    }

    $totalExpected = $event->users()->count();

    return view('reports.attendance', compact('event', 'presentUsers', 'absentUsers', 'totalExpected', 'selectedDay'));
}

    public function exportExcel(Event $event, Request $request)
    {
        $day = $request->query('day', 'all');
        $fileName = 'Attendance_' . str_replace(' ', '_', $event->title) . '_Day_' . $day . '.xlsx';

        return Excel::download(new AttendanceExport($event, $day), $fileName);
    }

    public function exportCsv(Event $event, Request $request)
    {
        $day = $request->query('day', 'all');
        $fileName = 'Attendance_' . str_replace(' ', '_', $event->title) . '_Day_' . $day . '.csv';

        return Excel::download(new AttendanceExport($event, $day), $fileName, \Maatwebsite\Excel\Excel::CSV);
    }
}