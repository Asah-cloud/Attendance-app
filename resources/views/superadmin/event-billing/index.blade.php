<x-app-layout>
    <x-slot name="header">Event Attendee Billing</x-slot>
    <div class="py-10"><div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        @if(session('success'))<div class="mb-5 rounded-2xl bg-green-50 p-4 font-bold text-green-700">{{ session('success') }}</div>@endif

        <div class="mb-6"><h1 class="text-2xl font-black text-slate-900">Attendee bills awaiting action</h1><p class="text-sm text-slate-500">Pending payments and refunds due across all companies.</p></div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wider text-slate-500"><tr>
                <th class="px-5 py-4">Company</th>
                <th class="px-5 py-4">Event</th>
                <th class="px-5 py-4">Status</th>
                <th class="px-5 py-4">Amount</th>
                <th class="px-5 py-4">Refund due</th>
                <th class="px-5 py-4">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">@forelse($charges as $charge)
                <tr>
                    <td class="px-5 py-4 font-bold text-slate-900">{{ $charge->company->name }}</td>
                    <td class="px-5 py-4 text-slate-700">{{ $charge->event->title }}</td>
                    <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold uppercase">{{ str_replace('_', ' ', $charge->status) }}</span></td>
                    <td class="px-5 py-4">{{ $charge->currency }} {{ number_format($charge->amount_minor / 100, 2) }}</td>
                    <td class="px-5 py-4">{{ $charge->refund_amount_minor ? $charge->currency.' '.number_format($charge->refund_amount_minor / 100, 2) : '—' }}</td>
                    <td class="px-5 py-4">
                        @if($charge->status === 'refund_due')
                            <form method="POST" action="{{ route('attendee-billing.refund', $charge) }}" onsubmit="return confirm('Confirm the refund has been sent outside the app?')">@csrf<button class="text-xs font-bold text-emerald-700">Mark refunded</button></form>
                        @endif
                    </td>
                </tr>
            @empty<tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">Nothing awaiting action.</td></tr>@endforelse</tbody>
        </table></div></div><div class="mt-5">{{ $charges->links() }}</div>
    </div></div>
</x-app-layout>
