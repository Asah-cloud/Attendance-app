{{-- One person on the room board. Checked-in people can't be dragged. --}}
@php
    $person = $registration->participant;
    $isFemale = str_starts_with(strtolower((string) $person->gender), 'f');
    $locked = $registration->roomAssignment?->status === 'checked_in';
@endphp
<li data-registration-id="{{ $registration->id }}" data-name="{{ $person->name }}" data-search="{{ strtolower($person->name.' '.$person->room_group.' '.$person->phone) }}"
    class="{{ $locked ? 'is-locked cursor-not-allowed' : 'cursor-grab active:cursor-grabbing' }} flex items-center justify-between gap-2 rounded-xl border px-3 py-2 text-sm shadow-sm transition {{ $isFemale ? 'border-pink-200 bg-pink-50' : 'border-sky-200 bg-sky-50' }}"
    title="{{ $locked ? 'Checked in - can\'t be moved' : 'Drag to another room' }}">
    <span class="min-w-0">
        <span class="block truncate font-bold text-slate-800">{{ $person->name }}</span>
        <span class="block truncate text-[11px] text-slate-500">{{ collect([$person->gender, $person->room_group])->filter()->join(' · ') ?: '—' }}</span>
    </span>
    @if($locked)<span class="shrink-0 text-xs font-black text-emerald-600">✓ In</span>@endif
</li>
