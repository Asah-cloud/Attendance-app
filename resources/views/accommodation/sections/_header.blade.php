{{-- Top of each accommodation area page: a way back to the hub, and what the page is for. --}}
<div>
    <a href="{{ route('events.accommodation.index', $event) }}" class="inline-flex items-center gap-1 text-sm font-bold text-indigo-600 hover:text-indigo-800">← Accommodation</a>
    <h1 class="mt-2 text-3xl font-black text-slate-900">{{ $title }}</h1>
    <p class="mt-1 text-sm text-slate-500">{{ $intro }}</p>
</div>
