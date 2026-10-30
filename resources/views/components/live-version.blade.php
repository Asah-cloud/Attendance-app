{{-- What an open page watches to redraw itself when people, registrations or rooms change
     (resources/js/live-updates.js): the event in the address, else the user's company. --}}
@auth
    @php
        $liveEvent = request()->route('event');
        $liveEvent = $liveEvent instanceof \App\Models\Event ? $liveEvent : null;
        $live = app(\App\Services\LiveVersion::class);
    @endphp
    @if($liveEvent || auth()->user()->company_id)
        <meta name="live-version" content="{{ route('live.version', $liveEvent ? ['event' => $liveEvent->id] : []) }}" data-version="{{ $liveEvent ? $live->event($liveEvent->id) : $live->company((int) auth()->user()->company_id) }}">
    @endif
@endauth
