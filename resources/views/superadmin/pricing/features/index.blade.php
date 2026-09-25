<x-app-layout>
    <x-slot name="header">Advanced Features</x-slot>
    <div class="py-10"><div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">

        <div class="mb-6 flex items-center justify-between gap-4">
            <div><p class="text-xs font-extrabold uppercase tracking-wider text-blue-600">Pricing</p><h2 class="mt-1 text-2xl font-black">Advanced features</h2><p class="mt-1 text-sm text-slate-500">Paid add-ons managers can select per event, on top of the standard attendance tools and per-attendee bill.</p></div>
            <a href="{{ route('pricing.features.create') }}" class="rounded-xl bg-blue-600 px-5 py-3 text-xs font-extrabold uppercase tracking-wider text-white shadow-lg shadow-blue-200 hover:-translate-y-0.5 hover:bg-blue-700">Add feature</a>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wider text-slate-500"><tr>
                <th class="px-5 py-4">Feature</th>
                <th class="px-5 py-4">Cost / event</th>
                <th class="px-5 py-4">Status</th>
                <th class="px-5 py-4"></th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">@forelse($features as $feature)
                <tr>
                    <td class="px-5 py-4 font-bold text-slate-900">{{ $feature->name }}<span class="ml-2 font-mono text-[10px] font-normal text-slate-400">{{ $feature->key }}</span></td>
                    <td class="px-5 py-4">GHS {{ number_format($feature->cost_minor / 100, 2) }}</td>
                    <td class="px-5 py-4">{{ $feature->is_active ? 'Active' : 'Inactive' }}</td>
                    <td class="px-5 py-4 text-right">
                        <a href="{{ route('pricing.features.edit', $feature) }}" class="text-xs font-bold text-blue-700">Edit</a>
                        <form method="POST" action="{{ route('pricing.features.destroy', $feature) }}" class="ml-4 inline" onsubmit="return confirm('Delete {{ $feature->name }}? This cannot be undone.')">@csrf @method('DELETE')<button class="text-xs font-bold text-red-600">Delete</button></form>
                    </td>
                </tr>
            @empty<tr><td colspan="4" class="px-5 py-12 text-center text-slate-500">No advanced features yet.</td></tr>@endforelse</tbody>
        </table></div></div>
    </div></div>
</x-app-layout>
