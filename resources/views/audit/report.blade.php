<x-app-layout>
    <x-slot name="header">Sharing point report</x-slot>
    <h1 class="mb-5 text-2xl font-black">{{ $event->title }} — your sharing points</h1>
    <div class="overflow-x-auto"><table class="w-full bg-white text-left text-sm"><thead><tr><th class="p-3">Meal</th><th class="p-3">Sharing point</th><th class="p-3">Participant</th><th class="p-3">Portions</th><th class="p-3">Last collected</th></tr></thead><tbody>
    @forelse($collections as $collection)<tr><td class="p-3">{{ $collection->distribution->name }}</td><td class="p-3">{{ $collection->station?->name }}</td><td class="p-3">{{ $collection->participant->name }}</td><td class="p-3">{{ $collection->quantity }}</td><td class="p-3">{{ $collection->collected_at->format('M j, g:i A') }}</td></tr>@empty<tr><td class="p-5" colspan="5">No collections at your sharing points.</td></tr>@endforelse
    </tbody></table></div>
    <div class="my-5">{{ $collections->links() }}</div>
    <a class="font-bold text-blue-700" href="{{ route('events.meals.index', $event) }}">Back to food operations</a>
</x-app-layout>
