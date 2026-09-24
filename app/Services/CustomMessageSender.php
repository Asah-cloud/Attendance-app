<?php

namespace App\Services;

use App\Jobs\SendCustomAttendeeMessageJob;
use App\Models\CustomMessage;
use App\Models\CustomMessageRecipient;
use App\Models\Participant;
use Illuminate\Support\Collection;
use Throwable;

/** Turns a message and its recipients into queued deliveries, immediately or when a schedule falls due. */
class CustomMessageSender
{
    public function __construct(private readonly CustomMessageService $routing) {}

    /**
     * Create one delivery row per person and channel, and queue each delivery.
     *
     * @param  Collection<int, array{participant_id: ?int, name: string, email: ?string, phone: ?string}>  $recipients
     */
    public function deliver(CustomMessage $message, Collection $recipients): void
    {
        $hasEmailBody = filled($message->email_body);
        $hasSmsBody = filled($message->sms_body);

        foreach ($recipients as $recipient) {
            $channels = $this->routing->determineChannels($message->mode, $recipient['email'], $recipient['phone'], $hasEmailBody, $hasSmsBody);
            $person = [
                'participant_id' => $recipient['participant_id'],
                'name' => $recipient['name'],
                'email' => $recipient['email'],
                'phone' => $recipient['phone'],
            ];

            if (empty($channels)) {
                $message->recipients()->create($person + ['channel' => null, 'status' => CustomMessageRecipient::STATUS_SKIPPED]);

                continue;
            }

            foreach ($channels as $channel) {
                $row = $message->recipients()->create($person + ['channel' => $channel, 'status' => CustomMessageRecipient::STATUS_PENDING]);

                SendCustomAttendeeMessageJob::dispatch($row->id);
            }
        }
    }

    /**
     * The people a draft or scheduled message will go to, read fresh from the saved selection so
     * contact details changed since it was written are picked up.
     *
     * @return Collection<int, array{participant_id: ?int, name: string, email: ?string, phone: ?string}>
     */
    public function storedRecipients(CustomMessage $message): Collection
    {
        $recipients = Participant::query()
            ->where('company_id', $message->company_id)
            ->whereIn('id', $message->draftParticipantIds())
            ->get()
            ->map(fn (Participant $participant) => [
                'participant_id' => $participant->id,
                'name' => $participant->name,
                'email' => $participant->email,
                'phone' => $participant->phone,
            ]);

        foreach ($message->draftExtras() as $extra) {
            $recipients->push([
                'participant_id' => null,
                'name' => $extra['name'],
                'email' => $extra['email'] ?? null,
                'phone' => $extra['phone'] ?? null,
            ]);
        }

        return self::deduplicate($recipients);
    }

    /**
     * Send a saved draft or scheduled message now. Returns false when it cannot be sent (no
     * recipients left, or another process already sent it), leaving it unsent as a draft.
     */
    public function sendStored(CustomMessage $message): bool
    {
        $recipients = $this->storedRecipients($message);

        if ($recipients->isEmpty()) {
            CustomMessage::whereKey($message->id)->where('status', '!=', CustomMessage::STATUS_SENT)
                ->update(['status' => CustomMessage::STATUS_DRAFT, 'scheduled_at' => null]);

            return false;
        }

        // Only one caller can win this update, so a message is never sent twice.
        $claimed = CustomMessage::whereKey($message->id)
            ->whereIn('status', [CustomMessage::STATUS_DRAFT, CustomMessage::STATUS_SCHEDULED])
            ->update(['status' => CustomMessage::STATUS_SENT, 'sent_at' => now(), 'recipient_count' => $recipients->count()]);

        if ($claimed === 0) {
            return false;
        }

        $this->deliver($message->refresh(), $recipients);

        return true;
    }

    /** Send every scheduled message whose time has come. Returns how many were sent. */
    public function dispatchDue(): int
    {
        $sent = 0;

        $due = CustomMessage::query()
            ->where('status', CustomMessage::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->get();

        foreach ($due as $message) {
            try {
                $sent += $this->sendStored($message) ? 1 : 0;
            } catch (Throwable $exception) {
                report($exception);
                // Never retry a failing schedule every minute: hand it back as a draft to fix.
                CustomMessage::whereKey($message->id)->where('status', CustomMessage::STATUS_SCHEDULED)
                    ->update(['status' => CustomMessage::STATUS_DRAFT, 'scheduled_at' => null]);
            }
        }

        return $sent;
    }

    /** @param Collection<int, array{participant_id: ?int, name: string, email: ?string, phone: ?string}> $recipients */
    public static function deduplicate(Collection $recipients): Collection
    {
        $seen = [];

        return $recipients->filter(function (array $recipient) use (&$seen): bool {
            $key = $recipient['email'] ? strtolower(trim($recipient['email']))
                : preg_replace('/\D+/', '', $recipient['phone'] ?? '');

            if ($key === '' || $key === null) {
                return true;
            }
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        })->values();
    }
}
