<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccommodationRoom extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = ['accommodation_floor_id', 'name', 'capacity', 'gender_restriction', 'category_restriction', 'is_accessible', 'status', 'priority', 'notes'];

    protected $casts = ['capacity' => 'integer', 'is_accessible' => 'boolean'];

    public function floor(): BelongsTo
    {
        return $this->belongsTo(AccommodationFloor::class, 'accommodation_floor_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RoomAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->whereIn('status', ['assigned', 'checked_in']);
    }

    public function label(): string
    {
        return $this->floor->block->site->name.' / '.$this->floor->block->name.' / '.$this->floor->name.' / '.$this->name;
    }
}
