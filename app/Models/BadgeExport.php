<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadgeExport extends Model
{
    use HasUuids;

    protected $fillable = ['event_id', 'user_id', 'status', 'total_batches', 'completed_batches', 'options', 'file_path', 'error', 'expires_at'];

    protected function casts(): array
    {
        return ['options' => 'array', 'expires_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
