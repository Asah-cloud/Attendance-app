<x-app-layout>
    <x-slot name="header">Staff check-in</x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-8 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-amber-700 to-slate-900 p-7 text-white shadow-xl sm:p-9">
                <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-center">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.2em] text-amber-200">Event support staff</p>
                        <h1 class="mt-2 text-3xl font-black">{{ $event->title }}</h1>
                        <p class="mt-3 text-sm text-amber-100">Check staff in once, when they collect their badge. This is separate from attendee attendance and does not repeat daily.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @can('scanAttendance', $event)
                            <a href="{{ route('support-staff.checkin.scanner', $event) }}" class="rounded-xl bg-white px-5 py-3 text-sm font-black text-slate-900">Open Staff scanner</a>
                        @endcan
                        <a href="{{ route('support-staff.report', $event) }}" class="rounded-xl border border-white/30 px-5 py-3 text-sm font-black text-white">Staff report</a>
                        <a href="{{ route('events.staff-badges', $event) }}" class="rounded-xl border border-white/30 px-5 py-3 text-sm font-black text-white">Staff badge studio</a>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-slate-50/70 p-6">
                    <h2 class="text-xs font-black uppercase tracking-widest text-slate-700">Staff assigned to this event</h2>
                    <p class="mt-2 text-sm text-slate-500">Not seeing someone? Import or assign them from the <a href="{{ route('support-staff.index') }}" class="font-bold text-blue-700 underline">Event Staff</a> page first.</p>
                </div>
                <div class="p-6">
                    <livewire:attendance-search :event="$event" mode="staff" :key="'staff-checkin-'.$event->id" />
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
