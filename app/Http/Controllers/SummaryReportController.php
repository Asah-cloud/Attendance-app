<?php

namespace App\Http\Controllers;

use App\Exports\AttendanceWorkbook;
use App\Models\Event;
use App\Services\ApplicationCache;
use App\Services\AttendanceGrid;
use App\Services\AttendanceReportData;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class SummaryReportController extends Controller
{
    public function __construct(private readonly ApplicationCache $cache, private readonly AttendanceReportData $attendanceReport) {}

    public function index(Event $event)
    {
        $this->authorize('view', $event);
        [
            'presentUsers' => $presentUsers,
            'absentUsers' => $absentUsers,
            'totalEventDays' => $totalEventDays,
            'registeredCount' => $registeredCount,
            'confirmedCount' => $confirmedCount,
            'arrivedCount' => $arrivedCount,
            'categoryBreakdown' => $categoryBreakdown,
            'genderBreakdown' => $genderBreakdown,
        ] = $this->summaryData($event);

        return view('reports.summary', compact('event', 'presentUsers', 'absentUsers', 'totalEventDays', 'registeredCount', 'confirmedCount', 'arrivedCount', 'categoryBreakdown', 'genderBreakdown'));
    }

    public function download(Event $event)
    {
        $this->authorize('view', $event);

        return Excel::download(new AttendanceWorkbook($event, 'all'), ReportController::fileName($event, 'Full summary', 'all', null, 'xlsx'));
    }

    public function downloadPdf(Event $event)
    {
        $this->authorize('view', $event);
        $summaryData = $this->summaryData($event);

        $grid = app(AttendanceGrid::class)->build($event, 'all');

        $pdf = Pdf::loadView('reports.summary-pdf', array_merge(['event' => $event, 'grid' => $grid], $summaryData))
            ->setPaper('a4', $grid['slots']->count() > 3 ? 'landscape' : 'portrait')
            // Embed only the letters used; the full DejaVu font makes every PDF ~900 KB.
            ->setOption('isFontSubsettingEnabled', true);

        return $pdf->download(ReportController::fileName($event, 'Full summary', 'all', null, 'pdf'));
    }

    private function summaryData(Event $event): array
    {
        $data = $this->cache->rememberEvent($event->id, 'summary-report:v3', function () use ($event): array {
            $start = Carbon::parse($event->event_date);
            $end = $event->end_date ? Carbon::parse($event->end_date) : $start;
            $totalEventDays = $start->diffInDays($end) + 1;
            $registeredCount = $event->registrations()->count();
            $confirmedCount = $event->confirmedParticipants()->count();
            $arrivedCount = $event->has_arrival_session ? $event->arrivedParticipants()->count() : $confirmedCount;

            ['presentUsers' => $presentUsers, 'absentUsers' => $absentUsers] = $this->attendanceReport->forPeriod($event, 'all');
            $presentUsers = $presentUsers
                ->map(function ($user) use ($event, $totalEventDays) {
                    $user->days_attended = $user->isNumberedParticipantStaff()
                        ? ($event->status === 'upcoming' ? 0 : ($event->status === 'active' ? $event->currentDay() : $totalEventDays))
                        : $user->attendances->where('day', '>=', 1)->count();
                    $user->attendance_rate = ($user->days_attended / $totalEventDays) * 100;

                    return $user;
                });

            return compact('presentUsers', 'absentUsers', 'totalEventDays', 'registeredCount', 'confirmedCount', 'arrivedCount');
        }, ApplicationCache::REPORT_TTL);

        $data['categoryBreakdown'] = $data['presentUsers']->countBy(fn ($user) => $user->category ?: 'Unspecified')->sortDesc();
        $data['genderBreakdown'] = $data['presentUsers']->countBy(fn ($user) => $user->gender ?: 'Unspecified')->sortDesc();

        return $data;
    }
}
