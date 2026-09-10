<x-app-layout>
    <x-slot name="header">Scoped approvals</x-slot>
    <h1 class="text-2xl font-black">{{ $event->title }} — approval codes</h1>
    <p class="my-4 text-slate-600">Codes apply to one staff member and one action. Billing, accommodation, company settings and staff management remain restricted.</p>
    @if($errors->any())<p class="my-4 text-red-700">{{ $errors->first() }}</p>@endif
    @if(session('approval_code'))<div class="my-5 rounded-xl bg-amber-50 p-5">One-time code: <strong class="font-mono text-xl">{{ session('approval_code') }}</strong></div>@endif
    @can('manageMeals', $event)
    <form method="POST" action="{{ route('audit.approvals.store', $event) }}" class="my-6 grid max-w-xl gap-4 rounded-2xl border bg-white p-6">
        @csrf
        <h2 class="text-lg font-bold">Approve a restricted action</h2>
        <label>Staff member<select name="user_id" required class="mt-1 w-full rounded-lg"><option value="">Choose staff</option>@foreach($staff as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select></label>
        <label>Action<select name="scope" required class="mt-1 w-full rounded-lg"><option value="override">Extra portion / serving override</option><option value="reverse">Reverse one portion</option>@can('update', $event)<option value="attendance_summary">View attendance summary once</option>@endcan</select></label>
        <label>Meal (food actions only)<select name="meal_id" class="mt-1 w-full rounded-lg"><option value="">Choose meal</option>@foreach($event->mealDistributions as $meal)<option value="{{ $meal->id }}">{{ $meal->name }}</option>@endforeach</select></label>
        <label>Participant registration code (food actions only)<input name="registration_code" class="mt-1 w-full rounded-lg"></label>
        <label>Reason<textarea name="reason" required maxlength="500" class="mt-1 w-full rounded-lg"></textarea></label>
        <button class="rounded-xl bg-blue-600 p-3 font-bold text-white">Generate one-time code</button>
    </form>
    @endcan
    @if(auth()->user()->isAudit())
    <form method="POST" action="{{ route('audit.attendance-summary', $event) }}" class="my-6 grid max-w-xl gap-4 rounded-2xl border bg-white p-6">
        @csrf
        <h2 class="text-lg font-bold">Restricted section: attendance summary</h2>
        <p>Ask your Manager or Admin for a code to view participant names and check-in status once. This does not grant editing access.</p>
        <label>Approval code<input name="approval_code" required autocomplete="off" class="mt-1 w-full rounded-lg"></label>
        <button class="rounded-xl bg-slate-900 p-3 font-bold text-white">View approved section</button>
    </form>
    @endif
    <h2 class="my-5 text-xl font-bold">Approval history</h2>
    <div class="mb-5 overflow-x-auto"><table class="w-full bg-white text-left text-sm"><thead><tr><th class="p-3">Staff</th><th class="p-3">Approver</th><th class="p-3">Action</th><th class="p-3">Reason</th><th class="p-3">Created</th><th class="p-3">Used / expiry</th></tr></thead><tbody>
    @forelse($approvals as $approval)<tr><td class="p-3">{{ $approval->staffMember?->name }}</td><td class="p-3">{{ $approval->approver?->name }}</td><td class="p-3">{{ str($approval->scope)->replace('_', ' ')->title() }}</td><td class="p-3">{{ $approval->reason }}</td><td class="p-3">{{ $approval->created_at->format('M j, g:i A') }}</td><td class="p-3">{{ $approval->used_at ? 'Used '.$approval->used_at->format('M j, g:i A') : ($approval->expires_at->isPast() ? 'Expired' : 'Expires '.$approval->expires_at->format('g:i A')) }}</td></tr>@empty<tr><td colspan="6" class="p-4">No approvals yet.</td></tr>@endforelse
    </tbody></table></div>
    {{ $approvals->links() }}
    <a href="{{ route('events.meals.index', $event) }}" class="font-bold text-blue-700">Back to food operations</a>
</x-app-layout>
