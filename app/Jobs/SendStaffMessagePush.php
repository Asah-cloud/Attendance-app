<?php

namespace App\Jobs;

use App\Models\PushSubscription;
use App\Models\StaffMessage;
use App\Services\WebPushSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/** Pops a staff message up on the recipients' phones, even when the app is closed. */
class SendStaffMessagePush implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $messageId) {}

    public function handle(WebPushSender $sender): void
    {
        $message = StaffMessage::with('sender')->find($this->messageId);
        if (! $message) {
            return;
        }

        $subscriptions = PushSubscription::query()
            ->whereIn('user_id', $message->recipients()->whereNull('read_at')->select('user_id'))
            ->get();

        $sender->send($subscriptions, [
            'title' => ($message->urgent ? 'URGENT · ' : '').'Message from '.($message->sender?->name ?? 'your coordinator'),
            'body' => Str::limit($message->body, 240),
            'tag' => 'staff-message-'.$message->id,
            'urgent' => $message->urgent,
            'url' => route('dashboard'),
        ], $message->urgent);
    }
}
