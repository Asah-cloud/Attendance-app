<?php

namespace App\Http\Controllers;

use App\Exports\AttendanceExport;
use App\Models\Event;
use App\Services\ApplicationCache;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class SummaryReportController extends Controller
{
    public function __construct(private readonly ApplicationCache $cache) {}

    public function index(Event $event)
    {
        $this->authorize('view', $event);
        $data = $this->cache->rememberEvent($event->id, 'summary-report', function () use ($event): array {
            $start = Carbon::parse($event->event_date);
            $end = $event->end_date ? Carbon::parse($event->end_date) : $start;
            $totalEventDays = $start->diffInDays($end) + 1;

            // Get users who attended at least once
            $presentUsers = $event->confirmedParticipants()
                ->whereHas('attendances', function ($q) use ($event) {
                    $q->where('event_id', $event->id);
                })
                ->with(['attendances' => function ($q) use ($event) {
                    $q->where('event_id', $event->id);
                }])
                ->get()
                ->map(function ($user) use ($totalEventDays) {
                    // Add a "score" to each user for easy display
                    $user->days_attended = $user->attendances->count();
                    $user->attendance_rate = ($user->days_attended / $totalEventDays) * 100;

                    return $user;
                });

            // Get users who never showed up
            $absentUsers = $event->confirmedParticipants()
                ->whereDoesntHave('attendances', function ($q) use ($event) {
                    $q->where('event_id', $event->id);
                })
                ->get();

            return compact('presentUsers', 'absentUsers', 'totalEventDays');
        }, ApplicationCache::REPORT_TTL);

        ['presentUsers' => $presentUsers, 'absentUsers' => $absentUsers, 'totalEventDays' => $totalEventDays] = $data;

        $categoryBreakdown = $presentUsers->countBy(fn ($user) => $user->category ?: 'Unspecified')->sortDesc();
        $genderBreakdown = $presentUsers->countBy(fn ($user) => $user->gender ?: 'Unspecified')->sortDesc();

        return view('reports.summary', compact('event', 'presentUsers', 'absentUsers', 'totalEventDays', 'categoryBreakdown', 'genderBreakdown'));
    }

    public function download(Event $event)
    {
        $this->authorize('view', $event);
        $fileName = 'Full_Summary_'.str_replace(' ', '_', $event->title).'.xlsx';

        return Excel::download(new AttendanceExport($event, 'all'), $fileName);
    }
}
