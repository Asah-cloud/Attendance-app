{{-- One person on the room board. Checked-in people can't be dragged. In folded rooms and compact
     view only the initials show (the name stays in the tooltip). --}}
@php
    $person = $registration->participant;
    $isFemale = str_starts_with(strtolower((string) $person->gender), 'f');
    $locked = $registration->roomAssignment?->status === 'checked_in';
    $initials = collect(preg_split('/\s+/', trim((string) $person->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('');
@endphp
<li data-registration-id="{{ $registration->id }}" data-name="{{ $person->name }}" data-search="{{ strtolower($person->name.' '.$person->room_group.' '.$person->phone) }}"
    class="board-chip {{ $locked ? 'is-locked cursor-not-allowed' : 'cursor-grab active:cursor-grabbing' }} flex items-center gap-3 rounded-2xl border border-white/10 bg-white/90 px-3 py-2 text-sm shadow-lg shadow-black/10 transition hover:bg-white"
    title="{{ $person->name }}{{ $person->room_group ? ' · '.$person->room_group : '' }} — {{ $locked ? 'checked in, can\'t be moved' : 'drag to another room' }}">
    <span class="chip-avatar grid h-9 w-9 shrink-0 place-items-center rounded-xl text-xs font-black text-white {{ $isFemale ? 'bg-gradient-to-br from-pink-400 to-rose-500' : 'bg-gradient-to-br from-sky-400 to-blue-600' }} {{ $locked ? 'ring-2 ring-emerald-400' : '' }}">{{ $initials ?: '?' }}</span>
    <span class="chip-text min-w-0 flex-1">
        <span class="block truncate font-bold text-slate-900">{{ $person->name }}</span>
        <span class="block truncate text-[11px] text-slate-500">{{ collect([$person->gender, $person->room_group])->filter()->join(' · ') ?: '—' }}</span>
    </span>
    @if($locked)<span class="chip-badge shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black text-emerald-700">✓ In</span>@endif
</li>
