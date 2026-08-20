<x-app-layout>
    <x-slot name="header">Event Attendees</x-slot>
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div><h1 class="text-2xl font-black text-slate-900">Registrations</h1><p class="text-sm text-slate-500">Review and manage this event’s attendees.</p></div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('events.registrations.export', $event) }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-black uppercase tracking-wider text-white">Export CSV</a>
                <a href="{{ route('events.registration-form.edit', $event) }}" class="rounded-xl bg-amber-300 px-4 py-2 text-xs font-black uppercase tracking-wider text-amber-950">Form & sharing</a>
                <a href="{{ route('events.confirmations.index', $event) }}" class="rounded-xl bg-violet-300 px-4 py-2 text-xs font-black uppercase tracking-wider text-violet-950">Hard-copy confirmations</a>
                <a href="{{ route('events.attendance', $event) }}" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-black uppercase tracking-wider text-white">Attendance</a>
            </div>
        </div>
        @if($errors->any())<div class="mb-5 rounded-xl bg-red-50 p-4 text-sm font-bold text-red-700">{{ $errors->first() }}</div>@endif
        @if(session('success'))<div class="mb-5 rounded-xl bg-emerald-50 p-4 text-sm font-bold text-emerald-700">{{ session('success') }}</div>@endif
        <details class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @if($errors->hasAny(['name','email','phone','category'])) open @endif>
            <summary class="cursor-pointer font-black text-slate-900">Manually register an attendee</summary>
            <form method="POST" action="{{ route('events.registrations.store', $event) }}" class="mt-5 grid gap-4 md:grid-cols-2">@csrf
                <input name="name" value="{{ old('name') }}" required placeholder="Full name" class="rounded-xl border-slate-200">
                <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" class="rounded-xl border-slate-200">
                <input name="phone" value="{{ old('phone') }}" placeholder="Phone (required without email)" class="rounded-xl border-slate-200">
                <select name="gender" required class="rounded-xl border-slate-200">
                    <option value="">Select gender</option>
                    @foreach($genderField->options ?? ['Male', 'Female'] as $option)<option value="{{ $option }}" @selected(old('gender') === $option)>{{ $option }}</option>@endforeach
                </select>
                @if($categoryField && $categoryField->field_type === 'select')
                    <select name="category" required class="rounded-xl border-slate-200 md:col-span-2">
                        <option value="">Select attendee type</option>
                        @foreach($categoryField->options ?? [] as $option)<option value="{{ $option }}" @selected(old('category') === $option)>{{ $option }}</option>@endforeach
                    </select>
                @else
                    <input name="category" value="{{ old('category', 'Member') }}" required placeholder="Attendee type" class="rounded-xl border-slate-200 md:col-span-2">
                @endif
                <button class="rounded-xl bg-blue-900 px-5 py-3 text-xs font-black uppercase tracking-wider text-white md:col-span-2">Register attendee</button>
            </form>
        </details>
        <form class="mb-5"><select name="status" onchange="this.form.submit()" class="rounded-xl border-slate-200 text-sm font-bold"><option value="">All statuses</option>@foreach(['confirmed','pending','waitlisted','cancelled','rejected'] as $option)<option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>@endforeach</select></form>
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-4">Attendee</th><th class="px-5 py-4">Contact</th><th class="px-5 py-4">Type</th><th class="px-5 py-4">Gender</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Registered</th><th class="px-5 py-4">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100">@forelse($registrations as $registration)
                <tr><td class="px-5 py-4 font-bold text-slate-900">{{ $registration->participant->name }}</td><td class="px-5 py-4 text-slate-600">{{ $registration->participant->email ?: '—' }}<br>{{ $registration->participant->phone ?: '' }}</td><td class="px-5 py-4">{{ $registration->participant->category }}</td><td class="px-5 py-4">{{ $registration->participant->gender ?: '—' }}</td><td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{{ ucfirst($registration->status) }}</span></td><td class="px-5 py-4 text-slate-500">{{ $registration->registered_at?->format('M j, Y g:i A') }}</td>
                    <td class="px-5 py-4"><div class="flex min-w-56 flex-wrap gap-2">
                        <button type="button" onclick="document.getElementById('edit-participant-{{ $registration->id }}').classList.toggle('hidden')" class="text-xs font-bold text-slate-700">Edit</button>
                        @if(in_array($registration->status, ['pending','waitlisted','rejected','cancelled']))<form method="POST" action="{{ route('events.registrations.approve', [$event, $registration]) }}">@csrf @method('PATCH')<button class="text-xs font-bold text-emerald-700">Approve</button></form>@endif
                        @if(!in_array($registration->status, ['rejected','cancelled']))<form method="POST" action="{{ route('events.registrations.reject', [$event, $registration]) }}">@csrf @method('PATCH')<button class="text-xs font-bold text-amber-700">Reject</button></form>@endif
                        @if($registration->status !== 'cancelled')<form method="POST" action="{{ route('events.registrations.cancel', [$event, $registration]) }}">@csrf @method('PATCH')<button class="text-xs font-bold text-red-700">Cancel</button></form>@endif
                        @if($registration->participant->email)<form method="POST" action="{{ route('events.registrations.resend', [$event, $registration]) }}">@csrf<button class="text-xs font-bold text-blue-700">Resend</button></form>@endif
                    </div></td></tr>
                <tr id="edit-participant-{{ $registration->id }}" class="hidden bg-slate-50">
                    <td colspan="7" class="px-5 py-5">
                        <form method="POST" action="{{ route('events.registrations.participant.update', [$event, $registration]) }}" class="grid gap-3 md:grid-cols-2 lg:grid-cols-6">
                            @csrf @method('PATCH')
                            <input name="name" value="{{ old('name', $registration->participant->name) }}" required placeholder="Full name" class="rounded-xl border-slate-200 text-sm">
                            <input type="email" name="email" value="{{ old('email', $registration->participant->email) }}" placeholder="Email" class="rounded-xl border-slate-200 text-sm">
                            <input name="phone" value="{{ old('phone', $registration->participant->phone) }}" placeholder="Phone" class="rounded-xl border-slate-200 text-sm">
                            <select name="gender" required class="rounded-xl border-slate-200 text-sm">
                                <option value="">Select gender</option>
                                @foreach($genderField->options ?? ['Male', 'Female'] as $option)<option value="{{ $option }}" @selected(old('gender', $registration->participant->gender) === $option)>{{ $option }}</option>@endforeach
                            </select>
                            @if($categoryField && $categoryField->field_type === 'select')
                                <select name="category" required class="rounded-xl border-slate-200 text-sm">
                                    <option value="">Select attendee type</option>
                                    @foreach($categoryField->options ?? [] as $option)<option value="{{ $option }}" @selected(old('category', $registration->participant->category) === $option)>{{ $option }}</option>@endforeach
                                </select>
                            @else
                                <input name="category" value="{{ old('category', $registration->participant->category) }}" required placeholder="Attendee type" class="rounded-xl border-slate-200 text-sm">
                            @endif
                            <input name="member_id" value="{{ old('member_id', $registration->participant->member_id) }}" placeholder="Member ID" class="rounded-xl border-slate-200 text-sm">
                            <button class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-black uppercase tracking-wider text-white lg:col-span-6 lg:w-fit">Save changes</button>
                        </form>
                    </td>
                </tr>
            @empty<tr><td colspan="7" class="px-5 py-12 text-center text-slate-500">No registrations found.</td></tr>@endforelse</tbody>
        </table></div></div><div class="mt-5">{{ $registrations->links() }}</div>
    </div>
</x-app-layout>
