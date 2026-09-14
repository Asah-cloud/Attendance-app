<div>
    @if($flash)
        <div class="mb-4 rounded-2xl p-4 text-sm font-bold {{ $flashType === 'success' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800' }}" role="status">{{ $flash }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search name, email or room…" class="w-full rounded-xl border-slate-300 text-sm sm:w-72">
        <label class="flex items-center gap-2 text-xs font-bold text-slate-600"><input type="checkbox" wire:model.live="unassignedOnly"> Unassigned only</label>
        <label class="flex items-center gap-2 text-xs font-bold text-slate-600"><input type="checkbox" wire:model.live="needsOnly"> Needs room only</label>
        <details class="ml-auto">
            <summary class="cursor-pointer list-none rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-black text-indigo-700">Everyone needs a room</summary>
            <form method="POST" action="{{ route('events.accommodation.mark-all-required', $event) }}" class="mt-2 flex flex-wrap items-center gap-2 rounded-xl bg-indigo-50 p-3">@csrf
                <span class="text-xs text-slate-600">Ticks "Needs room" for every confirmed attendee. Type <strong>{{ $event->title }}</strong> to confirm:</span>
                <input name="confirm_title" autocomplete="off" placeholder="{{ $event->title }}" class="rounded-lg border-indigo-200 text-xs">
                <button class="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-black text-white">Apply</button>
            </form>
        </details>
    </div>
    <p class="mt-2 text-xs text-slate-400">Rooms set to <strong>reserved</strong> are skipped by "Assign rooms now" — pick them here to hand-place special guests, then tick <strong>Lock</strong>. "Closed" rooms can't be assigned at all. A room marked <strong>differs</strong> doesn't match this attendee's gender, category, or step-free needs — you can still assign it as a deliberate override. Untick <strong>Email now</strong> to hold off notifying an attendee.</p>

    <div class="mt-4 overflow-x-auto" wire:loading.class="opacity-50">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr><th class="px-5 py-3">Attendee</th><th class="px-5 py-3">Room needs</th><th class="px-5 py-3">Assignment</th><th class="px-5 py-3">Assign a room</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($registrations as $registration)
                    @php $currentRoomId = $registration->roomAssignment?->accommodation_room_id; @endphp
                    <tr wire:key="reg-{{ $registration->id }}">
                        <td class="px-5 py-4">
                            <strong>{{ $registration->participant->name }}</strong>
                            <div class="text-xs text-slate-500">{{ $registration->participant->gender ?: 'Gender unspecified' }} · {{ $registration->participant->category ?: 'No category' }}{{ $registration->participant->room_group ? ' · Group: '.$registration->participant->room_group : '' }}</div>
                            <a href="{{ route('events.accommodation.room-preview', [$event, $registration]) }}" target="_blank" class="mt-1 inline-block text-xs font-bold text-indigo-600 underline">Preview picker page</a>
                        </td>
                        <td class="px-5 py-4">
                            <form method="POST" action="{{ route('events.accommodation.requirements.update', [$event, $registration]) }}" class="space-y-2">@csrf @method('PATCH')
                                <label class="flex gap-2"><input type="checkbox" name="accommodation_required" value="1" @checked($registration->accommodation_required)> Needs room</label>
                                <label class="flex gap-2"><input type="checkbox" name="accessibility_required" value="1" @checked($registration->accessibility_required)> Needs a step-free room</label>
                                <input name="room_group" value="{{ $registration->participant->room_group }}" placeholder="Room with (group/area)" title="People sharing this value are seated in the same room where possible." class="w-48 rounded-lg border-slate-300 p-1 text-xs">
                                <input name="accommodation_notes" value="{{ $registration->accommodation_notes }}" placeholder="Notes" class="w-48 rounded-lg border-slate-300 p-1 text-xs">
                                <button class="mt-1 block rounded-lg bg-indigo-600 px-3 py-1 text-xs font-black text-white">Save requirement</button>
                            </form>
                        </td>
                        <td class="px-5 py-4">
                            @if($registration->roomAssignment)
                                <strong>{{ $registration->roomAssignment->room->label() }}</strong>
                                <div class="text-xs font-bold capitalize text-slate-500">{{ str_replace('_', ' ', $registration->roomAssignment->status) }} · {{ ucfirst($registration->roomAssignment->method) }}{{ $registration->roomAssignment->is_locked ? ' · Locked' : '' }}</div>
                                <div class="mt-2 flex gap-2">
                                    @if($registration->roomAssignment->status === 'assigned')
                                        <form method="POST" action="{{ route('events.accommodation.check-in', [$event, $registration]) }}">@csrf<button class="rounded-lg bg-emerald-600 px-2 py-1 text-xs font-black text-white">Check in</button></form>
                                    @elseif($registration->roomAssignment->status === 'checked_in')
                                        <form method="POST" action="{{ route('events.accommodation.check-out', [$event, $registration]) }}">@csrf<button class="rounded-lg bg-slate-700 px-2 py-1 text-xs font-black text-white">Check out</button></form>
                                    @endif
                                </div>
                            @else
                                <span class="font-bold text-amber-700">No room yet</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                <select wire:model="selectedRoom.{{ $registration->id }}" class="max-w-56 rounded-lg border-slate-300 text-xs">
                                    <option value="">Choose room</option>
                                    @foreach($roomGroups as $groupLabel => $roomsInGroup)
                                        <optgroup label="{{ $groupLabel }}">
                                            @foreach($roomsInGroup as $room)
                                                @php
                                                    $isFull = $room->active_assignments_count >= $room->capacity && $room->id !== $currentRoomId;
                                                    $mismatch = ! $allocator->matches($registration, $room);
                                                @endphp
                                                <option value="{{ $room->id }}" @selected(($selectedRoom[$registration->id] ?? $currentRoomId) === $room->id) @disabled($isFull)>
                                                    {{ $room->floor->name }} · {{ $room->name }} ({{ $room->active_assignments_count }}/{{ $room->capacity }}){{ $room->status === 'reserved' ? ' — reserved' : '' }}{{ $isFull ? ' — full' : '' }}{{ $mismatch ? ' — differs' : '' }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" wire:model="lockRoom.{{ $registration->id }}" @checked($registration->roomAssignment?->is_locked)> Lock</label>
                                <label class="flex items-center gap-1 text-xs" title="Emails their room and QR code right away. Leave unticked to send later from &quot;Email rooms to attendees&quot; instead."><input type="checkbox" wire:model="emailNow.{{ $registration->id }}" checked> Email now</label>
                                <button wire:click="assign({{ $registration->id }})" wire:loading.attr="disabled" wire:target="assign({{ $registration->id }})" class="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-black text-white disabled:opacity-50">Assign</button>
                            </div>
                            @if($registration->roomAssignment && ! in_array($registration->roomAssignment->status, ['checked_in'], true))
                                <button wire:click="removeAssignment({{ $registration->id }})" wire:confirm="Remove this room assignment?" class="mt-2 text-xs font-bold text-red-600">Remove assignment</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-8 text-center text-slate-500">No confirmed attendees match your search.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $registrations->links() }}</div>
</div>
