<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class CustomMessage extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SENT = 'sent';

    protected $fillable = [
        'event_id',
        'company_id',
        'created_by',
        'subject',
        'email_body',
        'sms_body',
        'attachments',
        'mode',
        'status',
        'scheduled_at',
        'sent_at',
        'draft_recipients',
        'recipient_count',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'draft_recipients' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    /** Draft or scheduled: not sent yet, so it can still be edited. */
    public function isPending(): bool
    {
        return $this->isDraft() || $this->isScheduled();
    }

    /** The moment that best describes this message in a list. */
    public function displayTime(): Carbon
    {
        return $this->sent_at ?? $this->scheduled_at ?? $this->updated_at ?? $this->created_at;
    }

    /** @return list<array{name: string, email: ?string, phone: ?string}> people from an uploaded list, kept with a draft */
    public function draftExtras(): array
    {
        return array_values($this->draft_recipients['extras'] ?? []);
    }

    /** @return list<int> */
    public function draftParticipantIds(): array
    {
        return array_map('intval', $this->draft_recipients['participants'] ?? []);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CustomMessageRecipient::class);
    }
}
