<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccommodationBlock extends Model
{
    protected $fillable = ['accommodation_site_id', 'name', 'gender_restriction', 'category_restriction', 'priority', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(AccommodationSite::class, 'accommodation_site_id');
    }

    public function floors(): HasMany
    {
        return $this->hasMany(AccommodationFloor::class);
    }
}
