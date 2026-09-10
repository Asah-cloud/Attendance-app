<?php

namespace App\Services;

use App\Models\AuditApproval;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AuditApprovalService
{
    // Caller holds a database transaction: consuming the code and the action commit together.
    public function consume(Request $request, Event $event, string $scope, ?int $mealId = null, ?int $targetId = null): AuditApproval
    {
        $approval = AuditApproval::where('code_hash', hash('sha256', strtoupper(trim((string) $request->input('approval_code')))))
            ->where('event_id', $event->id)->where('user_id', $request->user()->id)
            ->where('scope', $scope)->where('meal_id', $mealId)->where('target_id', $targetId)
            ->whereNull('used_at')->where('expires_at', '>', now())->lockForUpdate()->first();
        $approver = $approval ? User::find($approval->approved_by) : null;
        if (! $approval || ! $approver || ! Gate::forUser($approver)->allows('manageMeals', $event)
            || ($scope === 'attendance_summary' && ! Gate::forUser($approver)->allows('update', $event))) {
            throw ValidationException::withMessages(['approval_code' => 'Enter a valid, unused approval code for this action. Codes expire after 10 minutes.']);
        }
        $approval->update(['used_at' => now()]);

        return $approval;
    }
}
