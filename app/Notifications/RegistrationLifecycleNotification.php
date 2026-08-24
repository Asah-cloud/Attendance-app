<?php

namespace App\Notifications;

use App\Models\EventRegistration;
use App\Notifications\Concerns\UsesAttendanceChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class RegistrationLifecycleNotification extends Notification implements ShouldQueue
{
    use Queueable, UsesAttendanceChannels;

    public function __construct(public EventRegistration $registration, public string $type) {}

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->registration->event;
        $company = $event->company;
        $organization = $company?->name ?? 'The event team';
        $subject = $this->subject().' - '.$event->title;

        $mail = (new MailMessage)
            ->subject($subject)
            ->view('emails.registration', [
                'subject' => $subject,
                'event' => $event,
                'organizationName' => $organization,
                'companyLogoUrl' => $company?->logo_path ? url(Storage::url($company->logo_path)) : null,
                'eventLogoUrl' => $event->logo_path ? url(Storage::url($event->logo_path)) : null,
                'greeting' => 'Hello '.$notifiable->name.'!',
                'lines' => array_values(array_filter([
                    $this->message(),
                    'Event: '.$event->title,
                    'Date: '.$event->event_date->format('M j, Y'),
                    $event->location ? 'Location: '.$event->location : null,
                    'Please keep your personal registration link safe. We look forward to welcoming you!',
                ])),
                'actionLabel' => $this->actionLabel(),
                'actionUrl' => route('registrations.confirmation', $this->registration->registration_code),
                'salutation' => 'Warm regards, '.$organization,
            ]);

        return $company?->approvedEmailFromAddress()
            ? $mail->from($company->approvedEmailFromAddress(), $company->email_from_name ?: $company->name)
            : $mail;
    }

    public function smsSenderId(): ?string
    {
        return $this->registration->event->company?->approvedSmsSenderId();
    }

    public function toArkesel(object $notifiable): string
    {
        $event = $this->registration->event;
        $organization = $event->company?->name ?? config('app.name');

        return 'Hello '.$notifiable->name.'! '.$this->smsMessage().' Event: '.$event->title.', '
            .$event->event_date->format('M j, Y').'. From '.$organization.'. Details: '
            .route('registrations.confirmation', $this->registration->registration_code);
    }

    private function subject(): string
    {
        return match ($this->type) {
            'confirmed' => 'You are confirmed!',
            'waitlisted' => 'You are on the waitlist',
            'approved' => 'Good news - you are approved!',
            'rejected' => 'An update about your registration',
            'cancelled' => 'Your registration has been cancelled',
            'promoted' => 'Good news - a place is available!',
            'reminder' => 'We look forward to seeing you soon',
            'event_changed' => 'Important event details have changed',
            'event_cancelled' => 'Important event cancellation notice',
            default => 'Thank you - your registration was received',
        };
    }

    private function message(): string
    {
        return match ($this->type) {
            'confirmed' => 'Great news! Your place is confirmed. Please bring your personal QR code with you for a quick check-in.',
            'waitlisted' => 'Thank you for registering. The event is currently full, but we have saved your place on the waitlist and will let you know if a space becomes available.',
            'approved' => 'Good news! Your registration has been approved and your place is now confirmed. Please bring your personal QR code with you.',
            'rejected' => 'Thank you for your interest. Unfortunately, we are unable to approve your registration at this time. Please contact the event organizer if you need more information.',
            'cancelled' => 'Your registration has been cancelled as requested. We hope to welcome you at another event soon.',
            'promoted' => 'Good news! A place has become available and you are now confirmed. We look forward to seeing you at the event.',
            'reminder' => 'Just a friendly reminder that your event is coming up soon. Please remember to bring your personal QR code for check-in.',
            'event_changed' => 'There has been an update to the event date or location. Please review the latest details below before attending.',
            'event_cancelled' => 'We are sorry to let you know that this event has been cancelled. Thank you for your understanding.',
            default => 'Thank you for registering! We have received your details and will keep you updated about your registration.',
        };
    }

    private function actionLabel(): string
    {
        return in_array($this->type, ['event_changed', 'reminder'], true)
            ? 'View latest event details'
            : 'View my registration and QR code';
    }

    private function smsMessage(): string
    {
        return match ($this->type) {
            'confirmed' => 'Great news - your place is confirmed. Please bring your QR code.',
            'waitlisted' => 'The event is full, but your place on the waitlist is saved.',
            'approved' => 'Good news - your registration is approved and confirmed.',
            'rejected' => 'We are sorry, your registration could not be approved. Contact the organizer for help.',
            'cancelled' => 'Your registration has been cancelled as requested.',
            'promoted' => 'Good news - a place is available and you are now confirmed!',
            'reminder' => 'A friendly reminder that your event is coming up soon. Please bring your QR code.',
            'event_changed' => 'Important: the event date or location has changed. Please review the latest details.',
            'event_cancelled' => 'We are sorry, this event has been cancelled. Thank you for understanding.',
            default => 'Thank you for registering. We received your details successfully.',
        };
    }
}
