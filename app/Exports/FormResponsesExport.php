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
        return array_merge(['Submitted At'], $this->fields->pluck('label')->all());
    }

    public function map($response): array
    {
        /** @var FormResponse $response */
        $row = [$response->created_at?->format('d-m-Y h:i A')];

        foreach ($this->fields as $field) {
            $row[] = $response->answers[$field->field_key] ?? '';
        }

        return $row;
    }
}
