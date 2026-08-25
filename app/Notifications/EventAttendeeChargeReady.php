<?php

namespace App\Notifications;

use App\Models\EventAttendeeCharge;
use App\Notifications\Concerns\UsesAttendanceChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventAttendeeChargeReady extends Notification implements ShouldQueue
{
    use Queueable, UsesAttendanceChannels;

    public function __construct(public EventAttendeeCharge $charge) {}

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->charge->event;
        $amount = number_format($this->charge->amount_minor / 100, 2);

        return (new MailMessage)
            ->subject('Attendee bill ready - '.$event->title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('The attendee bill for "'.$event->title.'" is ready: '.$this->charge->currency.' '.$amount.' for '.$this->charge->registered_count.' registered attendee(s).')
            ->action('Review and pay', route('events.billing.show', $event))
            ->salutation('Warm regards, '.config('mail.from.name'));
    }

    public function toArkesel(object $notifiable): string
    {
        $event = $this->charge->event;
        $amount = number_format($this->charge->amount_minor / 100, 2);

        return 'Attendee bill ready for "'.$event->title.'": '.$this->charge->currency.' '.$amount.'. '.route('events.billing.show', $event);
    }
}
