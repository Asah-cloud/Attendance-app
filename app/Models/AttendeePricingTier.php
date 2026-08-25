<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendeePricingTier extends Model
{
    public const SCOPE_PLATFORM = 'platform';

    public const SCOPE_PLAN = 'plan';

    public const SCOPE_COMPANY = 'company';

    protected $fillable = [
        'scope_type',
        'plan_key',
        'company_id',
        'band_from',
        'band_to',
        'rate_minor',
    ];

    protected function casts(): array
    {
        return [
            'band_from' => 'integer',
            'band_to' => 'integer',
            'rate_minor' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
