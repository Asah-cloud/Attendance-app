<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRegistrationField extends Model
{
    public const SYSTEM_FIELDS = [
        'full_name' => ['label' => 'Full name', 'field_type' => 'text', 'display_order' => 10],
        'email' => ['label' => 'Email address', 'field_type' => 'email', 'display_order' => 20],
        'phone' => ['label' => 'Phone number', 'field_type' => 'tel', 'display_order' => 30],
        'gender' => ['label' => 'Gender', 'field_type' => 'select', 'options' => ['Male', 'Female'], 'display_order' => 35],
        'category' => ['label' => 'Attendee category', 'field_type' => 'text', 'display_order' => 40],
        'consent' => ['label' => 'I agree to the event terms and privacy notice', 'field_type' => 'checkbox', 'display_order' => 1000],
    ];

    public const CUSTOM_TYPES = ['text', 'textarea', 'number', 'date', 'select', 'radio', 'checkbox'];

    protected $fillable = [
        'event_id',
        'field_key',
        'label',
        'field_type',
        'is_system',
        'is_required',
        'options',
        'validation_rules',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'options' => 'array',
            'validation_rules' => 'array',
            'display_order' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
