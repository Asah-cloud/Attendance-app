<?php

namespace App\Http\Controllers;

use App\Models\AuditApproval;
use App\Models\Event;
use App\Models\User;
use App\Services\AuditApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuditAccessController extends Controller
{
    public function index(Event $event)
    {
        $this->authorize('viewMeals', $event);
        $staff = $event->staff()->where('company_id', $event->company_id)->role(['audit_head', 'audit_staff'])->get();
        $approvals = AuditApproval::where('event_id', $event->id)
            ->when(! request()->user()->can('manageMeals', $event), fn ($q) => $q->where('user_id', request()->user()->id))
            ->with(['staffMember', 'approver'])->latest()->paginate(20);

        return response()->view('audit.access', compact('event', 'staff', 'approvals'))->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, Event $event)
    {
        $this->authorize('manageMeals', $event);
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'scope' => ['required', Rule::in(['override', 'reverse', 'attendance_summary'])],
            'meal_id' => ['nullable', 'integer', 'required_unless:scope,attendance_summary'],
            'registration_code' => ['nullable', 'string', 'required_unless:scope,attendance_summary'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $target = User::findOrFail($data['user_id']);
        abort_unless($target->isAudit() && $target->company_id === $event->company_id
            && $target->events()->whereKey($event->id)->exists() && $target->id !== $request->user()->id, 403);
        $mealId = $targetId = null;
        if ($data['scope'] === 'attendance_summary') {
            $this->authorize('update', $event);
        } else {
            $meal = $event->mealDistributions()->findOrFail($data['meal_id']);
            $registration = $event->registrations()->where('registration_code', $data['registration_code'])->firstOrFail();
            $mealId = $meal->id;
            $targetId = $registration->id;
            if ($data['scope'] === 'reverse') {
                $targetId = $meal->collections()->where('event_registration_id', $registration->id)->firstOrFail()->id;
            }
        }
        $code = strtoupper(Str::random(12));
        AuditApproval::create([
            'event_id' => $event->id, 'user_id' => $target->id, 'approved_by' => $request->user()->id,
            'scope' => $data['scope'], 'meal_id' => $mealId, 'target_id' => $targetId,
            'reason' => $data['reason'], 'code_hash' => hash('sha256', $code), 'expires_at' => now()->addMinutes(10),
        ]);

        return back()->with('approval_code', $code)->with('success', 'One-time code created for '.$target->name.'. Share it directly; it expires in 10 minutes.');
    }

    public function summary(Request $request, Event $event, AuditApprovalService $approvals)
    {
        $this->authorize('viewMeals', $event);
        abort_unless($request->user()->isAudit(), 403);
        DB::transaction(fn () => $approvals->consume($request, $event, 'attendance_summary'));
        $registrations = $event->registrations()->where('status', 'confirmed')->with('participant')->get();
        $arrived = $event->attendances()->pluck('participant_id')->unique();

        return response()->view('audit.attendance-summary', compact('event', 'registrations', 'arrived'))
            ->header('Cache-Control', 'no-store, private');
    }
}
