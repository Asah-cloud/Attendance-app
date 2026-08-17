<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    @if($dashboardType === 'admin')
        <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div><p class="text-sm font-semibold text-blue-600">Platform overview</p><h2 class="mt-1 text-3xl font-black tracking-tight text-slate-950">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ str(auth()->user()->name)->before(' ') }}</h2><p class="mt-2 text-sm text-slate-500">Here is what is happening across Asah Apex today.</p></div>
            <a href="{{ route('companies.create') }}" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-3 text-sm font-extrabold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700">Add company</a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach([
                ['Companies', $stats['companies'], 'bg-blue-50 text-blue-700'],
                ['Active subscriptions', $stats['activeSubscriptions'], 'bg-emerald-50 text-emerald-700'],
                ['Total events', $stats['events'], 'bg-violet-50 text-violet-700'],
                ['Participants', number_format($stats['participants']), 'bg-amber-50 text-amber-700'],
                ["Today's check-ins", number_format($stats['checkInsToday']), 'bg-cyan-50 text-cyan-700'],
                ['Revenue collected', ($stats['revenueMinor'] / 100).' GHS', 'bg-rose-50 text-rose-700'],
            ] as [$label, $value, $tone])
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex items-start justify-between"><div><p class="text-xs font-extrabold uppercase tracking-[0.14em] text-slate-400">{{ $label }}</p><p class="mt-3 text-3xl font-black text-slate-950">{{ $value }}</p></div><span class="grid h-10 w-10 place-items-center rounded-xl {{ $tone }}"><span class="h-2.5 w-2.5 rounded-full bg-current"></span></span></div></div>
            @endforeach
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5"><div><h3 class="font-extrabold text-slate-950">Upcoming events</h3><p class="mt-1 text-xs text-slate-500">Next events across every company</p></div><a href="{{ route('events.index') }}" class="text-xs font-extrabold text-blue-600">View all</a></div>
                <div class="divide-y divide-slate-100">
                    @forelse($upcomingEvents as $event)
                        <a href="{{ route('events.attendance', $event) }}" class="flex items-center gap-4 px-6 py-4 hover:bg-slate-50"><span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-blue-50 text-center"><span class="text-[10px] font-black uppercase text-blue-500">{{ $event->event_date->format('M') }}</span><span class="-mt-1 text-lg font-black text-blue-800">{{ $event->event_date->format('d') }}</span></span><span class="min-w-0 flex-1"><span class="block truncate text-sm font-extrabold text-slate-900">{{ $event->title }}</span><span class="mt-1 block truncate text-xs text-slate-500">{{ $event->company?->name }} · {{ $event->registrations_count }} registrations</span></span><span class="text-slate-300">›</span></a>
                    @empty <p class="px-6 py-10 text-center text-sm text-slate-500">No upcoming events.</p> @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-5"><h3 class="font-extrabold text-slate-950">Subscriptions ending soon</h3><p class="mt-1 text-xs text-slate-500">Next 14 days</p></div>
                <div class="divide-y divide-slate-100">
                    @forelse($expiringCompanies as $company)
                        <a href="{{ route('companies.edit', $company) }}" class="block px-6 py-4 hover:bg-slate-50"><div class="flex items-center justify-between gap-3"><span class="truncate text-sm font-bold text-slate-800">{{ $company->name }}</span><span class="shrink-0 rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-black text-amber-700">{{ now()->startOfDay()->diffInDays($company->subscription_ends_at, false) }} days</span></div><p class="mt-1 text-xs text-slate-500">Ends {{ $company->subscription_ends_at->format('M j, Y') }}</p></a>
                    @empty <p class="px-6 py-10 text-center text-sm text-slate-500">No subscriptions ending soon.</p> @endforelse
                </div>
            </section>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-100 px-6 py-5"><h3 class="font-extrabold">Newest companies</h3></div><div class="divide-y divide-slate-100">@forelse($recentCompanies as $company)<div class="flex items-center justify-between px-6 py-4"><div><p class="text-sm font-bold">{{ $company->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $company->events_count }} events · {{ $company->users_count }} team members</p></div><span class="text-xs text-slate-400">{{ $company->created_at->diffForHumans() }}</span></div>@empty<p class="p-6 text-sm text-slate-500">No companies yet.</p>@endforelse</div></section>
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-100 px-6 py-5"><h3 class="font-extrabold">Recent payments</h3></div><div class="divide-y divide-slate-100">@forelse($recentPayments as $payment)<div class="flex items-center justify-between px-6 py-4"><div><p class="text-sm font-bold">{{ $payment->company?->name }}</p><p class="mt-1 text-xs text-slate-500">{{ ucfirst($payment->type) }} · {{ ucfirst($payment->plan_key) }}</p></div><div class="text-right"><p class="text-sm font-black text-emerald-700">{{ number_format($payment->amount_minor / 100, 2) }} {{ $payment->currency }}</p><p class="mt-1 text-xs text-slate-400">{{ $payment->paid_at?->diffForHumans() }}</p></div></div>@empty<p class="p-6 text-sm text-slate-500">No payments yet.</p>@endforelse</div></section>
        </div>

    @elseif($dashboardType === 'manager')
        @php
            $statusTotal = max(1, (int) $registrationStatuses->sum());
            $daysLeft = $company->subscription_ends_at ? now()->startOfDay()->diffInDays($company->subscription_ends_at, false) : null;
        @endphp
        <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div><p class="text-sm font-semibold text-blue-600">{{ $company->name }}</p><h2 class="mt-1 text-3xl font-black tracking-tight text-slate-950">Welcome back, {{ str(auth()->user()->name)->before(' ') }}</h2><p class="mt-2 text-sm text-slate-500">Your events, registrations and attendance at a glance.</p></div>
            <div class="flex gap-3"><a href="{{ route('events.create') }}" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-extrabold text-white shadow-lg shadow-blue-200 hover:bg-blue-700">Create event</a></div>
        </div>

        @if($daysLeft !== null && $daysLeft <= 14)
            <a href="{{ route('billing.index') }}" class="mb-6 flex items-center justify-between rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-900"><span><strong class="block text-sm">Subscription {{ $daysLeft < 0 ? 'expired' : 'ending soon' }}</strong><span class="text-xs">{{ $daysLeft < 0 ? 'Renew now to restore full access.' : $daysLeft.' days remaining.' }}</span></span><span class="text-sm font-extrabold">Manage billing →</span></a>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach([
                ['Total events', $stats['events'], 'Event workspace'],
                ['Upcoming events', $stats['upcomingEvents'], 'Ready to run'],
                ['Participants', number_format($stats['participants']), 'Company directory'],
                ['Registrations', number_format($stats['registrations']), 'Across all events'],
                ["Today's check-ins", number_format($stats['checkInsToday']), 'Recorded today'],
                ['Pending approval', number_format($stats['pending']), 'Needs your attention'],
            ] as [$label, $value, $hint])
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-extrabold uppercase tracking-[0.14em] text-slate-400">{{ $label }}</p><div class="mt-3 flex items-end justify-between gap-3"><p class="text-3xl font-black text-slate-950">{{ $value }}</p><p class="text-right text-[11px] font-semibold text-slate-400">{{ $hint }}</p></div></div>
            @endforeach
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5"><div><h3 class="font-extrabold">Upcoming events</h3><p class="mt-1 text-xs text-slate-500">Open an event to manage attendance</p></div><a href="{{ route('events.index') }}" class="text-xs font-extrabold text-blue-600">All events</a></div>
                <div class="divide-y divide-slate-100">@forelse($upcomingEvents as $event)<a href="{{ route('events.attendance', $event) }}" class="flex items-center gap-4 px-6 py-4 hover:bg-slate-50"><span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-blue-50 text-center"><span class="text-[10px] font-black uppercase text-blue-500">{{ $event->event_date->format('M') }}</span><span class="-mt-1 text-lg font-black text-blue-800">{{ $event->event_date->format('d') }}</span></span><span class="min-w-0 flex-1"><span class="block truncate text-sm font-extrabold">{{ $event->title }}</span><span class="mt-1 block text-xs text-slate-500">{{ $event->registrations_count }} registrations · {{ $event->attendances_count }} check-ins</span></span><span class="text-slate-300">›</span></a>@empty<p class="px-6 py-10 text-center text-sm text-slate-500">No upcoming events. Create your first event.</p>@endforelse</div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="font-extrabold">Registration status</h3><p class="mt-1 text-xs text-slate-500">All company events</p>
                <div class="mt-6 space-y-5">
                    @forelse($registrationStatuses as $status => $total)
                        @php $percent = round(($total / $statusTotal) * 100); @endphp
                        <div><div class="mb-2 flex justify-between text-xs"><span class="font-bold capitalize text-slate-600">{{ $status }}</span><span class="font-black text-slate-900">{{ $total }} · {{ $percent }}%</span></div><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $status === 'confirmed' ? 'bg-emerald-500' : ($status === 'pending' ? 'bg-amber-500' : ($status === 'waitlisted' ? 'bg-blue-500' : 'bg-slate-400')) }}" style="width: {{ $percent }}%"></div></div></div>
                    @empty <p class="py-8 text-center text-sm text-slate-500">No registrations yet.</p> @endforelse
                </div>
            </section>
        </div>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5"><div><h3 class="font-extrabold">Recent registrations</h3><p class="mt-1 text-xs text-slate-500">Newest people across your events</p></div></div>
            <div class="grid divide-y divide-slate-100 lg:grid-cols-2 lg:divide-x lg:divide-y-0">@forelse($recentRegistrations->chunk(3) as $chunk)<div class="divide-y divide-slate-100">@foreach($chunk as $registration)<div class="flex items-center gap-3 px-6 py-4"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-slate-100 text-sm font-black text-slate-600">{{ strtoupper(substr($registration->participant->name, 0, 1)) }}</span><div class="min-w-0 flex-1"><p class="truncate text-sm font-bold">{{ $registration->participant->name }}</p><p class="truncate text-xs text-slate-500">{{ $registration->event->title }} · {{ $registration->registered_at?->diffForHumans() }}</p></div><span class="rounded-full px-2.5 py-1 text-[10px] font-black capitalize {{ $registration->status === 'confirmed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $registration->status }}</span></div>@endforeach</div>@empty<p class="p-8 text-sm text-slate-500">No registrations yet.</p>@endforelse</div>
        </section>

    @else
        <div class="mb-8"><p class="text-sm font-semibold text-blue-600">Staff workspace</p><h2 class="mt-1 text-3xl font-black">Your assigned events</h2><p class="mt-2 text-sm text-slate-500">Choose an event to begin marking attendance.</p></div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">@forelse($events as $event)<a href="{{ route('events.attendance', $event) }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"><div class="flex justify-between gap-3"><div><p class="text-xs font-black uppercase tracking-wider text-blue-600">{{ ucfirst($event->status) }}</p><h3 class="mt-2 text-lg font-black">{{ $event->title }}</h3></div><span class="text-sm font-bold text-slate-400">{{ $event->event_date->format('M d') }}</span></div><p class="mt-5 text-xs text-slate-500">{{ $event->confirmed_participants_count }} participants · {{ $event->attendances_count }} check-ins</p></a>@empty<div class="rounded-2xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500 sm:col-span-2">No events have been assigned to you.</div>@endforelse</div>
    @endif
</x-app-layout>
