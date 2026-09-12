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

        // Skip empty rows (checking index 1 for Name), and the header row specifically
        // (only on row 1, so a real attendee named e.g. "Staff" is never mistaken for one).
        $headerLabels = ['name', 'staff', 'full name', 'participant', 'participant name'];
        if (empty($data[1]) || ($row->getIndex() === 1 && in_array(strtolower(trim($data[1])), $headerLabels, true))) {
            return;
        }

        $id = trim((string) $data[0]);
        $name = trim((string) $data[1]);
        $gender = ! empty(trim($data[2] ?? '')) ? trim($data[2]) : null;
        $roomGroup = ! empty(trim($data[3] ?? '')) ? trim($data[3]) : null;
        $category = ! empty(trim($data[4] ?? '')) ? trim($data[4]) : 'Member';

        $rawPhone = ! empty(trim($data[5] ?? '')) ? trim($data[5]) : null;
        $rawEmail = ! empty(trim($data[6] ?? '')) ? trim($data[6]) : null;
        // A blank column A (e.g. a merged "Department/Role" cell that only the extractor's
        // top row kept) must not collide multiple attendees onto the same member_id — fall
        // back to the row number, which still keeps re-imports of the same file idempotent.
        $id = $id !== '' ? $id : 'row'.$row->getIndex();
        $companyMemberId = $this->event->company_id.':'.$id;
        $legacyEmail = 'event'.$this->event->id.'_user'.$id.'@example.invalid';
        $companyEmail = 'company'.$this->event->company_id.'_member'.$id.'@example.invalid';

        [, $registration] = app(ParticipantRegistrationService::class)->register($this->event, [
            'name' => $name,
            'email' => $rawEmail,
            'phone' => $rawPhone,
            'member_id' => $companyMemberId,
            'category' => $category,
            'gender' => $gender,
            'room_group' => $roomGroup,
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
