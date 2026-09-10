<x-app-layout>
    <x-slot name="header">Food operations</x-slot>
    <x-event-closed-banner :event="$event" />
    <h1 class="text-3xl font-black">{{ $event->title }}</h1>
    <div class="my-6 flex flex-wrap gap-5 rounded-2xl bg-blue-50 p-5"><span><strong>{{ $confirmedCount }}</strong> confirmed participants</span><span><strong>{{ $checkedInCount }}</strong> checked in</span></div>
    <p class="mb-5">Every confirmed participant is eligible. Stock and serving totals below cover your assigned sharing points.</p>
    <a class="font-bold text-blue-700" href="{{ route('audit.approvals.index', $event) }}">Restricted sections / approval codes</a>
    <a class="ml-4 font-bold text-blue-700" href="{{ route('events.meals.report', $event) }}">Sharing point collection report</a>
    @if($stations->isEmpty())<p class="my-5 rounded-xl bg-amber-50 p-5">Ask your Audit Head to assign you to a sharing point and allocate stock before scanning.</p>@endif
    <div class="my-6 grid gap-5 lg:grid-cols-2">
    @forelse($meals as $meal)
        <article class="rounded-2xl border bg-white p-6">
            <h2 class="text-xl font-bold">{{ $meal->name }}</h2>
            <p class="my-3 text-sm">{{ $meal->isOpen() ? 'Open now' : 'Closed' }} — {{ $meal->opens_at?->format('M j, g:i A') }} {{ $meal->closes_at ? 'to '.$meal->closes_at->format('M j, g:i A') : '' }}</p>
            <div class="my-4 flex flex-wrap gap-4"><span>Allocated: <strong>{{ $meal->total_portions }}</strong></span><span>Served: <strong>{{ $meal->issuedPortions() }}</strong></span><span>Remaining: <strong>{{ $meal->remainingPortions() }}</strong></span></div>
            <p class="text-sm text-slate-600">{{ $meal->awaiting_collection_count }} confirmed participants across the event have yet to collect this meal.</p>
            <table class="my-4 w-full text-left text-sm"><thead><tr><th>Sharing point</th><th>Allocated</th><th>Served</th><th>Remaining</th></tr></thead><tbody>
            @foreach($stations as $station)<tr><td class="py-2">{{ $station->name }}</td><td>{{ $meal->allocatedPortionsFor($station->id) ?? 0 }}</td><td>{{ $meal->issuedPortionsAtStation($station->id) }}</td><td>{{ $meal->remainingPortionsAtStation($station->id) ?? 0 }}</td></tr>@endforeach
            </tbody></table>
            @if($stations->isNotEmpty())<a class="inline-block rounded-xl bg-blue-600 px-5 py-3 font-bold text-white" href="{{ route('events.meals.scanner', [$event, $meal]) }}">Scan / view collection history</a>@endif
        </article>
    @empty<p>No meals have been created yet.</p>@endforelse
    </div>
</x-app-layout>
