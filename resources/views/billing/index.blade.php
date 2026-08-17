<x-app-layout>
    <x-slot name="header">Billing & Subscription</x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            @if(session('success'))<div class="rounded-2xl bg-green-50 p-4 font-bold text-green-700">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="rounded-2xl bg-red-50 p-4 font-bold text-red-700">{{ session('error') }}</div>@endif

            @php
                $currentPlan = $plans[$company->plan_key] ?? null;
                $expired = $company->subscription_ends_at && $company->subscription_ends_at->endOfDay()->isPast();
            @endphp

            <section class="grid gap-6 lg:grid-cols-[1.2fr_.8fr]">
                <div class="rounded-3xl bg-[#071426] p-8 text-white shadow-xl">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.2em] text-blue-300">Current subscription</p>
                            <p class="mt-2 text-sm font-bold text-slate-300">{{ $company->name }}</p>
                            <h3 class="mt-3 text-4xl font-black">{{ $currentPlan['name'] ?? ucfirst($company->plan_key ?? 'Unassigned') }}</h3>
                            <p class="mt-2 text-slate-300">GHS {{ number_format(($company->plan_price_minor ?? 0) / 100, 2) }} per month</p>
                        </div>
                        <span class="rounded-full px-4 py-2 text-xs font-black uppercase {{ $expired ? 'bg-red-500 text-white' : 'bg-green-400/20 text-green-300' }}">{{ $expired ? 'Expired' : 'Active' }}</span>
                    </div>
                    <div class="mt-8 grid gap-4 sm:grid-cols-3">
                        <div><p class="text-xs text-slate-400">Event allowance</p><p class="mt-1 text-xl font-black">{{ $company->event_limit }}</p></div>
                        <div><p class="text-xs text-slate-400">Access until</p><p class="mt-1 text-xl font-black">{{ $company->subscription_ends_at?->format('M d, Y') ?? '—' }}</p></div>
                        <div><p class="text-xs text-slate-400">Automatic renewal</p><p class="mt-1 text-xl font-black">{{ $company->subscription_auto_renews ? 'On' : 'Off' }}</p></div>
                    </div>
                </div>

                <div class="rounded-3xl border border-gray-100 bg-white p-7 shadow-sm">
                    <h3 class="font-black text-gray-900">Billing contact</h3>
                    <form method="POST" action="{{ route('billing.contact.update') }}" class="mt-5 space-y-4">
                        @csrf @method('PATCH')
                        <input type="email" name="email" value="{{ old('email', $company->email) }}" required class="w-full rounded-xl border-gray-200">
                        <x-input-error :messages="$errors->get('email')" />
                        <button class="w-full rounded-xl bg-gray-900 px-4 py-3 text-sm font-bold text-white">Update billing contact</button>
                    </form>
                    <div class="mt-5 border-t border-gray-100 pt-5">
                        @if($company->subscription_auto_renews)
                            <form method="POST" action="{{ route('billing.cancel') }}">@csrf<button class="text-sm font-bold text-red-600">Cancel automatic renewal</button></form>
                        @else
                            <form method="POST" action="{{ route('billing.resume') }}">@csrf<button class="text-sm font-bold text-blue-600">Resume automatic renewal</button></form>
                            <p class="mt-2 text-xs text-gray-500">Your existing access remains available through the current end date.</p>
                        @endif
                    </div>
                </div>
            </section>

            <section>
                <h3 class="mb-4 text-lg font-black text-gray-900">Renew or change plan</h3>
                <div class="grid gap-5 md:grid-cols-3">
                    @foreach($plans as $key => $plan)
                        <article class="group rounded-3xl border {{ $company->plan_key === $key ? 'border-blue-500 ring-2 ring-blue-100' : 'border-gray-100' }} bg-white p-6 shadow-sm hover:-translate-y-1 hover:shadow-xl">
                            <p class="font-black text-blue-700">{{ $plan['name'] }}</p>
                            <p class="mt-3 text-3xl font-black text-gray-900">GHS {{ number_format($plan['price_minor'] / 100, 0) }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $plan['event_limit'] }} events · monthly</p>
                            <a href="{{ route('billing.checkout', $key) }}" class="mt-6 block rounded-xl bg-blue-600 px-4 py-3 text-center text-sm font-bold text-white">{{ $company->plan_key === $key ? 'Renew plan' : 'Change to this plan' }}</a>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 p-6"><h3 class="font-black text-gray-900">Payment history</h3></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="p-4">Date</th><th class="p-4">Plan</th><th class="p-4">Type</th><th class="p-4">Reference</th><th class="p-4 text-right">Amount</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($payments as $payment)
                                <tr><td class="p-4">{{ $payment->paid_at->format('M d, Y H:i') }}</td><td class="p-4 font-bold">{{ $plans[$payment->plan_key]['name'] ?? ucfirst($payment->plan_key) }}</td><td class="p-4 capitalize">{{ str_replace('_', ' ', $payment->type) }}</td><td class="p-4 font-mono text-xs">{{ $payment->payment_reference }}</td><td class="p-4 text-right font-black">{{ $payment->currency }} {{ number_format($payment->amount_minor / 100, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="p-8 text-center text-gray-500">No payments recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($payments->hasPages())<div class="border-t border-gray-100 p-4">{{ $payments->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
