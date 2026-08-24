<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    public const CUSTOM_TYPES = ['text', 'textarea', 'number', 'date', 'select', 'radio', 'checkbox'];

    protected $fillable = [
        'form_id',
        'field_key',
        'label',
        'field_type',
        'is_required',
        'options',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'options' => 'array',
            'display_order' => 'integer',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
