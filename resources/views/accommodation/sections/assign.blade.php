@php use App\Support\AccommodationPriority as Priority; @endphp
<x-app-layout>
    <x-slot name="header">Assign rooms automatically</x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl space-y-7 sm:px-6 lg:px-8">
        @include('accommodation.sections._header', ['title' => 'Assign rooms automatically', 'intro' => 'Place everyone who needs a room in one go, or a chosen category or gender at a time.'])
        @if($errors->any())<div class="rounded-2xl bg-red-50 p-4 text-sm font-bold text-red-800">{{ $errors->first() }}</div>@endif
        <section data-tour="event-rooms-assign" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div><h2 class="text-xl font-black text-slate-900">Assign rooms automatically</h2><p class="mt-1 text-sm text-slate-500">People who need a step-free room are placed first. Rooms then fill in the priority order you set.</p></div>
            <form method="POST" action="{{ route('events.accommodation.allocate', $event) }}" class="mt-4 flex flex-wrap items-center gap-2 rounded-2xl bg-indigo-50 p-3">@csrf
                <span class="text-xs font-black text-indigo-900">Assign only</span>
                <select name="category" class="rounded-lg border-indigo-200 text-sm"><option value="">All categories</option>@foreach($allocationCategories as $option)<option value="{{ $option }}" @selected($allocationCategory === $option)>{{ $option }}</option>@endforeach</select>
                <select name="gender" class="rounded-lg border-indigo-200 text-sm"><option value="">All genders</option>@foreach($allocationGenders as $option)<option value="{{ $option }}" @selected($allocationGender === $option)>{{ $option }}</option>@endforeach</select>
                <button class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-black text-white" @disabled(!$event->accommodation_enabled)>Assign rooms now</button>
                <a href="{{ route('events.accommodation.section', array_filter(['event' => $event, 'section' => 'assign', 'preview' => 1, 'category' => $allocationCategory, 'gender' => $allocationGender])) }}" class="rounded-xl border border-indigo-200 bg-white px-4 py-2 text-sm font-black text-indigo-700">Preview selection</a>
                @if($preview)<a href="{{ route('events.accommodation.section', [$event, 'assign']) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-500">Hide preview</a>@endif
                <span class="text-xs text-indigo-700">Choose a category, gender, or both. Run each group separately; people already assigned are left unchanged. Preview is a fresh calculation each time — it doesn't assign anything.</span>
            </form>
            @if($preview)
                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    <div class="rounded-2xl bg-emerald-50 p-4"><h3 class="font-black text-emerald-900">{{ $preview['proposals']->count() }} proposed</h3>@foreach($preview['proposals']->take(100) as $proposal)<p class="mt-2 text-sm text-emerald-800">{{ $proposal['registration']->participant->name }} → {{ $proposal['room']->label() }}</p>@endforeach @if($preview['proposals']->count() > 100)<p class="mt-2 text-xs font-bold text-emerald-700">…and {{ $preview['proposals']->count() - 100 }} more. Run "Assign rooms now" to place them.</p>@endif</div>
                    <div class="rounded-2xl bg-amber-50 p-4"><h3 class="font-black text-amber-900">{{ $preview['unallocated']->count() }} unallocated</h3>@foreach($preview['unallocated']->take(100) as $item)<p class="mt-2 text-sm text-amber-800">{{ $item['registration']->participant->name }} — {{ $item['reason'] }}</p>@endforeach @if($preview['unallocated']->count() > 100)<p class="mt-2 text-xs font-bold text-amber-700">…and {{ $preview['unallocated']->count() - 100 }} more with the same blocker.</p>@endif</div>
                </div>
            @endif
        </section>
    </div></div>
</x-app-layout>
