<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Participant;

class ParticipantRosterService
{
    /**
     * Delete a company's participants who have never been marked present anywhere,
     * mirroring the "delete all attendees" event tool's protection of check-in history.
     *
     * @return array{removed: int, kept: int}
     */
    public function clearRosterWithoutAttendance(Company $company): array
    {
        $deletable = fn () => Participant::where('company_id', $company->id)->doesntHave('attendances');

        $total = Participant::where('company_id', $company->id)->count();
        $removed = $deletable()->count();
        $deletable()->delete();

        app(ApplicationCache::class)->invalidateCompany($company->id);

        return ['removed' => $removed, 'kept' => $total - $removed];
    }
}
