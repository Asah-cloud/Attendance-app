<?php

namespace App\Imports;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
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

    /**
     * Rows that would rename an existing participant, collected for the manager to review.
     *
     * @var list<array<string, mixed>>
     */
    private array $reviewRows = [];

    /** @var array<string, int> */
    private array $counts = ['added' => 0, 'updated' => 0, 'skipped' => 0];

    /**
     * @param  array<int|string, string>|null  $decisions  The manager's choice per row number for rows
     *                                                     that would rename someone: 'update', 'new' or 'skip'.
     *                                                     A row needing a choice but given none is added as a
     *                                                     new person. Null (no review step) updates them as before.
     * @param  bool  $trial  A dry run inside a transaction the caller rolls back: rooms are not
     *                       allocated, since that can message attendees immediately.
     */
    public function __construct(Event $event, bool $needsRoom = false, private ?array $decisions = null, private bool $trial = false)
    {
        $this->event = $event;
        $this->needsRoom = $needsRoom;
    }

    /** @return list<array<string, mixed>> */
    public function reviewRows(): array
    {
        return $this->reviewRows;
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return $this->counts;
    }

    public function onRow(Row $row): void
    {
        $this->importRow($row->toArray(), $row->getIndex());
    }

    /** @param array<int, mixed> $data */
    public function importRow(array $data, int $rowIndex): void
    {

        // Skip empty rows (checking index 1 for Name), and the header row specifically
        // (only on row 1, so a real attendee named e.g. "Staff" is never mistaken for one).
        $headerLabels = ['name', 'staff', 'full name', 'participant', 'participant name'];
        if (empty($data[1]) || ($rowIndex === 1 && in_array(strtolower(trim($data[1])), $headerLabels, true))) {
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
        $id = $id !== '' ? $id : 'row'.$rowIndex;
        // Spreadsheet IDs are commonly row numbers that restart in every event list.
        // Keep them stable for re-imports of this event without allowing row "1" from
        // a later event to reuse and overwrite row "1" from an earlier event.
        $companyMemberId = $this->event->company_id.':event'.$this->event->id.':'.$id;
        $companyEmail = 'company'.$this->event->company_id.'_event'.$this->event->id.'_member'.$id.'@example.invalid';

        $service = app(ParticipantRegistrationService::class);
        $details = [
            'name' => $name,
            'email' => $rawEmail,
            'phone' => $rawPhone,
            'member_id' => $companyMemberId,
            'category' => $category,
            'gender' => $gender,
            'room_group' => $roomGroup,
            // Synthetic placeholder addresses are storage fallbacks, not proof of identity.
            // Matching them can connect a reused row number from a new list to the wrong person.
            'lookup_emails' => array_filter([$rawEmail]),
            'generated_email' => $companyEmail,
        ];

        // A row whose phone or email belongs to someone with a different name would rename
        // them. That is often right (a corrected name) but can be a different person sharing
        // a phone or email, so the manager decides - and until they have, it's a new person.
        $match = $service->findMatch($this->event, $details);
        if ($match && ! $service->sameName($match->name, $name)) {
            $this->reviewRows[] = [
                'row' => $rowIndex,
                'name' => $name,
                'phone' => $rawPhone,
                'email' => $rawEmail,
                'existing_id' => $match->id,
                'existing_name' => $match->name,
                'existing_phone' => $match->phone,
                'existing_email' => str_ends_with((string) $match->email, '@example.invalid') ? null : $match->email,
                'matched_by' => $this->matchedBy($match, $rawEmail),
            ];

            $decision = $this->decisions === null ? 'update' : ($this->decisions[$rowIndex] ?? 'new');
            if ($decision === 'skip') {
                $this->counts['skipped']++;

                return;
            }
            $details['force_new'] = $decision !== 'update';
        }

        [$participant, $registration] = $service->register($this->event, $details, 'import');
        $this->counts[$participant->wasRecentlyCreated ? 'added' : 'updated']++;

        if ($registration->wasRecentlyCreated) {
            $this->newRegistrationIds[] = $registration->id;

            if ($this->needsRoom && $this->event->accommodation_enabled && ! $this->trial) {
                $registration->update(['accommodation_required' => true]);
                app(RegistrationLifecycleService::class)->allocateAccommodation($registration);
            }
        }
    }

    /** A row ID only re-finds someone of the same name, so a rename match is by email or phone. */
    private function matchedBy(Participant $match, ?string $email): string
    {
        return $email !== null && strcasecmp((string) $match->email, $email) === 0 ? 'email' : 'phone';
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
