<?php

namespace App\Notifications;

use App\Models\EventRegistration;
use App\Notifications\Concerns\UsesAttendanceChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class EventRegistrationSubmitted extends Notification implements ShouldQueue
{
    use Queueable, UsesAttendanceChannels;

    public function __construct(public EventRegistration $registration) {}

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->registration->event;
        $company = $event->company;
        $organization = $company?->name ?? 'The event team';
        $subject = 'Thank you for registering - '.$event->title;

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.registration', [
                'subject' => $subject,
                'event' => $event,
                'organizationName' => $organization,
                'companyLogoUrl' => $company?->logo_path ? url(Storage::url($company->logo_path)) : null,
                'eventLogoUrl' => $event->logo_path ? url(Storage::url($event->logo_path)) : null,
                'greeting' => 'Hello '.$notifiable->name.'!',
                'lines' => array_values(array_filter([
                    'Thank you for registering for '.$event->title.'. We have received your details successfully.',
                    'Status: '.ucfirst($this->registration->status),
                    'Date: '.$event->event_date->format('M j, Y'),
                    $event->location ? 'Location: '.$event->location : null,
                    'Please keep this personal link safe. We will send you another update if your registration status changes.',
                ])),
                'actionLabel' => 'View my registration and QR code',
                'actionUrl' => route('registrations.confirmation', $this->registration->registration_code),
                'salutation' => 'Warm regards, '.$organization,
            ]);
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
