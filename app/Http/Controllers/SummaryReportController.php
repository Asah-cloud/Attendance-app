<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use App\Exports\AttendanceExport;
use Maatwebsite\Excel\Facades\Excel;

class SummaryReportController extends Controller
{
    public function index(Event $event)
    {
        // Calculate total days for the event
        $start = \Carbon\Carbon::parse($event->event_date);
        $end = $event->end_date ? \Carbon\Carbon::parse($event->end_date) : $start;
        $totalEventDays = $start->diffInDays($end) + 1;

        // Get users who attended at least once
        $presentUsers = $event->users()
            ->whereHas('attendances', function ($q) use ($event) {
                $q->where('event_id', $event->id);
            })
            ->with(['attendances' => function($q) use ($event) {
                $q->where('event_id', $event->id);
            }])
            ->get()
            ->map(function($user) use ($totalEventDays) {
                // Add a "score" to each user for easy display
                $user->days_attended = $user->attendances->count();
                $user->attendance_rate = ($user->days_attended / $totalEventDays) * 100;
                return $user;
            });

        // Get users who never showed up
        $absentUsers = $event->users()
            ->whereDoesntHave('attendances', function ($q) use ($event) {
                $q->where('event_id', $event->id);
            })
            ->get();

        return view('reports.summary', compact('event', 'presentUsers', 'absentUsers', 'totalEventDays'));
    }

    public function download(Event $event)
    {
        $fileName = 'Full_Summary_' . str_replace(' ', '_', $event->title) . '.xlsx';
        return Excel::download(new AttendanceExport($event, 'all'), $fileName);
    }
}