<x-public-layout :title="'Test checkout — '.$plan['name']" :noindex="true">
    <section class="min-h-screen bg-slate-50 px-5 pb-20 pt-32">
        <div class="mx-auto max-w-xl">
            <div class="mb-5 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm font-bold text-amber-900">
                Test mode: this checkout does not collect money or payment details.
            </div>
            <div class="rounded-[2rem] border border-slate-200 bg-white p-8 shadow-xl shadow-slate-950/5">
                <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-blue-600">Selected plan</p>
                <h1 class="mt-3 text-4xl font-extrabold text-slate-950">{{ $plan['name'] }}</h1>
                <p class="mt-3 text-3xl font-black text-slate-900">GHS {{ number_format($plan['price_minor'] / 100, 2) }} <span class="text-sm font-semibold text-slate-400">per month</span></p>
                <p class="mt-5 text-sm leading-7 text-slate-600">{{ $plan['description'] }}</p>
                <div class="mt-8 rounded-2xl bg-slate-50 p-5 text-sm text-slate-600">Completing this simulation unlocks manager and company account setup. Your subscription will run for one month.</div>
                <form method="POST" action="{{ route('checkout.test-payment', $planKey) }}" class="mt-8">
                    @csrf
                    <button class="w-full rounded-2xl bg-blue-600 px-6 py-4 text-sm font-extrabold text-white shadow-lg transition hover:bg-blue-500">Approve test payment</button>
                </form>
                <a href="{{ route('pricing') }}" class="mt-4 block text-center text-sm font-bold text-slate-500 hover:text-slate-800">Choose another plan</a>
            </div>
        </div>
    </section>
</x-public-layout>
