{{-- Detailed: filters, every day, every group, every person. --}}
@php
    $all = $selectedDay === 'all';
    $slots = $grid['slots'];
    $expected = $grid['totalExpected'];
    $absentByGroup = fn (callable $key) => $grid['absent']->countBy($key);
    $groupTable = function (callable $key) use ($grid, $absentByGroup) {
        $absent = $absentByGroup($key);
        return $grid['rows']->groupBy(fn ($row) => $key($row->person))
            ->map(fn ($group, $label) => ['label' => $label, 'rows' => $group, 'present' => $group->count(), 'expected' => $group->count() + ($absent[$label] ?? 0)])
            ->union($absent->diffKeys($grid['rows']->groupBy(fn ($row) => $key($row->person)))->map(fn ($count, $label) => ['label' => $label, 'rows' => collect(), 'present' => 0, 'expected' => $count]))
            ->sortByDesc('present')->values();
    };
    $areaKey = fn ($person) => \App\Services\AttendanceGrid::area($person);
    $categoryKey = fn ($person) => $person->category ?: 'Unspecified';
    $genderKey = fn ($person) => $person->gender ?: 'Unspecified';
    $cellClass = fn ($value) => match (true) {
        $value === \App\Services\AttendanceGrid::NOT_PRESENT => 'text-slate-300',
        $value === 'Staff' => 'text-amber-700 font-black text-[10px] uppercase',
        $value === '' => '',
        default => 'font-bold text-emerald-700',
    };
    $rate = fn ($n, $of) => $of ? round($n / $of * 100) : 0;
@endphp

{{-- Filters --}}
<form method="GET" action="{{ route('reports.event', ['event' => $event->id, 'day' => $selectedDay]) }}" class="flex flex-wrap items-center gap-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm print:hidden">
    <input type="hidden" name="view" value="detailed">
    @if($selectedSession)<input type="hidden" name="session" value="{{ $selectedSession }}">@endif
    <span class="text-xs font-black uppercase tracking-widest text-slate-400">Filter</span>
    @foreach(['area' => 'All areas', 'category' => 'All categories', 'gender' => 'All genders'] as $key => $placeholder)
        <select name="{{ $key }}" onchange="this.form.submit()" aria-label="{{ ucfirst($key) }}" class="rounded-xl border-slate-200 text-xs font-bold">
            <option value="">{{ $placeholder }}</option>
            @foreach($filterOptions[$key] as $option)<option value="{{ $option }}" @selected(($filters[$key] ?? '') === $option)>{{ $option }}</option>@endforeach
        </select>
    @endforeach
    @if($filters !== [])
        <a href="{{ route('reports.event', array_filter(['event' => $event->id, 'day' => $selectedDay, 'session' => $selectedSession, 'view' => 'detailed'])) }}" class="text-xs font-bold text-slate-500 underline">Clear filters</a>
    @endif
    <span class="ml-auto text-xs text-slate-500"><strong class="text-slate-900">{{ $grid['rows']->count() }}</strong> {{ strtolower($grid['presentLabel']) }} · <strong class="text-slate-900">{{ $grid['absent']->count() }}</strong> {{ strtolower($grid['absentLabel']) }} · of {{ $expected }} expected</span>
</form>

@if(! $grid['held'])
    <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">No check-ins have been recorded {{ $all ? 'for this event yet' : 'on '.$grid['periodLabel'] }}.</div>
