<x-app-layout>
    <x-slot name="header">Subscription Checkout</x-slot>
    <div class="py-12"><div class="mx-auto max-w-xl px-4">
        @if($errors->has('payment'))
            <div class="mb-5 rounded-2xl border border-red-300 bg-red-50 p-4 text-sm font-bold text-red-900">{{ $errors->first('payment') }}</div>
        @endif
        <div class="rounded-3xl border border-gray-100 bg-white p-8 shadow-xl">
            <p class="text-xs font-black uppercase tracking-widest text-blue-600">{{ $company->name }}</p>
            <h3 class="mt-3 text-4xl font-black text-gray-900">{{ $plan['name'] }}</h3>
            <p class="mt-3 text-2xl font-black">GHS {{ number_format($plan['price_minor'] / 100, 2) }} <span class="text-sm text-gray-400">per month</span></p>
            <p class="mt-5 text-sm leading-7 text-gray-600">Payment adds one month after your current end date, or from today if the subscription has expired. Plan changes take effect immediately.</p>
            <form method="POST" action="{{ route('billing.checkout.start', $planKey) }}" class="mt-8">@csrf<button class="w-full rounded-2xl bg-blue-600 px-6 py-4 font-black text-white">Pay with Paystack</button></form>
            <a href="{{ route('billing.index') }}" class="mt-4 block text-center text-sm font-bold text-gray-500">Back to billing</a>
        </div>
    </div></div>
</x-app-layout>
