<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Form;
use App\Models\FormResponse;
use App\Services\AttendeePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class PublicFormController extends Controller
{
    public function show(string $eventSlug, string $formSlug): View
    {
        $form = $this->findForm($eventSlug, $formSlug);
        $event = $form->event;
        $fields = $form->fields()->where('is_active', true)->get();

        return view('forms.public-show', [
            'event' => $event,
            'form' => $form,
            'fields' => $fields,
            'isOpen' => $form->isOpen(),
        ]);
    }

    public function store(Request $request, string $eventSlug, string $formSlug, AttendeePaymentService $payments): RedirectResponse
    {
        $form = $this->findForm($eventSlug, $formSlug);
        abort_unless($form->isOpen(), 404);

        $fields = $form->fields()->where('is_active', true)->get();
        $paid = $form->collectsPayment();
        $validated = $request->validate($this->rules($fields) + ($paid ? ['payer_email' => ['required', 'email', 'max:255']] + $form->paymentAmountRules() : []));

        $response = FormResponse::create([
            'form_id' => $form->id,
            'answers' => $validated['answers'] ?? [],
            'ip_address' => $request->ip(),
        ]);

        // The answers are kept either way; the response shows as unpaid until Paystack confirms.
        if ($paid) {
            try {
                return redirect()->away($payments->start($response, $form->event, $form->chargeFor($validated), $validated['payer_email'], $validated['payment_option'] ?? null));
            } catch (RuntimeException) {
                return redirect()->route('payments.return', $response->payments()->latest('id')->value('reference'));
            }
        }

        return redirect()->route('forms.thank-you', [$form->event->slug, $form->slug]);
    }

    public function thankYou(string $eventSlug, string $formSlug): View
    {
        $form = $this->findForm($eventSlug, $formSlug);

        return view('forms.thank-you', ['event' => $form->event, 'form' => $form]);
    }

    private function findForm(string $eventSlug, string $formSlug): Form
    {
        $event = Event::findBySlug($eventSlug) ?? abort(404);

        return $event->forms()->where('slug', $formSlug)->firstOrFail();
    }

    private function rules($fields): array
    {
        $rules = ['answers' => ['nullable', 'array']];

        foreach ($fields as $field) {
            $fieldRules = [$field->is_required ? 'required' : 'nullable'];
            $fieldRules = array_merge($fieldRules, match ($field->field_type) {
                'textarea' => ['string', 'max:2000'],
                'number' => ['numeric'],
                'date' => ['date'],
                'select', 'radio' => [Rule::in($field->options ?? [])],
                'checkbox' => [$field->is_required ? 'accepted' : 'boolean'],
                default => ['string', 'max:255'],
            });
            $rules['answers.'.$field->field_key] = $fieldRules;
        }

        return $rules;
    }
}
