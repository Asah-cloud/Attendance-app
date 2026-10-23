<?php

namespace App\Http\Controllers;

use App\Exports\AreaAttendanceSummaryExport;
use App\Exports\AttendanceExport;
use App\Exports\AttendanceWorkbook;
use App\Models\Event;
use App\Services\ApplicationCache;
use App\Services\AttendanceGrid;
use App\Services\AttendanceReportData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(private readonly ApplicationCache $cache, private readonly AttendanceReportData $attendanceReport, private readonly AttendanceGrid $grid) {}

    public function show(Request $request, Event $event, $day = 1)
    {
        $this->authorize('view', $event);
        // Use the route parameter directly instead of request query
        $selectedDay = $this->validatedDay($event, $day);
        $selectedSession = $this->validatedSession($event, $selectedDay, $request->query('session'));

        [
            'presentUsers' => $presentUsers,
            'absentUsers' => $absentUsers,
            'totalExpected' => $totalExpected,
            'categoryBreakdown' => $categoryBreakdown,
            'genderBreakdown' => $genderBreakdown,
            'areaBreakdown' => $areaBreakdown,
        ] = $this->reportData($event, $selectedDay, $selectedSession);

        $reportParticipants = $presentUsers->merge($absentUsers);

        $filterCategory = request()->string('category')->toString();
        $filterGender = request()->string('gender')->toString();
        $filterArea = request()->string('area')->toString();
        if ($filterCategory !== '') {
            $presentUsers = $presentUsers->where('category', $filterCategory)->values();
            $absentUsers = $absentUsers->where('category', $filterCategory)->values();
        }
        if ($filterGender !== '') {
            $presentUsers = $presentUsers->where('gender', $filterGender)->values();
            $absentUsers = $absentUsers->where('gender', $filterGender)->values();
        }
        if ($filterArea !== '') {
            $presentUsers = $presentUsers->filter(fn ($participant) => $this->participantArea($participant) === $filterArea)->values();
            $absentUsers = $absentUsers->filter(fn ($participant) => $this->participantArea($participant) === $filterArea)->values();
        }

        $availableCategories = $reportParticipants->pluck('category')->filter()->unique()->sort()->values();
        $availableGenders = $reportParticipants->pluck('gender')->filter()->unique()->sort()->values();
        $availableAreas = $reportParticipants
            ->map(fn ($participant) => $this->participantArea($participant))->unique()->sort()->values();
        if ($filterCategory !== '' || $filterGender !== '' || $filterArea !== '') {
            $totalExpected = $presentUsers->count() + $absentUsers->count();
            $categoryBreakdown = $presentUsers->countBy(fn ($participant) => $participant->category ?: 'Unspecified')->sortDesc();
            $genderBreakdown = $presentUsers->countBy(fn ($participant) => $participant->gender ?: 'Unspecified')->sortDesc();
            $areaBreakdown = $presentUsers->countBy(fn ($participant) => $this->participantArea($participant))->sortDesc();
        }

        return view('reports.attendance', compact(
            'event', 'presentUsers', 'absentUsers', 'totalExpected', 'selectedDay', 'selectedSession',
            'categoryBreakdown', 'genderBreakdown', 'areaBreakdown', 'filterCategory', 'filterGender', 'filterArea',
            'availableCategories', 'availableGenders', 'availableAreas'
        ));
    }

    public function exportExcel(Request $request, Event $event, $day = 'all')
    {
        $this->authorize('view', $event);
        $day = $this->validatedDay($event, $day);
        $session = $this->validatedSession($event, $day, $request->query('session'));

        return Excel::download(new AttendanceWorkbook($event, $day, null, $session), $this->fileName($event, 'Attendance', $day, $session, 'xlsx'));
    }

    public function exportCsv(Request $request, Event $event, $day = 'all')
    {
        $this->authorize('view', $event);
        $day = $this->validatedDay($event, $day);
        $session = $this->validatedSession($event, $day, $request->query('session'));

        return Excel::download(new AttendanceExport($event, $day, null, $session), $this->fileName($event, 'Attendance', $day, $session, 'csv'), \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportAreaSummary(Request $request, Event $event, $day = 'all')
    {
        $this->authorize('view', $event);
        $day = $this->validatedDay($event, $day);
        $session = $this->validatedSession($event, $day, $request->query('session'));

        return Excel::download(new AreaAttendanceSummaryExport($event, $day, $session), $this->fileName($event, 'Area_Attendance', $day, $session, 'xlsx'));
    }

    public function exportAreaDetail(Request $request, Event $event, string $area, $day = 'all')
    {
        $this->authorize('view', $event);
        $day = $this->validatedDay($event, $day);
        $session = $this->validatedSession($event, $day, $request->query('session'));

        return Excel::download(new AttendanceWorkbook($event, $day, $area, $session), $this->fileName($event, 'Area_'.$area, $day, $session, 'xlsx'));
    }

    public function exportPdf(Request $request, Event $event, $day = 'all')
    {
        $this->authorize('view', $event);
        $selectedDay = $this->validatedDay($event, $day);
        $selectedSession = $this->validatedSession($event, $selectedDay, $request->query('session'));
        $reportData = $this->reportData($event, $selectedDay, $selectedSession);

        $grid = $this->grid->build($event, $selectedDay, $selectedSession);

        $pdf = Pdf::loadView('reports.attendance-pdf', array_merge(
            ['event' => $event, 'selectedDay' => $selectedDay, 'selectedSession' => $selectedSession, 'grid' => $grid],
            $reportData
        ))->setPaper('a4', $grid['slots']->count() > 2 ? 'landscape' : 'portrait')
            // Embed only the letters used; the full DejaVu font makes every PDF ~900 KB.
            ->setOption('isFontSubsettingEnabled', true);

        return $pdf->download($this->fileName($event, 'Attendance', $selectedDay, $selectedSession, 'pdf'));
    }

    private function reportData(Event $event, int|string $selectedDay, ?int $selectedSession = null): array
    {
        $data = $this->cache->rememberEvent($event->id, "attendance-report:v4:{$selectedDay}:{$selectedSession}", fn (): array => $this->attendanceReport->forPeriod($event, $selectedDay, $selectedSession), ApplicationCache::REPORT_TTL);

        $data['categoryBreakdown'] = $data['presentUsers']->countBy(fn ($user) => $user->category ?: 'Unspecified')->sortDesc();
        $data['genderBreakdown'] = $data['presentUsers']->countBy(fn ($user) => $user->gender ?: 'Unspecified')->sortDesc();
        $data['areaBreakdown'] = $data['presentUsers']->countBy(fn ($user) => $this->participantArea($user))->sortDesc();

        return $data;
    }

    private function participantArea($participant): string
    {
        return $participant->room_group ?: ($participant->department ?: 'Area not specified');
    }

    /** e.g. TAC_Ministers_Orientation_Attendance_All_days.xlsx */
    public static function fileName(Event $event, string $kind, int|string $day, ?int $session, string $extension): string
    {
        $period = $day === 'all' ? 'All days' : $event->attendanceSessionLabel($day, $session ?? 1);
        $clean = fn (string $text) => trim(preg_replace('/[^A-Za-z0-9]+/', '_', str_replace("'", '', $text)), '_');

        return $clean($event->title).'_'.$clean($kind).'_'.$clean($period).'.'.$extension;
    }

    private function validatedDay(Event $event, mixed $day): int|string
    {
        if ($day === 'all') {
            return 'all';
        }

        $day = filter_var($day, FILTER_VALIDATE_INT);
        $totalDays = $event->event_date->diffInDays($event->end_date ?? $event->event_date) + 1;

        $minimum = $event->has_arrival_session ? 0 : 1;
        if ($day === false || $day < $minimum || $day > $totalDays) {
            throw ValidationException::withMessages(['day' => 'The selected event day is invalid.']);
        }

        return $day;
    }

    /** A session filter only applies to a specific numbered day; returns null (all sessions) otherwise. */
    private function validatedSession(Event $event, int|string $day, mixed $session): ?int
    {
        if ($session === null || $session === '' || $day === 'all' || (int) $day < 1) {
            return null;
        }

        $session = filter_var($session, FILTER_VALIDATE_INT);
        $maxSessions = $event->sessionCountForDay((int) $day);
        if ($session === false || $session < 1 || $session > $maxSessions) {
            throw ValidationException::withMessages(['session' => 'The selected session is invalid.']);
        }

        return $session;
    }
}
