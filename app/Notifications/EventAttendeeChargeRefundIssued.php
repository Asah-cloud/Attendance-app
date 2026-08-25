<?php

namespace App\Notifications;

use App\Models\EventAttendeeCharge;
use App\Notifications\Concerns\UsesAttendanceChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventAttendeeChargeRefundIssued extends Notification implements ShouldQueue
{
    use Queueable, UsesAttendanceChannels;

    public function __construct(public EventAttendeeCharge $charge) {}

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->charge->event;
        $refund = number_format($this->charge->refund_amount_minor / 100, 2);

        return (new MailMessage)
            ->subject('Attendee bill reconciled - '.$event->title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('"'.$event->title.'" had '.$this->charge->registered_count.' registered attendee(s) and '.$this->charge->checked_in_count.' checked in.')
            ->line('A refund of '.$this->charge->currency.' '.$refund.' is due for the no-shows.')
            ->action('View billing details', route('events.billing.show', $event))
            ->salutation('Warm regards, '.config('mail.from.name'));
    }

    public function toArkesel(object $notifiable): string
    {
        $event = $this->charge->event;
        $refund = number_format($this->charge->refund_amount_minor / 100, 2);

        return 'A refund of '.$this->charge->currency.' '.$refund.' is due for "'.$event->title.'". '.route('events.billing.show', $event);
    }
}
