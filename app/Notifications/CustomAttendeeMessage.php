<?php

namespace App\Notifications;

use App\Models\Event;
use App\Notifications\Channels\ArkeselChannel;
use App\Services\CompanyMail;
use App\Support\MergeFields;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * A manager-composed, free-text message. Sent synchronously (no
 * ShouldQueue) from inside SendCustomAttendeeMessageJob, which is already
 * queued per recipient — that lets the job record success/failure
 * immediately instead of racing a second, independently-queued job.
 *
 * $body is already the channel-appropriate text (email body or SMS body)
 * — the job picks which one to pass in based on $channel.
 */
class CustomAttendeeMessage extends Notification
{
    /** @param array<int, array{path: string, name: string, mime: ?string}> $attachments */
    public function __construct(
        public string $body,
        public ?string $subject,
        private string $channel,
        private Event $event,
        private ?string $smsSenderId = null,
        private array $attachments = [],
    ) {}

    public function via(object $notifiable): array
    {
        return [$this->channel === 'sms' ? ArkeselChannel::class : 'mail'];
    }

    /** @return array<string, string> */
    private function mergeValues(object $notifiable): array
    {
        return MergeFields::values($notifiable->name ?? null, $this->event->title, $this->event->company?->name);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $values = $this->mergeValues($notifiable);

        $mail = CompanyMail::make(
            $this->event,
            MergeFields::render($this->subject, $values) ?: 'A message for you',
            'Hello '.$notifiable->name.'!',
            preg_split('/\r?\n/', trim(MergeFields::render($this->body, $values))),
        );

        foreach ($this->attachments as $attachment) {
            $mail->attach(Storage::disk('local')->path($attachment['path']), [
                'as' => $attachment['name'],
                'mime' => $attachment['mime'] ?? null,
            ]);
        }

        return $mail;
    }

    public function smsSenderId(): ?string
    {
        return $this->smsSenderId;
    }

    public function toArkesel(object $notifiable): string
    {
        return MergeFields::render($this->body, $this->mergeValues($notifiable));
    }
}
