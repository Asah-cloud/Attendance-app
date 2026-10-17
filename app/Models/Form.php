<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
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

        static::deleted(function (Form $form): void {
            Storage::disk('public')->delete($form->flyers());
        });
    }

    /** Most flyers a form can show. */
    public const MAX_FLYERS = 5;

    /** @return list<string> Stored paths (public disk) of the form's flyers, in display order. */
    public function flyers(): array
    {
        return array_values($this->flyer_paths ?? []);
    }

    protected $fillable = [
        'event_id',
        'title',
        'slug',
        'description',
        'flyer_paths',
        'is_open',
        'opens_at',
        'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'flyer_paths' => 'array',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    /** People allowed to see and export this form's responses (see FormViewerController). */
    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'form_viewers')->withPivot('invited_by')->withTimestamps();
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
