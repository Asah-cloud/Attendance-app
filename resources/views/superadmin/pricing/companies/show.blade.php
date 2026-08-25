<x-app-layout>
    <x-slot name="header">{{ $company->name }} Pricing</x-slot>
    <div class="py-10"><div class="mx-auto max-w-4xl space-y-8 px-4 sm:px-6 lg:px-8">
        @if($errors->any())<div class="rounded-2xl bg-red-50 p-4 text-sm font-bold text-red-700">{{ $errors->first() }}</div>@endif

        <div class="flex items-center justify-between gap-4">
            <div><p class="text-xs font-extrabold uppercase tracking-wider text-blue-600">Company pricing</p><h2 class="mt-1 text-2xl font-black">{{ $company->name }}</h2></div>
            <a href="{{ route('pricing.companies.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-extrabold text-slate-600 hover:border-blue-300 hover:text-blue-700">Back to companies</a>
        </div>

        <div class="rounded-3xl border border-gray-100 bg-white p-7 shadow-sm">
            <h3 class="text-lg font-black">Negotiated rate</h3>
            <p class="mt-1 text-sm text-gray-500">Overrides both the plan and platform default tiers for every event this company runs, unless a specific event below has its own override. One tier per line, <code>from-to:rate</code> (major currency units), last line unbounded, e.g. <code>1000-:0.50</code>. Leave blank to use the plan/platform default.</p>
            <form method="POST" action="{{ route('pricing.companies.update', $company) }}" class="mt-5">
                @csrf @method('PUT')
                <textarea name="tiers" rows="6" class="w-full rounded-xl border-gray-200 p-3 font-mono text-sm" placeholder="0-100:2.00&#10;100-300:1.50&#10;300-:1.00">{{ old('tiers', $companyTiersText) }}</textarea>
                <button class="mt-4 rounded-xl bg-gray-900 px-5 py-3 text-sm font-bold text-white">Save company pricing</button>
            </form>
        </div>

        <div class="rounded-3xl border border-gray-100 bg-white p-7 shadow-sm">
            <h3 class="text-lg font-black">Events</h3>
            <p class="mt-1 text-sm text-gray-500">Override pricing for a single event — beats this company's negotiated rate above.</p>
            <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wider text-slate-500"><tr>
                    <th class="px-5 py-3">Event</th>
                    <th class="px-5 py-3">Date</th>
                    <th class="px-5 py-3">Pricing</th>
                    <th class="px-5 py-3"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">@forelse($events as $event)
                    <tr>
                        <td class="px-5 py-3 font-bold text-slate-900">{{ $event->title }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ \Carbon\Carbon::parse($event->event_date)->format('M j, Y') }}</td>
                        <td class="px-5 py-3">
                            @if(in_array($event->id, $eventsWithOverride))
                                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">Override set</span>
                            @else
                                <span class="text-xs text-slate-400">Uses company/plan/platform</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right"><a href="{{ route('pricing.companies.events.edit', [$company, $event]) }}" class="text-xs font-bold text-blue-700">Edit &rarr;</a></td>
                    </tr>
                @empty<tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">This company has no events yet.</td></tr>@endforelse</tbody>
            </table></div></div>
        </div>
    </div></div>
</x-app-layout>
