<x-app-layout>
    <x-slot name="header">Imports</x-slot>

    <div class="py-10 bg-[#f8fafc]">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <a href="{{ route('events.attendance', $event) }}" class="text-xs font-bold text-slate-500 hover:text-slate-800">&larr; Back to attendance</a>
                <h1 class="mt-2 text-2xl font-black text-slate-900">{{ $event->title }} &middot; Imports</h1>
                <p class="mt-1 text-sm text-slate-500">Every participant list imported into this event, newest first. Undoing an import removes the people it added to this event. Anyone who has already checked in is kept.</p>
            </div>

            @if(session('success'))
                <div class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{{ session('error') }}</div>
            @endif

            @if($imports->isEmpty())
                <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">No lists have been imported into this event yet.</div>
            @else
                <ul class="space-y-3">
                    @foreach($imports as $import)
                        <li class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between" data-import>
                            <div class="min-w-0 text-sm text-slate-600">
                                <p class="truncate font-black text-slate-900">{{ $import->file_name }}</p>
                                <p class="mt-1 text-xs">{{ $import->created_at->format('j M Y, g:i a') }}@if($import->user) · {{ $import->user->name }}@endif · added {{ $import->addedToEventCount() }} {{ Str::plural('person', $import->addedToEventCount()) }}</p>
                            </div>
                            @if($import->undone_at)
                                <span class="shrink-0 text-xs font-bold text-slate-400">Undone {{ $import->undone_at->format('j M, g:i a') }}</span>
                            @else
                                @include('events.partials.undo-import', ['import' => $import])
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-app-layout>
