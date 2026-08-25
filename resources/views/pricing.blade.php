<x-public-layout title="Pricing — Asah Apex Attendance">
    <section class="bg-[#071426] px-5 pb-24 pt-40 text-center text-white">
        <p class="text-xs font-extrabold uppercase tracking-[0.25em] text-amber-300">Straightforward pricing</p>
        <h1 class="mx-auto mt-5 max-w-3xl text-5xl font-extrabold tracking-[-0.05em] sm:text-6xl">Choose your workspace plan.</h1>
        <p class="mx-auto mt-6 max-w-2xl text-lg leading-8 text-slate-300">Select a plan, complete test checkout, and create your company manager account.</p>
    </section>

    <section class="mx-auto -mt-12 max-w-7xl px-5 pb-24 lg:px-8">
        <div class="grid items-stretch gap-6 lg:grid-cols-3">
            @foreach($plans as $key => $plan)
                @php $featured = $plan['featured'] ?? false; @endphp
                <article class="relative flex flex-col rounded-[2rem] border {{ $featured ? 'border-blue-500 bg-[#0d2140] text-white shadow-2xl shadow-blue-950/20 lg:-translate-y-4' : 'border-slate-200 bg-white text-[#071426] shadow-xl shadow-slate-950/5' }} p-8">
                    @if($featured)<span class="absolute right-7 top-7 rounded-full bg-amber-300 px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider text-amber-950">Most popular</span>@endif
                    <p class="text-sm font-extrabold {{ $featured ? 'text-blue-300' : 'text-blue-600' }}">{{ $plan['name'] }}</p>
                    <h2 class="mt-7 text-4xl font-extrabold">GHS {{ number_format($plan['price_minor'] / 100, 0) }}<span class="text-sm font-semibold opacity-60">/month</span></h2>
                    <p class="mt-4 min-h-14 text-sm leading-7 {{ $featured ? 'text-slate-300' : 'text-slate-600' }}">{{ $plan['description'] }}</p>
                    <ul class="mt-8 flex-1 space-y-4 text-sm font-semibold">
                        @foreach($plan['features'] as $feature)<li class="flex gap-3"><span class="text-blue-500">✓</span><span>{{ $feature }}</span></li>@endforeach
                    </ul>
                    <a href="{{ route('checkout', $key) }}" class="mt-9 rounded-2xl px-5 py-4 text-center text-sm font-extrabold transition {{ $featured ? 'bg-blue-600 text-white hover:bg-blue-500' : 'bg-[#071426] text-white hover:bg-slate-800' }}">Choose {{ $plan['name'] }}</a>
                </article>
            @endforeach
        </div>

        <div class="mt-10 rounded-[2rem] border border-blue-200 bg-blue-50/40 p-8">
            <p class="text-sm font-extrabold text-blue-700">No subscription</p>
            <h2 class="mt-2 text-3xl font-extrabold text-[#071426]">Pay only for the events you run.</h2>
            <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600">No monthly fee, no event cap. You're billed per confirmed attendee, once per event, using graduated rates that drop as your attendee count grows.</p>
            <div class="mt-6 overflow-hidden rounded-2xl border border-blue-100 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-blue-50 text-xs uppercase text-blue-700"><tr><th class="p-4">Attendees</th><th class="p-4 text-right">Rate per attendee</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($payPerEventTiers as $tier)
                            <tr><td class="p-4 font-semibold">{{ $tier->band_from }}{{ $tier->band_to ? '–'.$tier->band_to : '+' }}</td><td class="p-4 text-right font-black">GHS {{ number_format($tier->rate_minor / 100, 2) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <form method="POST" action="{{ route('onboarding.pay-per-event') }}" class="mt-6">
                @csrf
                <button class="rounded-2xl bg-[#071426] px-6 py-4 text-sm font-extrabold text-white hover:bg-slate-800">Start with pay-per-event</button>
            </form>
        </div>

        <div class="mt-10 rounded-3xl border border-amber-200 bg-amber-50 p-8 text-center">
            <p class="font-extrabold text-amber-950">Test mode is active.</p>
            <p class="mt-2 text-sm leading-7 text-amber-800">No real charge will be made. The simulated checkout will be replaced by Card and Mobile Money payment before launch.</p>
        </div>
    </section>
</x-public-layout>
