<x-public-layout
    title="Pricing — Asah Apex Attendance"
    description="No subscription. Register your company free, pay only per confirmed attendee, once per event, and add advanced features only when you need them."
>
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "Product",
        "name": "Asah Apex Attendance",
        "description": "Event attendance and check-in software with QR check-in, live tracking, and reporting.",
        "offers": {
            "@@type": "Offer",
            "name": "Pay-per-event attendance billing",
            "priceCurrency": "GHS",
            "url": "{{ route('onboarding.pay-per-event') }}"
        }
    }
    </script>
    <section class="bg-[#071426] px-5 pb-24 pt-40 text-center text-white">
        <p class="text-xs font-extrabold uppercase tracking-[0.25em] text-amber-300">No subscription</p>
        <h1 class="mx-auto mt-5 max-w-3xl text-5xl font-extrabold tracking-[-0.05em] sm:text-6xl">Register free. Pay only for the events you run.</h1>
        <p class="mx-auto mt-6 max-w-2xl text-lg leading-8 text-slate-300">No monthly fee, no event cap. Create your company account for free, then pay per confirmed attendee — once per event — and add advanced features only when you need them.</p>
    </section>

    <section class="mx-auto -mt-12 max-w-5xl px-5 pb-16 lg:px-8">
        <div class="rounded-[2rem] border border-blue-200 bg-blue-50/40 p-8">
            <p class="text-sm font-extrabold text-blue-700">Standard, always included</p>
            <h2 class="mt-2 text-3xl font-extrabold text-[#071426]">Billed per confirmed attendee.</h2>
            <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600">Graduated rates that drop as your attendee count grows. Attendance, QR/manual check-in, registration forms, and daily & summary reports are included at no extra cost on every event.</p>
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
                <button class="rounded-2xl bg-[#071426] px-6 py-4 text-sm font-extrabold text-white hover:bg-slate-800">Register your company free</button>
            </form>
        </div>

        @if($features->isNotEmpty())
            <div class="mt-10">
                <p class="text-sm font-extrabold text-blue-700">Advanced, add only what you need</p>
                <h2 class="mt-2 text-3xl font-extrabold text-[#071426]">Optional features, priced per event.</h2>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600">Pick these when you finalize an event's bill — pay only for the events where you use them.</p>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    @foreach($features as $feature)
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <p class="font-extrabold text-[#071426]">{{ $feature->name }}</p>
                                <p class="whitespace-nowrap font-black text-blue-700">GHS {{ number_format($feature->cost_minor / 100, 2) }}</p>
                            </div>
                            @if($feature->description)<p class="mt-2 text-sm leading-6 text-slate-600">{{ $feature->description }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
</x-public-layout>
