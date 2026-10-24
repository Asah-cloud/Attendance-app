{{-- Advanced: always the whole event. Check-in patterns, follow-ups, comparison and data checks. --}}
@php
    ['grid' => $allDays, 'times' => $times, 'methods' => $methods, 'dropOffs' => $dropOffs, 'comparison' => $comparison, 'quality' => $quality] = $insights;
    $canMessage = auth()->user()->can('manageMessages', $event);
    $canFix = auth()->user()->can('manageWhenOpen', $event);
    $messageLink = fn ($people) => route('events.messages.create', ['event' => $event, 'participants' => $people->pluck('id')->join(',')]);
    $hourLabel = fn (int $hour) => \Carbon\Carbon::createFromTime($hour)->format('ga');
@endphp

@if($selectedDay !== 'all')
    <p class="rounded-2xl bg-blue-50 px-5 py-3 text-xs font-semibold text-blue-800">Advanced always looks at the whole event, so day-to-day patterns can be compared.</p>
@endif

@if(! $allDays['held'])
    <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">No check-ins yet. Check-in patterns and follow-ups appear here once the event starts. The data checks below are ready now.</div>
@else
    <div class="grid gap-6 lg:grid-cols-2">
        {{-- When people checked in --}}
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xs font-black uppercase tracking-widest text-slate-500">When people checked in</h2>
            <p class="mt-1 text-xs text-slate-500">Each person's first check-in of the day, by hour. Plan staff for the busiest hour.</p>
            <div class="mt-5 space-y-5">
                @foreach($times['days'] as $day)
                    <div>
                        <p class="mb-2 flex justify-between text-xs font-bold text-slate-700"><span>{{ $day['label'] }}</span>@if($day['peak'] !== null)<span class="text-slate-500">Busiest: {{ $hourLabel($day['peak']) }}–{{ $hourLabel(($day['peak'] + 1) % 24) }}</span>@endif</p>
                        <div class="flex h-24 items-end gap-1">
                            @foreach($times['hours'] as $hour)
                                @php($n = $day['hours'][$hour] ?? 0)
                                <div class="flex flex-1 flex-col items-center justify-end gap-1" title="{{ $hourLabel($hour) }}: {{ $n }}">
                                    <span class="text-[9px] font-bold text-slate-500">{{ $n ?: '' }}</span>
                                    <span class="w-full rounded-t {{ $hour === $day['peak'] ? 'bg-blue-600' : 'bg-blue-300' }}" style="height: {{ $n ? max(4, round($n / $times['max'] * 64)) : 0 }}px"></span>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-1 flex gap-1">@foreach($times['hours'] as $hour)<span class="flex-1 text-center text-[9px] text-slate-400">{{ $hourLabel($hour) }}</span>@endforeach</div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Self vs staff --}}
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xs font-black uppercase tracking-widest text-slate-500">Self check-in or by staff</h2>
            <p class="mt-1 text-xs text-slate-500">People who checked themselves in on the shared link, against those checked in by staff.</p>
            <div class="mt-5 space-y-4">
                @foreach($methods as $row)
                    @php($selfPct = $row['total'] ? round($row['self'] / $row['total'] * 100) : 0)
                    <div>
                        <p class="mb-1 flex justify-between text-xs font-bold text-slate-700"><span>{{ $row['label'] }}</span><span class="text-slate-500">{{ $row['total'] }} people</span></p>
                        <div class="flex h-4 overflow-hidden rounded-full bg-slate-100">
                            <span class="bg-violet-500" style="width: {{ $selfPct }}%"></span><span class="bg-slate-400" style="width: {{ $row['total'] ? 100 - $selfPct : 0 }}%"></span>
                        </div>
                        <p class="mt-1 flex justify-between text-[11px] text-slate-500"><span><span class="font-bold text-violet-600">{{ $row['self'] }}</span> self ({{ $selfPct }}%)</span><span><span class="font-bold text-slate-700">{{ $row['staff'] }}</span> by staff</span></p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    {{-- Follow-ups --}}
    <section class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-xs font-black uppercase tracking-widest text-amber-700">Came, then missed a day ({{ $dropOffs->count() }})</h2>
                    <p class="mt-1 text-xs text-slate-500">Checked in on a day, then missed at least one later day with check-ins.</p>
                </div>
                @if($canMessage && $dropOffs->isNotEmpty())<a href="{{ $messageLink($dropOffs->pluck('person')) }}" class="rounded-xl bg-amber-500 px-4 py-2 text-xs font-black text-white hover:bg-amber-600">Message these {{ $dropOffs->count() }}</a>@endif
            </div>
            <ul class="mt-4 max-h-80 divide-y divide-slate-100 overflow-auto text-sm">
                @forelse($dropOffs as $row)
                    <li class="flex items-center justify-between gap-3 py-2"><span><span class="font-bold text-slate-800">{{ $row->person->name }}</span> <span class="text-xs text-slate-400">{{ $row->area }}</span></span><span class="shrink-0 text-xs text-amber-700">Missed {{ $row->missed->map(fn ($d) => 'Day '.$d)->join(', ') }}</span></li>
                @empty
                    <li class="py-6 text-center text-slate-500">Nobody dropped off. Everyone who came kept coming.</li>
                @endforelse
            </ul>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-xs font-black uppercase tracking-widest text-rose-700">Never came ({{ $allDays['absent']->count() }})</h2>
                    <p class="mt-1 text-xs text-slate-500">Confirmed, but no check-in on any day.</p>
                </div>
                @if($canMessage && $allDays['absent']->isNotEmpty())<a href="{{ $messageLink($allDays['absent']) }}" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-black text-white hover:bg-rose-700">Message these {{ $allDays['absent']->count() }}</a>@endif
            </div>
            <ul class="mt-4 max-h-80 divide-y divide-slate-100 overflow-auto text-sm">
                @forelse($allDays['absent'] as $person)
                    <li class="flex items-center justify-between gap-3 py-2"><span class="font-bold text-slate-800">{{ $person->name }}</span><span class="shrink-0 text-xs text-slate-400">{{ $person->phone ?: 'No phone' }}</span></li>
                @empty
                    <li class="py-6 text-center text-slate-500">Everyone confirmed came at least once.</li>
                @endforelse
            </ul>
        </div>
    </section>
