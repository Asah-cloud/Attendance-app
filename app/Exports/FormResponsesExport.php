<?php

namespace App\Exports;

use App\Models\Form;
use App\Models\FormResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FormResponsesExport implements FromCollection, WithHeadings, WithMapping
{
    protected Form $form;

    protected Collection $fields;

    private ?bool $showPayment = null;

    public function __construct(Form $form)
    {
        $this->form = $form;
        $this->fields = $form->fields()->where('is_active', true)->get();
    }

    public function collection(): Enumerable
    {
        return $this->form->responses()->latest('created_at')->get();
    }

    public function headings(): array
    {
        return array_merge(['Submitted At'], $this->showsPayment() ? ['Payment', 'Amount paid (GH₵)'] : [], $this->fields->pluck('label')->all());
    }

    private function showsPayment(): bool
    {
        return $this->showPayment ??= $this->form->payment_enabled || $this->form->responses()->whereNotNull('payment_status')->exists();
    }

    public function map($response): array
    {
        /** @var FormResponse $response */
        $row = [$response->created_at?->format('d-m-Y h:i A')];
        if ($this->showsPayment()) {
            $row[] = match ($response->payment_status) {
                'paid' => 'Paid',
                'unpaid' => 'Not paid',
                default => '',
            };
            $row[] = $response->amount_paid_minor !== null ? number_format($response->amount_paid_minor / 100, 2, '.', '') : '';
        }

        foreach ($this->fields as $field) {
            $row[] = $response->answers[$field->field_key] ?? '';
        }

        return $row;
    }
}
