<?php

namespace App\Imports;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Notifications\Concerns\NotifiesPerChannel;
use App\Notifications\EventRegistrationSubmitted;
use App\Services\ParticipantRegistrationService;
use App\Services\RegistrationLifecycleService;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Row;

class UsersImport implements OnEachRow
{
    private $event;

    private bool $needsRoom;

    /** @var array<int, int> */
    private array $newRegistrationIds = [];

    public function __construct(Event $event, bool $needsRoom = false)
    {
        $this->event = $event;
        $this->needsRoom = $needsRoom;
    }

    public function onRow(Row $row): void
    {
        $data = $row->toArray();

        // Skip header or empty rows (Checking index 1 for Name)
        if (empty($data[1]) || strtolower(trim($data[1])) === 'name') {
            return;
        }

        $id = trim((string) $data[0]);
        $name = trim((string) $data[1]);
        $category = ! empty(trim($data[4] ?? '')) ? trim($data[4]) : 'Member';

        $rawPhone = ! empty(trim($data[5] ?? '')) ? trim($data[5]) : null;
        $rawEmail = ! empty(trim($data[6] ?? '')) ? trim($data[6]) : null;
        $companyMemberId = $this->event->company_id.':'.$id;
        $legacyEmail = 'event'.$this->event->id.'_user'.$id.'@example.invalid';
        $companyEmail = 'company'.$this->event->company_id.'_member'.$id.'@example.invalid';

        [, $registration] = app(ParticipantRegistrationService::class)->register($this->event, [
            'name' => $name,
            'email' => $rawEmail,
            'phone' => $rawPhone,
            'member_id' => $companyMemberId,
            'category' => $category,
            'lookup_emails' => array_filter([$rawEmail, $legacyEmail, $companyEmail]),
            'generated_email' => $companyEmail,
        ], 'import');

        if ($registration->wasRecentlyCreated) {
            $this->newRegistrationIds[] = $registration->id;

            if ($this->needsRoom && $this->event->accommodation_enabled) {
                $registration->update(['accommodation_required' => true]);
                app(RegistrationLifecycleService::class)->allocateAccommodation($registration);
            }
        }
    }

    public function sendNotifications(): void
    {
        EventRegistration::query()
            ->whereIn('id', $this->newRegistrationIds)
            ->with(['event', 'participant'])
            ->each(function (EventRegistration $registration): void {
                NotifiesPerChannel::send(
                    $registration->participant,
                    new EventRegistrationSubmitted($registration)
                );
            });
    }
}