@endif

{{-- Compare with another event --}}
<section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xs font-black uppercase tracking-widest text-slate-500">Compare with another event</h2>
            <p class="mt-1 text-xs text-slate-500">Same rules for both, so the numbers compare fairly.</p>
        </div>
        <form method="GET" action="{{ route('reports.event', ['event' => $event->id, 'day' => 'all']) }}" class="flex items-center gap-2">
            <input type="hidden" name="view" value="advanced">
            <select name="compare" onchange="this.form.submit()" aria-label="Event to compare with" class="rounded-xl border-slate-200 text-xs font-bold">
                <option value="">Choose an event…</option>
                @foreach($comparison['candidates'] as $candidate)<option value="{{ $candidate->id }}" @selected(($comparison['other']['event'] ?? null)?->id === $candidate->id)>{{ $candidate->title }} ({{ $candidate->event_date->format('M Y') }})</option>@endforeach
            </select>
        </form>
    </div>
    @php($columns = array_filter([$comparison['current'], $comparison['other']]))
    <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm">
        <thead class="text-left text-[11px] uppercase tracking-wider text-slate-500"><tr><th class="py-2 pr-4"></th>@foreach($columns as $column)<th class="px-4 py-2 text-right">{{ $column['event']->title }}</th>@endforeach</tr></thead>
        <tbody class="divide-y divide-slate-100">
            @foreach(['expected' => 'Expected', 'came' => 'Came at least once', 'never' => 'Never came', 'days' => 'Days with check-ins', 'turnout' => 'Average daily turnout', 'best' => 'Best day'] as $key => $label)
                <tr><td class="py-2.5 pr-4 font-bold text-slate-700">{{ $label }}</td>@foreach($columns as $column)<td class="px-4 py-2.5 text-right {{ $key === 'turnout' ? 'font-black text-blue-600' : '' }}">{{ $key === 'turnout' ? $column[$key].'%' : (is_int($column[$key]) ? number_format($column[$key]) : $column[$key]) }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table></div>
    @if($comparison['candidates']->isEmpty())<p class="mt-3 text-xs text-slate-400">There are no other events to compare with yet.</p>@endif
</section>

{{-- Data checks --}}
<section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-xs font-black uppercase tracking-widest text-slate-500">Data checks</h2>
    <p class="mt-1 text-xs text-slate-500">Missing or odd details among confirmed attendees make the breakdowns less reliable. {{ $canFix ? 'Click a name to fix it on the Registrations page.' : 'This event is closed; fix details before the next event so its reports are clean.' }}</p>
    <div class="mt-4 grid gap-3 md:grid-cols-2">
        @foreach($quality as $issue)
            <div x-data="{ open: false }" class="rounded-2xl border {{ $issue['count'] ? 'border-amber-200 bg-amber-50' : 'border-emerald-100 bg-emerald-50' }} p-4">
                <div class="flex items-start justify-between gap-3">
                    <div><p class="text-sm font-black {{ $issue['count'] ? 'text-amber-900' : 'text-emerald-800' }}">{{ $issue['label'] }}</p><p class="mt-0.5 text-xs {{ $issue['count'] ? 'text-amber-800/80' : 'text-emerald-700/80' }}">{{ $issue['count'] ? $issue['hint'] : 'All good.' }}</p></div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-black {{ $issue['count'] ? 'bg-amber-500 text-white' : 'bg-emerald-500 text-white' }}">{{ number_format($issue['count']) }}</span>
                </div>
                @if($issue['listable'] && $issue['count'])
                    <button type="button" @click="open = !open" class="mt-2 text-xs font-bold text-amber-800 underline" x-text="open ? 'Hide names' : 'Show names'"></button>
                    <ul x-cloak x-show="open" class="mt-2 max-h-48 space-y-1 overflow-auto text-xs">
                        @foreach($issue['people']->take(100) as $person)
                            <li>@if($canFix)<a href="{{ route('events.registrations.index', ['event' => $event, 'search' => $person->name]) }}" class="font-bold text-slate-800 hover:text-blue-600">{{ $person->name }}</a>@else<span class="font-bold text-slate-800">{{ $person->name }}</span>@endif <span class="text-slate-500">{{ collect([$person->phone, $person->gender, $person->category])->filter()->join(' · ') }}</span></li>
                        @endforeach
                        @if($issue['count'] > 100)<li class="text-slate-500">and {{ $issue['count'] - 100 }} more</li>@endif
                    </ul>
                @endif
            </div>
        @endforeach
    </div>
</section>
