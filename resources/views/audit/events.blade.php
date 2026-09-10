<x-app-layout>
    <x-slot name="header">Food operations</x-slot>
    <h1 class="mb-6 text-3xl font-black">Your assigned events</h1>
    <div class="grid gap-5 md:grid-cols-2">
        @forelse($events as $event)
            <article class="rounded-2xl border bg-white p-6">
                <h2 class="text-xl font-bold">{{ $event->title }}</h2>
                <p class="my-3 text-slate-600">{{ $event->event_date->format('M j, Y') }} — {{ $event->confirmed_participants_count }} confirmed participants</p>
                <a class="font-bold text-blue-700" href="{{ route('events.meals.index', $event) }}">Open food operations &rarr;</a>
            </article>
        @empty
            <p>Your manager must assign you to an event before you can access its food operations.</p>
        @endforelse
    </div>
</x-app-layout>
