<?php

namespace App\Notifications;

use App\Notifications\Channels\ArkeselChannel;
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
        private ?string $smsSenderId = null,
        private ?string $fromEmail = null,
        private ?string $fromName = null,
        private array $attachments = [],
    ) {}

    public function via(object $notifiable): array
    {
        return [$this->channel === 'sms' ? ArkeselChannel::class : 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject ?: 'A message for you');

        foreach (preg_split('/\r?\n/', trim($this->body)) as $line) {
            $mail->line($line);
        }

        foreach ($this->attachments as $attachment) {
            $mail->attach(Storage::disk('local')->path($attachment['path']), [
                'as' => $attachment['name'],
                'mime' => $attachment['mime'] ?? null,
            ]);
        }

        return $this->fromEmail ? $mail->from($this->fromEmail, $this->fromName) : $mail;
    }

    public function smsSenderId(): ?string
    {
        return $this->smsSenderId;
    }

    public function toArkesel(object $notifiable): string
    {
        return $this->body;
    }
}
