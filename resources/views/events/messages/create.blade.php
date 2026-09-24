<x-app-layout>
    <x-slot name="header">{{ isset($message) ? 'Edit and resend message' : 'Compose message' }}</x-slot>

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        @include('events.messages._compose', ['inModal' => false])
    </div>
</x-app-layout>
