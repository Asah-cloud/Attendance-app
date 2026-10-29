@php
    $statusLabels = [
        'already_registered' => ['Registered for this event', 'bg-emerald-50 text-emerald-700'],
        'newly_registered' => ['In the system - will be registered', 'bg-amber-50 text-amber-800'],
        'reconfirmed' => ['Was pending/cancelled - will be confirmed', 'bg-amber-50 text-amber-800'],
        'new_person' => ['New person - will be added and registered', 'bg-blue-50 text-blue-800'],
        'skipped' => ['Skipped', 'bg-slate-100 text-slate-500'],
    ];
    $counts = collect($review['rows'])->countBy('status');
    $preview = $review['preview'];
    $placed = collect($preview['rooms'])->sum(fn ($room) => count($room['people']));
    $decisions = $review['decisions'] ?? [];
@endphp
<x-app-layout>
    <x-slot name="header">Room plan preview</x-slot>
    <div class="mx-auto max-w-6xl space-y-6">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Room plan preview</h1>
            <p class="mt-1 text-sm text-slate-500">
                <strong>{{ $review['file_name'] }}</strong> · up to <strong>{{ $review['per_room'] }}</strong> per room · {{ $location }}.
                Nothing has been saved yet.
            </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-black uppercase text-slate-400">On the list</p><p class="mt-1 text-2xl font-black">{{ count($review['rows']) }}</p></div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4"><p class="text-xs font-black uppercase text-emerald-700">Will get a room</p><p class="mt-1 text-2xl font-black text-emerald-900">{{ $placed }}</p></div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4"><p class="text-xs font-black uppercase text-amber-700">Not registered yet</p><p class="mt-1 text-2xl font-black text-amber-900">{{ $counts->get('newly_registered', 0) + $counts->get('new_person', 0) + $counts->get('reconfirmed', 0) }}</p><p class="text-xs text-amber-800">They will be registered for this event.</p></div>
            <div class="rounded-2xl border {{ count($preview['unallocated']) ? 'border-rose-200 bg-rose-50' : 'border-slate-200 bg-white' }} p-4"><p class="text-xs font-black uppercase {{ count($preview['unallocated']) ? 'text-rose-700' : 'text-slate-400' }}">Can't be placed</p><p class="mt-1 text-2xl font-black">{{ count($preview['unallocated']) }}</p></div>
        </div>

        @if(($preview['to_place'] ?? 0) > ($preview['free_beds'] ?? PHP_INT_MAX))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" data-short-of-beds>
                <strong>Not enough beds:</strong> the chosen rooms have {{ $preview['free_beds'] }} free {{ Str::plural('bed', $preview['free_beds']) }}, but {{ $preview['to_place'] }} people on the list need a room. Go back and choose more rooms, or confirm and place the rest later.
            </div>
        @endif

        <form method="POST" action="{{ route('events.accommodation.plan.confirm', $event) }}" class="space-y-6">
            @csrf

            @if($review['review_rows'])
                <section class="rounded-3xl border border-amber-200 bg-amber-50 p-6">
                    <h2 class="text-lg font-black text-amber-900">Check {{ count($review['review_rows']) }} {{ Str::plural('row', count($review['review_rows'])) }}</h2>
                    <p class="mt-1 text-sm text-amber-800">These rows share a phone or email with someone registered under a different name. Choose <strong>Update</strong> if it's the same person, <strong>New person</strong> to keep both, or <strong>Skip</strong>. Press <strong>Update preview</strong> to see the rooms with your choices.</p>
                    <div class="mt-4 divide-y divide-amber-100 overflow-hidden rounded-2xl border border-amber-100 bg-white">
                        @foreach($review['review_rows'] as $row)
                            <div class="grid gap-3 p-4 md:grid-cols-[1fr_1fr_auto] md:items-center">
                                <div><p class="text-xs font-bold text-slate-400">Row {{ $row['row'] }} in your file</p><p class="font-bold text-slate-900">{{ $row['name'] }}</p><p class="text-xs text-slate-500">{{ $row['phone'] ?: '—' }} · {{ $row['email'] ?: '—' }}</p></div>
                                <div><p class="text-xs font-bold text-slate-400">Already registered · same {{ $row['matched_by'] }}</p><p class="font-bold text-slate-900">{{ $row['existing_name'] }}</p><p class="text-xs text-slate-500">{{ $row['existing_phone'] ?: '—' }} · {{ $row['existing_email'] ?: '—' }}</p></div>
                                <div class="flex gap-3 text-xs font-bold text-slate-700">
                                    @foreach(['update' => 'Update', 'new' => 'New person', 'skip' => 'Skip'] as $value => $label)
                                        <label class="flex items-center gap-1.5"><input type="radio" name="decisions[{{ $row['row'] }}]" value="{{ $value }}" @checked(($decisions[$row['row']] ?? 'new') === $value) class="text-blue-600"> {{ $label }}</label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <button type="submit" formaction="{{ route('events.accommodation.plan.refresh', $event) }}" class="mt-4 rounded-xl bg-amber-600 px-4 py-2 text-sm font-black text-white">Update preview</button>
                </section>
            @endif

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-black text-slate-900">Proposed rooms ({{ count($preview['rooms']) }})</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse($preview['rooms'] as $room)
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <p class="font-black text-slate-900">{{ $room['label'] }}</p>
                            <p class="text-xs text-slate-500">{{ $room['before'] + count($room['people']) }} of {{ $room['capacity'] }} beds used after this plan{{ $room['before'] ? ' ('.$room['before'].' already there)' : '' }}</p>
                            <ul class="mt-2 space-y-1 text-sm text-slate-700">
                                @foreach($room['people'] as $person)<li>{{ $person['name'] }} <span class="text-xs text-slate-400">{{ collect([$person['gender'], $person['group']])->filter()->join(' · ') }}</span></li>@endforeach
                            </ul>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No rooms proposed.</p>
                    @endforelse
                </div>
            </section>

            @if($preview['unallocated'] || $preview['already'])
                <section class="grid gap-4 lg:grid-cols-2">
                    @if($preview['unallocated'])
                        <div class="rounded-3xl border border-rose-200 bg-rose-50 p-6"><h2 class="font-black text-rose-900">Can't be placed ({{ count($preview['unallocated']) }})</h2>
                            <ul class="mt-2 space-y-1 text-sm text-rose-800">@foreach($preview['unallocated'] as $person)<li>{{ $person['name'] }} — {{ $person['reason'] }}</li>@endforeach</ul>
                            <p class="mt-3 text-xs text-rose-700">They will still be registered and marked as needing a room. Add rooms or choose "Any room", then place them later.</p></div>
                    @endif
                    @if($preview['already'])
                        <div class="rounded-3xl border border-slate-200 bg-white p-6"><h2 class="font-black text-slate-900">Already have a room ({{ count($preview['already']) }})</h2>
                            <ul class="mt-2 space-y-1 text-sm text-slate-600">@foreach($preview['already'] as $person)<li>{{ $person['name'] }} — {{ $person['room'] }}</li>@endforeach</ul>
                            <p class="mt-3 text-xs text-slate-500">Left where they are.</p></div>
                    @endif
                </section>
            @endif

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-black text-slate-900">Everyone on the list</h2>
                <div class="mt-3 max-h-96 divide-y divide-slate-100 overflow-y-auto rounded-2xl border border-slate-100">
                    @foreach($review['rows'] as $row)
                        @php([$label, $classes] = $statusLabels[$row['status']] ?? [$row['status'], 'bg-slate-100 text-slate-600'])
                        <div class="flex items-center justify-between gap-3 px-4 py-2 text-sm"><span><span class="text-xs text-slate-400">Row {{ $row['row'] }}</span> <span class="font-bold text-slate-800">{{ $row['name'] }}</span></span><span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $classes }}">{{ $label }}</span></div>
                    @endforeach
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="submit" form="cancelPlan" class="rounded-2xl px-5 py-3 text-xs font-black uppercase tracking-widest text-slate-500 hover:text-slate-700">Cancel</button>
                <button type="submit" class="rounded-2xl bg-indigo-600 px-6 py-3 text-xs font-black uppercase tracking-widest text-white shadow-lg hover:bg-indigo-700">Confirm room plan</button>
            </div>
        </form>
        <form id="cancelPlan" method="POST" action="{{ route('events.accommodation.plan.cancel', $event) }}" class="hidden">@csrf</form>
    </div>
</x-app-layout>
