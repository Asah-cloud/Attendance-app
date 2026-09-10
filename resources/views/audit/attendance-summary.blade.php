<x-app-layout>
    <x-slot name="header">Approved attendance summary</x-slot>
    <h1 class="mb-4 text-2xl font-black">{{ $event->title }}</h1>
    <p class="mb-5">Read-only access for this view. Reopening requires a new approval code.</p>
    <table class="w-full bg-white text-left"><thead><tr><th class="p-3">Confirmed participant</th><th class="p-3">Checked in</th></tr></thead><tbody>@foreach($registrations as $registration)<tr><td class="p-3">{{ $registration->participant->name }}</td><td class="p-3">{{ $arrived->contains($registration->participant_id) ? 'Yes' : 'No' }}</td></tr>@endforeach</tbody></table>
</x-app-layout>
