{{-- A draft or scheduled message: not sent yet, so there is no delivery to show, only what will go out. --}}
<div id="message-detail" x-init="$el.scrollIntoView({ block: 'nearest' })" class="border-t border-blue-100 bg-slate-50/50 pb-5">
    <div class="flex flex-wrap items-start justify-between gap-3 px-5 pt-4">
        <div>
            @if($selected->isScheduled())
                <p class="text-sm font-black text-blue-700">Scheduled for {{ $selected->scheduled_at->timezone(config('app.timezone'))->format('M j, Y \a\t H:i') }} <span class="text-xs font-bold text-blue-400">({{ config('app.timezone') }})</span></p>
                <p class="mt-0.5 text-xs text-slate-400">It will go out within a minute of that time.</p>
            @else
                <p class="text-sm font-black text-slate-700">Draft</p>
                <p class="mt-0.5 text-xs text-slate-400">Saved {{ $selected->updated_at->diffForHumans() }} by {{ $selected->creator?->name ?? 'a manager' }} · not sent yet</p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('events.messages.edit', [$event, $selected]) }}" class="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-black uppercase tracking-wide text-slate-700 hover:bg-slate-50">{{ $selected->isScheduled() ? 'Edit' : 'Continue editing' }}</a>
            <form method="POST" action="{{ route('events.messages.send-now', [$event, $selected]) }}" onsubmit="return confirm('Send this message now to {{ $selected->recipient_count }} {{ \Illuminate\Support\Str::plural('person', $selected->recipient_count) }}?')">@csrf<button class="rounded-xl bg-blue-900 px-3.5 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-blue-800">Send now</button></form>
            @if($selected->isScheduled())
                <form method="POST" action="{{ route('events.messages.unschedule', [$event, $selected]) }}">@csrf<button class="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-black uppercase tracking-wide text-slate-700 hover:bg-slate-50">Cancel schedule</button></form>
            @endif
            <form method="POST" action="{{ route('events.messages.destroy', [$event, $selected]) }}" onsubmit="return confirm('Delete this {{ $selected->isScheduled() ? 'scheduled message' : 'draft' }}? This cannot be undone.')">@csrf @method('DELETE')<button class="rounded-xl border border-rose-200 bg-white px-3.5 py-2 text-xs font-black uppercase tracking-wide text-rose-600 hover:bg-rose-50">Delete</button></form>
        </div>
    </div>

    @include('events.messages._content')

    <div class="px-5 pt-4">
        <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">Will go to <span class="ml-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs normal-case tracking-normal text-blue-700">{{ $planned['total'] }} {{ \Illuminate\Support\Str::plural('person', $planned['total']) }}</span> · {{ str_replace('_', ' ', $selected->mode) }}</p>
        @if($planned['total'] > 0)
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach($planned['names'] as $name)<span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">{{ $name }}</span>@endforeach
                @if($planned['total'] > count($planned['names']))<span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500">+ {{ $planned['total'] - count($planned['names']) }} more</span>@endif
            </div>
        @else
            <p class="mt-2 text-xs text-slate-400">No recipients chosen yet. Continue editing to pick who this goes to.</p>
        @endif
    </div>
</div>
