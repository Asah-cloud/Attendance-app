<?php

namespace App\Imports;

use App\Models\EventRegistration;
use App\Models\Participant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Row;

class SupportStaffImport implements OnEachRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $assigned = 0;

    /** @param array<int> $eventIds */
    public function __construct(private readonly int $companyId, private readonly array $eventIds) {}

    public function onRow(Row $row): void
    {
        $values = $row->toArray();
        $name = trim((string) ($values[0] ?? ''));
        $department = trim((string) ($values[1] ?? ''));
        $category = trim((string) ($values[2] ?? '')) ?: 'Staff';
        $gender = trim((string) ($values[3] ?? '')) ?: null;

        if ($name === '' || ($row->getIndex() === 1 && in_array(Str::lower($name), ['name', 'staff name', 'full name'], true))) {
            return;
        }

        DB::transaction(function () use ($name, $department, $category, $gender): void {
            $participant = Participant::query()
                ->where('company_id', $this->companyId)
                ->where('is_support_staff', true)
                ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
                ->whereRaw("LOWER(COALESCE(department, '')) = ?", [Str::lower($department)])
                ->first();

            if (! $participant) {
                $participant = Participant::create([
                    'company_id' => $this->companyId,
                    'name' => $name,
                    'department' => $department ?: null,
                    'category' => $category,
                    'gender' => $gender,
                    'is_support_staff' => true,
                    'staff_qr_token' => Str::random(48),
                ]);
                $participant->update([
                    'staff_code' => 'STF-'.str_pad((string) $participant->id, 6, '0', STR_PAD_LEFT),
                    'member_id' => 'staff:'.$participant->id,
                ]);
                $this->created++;
            } else {
                $participant->update(['name' => $name, 'department' => $department ?: null, 'category' => $category, 'gender' => $gender ?? $participant->gender]);
                $this->updated++;
            }

            foreach ($this->eventIds as $eventId) {
                $registration = EventRegistration::query()->firstOrCreate(
                    ['event_id' => $eventId, 'participant_id' => $participant->id],
                    ['status' => EventRegistration::STATUS_CONFIRMED, 'approved_at' => now(), 'source' => 'support_staff']
                );
                if (! $registration->wasRecentlyCreated && $registration->status !== EventRegistration::STATUS_CONFIRMED) {
                    $registration->update(['status' => EventRegistration::STATUS_CONFIRMED, 'approved_at' => now(), 'cancelled_at' => null]);
                }
                $this->assigned += $registration->wasRecentlyCreated ? 1 : 0;
            }
        });
    }
}
