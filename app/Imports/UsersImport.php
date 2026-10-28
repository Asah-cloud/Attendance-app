<?php

namespace App\Imports;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Notifications\Concerns\NotifiesPerChannel;
use App\Notifications\EventRegistrationSubmitted;
use App\Services\ParticipantRegistrationService;
use App\Services\PhoneNumberService;
use App\Services\RegistrationLifecycleService;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Row;

class UsersImport implements OnEachRow
{
    private $event;

    private bool $needsRoom;

    /** @var array<int, int> */
    private array $newRegistrationIds = [];

    /** @var array<int, int> People created by this import (not found already in the company). */
    private array $newParticipantIds = [];

    /**
     * Rows that would rename an existing participant, collected for the manager to review.
     *
     * @var list<array<string, mixed>>
     */
    private array $reviewRows = [];

    /** @var array<string, int> */
    private array $counts = ['added' => 0, 'updated' => 0, 'skipped' => 0];

    /**
     * Column number of each field, read from the heading row when the file has one;
     * null means the fixed order (A ID, B Name, C Gender, D Room group, E Category, F Phone, G Email).
     *
     * @var array<string, int>|null
     */
    private ?array $columns = null;

    /** Fields in the fixed column order, then those only read from a heading (Country). */
    private const FIELDS = ['id', 'name', 'gender', 'area', 'category', 'phone', 'email', 'country'];

    /**
     * What happened to each row: new_person (created), newly_registered (already in the system,
     * now added to this event), reconfirmed (was pending or cancelled here), already_registered,
     * or skipped.
     *
     * @var list<array{row: int, name: string, status: string, registration_id: ?int}>
     */
    private array $rowResults = [];

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

    /** @return array{registrations: array<int, int>, participants: array<int, int>} What undoing this import removes. */
    public function createdIds(): array
    {
        return ['registrations' => array_values(array_unique($this->newRegistrationIds)), 'participants' => array_values(array_unique($this->newParticipantIds))];
    }

    /** @return list<array{row: int, name: string, status: string, registration_id: ?int}> */
    public function rowResults(): array
    {
        return $this->rowResults;
    }

    public function onRow(Row $row): void
    {
        $values = $row->toArray();

        // A formula cell is read as the value it shows in Excel, not its formula text. Formulas
        // pointing at another workbook can't be recalculated here, but Excel saves the last
        // result in the file and that is used.
        $index = 0;
        foreach ($row->getDelegate()->getCellIterator('A') as $cell) {
            if ($cell->isFormula()) {
                try {
                    $values[$index] = $cell->getCalculatedValue();
                } catch (\Throwable) {
                    // No saved result either: keep the formula text so importRow() reports the row.
                    $values[$index] = $cell->getOldCalculatedValue() ?? $cell->getValue();
                }
            }
            $index++;
        }

        $this->importRow($values, $row->getIndex());
    }

