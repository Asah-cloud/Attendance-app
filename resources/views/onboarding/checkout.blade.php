<x-public-layout :title="'Test checkout — '.$plan['name']" :noindex="true">
    <section class="min-h-screen bg-slate-50 px-5 pb-20 pt-32">
        <div class="mx-auto max-w-xl">
            @if($errors->has('payment'))
                <div class="mb-5 rounded-2xl border border-red-300 bg-red-50 p-4 text-sm font-bold text-red-900">{{ $errors->first('payment') }}</div>
            @endif
            <div class="rounded-[2rem] border border-slate-200 bg-white p-8 shadow-xl shadow-slate-950/5">
                <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-blue-600">Selected plan</p>
                <h1 class="mt-3 text-4xl font-extrabold text-slate-950">{{ $plan['name'] }}</h1>
                <p class="mt-3 text-3xl font-black text-slate-900">GHS {{ number_format($plan['price_minor'] / 100, 2) }} <span class="text-sm font-semibold text-slate-400">per month</span></p>
                <p class="mt-5 text-sm leading-7 text-slate-600">{{ $plan['description'] }}</p>
                <div class="mt-8 rounded-2xl bg-slate-50 p-5 text-sm text-slate-600">You'll create your manager and company account right after payment. Your subscription will run for one month.</div>
                <form method="POST" action="{{ route('checkout.start', $planKey) }}" class="mt-8">
                    @csrf
                    <div class="text-left">
                        <x-input-label for="email" :value="__('Work email')" />
                        <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autofocus />
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                        <p class="mt-1 text-xs text-slate-400">This is the email you'll pay with and register your account with.</p>
                    </div>
                    <button class="mt-5 w-full rounded-2xl bg-blue-600 px-6 py-4 text-sm font-extrabold text-white shadow-lg transition hover:bg-blue-500">Pay with Paystack</button>
                </form>
                <a href="{{ route('pricing') }}" class="mt-4 block text-center text-sm font-bold text-slate-500 hover:text-slate-800">Choose another plan</a>
            </div>
        </div>
    </section>
</x-public-layout>
