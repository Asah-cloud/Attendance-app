<?php

namespace App\Http\Controllers;

use App\Exports\AttendanceExport;
use App\Exports\AttendanceWorkbook;
use App\Exports\GroupAttendanceSummaryExport;
use App\Models\Event;
use App\Services\ApplicationCache;
use App\Services\AttendanceGrid;
use App\Services\AttendanceInsights;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(private readonly ApplicationCache $cache, private readonly AttendanceGrid $grid, private readonly AttendanceInsights $insights) {}

    public const VIEWS = ['simple', 'detailed', 'advanced'];

    public function show(Request $request, Event $event, $day = 'all')
    {
        $this->authorize('view', $event);
        $view = in_array($request->query('view'), self::VIEWS, true) ? $request->query('view') : 'simple';
        $selectedDay = $this->validatedDay($event, $day);
        $selectedSession = $this->validatedSession($event, $selectedDay, $request->query('session'));
        // Filters belong to the Detailed view; Simple and Advanced always show the whole event.
        $filters = $view === 'detailed' ? $this->filters($request) : [];
        $grid = $this->cachedGrid($event, $selectedDay, $selectedSession, $filters);

        $people = $event->confirmedParticipants()->get(['participants.id', 'participants.gender', 'participants.department', 'participants.phone', 'participants.country']);
        $filterOptions = [
            'area' => $people->map(fn ($person) => AttendanceGrid::area($person))->unique()->sort()->values(),
            'category' => $people->map(fn ($person) => $person->category ?: 'Unspecified')->unique()->sort()->values(),
            'gender' => $people->map(fn ($person) => $person->gender ?: 'Unspecified')->unique()->sort()->values(),
            'country' => $people->map(fn ($person) => AttendanceGrid::country($person))->unique()->sort()->values(),
        ];
        $registrationCounts = $event->registrations()
            ->whereHas('participant', fn ($query) => $query->where('is_support_staff', false))
            ->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status')->map(fn ($count) => (int) $count);
        $arrivedCount = $event->has_arrival_session ? $event->arrivedParticipants()->count() : null;

        $insights = null;
        if ($view === 'advanced') {
            $allDays = $selectedDay === 'all' && $filters === [] ? $grid : $this->cachedGrid($event, 'all', null, []);
            $insights = [
                'grid' => $allDays,
                'times' => $this->insights->checkInTimes($allDays),
                'methods' => $this->insights->methods($allDays),
                'dropOffs' => $this->insights->dropOffs($allDays),
                'comparison' => $this->insights->comparison($event, $allDays, $request->integer('compare') ?: null, $request->user()),
                'quality' => $this->insights->dataQuality($event),
            ];
        }

        return view('reports.attendance', compact(
            'event', 'view', 'grid', 'selectedDay', 'selectedSession', 'filters', 'filterOptions', 'registrationCounts', 'arrivedCount', 'insights'
        ));
    }

    /** The old summary page: now the Simple view of the whole event. */
    public function summary(Event $event)
    {
        $this->authorize('view', $event);

        return redirect()->route('reports.event', ['event' => $event, 'day' => 'all']);
    }

    public function exportExcel(Request $request, Event $event, $day = 'all')
    {
        $this->authorize('view', $event);
        $day = $this->validatedDay($event, $day);
        $session = $this->validatedSession($event, $day, $request->query('session'));

        return Excel::download(new AttendanceWorkbook($event, $day, $session, $this->filters($request)), $this->fileName($event, 'Attendance', $day, $session, 'xlsx'));
    }

    public function exportCsv(Request $request, Event $event, $day = 'all')
    {
        $this->authorize('view', $event);
        $day = $this->validatedDay($event, $day);
        $session = $this->validatedSession($event, $day, $request->query('session'));

        return Excel::download(new AttendanceExport($event, $day, $session, $this->filters($request)), $this->fileName($event, 'Attendance', $day, $session, 'csv'), \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportAreaSummary(Request $request, Event $event, $day = 'all')
    {
        $this->authorize('view', $event);
        $day = $this->validatedDay($event, $day);
        $session = $this->validatedSession($event, $day, $request->query('session'));

        return Excel::download(new GroupAttendanceSummaryExport($event, $day, $session, $this->filters($request)), $this->fileName($event, 'Area_Attendance', $day, $session, 'xlsx'));
    }

    public function exportCountrySummary(Request $request, Event $event, $day = 'all')
    {
        $this->authorize('view', $event);
        $day = $this->validatedDay($event, $day);
        $session = $this->validatedSession($event, $day, $request->query('session'));

        return Excel::download(new GroupAttendanceSummaryExport($event, $day, $session, $this->filters($request), 'country'), $this->fileName($event, 'Country_Attendance', $day, $session, 'xlsx'));
    }

    public function exportAreaDetail(Request $request, Event $event, string $area, $day = 'all')
    {
        $this->authorize('view', $event);
        $day = $this->validatedDay($event, $day);
        $session = $this->validatedSession($event, $day, $request->query('session'));

        return Excel::download(new AttendanceWorkbook($event, $day, $session, ['area' => $area] + $this->filters($request)), $this->fileName($event, 'Area_'.$area, $day, $session, 'xlsx'));
    }

    public function exportCountryDetail(Request $request, Event $event, string $country, $day = 'all')
    {
        $this->authorize('view', $event);
        $day = $this->validatedDay($event, $day);
        $session = $this->validatedSession($event, $day, $request->query('session'));

        return Excel::download(new AttendanceWorkbook($event, $day, $session, ['country' => $country] + $this->filters($request)), $this->fileName($event, 'Country_'.$country, $day, $session, 'xlsx'));
    }

    public function exportPdf(Request $request, Event $event, $day = 'all')
    {
        $this->authorize('view', $event);
        $selectedDay = $this->validatedDay($event, $day);
        $selectedSession = $this->validatedSession($event, $selectedDay, $request->query('session'));
        $grid = $this->grid->build($event, $selectedDay, $selectedSession, $this->filters($request));

        $pdf = Pdf::loadView('reports.attendance-pdf', ['event' => $event, 'grid' => $grid])
            ->setPaper('a4', $grid['slots']->count() > 2 ? 'landscape' : 'portrait')
            // Embed only the letters used; the full DejaVu font makes every PDF ~900 KB.
            ->setOption('isFontSubsettingEnabled', true);

        return $pdf->download($this->fileName($event, 'Attendance', $selectedDay, $selectedSession, 'pdf'));
    }

    private function cachedGrid(Event $event, int|string $day, ?int $session, array $filters): array
    {
        $key = 'report-grid:v2:'.$day.':'.$session.':'.md5(json_encode($filters));

        return $this->cache->rememberEvent($event->id, $key, fn () => $this->grid->build($event, $day, $session, $filters), ApplicationCache::REPORT_TTL);
    }

    /** Area / category / gender / country filters from the query string, so downloads match the screen. */
    private function filters(Request $request): array
    {
        return collect(AttendanceGrid::FILTERS)
            ->mapWithKeys(fn ($key) => [$key => trim((string) $request->query($key, ''))])
            ->filter(fn ($value) => $value !== '')
            ->all();
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
        $totalDays = $event->totalDays();
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
