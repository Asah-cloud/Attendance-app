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
        return $this->clearWithoutAttendance($company, fn ($query) => $query);
    }

    /**
     * Delete a company's support staff who have never been marked present anywhere,
     * leaving ordinary attendees untouched.
     *
     * @return array{removed: int, kept: int}
     */
    public function clearSupportStaffWithoutAttendance(Company $company): array
    {
        return $this->clearWithoutAttendance($company, fn ($query) => $query->where('is_support_staff', true));
    }

    /** @return array{removed: int, kept: int} */
    private function clearWithoutAttendance(Company $company, \Closure $scope): array
    {
        $base = fn () => $scope(Participant::where('company_id', $company->id));
        $deletable = fn () => $scope(Participant::where('company_id', $company->id))->doesntHave('attendances');

        $total = $base()->count();
        $removed = $deletable()->count();
        $deletable()->delete();

        app(ApplicationCache::class)->invalidateCompany($company->id);

        return ['removed' => $removed, 'kept' => $total - $removed];
    }
}
