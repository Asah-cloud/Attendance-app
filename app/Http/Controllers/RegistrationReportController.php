<?php

namespace App\Http\Controllers;

use App\Exports\RegistrationReportWorkbook;
use App\Models\Event;
use App\Services\RegistrationReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

/** The Registrations tab of Reports: who has registered, by status, category, gender, country and area. */
class RegistrationReportController extends Controller
{
    public function __construct(private readonly RegistrationReport $report) {}

    public function show(Request $request, Event $event): View
    {
        $this->authorize('view', $event);

        return view('reports.registrations', ['event' => $event, 'report' => $this->report->build($event, $this->filters($request))]);
    }

    public function exportExcel(Request $request, Event $event)
    {
        $this->authorize('view', $event);

        return Excel::download(new RegistrationReportWorkbook($event, $this->filters($request)), $this->fileName($event, $request, 'xlsx'));
    }

    public function exportPdf(Request $request, Event $event)
    {
        $this->authorize('view', $event);
        $report = $this->report->build($event, $this->filters($request));

        return Pdf::loadView('reports.registrations-pdf', ['event' => $event, 'report' => $report])
            ->setPaper('a4', 'landscape')
            // Embed only the letters used; the full DejaVu font makes every PDF ~900 KB.
            ->setOption('isFontSubsettingEnabled', true)
            ->download($this->fileName($event, $request, 'pdf'));
    }

    private function filters(Request $request): array
    {
        return RegistrationReport::normalise($request->only(RegistrationReport::FILTERS));
    }

    /** e.g. Demo_Retreat_Registrations_Confirmed_Ghana.xlsx */
    private function fileName(Event $event, Request $request, string $extension): string
    {
        $filters = $this->filters($request);
        $parts = [$event->title, 'Registrations', $filters['status'] === RegistrationReport::ANY_STATUS ? 'All' : $filters['status'], ...array_values(array_diff_key($filters, ['status' => true]))];

        return collect($parts)->map(fn ($part) => Str::of($part)->ascii()->replaceMatches('/[^A-Za-z0-9]+/', '_')->trim('_'))->filter()->join('_').'.'.$extension;
    }
}
