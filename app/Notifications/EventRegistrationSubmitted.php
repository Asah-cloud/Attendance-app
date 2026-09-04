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
        $needsRoomPick = $this->needsRoomPick();

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
                    'Thank you for registering for '.$event->title.'. We have received your details successfully.',
                    'Status: '.ucfirst($this->registration->status),
                    'Date: '.$event->event_date->format('M j, Y'),
                    $event->location ? 'Location: '.$event->location : null,
                    $needsRoomPick ? 'You asked for a room — please choose one before selection closes.' : null,
                    'Please keep this personal link safe. We will send you another update if your registration status changes.',
                ])),
                'actionLabel' => $needsRoomPick ? 'Select your room' : 'View my registration and QR code',
                'actionUrl' => $needsRoomPick
                    ? route('registrations.room.select', $this->registration->registration_code)
                    : route('registrations.confirmation', $this->registration->registration_code),
                'salutation' => 'Warm regards, '.$organization,
            ]);

        return $company?->approvedEmailFromAddress()
            ? $mail->from($company->approvedEmailFromAddress(), $company->email_from_name ?: $company->name)
            : $mail;
    }

    public function toArkesel(object $notifiable): string
    {
        $event = $this->registration->event;
        $organization = $event->company?->name ?? config('app.name');
        $needsRoomPick = $this->needsRoomPick();
        $link = $needsRoomPick
            ? route('registrations.room.select', $this->registration->registration_code)
            : route('registrations.confirmation', $this->registration->registration_code);

        return 'Hello '.$notifiable->name.'! Thank you for registering for '.$event->title.'. We received your details. Status: '
            .ucfirst($this->registration->status).'. From '.$organization.'. '
            .($needsRoomPick ? 'Please choose your room: ' : 'Details: ').$link;
    }

    /** Whether this attendee still needs to pick a room while self-selection is open. */
    private function needsRoomPick(): bool
    {
        $registration = $this->registration;

        return $registration->status === EventRegistration::STATUS_CONFIRMED
            && $registration->accommodation_required
            && ! $registration->roomAssignment
            && $registration->event->accommodationSelfSelectOpen();
    }

    public function smsSenderId(): ?string
    {
        return $this->registration->event->company?->approvedSmsSenderId();
    }
}
