<x-app-layout>
    <x-slot name="header">Subscription Plans</x-slot>
    <div class="py-10"><div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        @if(session('success'))<div class="mb-5 rounded-2xl bg-green-50 p-4 font-bold text-green-700">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-5 rounded-2xl bg-red-50 p-4 font-bold text-red-700">{{ session('error') }}</div>@endif

        <div class="mb-6 flex items-center justify-between gap-4">
            <div><p class="text-xs font-extrabold uppercase tracking-wider text-blue-600">Pricing</p><h2 class="mt-1 text-2xl font-black">Subscription plans</h2><p class="mt-1 text-sm text-slate-500">Prices, limits, and feature bullets shown on the public pricing page and billing screens.</p></div>
            <a href="{{ route('pricing.plans.create') }}" class="rounded-xl bg-blue-600 px-5 py-3 text-xs font-extrabold uppercase tracking-wider text-white shadow-lg shadow-blue-200 hover:-translate-y-0.5 hover:bg-blue-700">Add plan</a>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wider text-slate-500"><tr>
                <th class="px-5 py-4">Plan</th>
                <th class="px-5 py-4">Price / month</th>
                <th class="px-5 py-4">Event limit</th>
                <th class="px-5 py-4">Participant limit</th>
                <th class="px-5 py-4">Featured</th>
                <th class="px-5 py-4"></th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">@forelse($plans as $plan)
                <tr>
                    <td class="px-5 py-4 font-bold text-slate-900">{{ $plan->name }}<span class="ml-2 font-mono text-[10px] font-normal text-slate-400">{{ $plan->key }}</span></td>
                    <td class="px-5 py-4">GHS {{ number_format($plan->price_minor / 100, 2) }}</td>
                    <td class="px-5 py-4">{{ $plan->event_limit }}</td>
                    <td class="px-5 py-4">{{ number_format($plan->participant_limit) }}</td>
                    <td class="px-5 py-4">{{ $plan->featured ? 'Yes' : '—' }}</td>
                    <td class="px-5 py-4 text-right">
                        <a href="{{ route('pricing.plans.edit', $plan) }}" class="text-xs font-bold text-blue-700">Edit</a>
                        <form method="POST" action="{{ route('pricing.plans.destroy', $plan) }}" class="ml-4 inline" onsubmit="return confirm('Delete {{ $plan->name }}? This cannot be undone.')">@csrf @method('DELETE')<button class="text-xs font-bold text-red-600">Delete</button></form>
                    </td>
                </tr>
            @empty<tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">No plans yet.</td></tr>@endforelse</tbody>
        </table></div></div>
    </div></div>
</x-app-layout>
