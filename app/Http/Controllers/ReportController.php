<?php

namespace App\Http\Controllers;

use App\Exports\AreaAttendanceSummaryExport;
use App\Exports\AttendanceExport;
use App\Models\Event;
use App\Services\ApplicationCache;
use App\Services\AttendanceReportData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(private readonly ApplicationCache $cache, private readonly AttendanceReportData $attendanceReport) {}

    public function show(Event $event, $day = 1)
    {
        $this->authorize('view', $event);
        // Use the route parameter directly instead of request query
        $selectedDay = $this->validatedDay($event, $day);

        [
            'presentUsers' => $presentUsers,
            'absentUsers' => $absentUsers,
            'totalExpected' => $totalExpected,
            'categoryBreakdown' => $categoryBreakdown,
            'genderBreakdown' => $genderBreakdown,
            'areaBreakdown' => $areaBreakdown,
        ] = $this->reportData($event, $selectedDay);

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
            'event', 'presentUsers', 'absentUsers', 'totalExpected', 'selectedDay',
            'categoryBreakdown', 'genderBreakdown', 'areaBreakdown', 'filterCategory', 'filterGender', 'filterArea',
            'availableCategories', 'availableGenders', 'availableAreas'
        ));
    }

    public function exportExcel(Event $event, $day = 'all')
    {
        $this->authorize('view', $event);
        $day = $this->validatedDay($event, $day);
        // Accepts route parameter directly to match Excel route settings
        $fileName = 'Attendance_'.str_replace(' ', '_', $event->title).'_'.str_replace(' ', '_', $event->attendanceSessionLabel($day)).'.xlsx';

        return Excel::download(new AttendanceExport($event, $day), $fileName);
    }

    public function exportCsv(Event $event, $day = 'all')
    {
        $this->authorize('view', $event);
        $day = $this->validatedDay($event, $day);
        // Accepts route parameter directly to match CSV route settings
        $fileName = 'Attendance_'.str_replace(' ', '_', $event->title).'_'.str_replace(' ', '_', $event->attendanceSessionLabel($day)).'.csv';

        return Excel::download(new AttendanceExport($event, $day), $fileName, \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportAreaSummary(Event $event, $day = 'all')
    {
        $this->authorize('view', $event);
        $day = $this->validatedDay($event, $day);
        $fileName = 'Area_Attendance_'.str_replace(' ', '_', $event->title).'_'.str_replace(' ', '_', $event->attendanceSessionLabel($day)).'.xlsx';

        return Excel::download(new AreaAttendanceSummaryExport($event, $day), $fileName);
    }

    public function exportPdf(Event $event, $day = 'all')
    {
        $this->authorize('view', $event);
        $selectedDay = $this->validatedDay($event, $day);
        $reportData = $this->reportData($event, $selectedDay);

        $pdf = Pdf::loadView('reports.attendance-pdf', array_merge(
            ['event' => $event, 'selectedDay' => $selectedDay],
            $reportData
        ))->setPaper('a4');

        $fileName = 'Attendance_'.str_replace(' ', '_', $event->title).'_'.str_replace(' ', '_', $event->attendanceSessionLabel($selectedDay)).'.pdf';

        return $pdf->download($fileName);
    }

    private function reportData(Event $event, int|string $selectedDay): array
    {
        $data = $this->cache->rememberEvent($event->id, "attendance-report:v3:{$selectedDay}", fn (): array => $this->attendanceReport->forPeriod($event, $selectedDay), ApplicationCache::REPORT_TTL);

        $data['categoryBreakdown'] = $data['presentUsers']->countBy(fn ($user) => $user->category ?: 'Unspecified')->sortDesc();
        $data['genderBreakdown'] = $data['presentUsers']->countBy(fn ($user) => $user->gender ?: 'Unspecified')->sortDesc();
        $data['areaBreakdown'] = $data['presentUsers']->countBy(fn ($user) => $this->participantArea($user))->sortDesc();

        return $data;
    }

    private function participantArea($participant): string
    {
        return $participant->room_group ?: ($participant->department ?: 'Area not specified');
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
}
