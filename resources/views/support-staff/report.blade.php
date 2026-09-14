<x-app-layout>
    <x-slot name="header">Staff report</x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-6xl space-y-8 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-amber-700 to-slate-900 p-7 text-white shadow-xl sm:p-9">
                <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-center">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.2em] text-amber-200">Event support staff</p>
                        <h1 class="mt-2 text-3xl font-black">{{ $event->title }}</h1>
                        <p class="mt-3 text-sm text-amber-100">Who has collected their badge and checked in.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('support-staff.checkin', $event) }}" class="rounded-xl bg-white px-5 py-3 text-sm font-black text-slate-900">Staff check-in</a>
                        <a href="{{ route('support-staff.report.csv', $event) }}" class="rounded-xl border border-white/30 px-5 py-3 text-sm font-black text-white">Download CSV</a>
                    </div>
                </div>
            </section>

            <div class="grid gap-4 sm:grid-cols-3">
                <x-summary-card title="Total staff" :value="$total" color="blue" icon="users" />
                <x-summary-card title="Checked in" :value="$checkedIn->count()" color="green" icon="check" />
                <x-summary-card title="Not checked in" :value="$notCheckedIn->count()" color="red" />
            </div>

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-slate-50/70 p-6"><h2 class="text-xs font-black uppercase tracking-widest text-slate-700">Checked in ({{ $checkedIn->count() }})</h2></div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-6 py-4">Staff</th><th class="px-6 py-4">Department</th><th class="px-6 py-4">Category</th><th class="px-6 py-4">Checked in at</th></tr></thead><tbody class="divide-y divide-slate-100">
                    @forelse($checkedIn as $person)<tr><td class="px-6 py-4"><strong class="text-slate-900">{{ $person->name }}</strong><div class="font-mono text-xs text-slate-500">{{ $person->staff_code }}</div></td><td class="px-6 py-4">{{ $person->department ?: '—' }}</td><td class="px-6 py-4">{{ $person->category }}</td><td class="px-6 py-4">{{ $person->attendances->first()?->created_at?->format('j M, g:i A') }}</td></tr>
                    @empty<tr><td colspan="4" class="px-6 py-12 text-center text-slate-500">No one has checked in yet.</td></tr>@endforelse
                </tbody></table></div>
            </section>

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-slate-50/70 p-6"><h2 class="text-xs font-black uppercase tracking-widest text-slate-700">Not checked in ({{ $notCheckedIn->count() }})</h2></div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-6 py-4">Staff</th><th class="px-6 py-4">Department</th><th class="px-6 py-4">Category</th></tr></thead><tbody class="divide-y divide-slate-100">
                    @forelse($notCheckedIn as $person)<tr><td class="px-6 py-4"><strong class="text-slate-900">{{ $person->name }}</strong><div class="font-mono text-xs text-slate-500">{{ $person->staff_code }}</div></td><td class="px-6 py-4">{{ $person->department ?: '—' }}</td><td class="px-6 py-4">{{ $person->category }}</td></tr>
                    @empty<tr><td colspan="3" class="px-6 py-12 text-center text-slate-500">Everyone has checked in.</td></tr>@endforelse
                </tbody></table></div>
            </section>
        </div>
    </div>
</x-app-layout>
