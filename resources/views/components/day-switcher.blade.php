@props(['event'])

@if($event->has_arrival_session || $event->totalDays() > 1)
    <form method="POST" action="{{ route('events.update-day', $event) }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
        @csrf
        @method('PATCH')
        <label for="active-session" class="text-[10px] font-black uppercase tracking-widest text-white/70">Active check-in</label>
        <select id="active-session" name="day" onchange="this.form.submit()" class="rounded-xl border-0 bg-white px-4 py-2 text-xs font-black text-blue-800 focus:ring-2 focus:ring-blue-300">
            @for($day = 1; $day <= $event->totalDays(); $day++)
                <option value="{{ $day }}" @selected($event->activeAttendanceSession() === $day)>Day {{ $day }}</option>
            @endfor
        </select>
    </form>
@endif
