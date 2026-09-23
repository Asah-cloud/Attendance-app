<?php

namespace App\Models;

use App\Services\PhoneNumberService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;

class CustomMessageRecipient extends Model
{
    use Notifiable;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'custom_message_id',
        'participant_id',
        'name',
        'email',
        'phone',
        'channel',
        'status',
        'error_message',
    ];

    public function customMessage(): BelongsTo
    {
        return $this->belongsTo(CustomMessage::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->email;
    }

    public function routeNotificationForArkesel(): ?string
    {
        return PhoneNumberService::toArkeselFormat($this->phone);
    }
}
