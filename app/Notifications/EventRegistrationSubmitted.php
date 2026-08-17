<?php

namespace App\Notifications;

use App\Models\EventRegistration;
use App\Notifications\Concerns\UsesAttendanceChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventRegistrationSubmitted extends Notification implements ShouldQueue
{
    use Queueable, UsesAttendanceChannels;

    public function __construct(public EventRegistration $registration) {}

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->registration->event;
        $organization = $event->company?->name ?? 'The event team';

        return (new MailMessage)
            ->subject('Thank you for registering - '.$event->title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Thank you for registering for '.$event->title.'. We have received your details successfully.')
            ->line('Status: '.ucfirst($this->registration->status))
            ->line('Date: '.$event->event_date->format('M j, Y'))
            ->when($event->location, fn (MailMessage $mail) => $mail->line('Location: '.$event->location))
            ->action('View my registration and QR code', route('registrations.confirmation', $this->registration->registration_code))
            ->line('Please keep this personal link safe. We will send you another update if your registration status changes.')
            ->salutation('Warm regards, '.$organization);
    }

    public function toArkesel(object $notifiable): string
    {
        $event = $this->registration->event;
        $organization = $event->company?->name ?? config('app.name');

        return 'Hello '.$notifiable->name.'! Thank you for registering for '.$event->title.'. We received your details. Status: '
            .ucfirst($this->registration->status).'. From '.$organization.'. Details: '
            .route('registrations.confirmation', $this->registration->registration_code);
    }
}
