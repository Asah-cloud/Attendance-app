@php use App\Support\AccommodationPriority as Priority; @endphp
<x-app-layout>
    <x-slot name="header">Room plan import</x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl space-y-7 sm:px-6 lg:px-8">
        @include('accommodation.sections._header', ['title' => 'Room plan import', 'intro' => 'Place a listed group a set number to a room, registering anyone who is not registered yet.'])
        @if($errors->any())<div class="rounded-2xl bg-red-50 p-4 text-sm font-bold text-red-800">{{ $errors->first() }}</div>@endif
        @php
            $planSites = $event->accommodationSites()->with('blocks')->orderBy('name')->get();
        @endphp
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black text-slate-900">Room plan import</h2>
            <p class="mt-1 text-sm text-slate-500">Upload a list (same columns as the participant import) and choose how many of them share a room, even where rooms have more beds. Spare beds stay free for others. Anyone on the list who isn't registered for this event yet will be registered. You'll see a preview before anything is saved.</p>
            <form method="POST" enctype="multipart/form-data" action="{{ route('events.accommodation.plan.upload', $event) }}" class="mt-4 grid gap-3 rounded-2xl bg-slate-50 p-4 md:grid-cols-[2fr_1fr_1.5fr_auto] md:items-end">@csrf
                <div><label class="text-xs font-black uppercase text-slate-500">List</label><input type="file" name="file" required accept=".xlsx,.xls,.csv,.pdf" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white p-2 text-sm"></div>
                <div><label class="text-xs font-black uppercase text-slate-500">People per room</label><input type="number" name="per_room" min="1" max="20" value="{{ old('per_room', 3) }}" required class="mt-2 w-full rounded-xl border-slate-200"></div>
                <div><label class="text-xs font-black uppercase text-slate-500">Where</label>
                    <select name="location" class="mt-2 w-full rounded-xl border-slate-200" onchange="const [t, id] = this.value.split(':'); this.form.site_id.value = t === 'site' ? id : ''; this.form.block_id.value = t === 'block' ? id : '';">
                        <option value="">Any room</option>
                        @foreach($planSites as $site)
                            <option value="site:{{ $site->id }}">{{ $site->name }} (all buildings)</option>
                            @foreach($site->blocks as $block)<option value="block:{{ $block->id }}">&nbsp;&nbsp;{{ $site->name }} · {{ $block->name }}</option>@endforeach
                        @endforeach
                    </select>
                    <input type="hidden" name="site_id"><input type="hidden" name="block_id">
                </div>
                <button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-black text-white" @disabled(!$event->accommodation_enabled)>Preview plan</button>
            </form>
        </section>
    </div></div>
</x-app-layout>