    /** @param array<int, mixed> $data */
    public function importRow(array $data, int $rowIndex): void
    {
        // Formula text that was never turned into a value (no saved result, or a CSV cell
        // starting with "=") is never stored. A name like that means the row can't be read.
        $isFormula = fn ($value) => is_string($value) && str_starts_with(ltrim($value), '=');
        if ($isFormula($data[1] ?? null)) {
            $this->counts['skipped']++;
            $this->rowResults[] = ['row' => $rowIndex, 'name' => 'Unreadable formula in the Name column', 'status' => 'skipped', 'registration_id' => null];

            return;
        }
        $data = array_map(fn ($value) => $isFormula($value) ? null : $value, $data);

        // A first row of headings ("Name", "Phone", "Country"...) says where each field is,
        // whatever order the columns are in. Without one, the fixed order applies.
        if ($rowIndex === 1 && ($columns = self::headingColumns($data))) {
            // Name in column B is the fixed layout: keep reading it that way (column A stays the
            // row's ID, so re-importing an older list still finds the same people), plus Country.
            $this->columns = $columns['name'] === 1
                ? array_slice(array_flip(self::FIELDS), 0, 7) + array_intersect_key($columns, ['country' => true])
                : $columns;

            return;
        }
        $data = $this->columns === null ? $data : array_map(fn ($field) => isset($this->columns[$field]) ? ($data[$this->columns[$field]] ?? null) : null, self::FIELDS);

        // Skip empty rows (checking index 1 for Name), and the header row specifically
        // (only on row 1, so a real attendee named e.g. "Staff" is never mistaken for one).
        $headerLabels = ['name', 'staff', 'full name', 'participant', 'participant name'];
        if (empty($data[1]) || ($rowIndex === 1 && in_array(strtolower(trim($data[1])), $headerLabels, true))) {
            return;
        }

        $value = fn (int $index) => trim((string) ($data[$index] ?? '')) !== '' ? trim((string) $data[$index]) : null;
        $id = trim((string) $data[0]);
        $name = trim((string) $data[1]);
        $gender = $value(2);
        $roomGroup = $value(3);
        $category = $value(4) ?? 'Member';

        $rawPhone = $value(5);
        // Anything that isn't an email address (a country, "N/A"...) is ignored: otherwise every
        // row with the same text would be taken for one person sharing an email.
        $rawEmail = filter_var($value(6), FILTER_VALIDATE_EMAIL) ? $value(6) : null;
        $country = $value(7);
        $country = $country === null ? null : (collect(PhoneNumberService::countryNames())->first(fn ($known) => strcasecmp($known, $country) === 0) ?? $country);
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
            'country' => $country,
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
                $this->rowResults[] = ['row' => $rowIndex, 'name' => $name, 'status' => 'skipped', 'registration_id' => null];

                return;
            }
            $details['force_new'] = $decision !== 'update';
        }

        [$participant, $registration] = $service->register($this->event, $details, 'import');
        $this->counts[$participant->wasRecentlyCreated ? 'added' : 'updated']++;
        if ($participant->wasRecentlyCreated) {
            $this->newParticipantIds[] = $participant->id;
        }
        $this->rowResults[] = ['row' => $rowIndex, 'name' => $participant->name, 'registration_id' => $registration->id, 'status' => match (true) {
            $participant->wasRecentlyCreated => 'new_person',
            $registration->wasRecentlyCreated => 'newly_registered',
            $registration->wasChanged('status') => 'reconfirmed',
            default => 'already_registered',
        }];

        if ($registration->wasRecentlyCreated) {
            $this->newRegistrationIds[] = $registration->id;

            if ($this->needsRoom && $this->event->accommodation_enabled && ! $this->trial) {
                $registration->update(['accommodation_required' => true]);
                app(RegistrationLifecycleService::class)->allocateAccommodation($registration);
            }
        }
    }

    /**
     * Where each field is, if this row is a row of headings: it must name the Name column and at
     * least one other, so a first person's details are never taken for headings.
     *
     * @param  array<int, mixed>  $row
     * @return array<string, int>|null
     */
    public static function headingColumns(array $row): ?array
    {
        $columns = [];
        foreach ($row as $index => $cell) {
            $heading = preg_replace('/[^a-z]/', '', strtolower((string) $cell));
            $field = match (true) {
                $heading === '' => null,
                in_array($heading, ['id', 'no', 'sn', 'sno', 'serial', 'serialno', 'number', 'memberid', 'staffid'], true) => 'id',
                in_array($heading, ['name', 'names', 'fullname', 'participant', 'participantname', 'staff', 'staffname'], true) => 'name',
                in_array($heading, ['gender', 'sex'], true) => 'gender',
                str_contains($heading, 'roomgroup') || str_contains($heading, 'area') || $heading === 'group' => 'area',
                in_array($heading, ['category', 'role', 'type'], true) => 'category',
                str_contains($heading, 'phone') || str_contains($heading, 'mobile') || str_contains($heading, 'contact') || str_contains($heading, 'tel') => 'phone',
                str_contains($heading, 'email') => 'email',
                in_array($heading, ['country', 'nationality'], true) => 'country',
                default => null,
            };
            if ($field !== null && ! isset($columns[$field])) {
                $columns[$field] = $index;
            }
        }

        return isset($columns['name']) && count($columns) >= 2 ? $columns : null;
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
