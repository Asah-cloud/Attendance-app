<x-app-layout>
    <x-slot name="header">Company Pricing</x-slot>
    <div class="py-10"><div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

        <p class="text-sm text-gray-500">Pick a company to set a negotiated per-attendee rate for it, or to override pricing on one of its individual events.</p>

        <form method="GET" class="mt-5">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search companies..." class="w-full max-w-sm rounded-xl border-gray-200 p-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </form>

        <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wider text-slate-500"><tr>
                <th class="px-5 py-4">Company</th>
                <th class="px-5 py-4">Billing mode</th>
                <th class="px-5 py-4"></th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">@forelse($companies as $company)
                <tr>
                    <td class="px-5 py-4 font-bold text-slate-900">{{ $company->name }}</td>
                    <td class="px-5 py-4 text-slate-600">{{ $company->isPayPerEvent() ? 'Pay per event' : 'Subscription' }}</td>
                    <td class="px-5 py-4 text-right"><a href="{{ route('pricing.companies.show', $company) }}" class="text-xs font-bold text-blue-700">Manage pricing &rarr;</a></td>
                </tr>
            @empty<tr><td colspan="3" class="px-5 py-12 text-center text-slate-500">No companies found.</td></tr>@endforelse</tbody>
        </table></div></div><div class="mt-5">{{ $companies->links() }}</div>
    </div></div>
</x-app-layout>