@else
    {{-- Each day (or session) --}}
    @if($slots->count() > 1)
        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-500">Each day</h2>
            <div class="overflow-x-auto"><table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500"><tr><th class="px-6 py-3">Day</th><th class="px-4 py-3 text-right">Present</th><th class="px-4 py-3 text-right">Absent</th><th class="w-1/2 px-6 py-3">Turnout</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($slots as $slot)
                        @php($stat = $grid['slotStats'][$slot['key']])
                        <tr><td class="px-6 py-3 font-bold">{{ $slot['label'] }}</td><td class="px-4 py-3 text-right font-bold text-emerald-700">{{ $stat['present'] }}</td><td class="px-4 py-3 text-right text-rose-600">{{ max(0, $expected - $stat['present']) }}</td>
                            <td class="px-6 py-3"><div class="flex items-center gap-3"><span class="h-2.5 flex-1 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full bg-emerald-500" style="width: {{ $stat['rate'] }}%"></span></span><span class="w-10 text-right text-xs font-bold text-slate-600">{{ $stat['rate'] }}%</span></div></td></tr>
                    @endforeach
                </tbody>
            </table></div>
        </section>
    @endif

    {{-- By area, category and gender: present, expected and turnout for each group --}}
    <section class="grid gap-6 lg:grid-cols-2">
        @foreach(['Area' => $areaKey, 'Category' => $categoryKey, 'Gender' => $genderKey] as $heading => $key)
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm {{ $heading === 'Area' ? 'lg:col-span-2' : '' }}">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h2 class="text-xs font-black uppercase tracking-widest text-slate-500">By {{ strtolower($heading) }}</h2>
                    @if($heading === 'Area')<a href="{{ route('reports.area-summary', array_filter(['event' => $event->id, 'day' => $selectedDay, 'session' => $selectedSession] + $filters)) }}" class="text-xs font-bold text-blue-600">Download area summary</a>@endif
                </div>
                <div class="max-h-[28rem] overflow-auto"><table class="min-w-full text-sm">
                    <thead class="sticky top-0 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500"><tr>
                        <th class="px-6 py-3">{{ $heading }}</th>
                        @if($heading === 'Area' && $slots->count() > 1)@foreach($slots as $slot)<th class="px-3 py-3 text-right">{{ $slot['label'] }}</th>@endforeach @endif
                        <th class="px-3 py-3 text-right">{{ $all ? 'Came' : 'Present' }}</th><th class="px-3 py-3 text-right">Expected</th><th class="px-6 py-3 text-right">Turnout</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($groupTable($key) as $group)
                            <tr>
                                <td class="px-6 py-2.5 font-bold text-slate-800">
                                    @if($heading === 'Area')<a href="{{ route('reports.area-detail', array_filter(['event' => $event->id, 'area' => $group['label'], 'day' => $selectedDay, 'session' => $selectedSession])) }}" title="Download this area's list" class="hover:text-blue-600">{{ $group['label'] }}</a>@else{{ $group['label'] }}@endif
                                </td>
                                @if($heading === 'Area' && $slots->count() > 1)
                                    @foreach($slots as $slot)<td class="px-3 py-2.5 text-right text-slate-600">{{ $group['rows']->filter(fn ($row) => ! in_array($row->cells[$slot['key']], ['', \App\Services\AttendanceGrid::NOT_PRESENT], true))->count() }}</td>@endforeach
                                @endif
                                <td class="px-3 py-2.5 text-right font-bold text-emerald-700">{{ $group['present'] }}</td>
                                <td class="px-3 py-2.5 text-right text-slate-600">{{ $group['expected'] }}</td>
                                <td class="px-6 py-2.5 text-right font-bold">{{ $rate($group['present'], $group['expected']) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            </div>
        @endforeach
    </section>

    {{-- People --}}
    <section x-data="{ q: '' }" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
            <h2 class="text-xs font-black uppercase tracking-widest text-emerald-700">{{ $grid['presentLabel'] }} ({{ $grid['rows']->count() }})</h2>
            <input type="search" x-model="q" placeholder="Find a name, phone or area" class="w-64 rounded-xl border-slate-200 text-xs print:hidden">
        </div>
        <div class="max-h-[36rem] overflow-auto"><table class="min-w-full text-sm">
            <thead class="sticky top-0 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500"><tr>
                <th class="px-4 py-3">#</th><th class="px-4 py-3">Name</th><th class="px-4 py-3">Area</th><th class="px-4 py-3">Category</th>
                @foreach($slots as $slot)<th class="px-3 py-3 text-center">{{ $slot['label'] }}</th>@endforeach
                @if($grid['multiDay'])<th class="px-4 py-3 text-center">Days</th>@endif
                <th class="px-4 py-3">How</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($grid['rows'] as $index => $row)
                    <tr x-show="!q || {{ \Illuminate\Support\Js::from(strtolower($row->person->name.' '.$row->person->phone.' '.$row->area)) }}.includes(q.toLowerCase())">
                        <td class="px-4 py-2.5 text-xs text-slate-400">{{ $index + 1 }}</td>
                        <td class="px-4 py-2.5"><div class="font-bold text-slate-800">{{ $row->person->name }}</div><div class="text-[11px] text-slate-400">{{ $row->person->phone }}</div></td>
                        <td class="px-4 py-2.5 text-xs font-bold text-blue-700">{{ $row->area }}</td>
                        <td class="px-4 py-2.5 text-xs text-slate-600">{{ $row->person->category ?: '—' }}</td>
                        @foreach($slots as $slot)<td class="px-3 py-2.5 text-center text-xs {{ $cellClass($row->cells[$slot['key']]) }}">{{ $row->cells[$slot['key']] }}</td>@endforeach
                        @if($grid['multiDay'])<td class="px-4 py-2.5 text-center text-xs"><strong>{{ $row->daysAttended }}</strong> of {{ $grid['totalDays'] }}</td>@endif
                        <td class="px-4 py-2.5 text-[11px] text-slate-500">{{ $row->method }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </section>

    <section x-data="{ open: {{ $grid['absent']->count() <= 50 ? 'true' : 'false' }} }" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <button type="button" @click="open = !open" :aria-expanded="open.toString()" class="flex w-full items-center justify-between px-6 py-4 text-left">
            <h2 class="text-xs font-black uppercase tracking-widest text-rose-700">{{ $grid['absentLabel'] }} ({{ $grid['absent']->count() }})</h2>
            <span class="text-xs font-bold text-slate-500" x-text="open ? 'Hide' : 'Show'"></span>
        </button>
        <div x-cloak x-show="open" class="max-h-[30rem] overflow-auto border-t border-slate-100"><table class="min-w-full text-sm">
            <thead class="sticky top-0 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-3">#</th><th class="px-4 py-3">Name</th><th class="px-4 py-3">Phone</th><th class="px-4 py-3">Area</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Gender</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($grid['absent'] as $index => $person)
                    <tr><td class="px-4 py-2.5 text-xs text-slate-400">{{ $index + 1 }}</td><td class="px-4 py-2.5 font-bold text-slate-800">{{ $person->name }}</td><td class="px-4 py-2.5 text-xs">{{ $person->phone ?: '—' }}</td><td class="px-4 py-2.5 text-xs">{{ \App\Services\AttendanceGrid::area($person) }}</td><td class="px-4 py-2.5 text-xs">{{ $person->category ?: '—' }}</td><td class="px-4 py-2.5 text-xs">{{ $person->gender ?: '—' }}</td></tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">Everyone expected checked in.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
@endif
