<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use Illuminate\Support\Facades\DB;

class EventRegistrationResolver
{
    public function fromScan(Event $event, string $value): ?EventRegistration
    {
        $value = trim($value);

        if (str_starts_with($value, 'ASAH-STAFF:')) {
            $token = substr($value, strlen('ASAH-STAFF:'));
            $participant = Participant::query()
                ->where('company_id', $event->company_id)
                ->where('is_support_staff', true)
                ->where('staff_qr_token', $token)
                ->first();

            if (! $participant) {
                $aliasParticipantId = DB::table('staff_qr_aliases')
                    ->where('company_id', $event->company_id)
                    ->where('token', $token)
                    ->value('participant_id');
                $participant = $aliasParticipantId
                    ? Participant::query()->where('company_id', $event->company_id)->where('is_support_staff', true)->find($aliasParticipantId)
                    : null;
            }

            return $participant ? $event->registrations()
                ->where('participant_id', $participant->id)->first() : null;
        }

        if (str_starts_with($value, 'ASAH-ATTENDANCE:')) {
            $value = substr($value, strlen('ASAH-ATTENDANCE:'));
        } else {
            $path = parse_url($value, PHP_URL_PATH);
            if (is_string($path) && preg_match('#/check-in/([^/]+)$#', $path, $matches)) {
                $value = rawurldecode($matches[1]);
            }
        }

        return $event->registrations()->where('registration_code', $value)->first();
    }
}
