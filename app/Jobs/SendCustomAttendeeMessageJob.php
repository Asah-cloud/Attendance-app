<?php

namespace App\Jobs;

use App\Models\CustomMessageRecipient;
use App\Notifications\CustomAttendeeMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendCustomAttendeeMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $recipientId) {}

    public function handle(): void
    {
        $recipient = CustomMessageRecipient::with('customMessage.event.company')->find($this->recipientId);

        if (! $recipient || $recipient->status !== CustomMessageRecipient::STATUS_PENDING) {
            return;
        }

        if (! $recipient->channel) {
            $recipient->update(['status' => CustomMessageRecipient::STATUS_SKIPPED]);

            return;
        }

        $message = $recipient->customMessage;
        $company = $message->event->company;
        $isMail = $recipient->channel === 'mail';

        try {
            $recipient->notify(new CustomAttendeeMessage(
                body: $isMail ? $message->email_body : $message->sms_body,
                subject: $message->subject,
                channel: $recipient->channel,
                event: $message->event,
                smsSenderId: $company?->approvedSmsSenderId(),
                attachments: $isMail ? ($message->attachments ?? []) : [],
            ));
            $recipient->update(['status' => CustomMessageRecipient::STATUS_SENT]);
        } catch (Throwable $exception) {
            $recipient->update([
                'status' => CustomMessageRecipient::STATUS_FAILED,
                'error_message' => substr($exception->getMessage(), 0, 500),
            ]);
        }
    }
}
