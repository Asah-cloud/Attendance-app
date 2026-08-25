<x-app-layout>
    <x-slot name="header">Company history</x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-6 flex items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-extrabold uppercase tracking-wider text-blue-600">Archived tenants</p>
                    <h2 class="mt-1 text-2xl font-black">Company history</h2>
                    <p class="mt-1 text-sm text-slate-500">Archived companies stay here, with their events and attendees intact, until you permanently delete them.</p>
                </div>
                <a href="{{ route('companies.index') }}" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-xs font-extrabold uppercase tracking-wider text-slate-600 shadow-sm hover:border-blue-300 hover:text-blue-700">Back to companies</a>
            </div>

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-2xl">
                @if($companies->isEmpty())
                    <p class="p-10 text-center text-sm font-semibold text-slate-500">No archived companies yet.</p>
                @else
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100 text-[10px] uppercase text-gray-400 font-black tracking-widest">
                                <th class="p-6">Company Name</th>
                                <th class="p-6">Archived</th>
                                <th class="p-6">Events</th>
                                <th class="p-6">Attendees</th>
                                <th class="p-6">Staff</th>
                                <th class="p-6 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($companies as $company)
                                <tr>
                                    <td class="p-6 font-bold text-gray-900">{{ $company->name }}</td>
                                    <td class="p-6 text-sm text-gray-500">{{ $company->deleted_at->format('M j, Y') }}</td>
                                    <td class="p-6 text-sm font-bold">{{ number_format($company->events_count) }}</td>
                                    <td class="p-6 text-sm font-bold">{{ number_format($company->participants_count) }}</td>
                                    <td class="p-6 text-sm font-bold">{{ number_format($company->users_count) }}</td>
                                    <td class="p-6">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('companies.history.show', $company->id) }}" class="text-xs bg-slate-50 text-slate-600 hover:bg-slate-100 px-3 py-1.5 rounded-xl font-black uppercase tracking-wider transition-all">View</a>
                                            <form action="{{ route('companies.history.restore', $company->id) }}" method="POST" onsubmit="return confirm('Restore {{ $company->name }}? It will reappear in Manage companies and its staff will regain access.')">
                                                @csrf
                                                <button type="submit" class="text-xs bg-emerald-50 text-emerald-700 hover:bg-emerald-100 px-3 py-1.5 rounded-xl font-black uppercase tracking-wider transition-all">Restore</button>
                                            </form>
                                            <form action="{{ route('companies.history.destroy', $company->id) }}" method="POST" onsubmit="return confirm('Permanently delete {{ $company->name }}? This removes the company, its events, attendees, and staff accounts forever. This cannot be undone.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs bg-red-50 text-red-600 hover:bg-red-100 px-3 py-1.5 rounded-xl font-black uppercase tracking-wider transition-all">Delete forever</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
