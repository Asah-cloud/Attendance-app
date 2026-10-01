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
        'secondary_phone',
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

    /** @return array<int, string> up to two Arkesel-ready numbers: primary and secondary phone. */
    public function routeNotificationForArkesel(): array
    {
        return array_values(array_filter([
            PhoneNumberService::toArkeselFormat($this->phone),
            PhoneNumberService::toArkeselFormat($this->secondary_phone),
        ]));
    }
}
