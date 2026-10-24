{{-- "Tell people their rooms": write a custom message (with {room} etc.) or send the standard one.
     $dark switches the colours for the room board. --}}
@php
    $dark = $dark ?? false;
    $canMessage = auth()->user()->can('manageMessages', $event);
    $item = $dark ? 'hover:bg-white/10' : 'hover:bg-slate-50';
    $muted = $dark ? 'text-slate-400' : 'text-slate-500';
@endphp
<div x-data="{ open: false }" @keydown.escape.window="open = false" class="relative">
    <button type="button" @click="open = !open" :aria-expanded="open.toString()"
            class="{{ $dark ? 'bg-emerald-400 text-slate-900 hover:bg-emerald-300' : 'bg-indigo-600 text-white hover:bg-indigo-700' }} rounded-xl px-4 py-2 text-sm font-black shadow-lg transition">
        Tell people their rooms
    </button>
    <div x-cloak x-show="open" @click.outside="open = false" x-transition
         class="{{ $dark ? 'border-white/10 bg-[#141a3a] text-white' : 'border-slate-200 bg-white text-slate-900' }} absolute right-0 z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-2xl border p-2 text-left shadow-2xl">
        @if($canMessage)
            <p class="px-3 pb-1 pt-2 text-[11px] font-black uppercase tracking-wider {{ $muted }}">Write your own message</p>
            <a href="{{ route('events.messages.create', ['event' => $event, 'rooms' => 'new']) }}" class="block rounded-xl px-3 py-2.5 {{ $item }}">
                <span class="block text-sm font-bold">To people not told yet</span>
                <span class="block text-xs {{ $muted }}">Opens Messages with them selected and a room message you can change. {room}, {building} and {floor} fill in each person's own room.</span>
            </a>
            <a href="{{ route('events.messages.create', ['event' => $event, 'rooms' => 'all']) }}" class="block rounded-xl px-3 py-2.5 {{ $item }}">
                <span class="block text-sm font-bold">To everyone with a room</span>
                <span class="block text-xs {{ $muted }}">For a reminder or a change that affects everyone.</span>
            </a>
        @endif
        <p class="px-3 pb-1 pt-3 text-[11px] font-black uppercase tracking-wider {{ $muted }}">Or send the standard message</p>
        @if($event->accommodation_published)
            <form method="POST" action="{{ route('events.accommodation.notify', $event) }}" onsubmit="return confirm('Send the standard room email/SMS to everyone with a room who has not been told yet?')">
                @csrf
                <button class="block w-full rounded-xl px-3 py-2.5 text-left {{ $item }}">
                    <span class="block text-sm font-bold">Send standard room message</span>
                    <span class="block text-xs {{ $muted }}">“Your room for {{ $event->title }}” with their room and a link to its details, to people not told yet.</span>
                </button>
            </form>
        @else
            <p class="px-3 py-2 text-xs {{ $muted }}">Tick <strong>Show rooms to attendees</strong> on the <a href="{{ route('events.accommodation.index', $event) }}" class="font-bold underline">Accommodation</a> page and Save to use the standard message.</p>
        @endif
        <p class="px-3 pb-2 pt-2 text-[11px] {{ $muted }}">Moving someone to another room marks them as not told, so you can message just them.</p>
    </div>
</div>
