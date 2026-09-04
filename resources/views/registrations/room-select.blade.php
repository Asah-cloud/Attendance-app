<x-public-layout title="Choose your room" :noindex="true">
    <section class="min-h-screen bg-gradient-to-b from-slate-50 via-slate-50 to-blue-50/50 px-5 pb-20 pt-28">
        <div class="mx-auto max-w-2xl">
            @if($preview ?? false)
                <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-bold text-amber-800">Manager preview — this is what {{ $registration->participant->name }} sees. Attendees don't see this banner, and nothing here can be submitted from this view.</div>
            @endif
            <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-xl shadow-slate-200/70">
                <p class="text-xs font-black uppercase tracking-widest text-indigo-600">{{ request()->boolean('new') ? "You're registered — last step" : 'Accommodation' }}</p>
                <h1 class="mt-2 text-2xl font-black">Choose your room</h1>
                <p class="mt-2 text-sm text-slate-600">{{ $registration->participant->name }} · {{ $registration->event->title }}</p>
                @if($registration->event->accommodation_self_select_closes_at)
                    <p class="mt-1 text-xs font-bold text-slate-500">Selection closes {{ $registration->event->accommodation_self_select_closes_at->format('D j M Y, g:ia') }}. You can change your choice until then.</p>
                @elseif($preview ?? false)
                    <p class="mt-1 text-xs font-bold text-amber-700">No "attendees can pick until" deadline is set yet — attendees can't reach this page until you set one.</p>
                @endif
                @if(request()->boolean('new'))
                    <p class="mt-3 text-sm text-slate-600">Pick a room now, or <a href="{{ route('registrations.confirmation', $registration->registration_code) }}" class="font-bold text-indigo-700 underline">do it later</a> — we'll assign one automatically if you don't choose before the deadline.</p>
                @endif

                @if(session('success'))<div class="mt-4 rounded-2xl bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="mt-4 rounded-2xl bg-red-50 p-4 text-sm font-bold text-red-800">{{ session('error') }}</div>@endif
                @error('room_id')<div class="mt-4 rounded-2xl bg-red-50 p-4 text-sm font-bold text-red-800">{{ $message }}</div>@enderror

                @if($registration->roomAssignment)
                    <div class="mt-4 rounded-2xl border border-indigo-100 bg-indigo-50 p-4">
                        <p class="text-xs font-black uppercase tracking-widest text-indigo-700">Your current room</p>
                        <p class="mt-1 text-lg font-black text-indigo-950">{{ $registration->roomAssignment->room->label() }}</p>
                    </div>
                @endif

                @if($registration->accessibility_required)
                    <p class="mt-4 text-xs font-bold text-indigo-700">Showing step-free rooms only (no stairs), as you asked.</p>
                @endif

                <form @if(!($preview ?? false)) method="POST" action="{{ route('registrations.room.claim', $registration->registration_code) }}" @else onsubmit="return false" @endif class="mt-5 space-y-4">@csrf
                    @forelse($rooms as $blockName => $floors)
                        <div class="overflow-hidden rounded-2xl border border-slate-200">
                            <p class="border-b border-slate-100 bg-slate-50 px-4 py-2 text-sm font-black text-slate-800">{{ $blockName }}</p>
                            <div class="divide-y divide-slate-100">
                                @foreach($floors as $floorName => $floorRooms)
                                    <details class="group" open>
                                        <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-2 text-xs font-black uppercase tracking-wide text-slate-500">
                                            <span>{{ $floorName }}</span>
                                            <svg class="h-4 w-4 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                        </summary>
                                        <div class="px-2 pb-2">
                                            @foreach($floorRooms as $room)
                                                @php $free = $room->capacity - $room->active_assignments_count + ($registration->roomAssignment?->accommodation_room_id === $room->id ? 1 : 0); @endphp
                                                <label class="flex items-center gap-3 rounded-xl px-3 py-2 hover:bg-slate-50">
                                                    <input type="radio" name="room_id" value="{{ $room->id }}" required @checked($registration->roomAssignment?->accommodation_room_id === $room->id) @disabled($preview ?? false) class="text-indigo-600">
                                                    <span class="text-sm font-bold text-slate-800">{{ $room->name }}</span>
                                                    <span class="ml-auto text-xs text-slate-500">{{ $free }} bed{{ $free === 1 ? '' : 's' }} free@if($room->is_accessible || $room->floor->is_accessible) · step-free@endif</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="rounded-2xl bg-amber-50 p-4 text-sm font-bold text-amber-800">No rooms are open for selection right now. An organiser will assign you one.</p>
                    @endforelse

                    @if($rooms->isNotEmpty())
                        <button @disabled($preview ?? false) class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-black text-white disabled:cursor-not-allowed disabled:opacity-50">{{ ($preview ?? false) ? 'Preview only' : 'Confirm room' }}</button>
                    @endif
                </form>

                @if($preview ?? false)
                    <a href="{{ route('events.accommodation.index', $registration->event) }}" class="mt-4 block text-center text-xs font-bold text-slate-500">Back to Rooms</a>
                @else
                    <a href="{{ route('registrations.confirmation', $registration->registration_code) }}" class="mt-4 block text-center text-xs font-bold text-slate-500">Back to your registration</a>
                @endif
            </div>
        </div>
    </section>
</x-public-layout>
