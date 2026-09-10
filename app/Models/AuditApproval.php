<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditApproval extends Model
{
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected $guarded = [];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }
}
