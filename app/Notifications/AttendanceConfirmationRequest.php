<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Notifications\Concerns\UsesAttendanceChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class AttendanceConfirmationRequest extends Notification implements ShouldQueue
{
    use Queueable, UsesAttendanceChannels;

    public const DEFAULT_MESSAGE = "Hi {name}, thank you so much for being part of {event}! We'd love for you to confirm your attendance by tapping the button below — it only takes a moment.";

    public function __construct(public EventRegistration $registration) {}

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->registration->event;
        $company = $event->company;
        $organization = $company?->name ?? 'The event team';
        $subject = 'Please confirm your attendance - '.$event->title;

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.registration', [
                'subject' => $subject,
                'event' => $event,
                'organizationName' => $organization,
                'companyLogoUrl' => $company?->logo_path ? url(Storage::url($company->logo_path)) : null,
                'eventLogoUrl' => $event->logo_path ? url(Storage::url($event->logo_path)) : null,
                'greeting' => 'Hello '.$notifiable->name.'!',
                'lines' => [$this->renderMessage($event, $notifiable->name)],
                'actionLabel' => 'Confirm my attendance',
                'actionUrl' => route('attendance.confirm.show', $this->registration->registration_code),
                'salutation' => 'Warm regards, '.$organization,
            ]);
    }

    public function toArkesel(object $notifiable): string
    {
        $event = $this->registration->event;
        $organization = $event->company?->name ?? config('app.name');

        return $this->renderMessage($event, $notifiable->name).' From '.$organization.'. Confirm here: '
            .route('attendance.confirm.show', $this->registration->registration_code);
    }

    private function renderMessage(Event $event, string $name): string
    {
        $template = $event->confirmation_message ?: self::DEFAULT_MESSAGE;

        return str_replace(['{name}', '{event}'], [$name, $event->title], $template);
    }
}
