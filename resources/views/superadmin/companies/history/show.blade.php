<x-app-layout>
    <x-slot name="header">{{ $company->name }}</x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-extrabold uppercase tracking-wider text-blue-600">Archived company</p>
                    <h2 class="mt-1 text-2xl font-black">{{ $company->name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">Archived {{ $company->deleted_at->format('M j, Y') }} · {{ $company->events_count }} events · {{ $company->participants_count }} attendees · {{ $company->users_count }} staff accounts</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('companies.history.index') }}" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-xs font-extrabold uppercase tracking-wider text-slate-600 shadow-sm hover:border-blue-300 hover:text-blue-700">Back to history</a>
                    <form action="{{ route('companies.history.restore', $company->id) }}" method="POST" onsubmit="return confirm('Restore {{ $company->name }}? It will reappear in Manage companies and its staff will regain access.')">
                        @csrf
                        <button type="submit" class="rounded-xl bg-emerald-50 px-5 py-3 text-xs font-extrabold uppercase tracking-wider text-emerald-700 shadow-sm hover:bg-emerald-100">Restore</button>
                    </form>
                    <form action="{{ route('companies.history.destroy', $company->id) }}" method="POST" onsubmit="return confirm('Permanently delete {{ $company->name }}? This removes the company, its events, attendees, and staff accounts forever. This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-xl bg-red-50 px-5 py-3 text-xs font-extrabold uppercase tracking-wider text-red-600 shadow-sm hover:bg-red-100">Delete forever</button>
                    </form>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-5"><h3 class="font-extrabold text-slate-950">Events</h3></div>
                <div class="divide-y divide-slate-100">
                    @forelse($events as $event)
                        <div class="flex items-center justify-between px-6 py-4">
                            <div>
                                <p class="text-sm font-bold text-slate-900">{{ $event->title }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $event->event_date->format('M j, Y') }}@if($event->location) · {{ $event->location }}@endif</p>
                            </div>
                            <p class="text-xs font-bold text-slate-500">{{ $event->registrations_count }} registrations · {{ $event->attendances_count }} check-ins</p>
                        </div>
                    @empty
                        <p class="px-6 py-8 text-center text-sm text-slate-500">No events for this company.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-5"><h3 class="font-extrabold text-slate-950">Attendees</h3></div>
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-[10px] uppercase text-gray-400 font-black tracking-widest">
                            <th class="p-5">Name</th>
                            <th class="p-5">Email</th>
                            <th class="p-5">Phone</th>
                            <th class="p-5">Category</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($participants as $participant)
                            <tr>
                                <td class="p-5 font-bold text-gray-900">{{ $participant->name }}</td>
                                <td class="p-5 text-sm text-gray-500">{{ $participant->email }}</td>
                                <td class="p-5 text-sm text-gray-500">{{ $participant->phone }}</td>
                                <td class="p-5 text-sm text-gray-500">{{ $participant->category }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-8 text-center text-sm text-slate-500">No attendees for this company.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if($participants->hasPages())
                    <div class="border-t border-slate-100 px-6 py-4">{{ $participants->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
