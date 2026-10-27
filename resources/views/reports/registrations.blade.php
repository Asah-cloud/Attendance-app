@php use App\Services\RegistrationReport; @endphp
<x-app-layout>
    <x-slot name="header">Reports</x-slot>

    @php
        $filters = $report['filters'];
        $rows = $report['rows'];
        $total = $rows->count();
        $query = fn (array $change = []) => array_filter(['event' => $event->id] + $change + $filters, fn ($value) => $value !== null && $value !== '');
        $share = fn ($count) => $total ? round($count / $total * 100) : 0;
        $phase = match ($event->status) {
            'upcoming' => 'The event hasn\'t started: this is who is coming.',
            'active' => 'The event is running: the Checked in column shows who has arrived.',
            default => 'The event has ended: the Checked in column shows who came.',
        };
    @endphp

    <div class="mx-auto max-w-7xl space-y-6 py-8 sm:px-6 lg:px-8">
        {{-- Title and downloads --}}
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-blue-600">Registration report</p>
                    <h1 class="mt-1 text-2xl font-black text-slate-950">{{ $event->title }}</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $event->event_date->format('j M Y') }}@if($event->end_date && ! $event->end_date->isSameDay($event->event_date)) – {{ $event->end_date->format('j M Y') }}@endif
                        · {{ $phase }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 print:hidden">
                    <a href="{{ route('reports.registrations.excel', $query()) }}" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-extrabold text-white hover:bg-emerald-700">Download Excel</a>
                    <a href="{{ route('reports.registrations.pdf', $query()) }}" class="rounded-xl bg-red-600 px-4 py-2.5 text-xs font-extrabold text-white hover:bg-red-700">Download PDF</a>
                </div>
            </div>
            <p class="mt-3 text-xs font-semibold text-blue-700">Showing: {{ RegistrationReport::filterLabel($filters) }}. Downloads use the same selection.</p>
        </div>

        @include('reports._tabs', ['active' => 'registrations'])

        {{-- Status, then the other filters --}}
        <div class="space-y-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm print:hidden">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-black uppercase tracking-widest text-slate-400">Status</span>
                @foreach($report['statusCounts'] as $status => $count)
                    <a href="{{ route('reports.registrations', $query(['status' => $status])) }}" class="rounded-full px-3 py-1.5 text-xs font-bold {{ $filters['status'] === $status ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">{{ RegistrationReport::STATUSES[$status] }} ({{ $count }})</a>
                @endforeach
                <a href="{{ route('reports.registrations', $query(['status' => RegistrationReport::ANY_STATUS])) }}" class="rounded-full px-3 py-1.5 text-xs font-bold {{ $filters['status'] === RegistrationReport::ANY_STATUS ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">All statuses ({{ $report['statusCounts']->sum() }})</a>
            </div>
            <form method="GET" action="{{ route('reports.registrations', $event) }}" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                <span class="text-xs font-black uppercase tracking-widest text-slate-400">Filter</span>
                @foreach(['category' => 'All categories', 'gender' => 'All genders', 'country' => 'All countries', 'area' => 'All areas'] as $key => $placeholder)
                    <select name="{{ $key }}" onchange="this.form.submit()" aria-label="{{ ucfirst($key) }}" class="rounded-xl border-slate-200 text-xs font-bold">
                        <option value="">{{ $placeholder }}</option>
                        @foreach($report['options'][$key] as $option)<option value="{{ $option }}" @selected(($filters[$key] ?? '') === $option)>{{ $option }}</option>@endforeach
                    </select>
                @endforeach
                @if(array_diff_key($filters, ['status' => true]) !== [])
                    <a href="{{ route('reports.registrations', ['event' => $event->id, 'status' => $filters['status']]) }}" class="text-xs font-bold text-slate-500 underline">Clear filters</a>
                @endif
            </form>
        </div>

        {{-- Headline numbers --}}
        <section class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-black uppercase tracking-widest text-slate-400">People</p><p class="mt-2 text-3xl font-black text-slate-950">{{ number_format($total) }}</p></div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-black uppercase tracking-widest text-emerald-700">Checked in at least once</p><p class="mt-2 text-3xl font-black text-emerald-700">{{ number_format($report['checkedIn']) }}</p></div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-black uppercase tracking-widest text-slate-400">Not checked in yet</p><p class="mt-2 text-3xl font-black text-slate-950">{{ number_format($total - $report['checkedIn']) }}</p></div>
        </section>

        {{-- By category, gender, country and area --}}
        <section class="grid gap-6 lg:grid-cols-2">
            @foreach($report['breakdowns'] as $heading => $counts)
                <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <h2 class="border-b border-slate-100 px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-500">By {{ strtolower($heading) }}@if($heading === 'Country') <span class="normal-case tracking-normal text-slate-400">(as entered, or from the phone number)</span>@endif</h2>
                    <div class="max-h-[24rem] overflow-auto"><table class="min-w-full text-sm">
                        <thead class="sticky top-0 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500"><tr><th class="px-6 py-3">{{ $heading }}</th><th class="px-3 py-3 text-right">People</th><th class="w-2/5 px-6 py-3">Share</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($counts as $label => $count)
                                <tr>
                                    <td class="px-6 py-2.5 font-bold text-slate-800"><a href="{{ route('reports.registrations', $query([RegistrationReport::BREAKDOWNS[$heading] => $label])) }}" class="hover:text-blue-600">{{ $label }}</a></td>
                                    <td class="px-3 py-2.5 text-right font-bold text-slate-900">{{ $count }}</td>
                                    <td class="px-6 py-2.5"><div class="flex items-center gap-3"><span class="h-2.5 flex-1 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full bg-blue-500" style="width: {{ $share($count) }}%"></span></span><span class="w-10 text-right text-xs font-bold text-slate-600">{{ $share($count) }}%</span></div></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-6 py-4 text-sm text-slate-400">Nobody matches.</td></tr>
                            @endforelse
                        </tbody>
                    </table></div>
                </div>
            @endforeach
        </section>

        {{-- People --}}
        <section x-data="{ q: '' }" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
                <h2 class="text-xs font-black uppercase tracking-widest text-slate-500">People ({{ $total }})</h2>
                <input x-model="q" type="search" placeholder="Find a name, phone or country" class="w-64 rounded-xl border-slate-200 text-xs print:hidden">
            </div>
            <div class="overflow-x-auto"><table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500"><tr>
                    <th class="px-4 py-3">#</th><th class="px-4 py-3">Name</th><th class="px-4 py-3">Country</th><th class="px-4 py-3">Area</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Gender</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Registered</th><th class="px-4 py-3">Checked in</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $index => $row)
                        <tr x-show="!q || {{ \Illuminate\Support\Js::from(mb_strtolower($row->person->name.' '.$row->person->phone.' '.$row->country.' '.$row->area)) }}.includes(q.toLowerCase())">
                            <td class="px-4 py-2.5 text-xs text-slate-400">{{ $index + 1 }}</td>
                            <td class="px-4 py-2.5"><span class="font-bold text-slate-800">{{ $row->person->name }}</span><span class="block text-xs text-slate-400">{{ $row->person->phone }}</span></td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $row->country }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $row->area }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $row->category }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $row->gender }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $row->statusLabel }}</td>
                            <td class="px-4 py-2.5 text-xs text-slate-500">{{ $row->registration->registered_at?->format('j M Y') }}</td>
                            <td class="px-4 py-2.5 text-xs font-bold {{ $row->daysCheckedIn ? 'text-emerald-700' : 'text-slate-400' }}">{{ RegistrationReport::checkedInLabel($row, $report['eventDays']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-6 py-10 text-center text-sm text-slate-500">Nobody has registered with this selection yet.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </section>
    </div>
</x-app-layout>
