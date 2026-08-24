<x-app-layout>
    <x-slot name="header">Create form</x-slot>
    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-gray-100 bg-white p-7 shadow-sm">
            <h3 class="text-lg font-black">Create form</h3>
            <p class="mt-1 text-sm text-gray-500">Add questions and open it up for responses after creating it.</p>
            <form method="POST" action="{{ route('events.forms.store', $event) }}" class="mt-6 space-y-5">
                @csrf
                <div><label class="text-xs font-black uppercase text-gray-500">Title</label><input name="title" value="{{ old('title') }}" required class="mt-2 w-full rounded-xl border-gray-200"><x-input-error :messages="$errors->get('title')" class="mt-1.5" /></div>
                <div><label class="text-xs font-black uppercase text-gray-500">Description (optional)</label><textarea name="description" rows="3" class="mt-2 w-full rounded-xl border-gray-200">{{ old('description') }}</textarea><x-input-error :messages="$errors->get('description')" class="mt-1.5" /></div>
                <button class="rounded-xl bg-gray-900 px-5 py-3 font-bold text-white">Create form</button>
            </form>
        </div>
    </div>
</x-app-layout>
