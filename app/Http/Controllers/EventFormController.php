<?php

namespace App\Http\Controllers;

use App\Exports\FormResponsesExport;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EventFormController extends Controller
{
    public function index(Event $event): View
    {
        $this->authorize('update', $event);
        $forms = $event->forms()->withCount('responses')->latest()->get();

        return view('events.forms.index', compact('event', 'forms'));
    }

    public function create(Event $event): View
    {
        $this->authorize('update', $event);

        return view('events.forms.create', compact('event'));
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $form = $event->forms()->create($validated);

        return redirect()->route('events.forms.edit', [$event, $form])->with('success', 'Form created. Add some questions below.');
    }

    public function edit(Event $event, Form $form): View
    {
        $this->authorize('update', $event);
        $this->authorizeForm($event, $form);

        return view('events.forms.edit', [
            'event' => $event,
            'form' => $form->fresh('fields'),
            'fieldTypes' => FormField::CUSTOM_TYPES,
        ]);
    }

    public function update(Request $request, Event $event, Form $form): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->authorizeForm($event, $form);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_open' => ['required', 'boolean'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
        ]);

        $form->update($validated);

        return back()->with('success', 'Form settings updated.');
    }

    public function destroy(Event $event, Form $form): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->authorizeForm($event, $form);
        $form->delete();

        return redirect()->route('events.forms.index', $event)->with('success', 'Form deleted.');
    }

    public function storeField(Request $request, Event $event, Form $form): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->authorizeForm($event, $form);
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'field_type' => ['required', Rule::in(FormField::CUSTOM_TYPES)],
            'is_required' => ['nullable', 'boolean'],
            'options' => ['nullable', 'string', 'max:2000'],
        ]);
        $options = $this->options($validated['options'] ?? null);

        if (in_array($validated['field_type'], ['select', 'radio'], true) && count($options) < 2) {
            return back()->withErrors(['options' => 'Select and radio fields require at least two options.'])->withInput();
        }

        $form->fields()->create([
            'field_key' => 'field_'.Str::uuid(),
            'label' => $validated['label'],
            'field_type' => $validated['field_type'],
            'is_required' => $request->boolean('is_required'),
            'options' => $options ?: null,
            'display_order' => ((int) $form->fields()->max('display_order')) + 10,
            'is_active' => true,
        ]);

        return back()->with('success', 'Question added.');
    }

    public function updateField(Request $request, Event $event, Form $form, FormField $field): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->authorizeForm($event, $form);
        abort_unless($field->form_id === $form->id, 404);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'is_required' => ['nullable', 'boolean'],
            'options' => ['nullable', 'string', 'max:2000'],
        ]);
        $options = $this->options($validated['options'] ?? null);

        if (in_array($field->field_type, ['select', 'radio'], true) && count($options) < 2) {
            return back()->withErrors(['options' => 'Select and radio fields require at least two options.'])->withInput();
        }

        $field->update([
            'label' => $validated['label'],
            'is_required' => $request->boolean('is_required'),
            'options' => in_array($field->field_type, ['select', 'radio'], true) ? ($options ?: null) : $field->options,
        ]);

        return back()->with('success', 'Question updated.');
    }

    public function destroyField(Event $event, Form $form, FormField $field): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->authorizeForm($event, $form);
        abort_unless($field->form_id === $form->id, 404);
        $field->delete();

        return back()->with('success', 'Question removed.');
    }

    public function responses(Event $event, Form $form): View
    {
        $this->authorize('update', $event);
        $this->authorizeForm($event, $form);
        $fields = $form->fields()->where('is_active', true)->get();
        $responses = $form->responses()->latest('created_at')->paginate(25);

        return view('events.forms.responses', compact('event', 'form', 'fields', 'responses'));
    }

    public function exportExcel(Event $event, Form $form): BinaryFileResponse
    {
        $this->authorize('update', $event);
        $this->authorizeForm($event, $form);

        return Excel::download(new FormResponsesExport($form), $form->slug.'-responses.xlsx');
    }

    public function exportPdf(Event $event, Form $form): Response
    {
        $this->authorize('update', $event);
        $this->authorizeForm($event, $form);
        $fields = $form->fields()->where('is_active', true)->get();
        $responses = $form->responses()->latest('created_at')->get();

        $pdf = Pdf::loadView('reports.form-responses-pdf', compact('event', 'form', 'fields', 'responses'))->setPaper('a4');

        return $pdf->download($form->slug.'-responses.pdf');
    }

    public function printQr(Event $event, Form $form): View
    {
        $this->authorize('update', $event);
        $this->authorizeForm($event, $form);

        return view('events.forms.qr', compact('event', 'form'));
    }

    public function downloadQr(Event $event, Form $form): Response
    {
        $this->authorize('update', $event);
        $this->authorizeForm($event, $form);
        $svg = QrCode::format('svg')->size(1000)->generate(route('forms.show', [$event->slug, $form->slug]));

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.$form->slug.'-qr.svg"',
        ]);
    }

    private function authorizeForm(Event $event, Form $form): void
    {
        abort_unless($form->event_id === $event->id, 404);
    }

    private function options(?string $options): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $options ?? ''))
            ->map(fn ($option) => trim($option))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
