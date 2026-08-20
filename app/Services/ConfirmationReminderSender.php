<?php

namespace App\Services;

use App\Models\EventRegistration;
use App\Notifications\AttendanceConfirmationRequest;
use App\Notifications\Concerns\NotifiesPerChannel;

class ConfirmationReminderSender
{
    /**
     * Re-notify hard-copy attendees who are still awaiting confirmation
     * 3 days after their first confirmation request, once each.
     */
    public function sendDue(): int
    {
        $sent = 0;

        EventRegistration::query()
            ->where('status', EventRegistration::STATUS_AWAITING_CONFIRMATION)
            ->whereNotNull('confirmation_sent_at')
            ->where('confirmation_sent_at', '<=', now()->subDays(3))
            ->whereNull('confirmation_reminder_sent_at')
            ->with(['event', 'participant'])
            ->chunkById(100, function ($registrations) use (&$sent): void {
                foreach ($registrations as $registration) {
                    if (! $registration->participant->email && ! $registration->participant->phone) {
                        continue;
                    }
                    NotifiesPerChannel::send($registration->participant, new AttendanceConfirmationRequest($registration));
                    $registration->update(['confirmation_reminder_sent_at' => now()]);
                    $sent++;
                }
            });

        return $sent;
    }
}
