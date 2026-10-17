<?php

namespace App\Http\Controllers;

use App\Exports\FormResponsesExport;
use App\Models\Form;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Responses of forms shared with the signed-in user: view and export only. */
class SharedFormController extends Controller
{
    public function index(Request $request): View
    {
        $forms = $request->user()->sharedForms()->with('event')->withCount('responses')->latest('form_viewers.created_at')->get();

        return view('shared-forms.index', compact('forms'));
    }

    public function show(Request $request, Form $form): View
    {
        $this->authorizeShared($request, $form);

        return view('events.forms.responses', [
            'event' => $form->event,
            'form' => $form,
            'fields' => $form->fields()->where('is_active', true)->get(),
            'responses' => $form->responses()->latest('created_at')->paginate(25),
            'exportExcelUrl' => route('shared-forms.export', $form),
            'exportPdfUrl' => route('shared-forms.pdf', $form),
            'backUrl' => route('shared-forms.index'),
            'backLabel' => 'All shared forms',
        ]);
    }

    public function exportExcel(Request $request, Form $form): BinaryFileResponse
    {
        $this->authorizeShared($request, $form);

        return Excel::download(new FormResponsesExport($form), $form->slug.'-responses.xlsx');
    }

    public function exportPdf(Request $request, Form $form): Response
    {
        $this->authorizeShared($request, $form);
        $event = $form->event;
        $fields = $form->fields()->where('is_active', true)->get();
        $responses = $form->responses()->latest('created_at')->get();

        return Pdf::loadView('reports.form-responses-pdf', compact('event', 'form', 'fields', 'responses'))->setPaper('a4')
            ->download($form->slug.'-responses.pdf');
    }

    /** Only people the form was shared with (or who manage its event) - others get a plain 404. */
    private function authorizeShared(Request $request, Form $form): void
    {
        $user = $request->user();
        abort_unless($user->sharedForms()->whereKey($form->id)->exists() || $user->can('update', $form->event), 404);
    }
}
