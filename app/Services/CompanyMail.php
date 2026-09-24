<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;

/** Builds an attendee email in the company's branding, with no mention of the platform. */
class CompanyMail
{
    /** @param list<string> $lines */
    public static function make(Event $event, string $subject, string $greeting, array $lines, ?string $actionLabel = null, ?string $actionUrl = null): MailMessage
    {
        $company = $event->company;
        $organization = $company?->name ?? 'The event team';

        $mail = (new MailMessage)
            ->subject($subject)
            ->view('emails.registration', [
                'subject' => $subject,
                'event' => $event,
                'organizationName' => $organization,
                'companyLogoUrl' => $company?->logo_path ? url(Storage::url($company->logo_path)) : null,
                'eventLogoUrl' => $event->logo_path ? url(Storage::url($event->logo_path)) : null,
                'greeting' => $greeting,
                'lines' => array_values(array_filter($lines, fn ($line) => filled($line))),
                'actionLabel' => $actionLabel,
                'actionUrl' => $actionUrl,
                'salutation' => 'Regards, '.$organization,
            ]);

        return $company ? $company->applyEmailIdentity($mail) : $mail;
    }
}
