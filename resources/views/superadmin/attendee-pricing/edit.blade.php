<x-app-layout>
    <x-slot name="header">Attendee Pricing</x-slot>
    <div class="py-10"><div class="mx-auto max-w-4xl space-y-8 px-4 sm:px-6 lg:px-8">
        @if($errors->any())<div class="rounded-2xl bg-red-50 p-4 text-sm font-bold text-red-700">{{ $errors->first() }}</div>@endif

        <p class="text-sm text-gray-500">Graduated (marginal) tiers charged per attendee, upfront, per event. A company's own negotiated rate (set on its edit screen) beats its plan's tiers, which beat the platform default below. One tier per line, <code>from-to:rate</code> (major currency units), last line unbounded.</p>

        <div x-data="{ tab: 'platform' }">
            <nav class="flex flex-wrap gap-2 rounded-2xl border border-gray-100 bg-white p-2 shadow-sm">
                <button type="button" @click="tab = 'platform'" :class="tab === 'platform' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'" class="rounded-xl px-4 py-2.5 text-xs font-extrabold uppercase tracking-wider transition">Platform default</button>
                @foreach($plans as $plan)
                    <button type="button" @click="tab = '{{ $plan['key'] }}'" :class="tab === '{{ $plan['key'] }}' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'" class="rounded-xl px-4 py-2.5 text-xs font-extrabold uppercase tracking-wider transition">{{ $plan['name'] }}</button>
                @endforeach
            </nav>

            <div x-show="tab === 'platform'" class="mt-6 rounded-3xl border border-gray-100 bg-white p-7 shadow-sm">
                <h3 class="text-lg font-black">Platform default</h3>
                <p class="mt-1 text-sm text-gray-500">Used whenever a company has no plan or company-level override. Must always have at least one tier.</p>
                <form method="POST" action="{{ route('attendee-pricing.platform.update') }}" class="mt-5">
                    @csrf @method('PUT')
                    <textarea name="tiers" rows="6" required class="w-full rounded-xl border-gray-200 p-3 font-mono text-sm">{{ old('tiers', $platformText) }}</textarea>
                    <button class="mt-4 rounded-xl bg-gray-900 px-5 py-3 text-sm font-bold text-white">Save platform default</button>
                </form>
            </div>

            @foreach($plans as $plan)
                <div x-show="tab === '{{ $plan['key'] }}'" class="mt-6 rounded-3xl border border-gray-100 bg-white p-7 shadow-sm">
                    <h3 class="text-lg font-black">{{ $plan['name'] }}</h3>
                    <p class="mt-1 text-sm text-gray-500">Overrides the platform default for every company on the {{ $plan['name'] }} plan (unless that company has its own negotiated rate). Leave blank to fall back to the platform default.</p>
                    <form method="POST" action="{{ route('attendee-pricing.plan.update', $plan['key']) }}" class="mt-5">
                        @csrf @method('PUT')
                        <textarea name="tiers" rows="6" class="w-full rounded-xl border-gray-200 p-3 font-mono text-sm" placeholder="Leave blank to use the platform default">{{ old('tiers', $plan['text']) }}</textarea>
                        <button class="mt-4 rounded-xl bg-gray-900 px-5 py-3 text-sm font-bold text-white">Save {{ $plan['name'] }} pricing</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div></div>
</x-app-layout>
