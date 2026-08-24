<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Form extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Form $form): void {
            if ($form->slug) {
                return;
            }

            $base = Str::slug($form->title) ?: 'form';
            $slug = $base;
            $suffix = 2;
            while (static::query()->where('event_id', $form->event_id)->where('slug', $slug)->exists()) {
                $slug = $base.'-'.$suffix++;
            }
            $form->slug = $slug;
        });
    }

    protected $fillable = [
        'event_id',
        'title',
        'slug',
        'description',
        'is_open',
        'opens_at',
        'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('display_order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class);
    }

    public function isOpen(): bool
    {
        return $this->is_open
            && (! $this->opens_at || now()->gte($this->opens_at))
            && (! $this->closes_at || now()->lte($this->closes_at));
    }
}
