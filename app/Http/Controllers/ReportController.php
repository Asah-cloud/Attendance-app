<?php

namespace App\Http\Controllers;

use App\Exports\AttendanceExport;
use App\Models\Event;
use App\Services\ApplicationCache;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(private readonly ApplicationCache $cache) {}

    public function show(Event $event, $day = 1)
    {
        $this->authorize('view', $event);
        // Use the route parameter directly instead of request query
        $selectedDay = $this->validatedDay($event, $day);

        $data = $this->cache->rememberEvent($event->id, "attendance-report:{$selectedDay}", function () use ($event, $selectedDay): array {
            if ($selectedDay === 'all') {
                // 1. Get users who attended at least ONCE during the entire event
                $presentUsers = $event->confirmedParticipants()
                    ->whereHas('attendances', function ($query) use ($event) {
                        $query->where('event_id', $event->id);
                    })
                    ->with(['attendances' => function ($q) use ($event) {
                        $q->where('event_id', $event->id)->orderBy('day', 'asc');
                    }])
                    ->get();

                // 2. Absent users are those who never showed up on ANY day
                $presentIds = $presentUsers->pluck('id');
                $absentUsers = $event->confirmedParticipants()
                    ->whereNotIn('participants.id', $presentIds)
                    ->get();

            } else {
                // Standard single-day logic (Fixed for PostgreSQL ambiguity)
                $presentUsers = $event->confirmedParticipants()
                    ->whereHas('attendances', function ($query) use ($event, $selectedDay) {
                        $query->where('event_id', $event->id)
                            ->where('day', $selectedDay);
                    })
                    ->with(['attendances' => function ($q) use ($event, $selectedDay) {
                        $q->where('event_id', $event->id)->where('day', $selectedDay);
                    }])
                    ->get();

                $presentIds = $presentUsers->pluck('id');
                $absentUsers = $event->confirmedParticipants()
                    ->whereNotIn('participants.id', $presentIds)
                    ->get();
            }

            return [
                'presentUsers' => $presentUsers,
                'absentUsers' => $absentUsers,
                'totalExpected' => $event->confirmedParticipants()->count(),
            ];
        }, ApplicationCache::REPORT_TTL);

        ['presentUsers' => $presentUsers, 'absentUsers' => $absentUsers, 'totalExpected' => $totalExpected] = $data;

        $categoryBreakdown = $presentUsers->countBy(fn ($user) => $user->category ?: 'Unspecified')->sortDesc();
        $genderBreakdown = $presentUsers->countBy(fn ($user) => $user->gender ?: 'Unspecified')->sortDesc();

        $filterCategory = request()->string('category')->toString();
        $filterGender = request()->string('gender')->toString();
        if ($filterCategory !== '') {
            $presentUsers = $presentUsers->where('category', $filterCategory)->values();
            $absentUsers = $absentUsers->where('category', $filterCategory)->values();
        }
        if ($filterGender !== '') {
            $presentUsers = $presentUsers->where('gender', $filterGender)->values();
            $absentUsers = $absentUsers->where('gender', $filterGender)->values();
        }

        $availableCategories = $event->confirmedParticipants()->distinct()->pluck('category')->filter()->sort()->values();
        $availableGenders = $event->confirmedParticipants()->distinct()->pluck('gender')->filter()->sort()->values();

        return view('reports.attendance', compact(
            'event', 'presentUsers', 'absentUsers', 'totalExpected', 'selectedDay',
            'categoryBreakdown', 'genderBreakdown', 'filterCategory', 'filterGender', 'availableCategories', 'availableGenders'
        ));
    }

    public function exportExcel(Event $event, $day = 'all')
    {
        $this->authorize('view', $event);
        $day = $this->validatedDay($event, $day);
        // Accepts route parameter directly to match Excel route settings
        $fileName = 'Attendance_'.str_replace(' ', '_', $event->title).'_Day_'.$day.'.xlsx';

        return Excel::download(new AttendanceExport($event, $day), $fileName);
    }

    public function exportCsv(Event $event, $day = 'all')
    {
        $this->authorize('view', $event);
        $day = $this->validatedDay($event, $day);
        // Accepts route parameter directly to match CSV route settings
        $fileName = 'Attendance_'.str_replace(' ', '_', $event->title).'_Day_'.$day.'.csv';

        return Excel::download(new AttendanceExport($event, $day), $fileName, \Maatwebsite\Excel\Excel::CSV);
    }

    private function validatedDay(Event $event, mixed $day): int|string
    {
        if ($day === 'all') {
            return 'all';
        }

        $day = filter_var($day, FILTER_VALIDATE_INT);
        $totalDays = $event->event_date->diffInDays($event->end_date ?? $event->event_date) + 1;

        if ($day === false || $day < 1 || $day > $totalDays) {
            throw ValidationException::withMessages(['day' => 'The selected event day is invalid.']);
        }

        return $day;
    }
}
