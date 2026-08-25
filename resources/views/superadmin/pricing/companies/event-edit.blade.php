<x-app-layout>
    <x-slot name="header">{{ $event->title }} Pricing</x-slot>
    <div class="py-10"><div class="mx-auto max-w-4xl space-y-8 px-4 sm:px-6 lg:px-8">
        @if($errors->any())<div class="rounded-2xl bg-red-50 p-4 text-sm font-bold text-red-700">{{ $errors->first() }}</div>@endif

        <div class="flex items-center justify-between gap-4">
            <div><p class="text-xs font-extrabold uppercase tracking-wider text-blue-600">Event pricing</p><h2 class="mt-1 text-2xl font-black">{{ $event->title }}</h2><p class="mt-1 text-sm text-slate-500">{{ $company->name }}</p></div>
            <a href="{{ route('pricing.companies.show', $company) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-extrabold text-slate-600 hover:border-blue-300 hover:text-blue-700">Back to {{ $company->name }}</a>
        </div>

        <div class="rounded-3xl border border-gray-100 bg-white p-7 shadow-sm">
            <h3 class="text-lg font-black">Override for this event</h3>
            <p class="mt-1 text-sm text-gray-500">Beats this company's negotiated rate, plan tiers, and the platform default — for this event only. One tier per line, <code>from-to:rate</code> (major currency units), last line unbounded. Leave blank to fall back to the company/plan/platform pricing.</p>
            <form method="POST" action="{{ route('pricing.companies.events.update', [$company, $event]) }}" class="mt-5">
                @csrf @method('PUT')
                <textarea name="tiers" rows="6" class="w-full rounded-xl border-gray-200 p-3 font-mono text-sm" placeholder="Leave blank to use company/plan/platform pricing">{{ old('tiers', $text) }}</textarea>
                <button class="mt-4 rounded-xl bg-gray-900 px-5 py-3 text-sm font-bold text-white">Save event pricing</button>
            </form>
        </div>
    </div></div>
</x-app-layout>
