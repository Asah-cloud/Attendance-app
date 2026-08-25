<x-app-layout>
    <x-slot name="header">Events</x-slot>

    @role('admin')
        <div class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div><p class="text-sm font-semibold text-blue-600">Platform events</p><h2 class="mt-1 text-3xl font-black tracking-tight">Company event workspaces</h2><p class="mt-2 text-sm text-slate-500">Review events by company or create a new company workspace.</p></div>
            <div class="flex flex-wrap gap-3"><a href="{{ route('companies.index') }}" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-extrabold text-slate-700 hover:bg-slate-50">View companies</a><a href="{{ route('companies.create') }}" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-extrabold text-white shadow-lg shadow-blue-200 hover:bg-blue-700">Add company</a></div>
        </div>

        <div class="space-y-5">
            @forelse($companies as $company)
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col justify-between gap-4 border-b border-slate-100 px-6 py-5 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-4">
                            @if($company->logo_path)<img src="{{ Storage::url($company->logo_path) }}" alt="{{ $company->name }} logo" class="h-12 w-12 rounded-xl border border-slate-200 object-contain p-1">@else<div class="grid h-12 w-12 place-items-center rounded-xl bg-blue-50 text-lg font-black text-blue-700">{{ strtoupper(substr($company->name, 0, 1)) }}</div>@endif
                            <div><h3 class="text-lg font-black">{{ $company->name }}</h3><p class="mt-1 text-xs text-slate-500">@if($company->isPayPerEvent()) {{ $company->events->count() }} events · pay-per-event, no cap @else {{ $company->events->count() }} of {{ $company->event_limit }} event slots used @endif · {{ $company->users->filter(fn ($user) => $user->hasRole('manager'))->count() }} managers</p></div>
                        </div>
                        <div class="flex gap-2"><a href="{{ route('companies.edit', $company) }}" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-extrabold text-slate-700">Edit company</a><a href="{{ route('events.create', ['company_id' => $company->id]) }}" class="rounded-lg bg-blue-50 px-3 py-2 text-xs font-extrabold text-blue-700">Create event</a></div>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse($company->events as $event)
                            <div class="flex flex-col gap-4 px-6 py-4 sm:flex-row sm:items-center"><div class="min-w-0 flex-1"><p class="truncate text-sm font-extrabold">{{ $event->title }}</p><p class="mt-1 text-xs text-slate-500">{{ $event->event_date->format('M j, Y') }}{{ $event->location ? ' · '.$event->location : '' }}</p></div><span class="w-fit rounded-full px-3 py-1 text-[10px] font-black uppercase {{ $event->status === 'active' ? 'bg-emerald-50 text-emerald-700' : ($event->status === 'upcoming' ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-600') }}">{{ $event->status }}</span><div class="flex gap-2"><a href="{{ route('events.attendance', $event) }}" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-extrabold text-white">Open attendance</a><a href="{{ route('events.edit', $event) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-extrabold text-slate-600">Edit</a></div></div>
                        @empty <p class="px-6 py-8 text-center text-sm text-slate-500">No events created for this company.</p> @endforelse
                    </div>
                </section>
            @empty <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center text-sm text-slate-500">No companies have been created yet.</div> @endforelse
        </div>
    @endrole

    @hasanyrole('manager|usher')
        @role('manager')
            <section class="mb-7 overflow-hidden rounded-3xl bg-gradient-to-r from-[#071426] to-blue-900 p-6 text-white shadow-xl sm:p-8">
                <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-center">
                    <div class="flex min-w-0 items-center gap-5">
                        @if(auth()->user()->company?->logo_path)<img src="{{ Storage::url(auth()->user()->company->logo_path) }}" alt="{{ auth()->user()->company->name }} logo" class="h-20 w-20 shrink-0 rounded-2xl bg-white object-contain p-2 shadow-lg">@else<div class="grid h-20 w-20 shrink-0 place-items-center rounded-2xl bg-white/10 text-3xl font-black">{{ strtoupper(substr(auth()->user()->company?->name ?? 'O', 0, 1)) }}</div>@endif
                        <div class="min-w-0"><p class="text-xs font-extrabold uppercase tracking-[0.22em] text-blue-300">Event workspace</p><h2 class="mt-2 truncate text-2xl font-black sm:text-3xl">{{ auth()->user()->company?->name ?? 'Organization' }}</h2><p class="mt-2 text-sm text-blue-100">Manage events, registrations and attendance from one place.</p></div>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-3"><a href="{{ route('admin.register-person') }}" class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm font-extrabold hover:bg-white/20">Add usher</a><a href="{{ route('events.create') }}" class="rounded-xl bg-white px-5 py-3 text-sm font-extrabold text-blue-900 shadow-lg">Create event</a></div>
                </div>
            </section>
        @else
            <div class="mb-7"><p class="text-sm font-semibold text-blue-600">Attendance workspace</p><h2 class="mt-1 text-3xl font-black">Your assigned events</h2></div>
        @endrole

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-5"><h3 class="font-extrabold text-slate-950">Event schedule</h3><p class="mt-1 text-xs text-slate-500">{{ $events->count() }} {{ Str::plural('event', $events->count()) }} available</p></div>
            <div class="divide-y divide-slate-100">
                @forelse($events as $event)
                    @if(auth()->user()->hasRole('manager') || auth()->user()->can('view', $event))
                        <article class="flex flex-col gap-4 px-6 py-5 transition hover:bg-slate-50 lg:flex-row lg:items-center">
                            <div class="flex min-w-0 flex-1 items-center gap-4"><div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-blue-50 text-lg font-black text-blue-700">{{ strtoupper(substr($event->title, 0, 1)) }}</div><div class="min-w-0"><h4 class="truncate text-base font-extrabold text-slate-900">{{ $event->title }}</h4><p class="mt-1 truncate text-xs text-slate-500">{{ $event->description ? Str::limit($event->description, 75) : 'No description provided' }}</p></div></div>
                            <div class="flex flex-wrap items-center gap-3 lg:justify-end"><span class="rounded-full px-3 py-1.5 text-[10px] font-black uppercase {{ $event->status === 'active' ? 'bg-emerald-50 text-emerald-700' : ($event->status === 'upcoming' ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-600') }}">{{ $event->status }}</span><span class="text-xs font-bold text-slate-500">{{ $event->event_date->format('M j, Y') }}</span><a href="{{ route('events.attendance', $event) }}" class="rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-extrabold text-white shadow-sm hover:bg-blue-700">Take attendance</a>@role('manager')<a href="{{ route('events.edit', $event) }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-extrabold text-slate-600 hover:bg-white">Edit</a>@endrole</div>
                        </article>
                    @endif
                @empty <div class="px-6 py-14 text-center"><p class="text-sm font-bold text-slate-700">No events yet</p><p class="mt-2 text-xs text-slate-500">Create an event to begin collecting registrations and attendance.</p>@role('manager')<a href="{{ route('events.create') }}" class="mt-5 inline-block rounded-xl bg-blue-600 px-5 py-3 text-sm font-extrabold text-white">Create your first event</a>@endrole</div> @endforelse
            </div>
        </div>
    @endhasanyrole
</x-app-layout>
