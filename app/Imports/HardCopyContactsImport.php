<?php

namespace App\Imports;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\ParticipantRegistrationService;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Row;

class HardCopyContactsImport implements OnEachRow
{
    public function __construct(private Event $event) {}

    public function onRow(Row $row): void
    {
        $data = $row->toArray();

        $name = trim((string) ($data[0] ?? ''));
        $phone = trim((string) ($data[1] ?? ''));
        $email = trim((string) ($data[2] ?? ''));

        if (strtolower($name) === 'name' && strtolower($phone) === 'phone') {
            return; // header row
        }

        if ($phone === '' && $email === '') {
            return; // need at least one way to reach them
        }

        $name = $name !== '' ? $name : ($phone !== '' ? $phone : $email);

        $participant = app(ParticipantRegistrationService::class)->resolveParticipant($this->event, [
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
        ]);

        $alreadyRegistered = $this->event->registrations()->where('participant_id', $participant->id)->exists();
        if ($alreadyRegistered) {
            return; // don't disturb an existing registration of any status
        }

        $this->event->registrations()->create([
            'participant_id' => $participant->id,
            'status' => EventRegistration::STATUS_AWAITING_CONFIRMATION,
            'source' => 'hardcopy_import',
        ]);
    }
}
