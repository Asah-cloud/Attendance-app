<x-app-layout>
    <x-slot name="header">{{ $event->title }} · Billing</x-slot>
    <div class="py-10"><div class="mx-auto max-w-3xl space-y-8 px-4 sm:px-6 lg:px-8">

        @if(!$charge)
            <section data-tour="event-billing-estimate" class="rounded-3xl border border-gray-100 bg-white p-7 shadow-sm">
                <h3 class="text-lg font-black">Estimated attendee bill</h3>
                <p class="mt-1 text-sm text-gray-500">Live estimate based on {{ $estimate['registered_count'] }} confirmed registration(s) right now. This will keep changing until you finalize it.</p>

                <div class="mt-5 overflow-hidden rounded-2xl border border-gray-100">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs font-black uppercase text-gray-500"><tr><th class="p-3">Band</th><th class="p-3">Attendees</th><th class="p-3">Rate</th><th class="p-3 text-right">Subtotal</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($estimate['breakdown'] as $band)
                                <tr><td class="p-3">{{ $band['band_from'] }}–{{ $band['band_to'] ?? '∞' }}</td><td class="p-3">{{ $band['count_in_band'] }}</td><td class="p-3">{{ number_format($band['rate_minor'] / 100, 2) }}</td><td class="p-3 text-right font-bold">{{ number_format($band['subtotal_minor'] / 100, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="p-4 text-center text-gray-500">No confirmed attendees yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="mt-4 text-lg font-bold text-gray-500">Attendee subtotal: {{ number_format($estimate['amount_minor'] / 100, 2) }}</p>

                <form method="POST" action="{{ route('events.billing.request', $event) }}" class="mt-6" x-data="{ featuresMinor: 0 }">
                    @csrf

                    @if($features->isNotEmpty())
                        <div data-tour="event-billing-features" class="rounded-2xl border border-gray-100 p-5">
                            <h4 class="font-black text-gray-900">Advanced features</h4>
                            <p class="mt-1 text-xs text-gray-500">Optional, extra cost per event. Standard attendance, check-in and reports are always included free.</p>
                            <div class="mt-4 space-y-3">
                                @foreach($features as $feature)
                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-100 p-3 hover:bg-gray-50">
                                        <input type="checkbox" name="features[]" value="{{ $feature->key }}" data-cost="{{ $feature->cost_minor }}"
                                               x-on:change="featuresMinor = Array.from($el.closest('form').querySelectorAll('input[name=\'features[]\']:checked')).reduce((sum, el) => sum + Number(el.dataset.cost), 0)"
                                               class="mt-1 h-4 w-4 rounded border-gray-300">
                                        <span class="flex-1">
                                            <span class="flex items-center justify-between gap-3">
                                                <span class="font-bold text-gray-900">{{ $feature->name }}</span>
                                                <span class="font-black text-blue-700">+{{ number_format($feature->cost_minor / 100, 2) }}</span>
                                            </span>
                                            @if($feature->description)<span class="mt-0.5 block text-xs text-gray-500">{{ $feature->description }}</span>@endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <p class="mt-5 text-2xl font-black">Estimated total: {{ number_format($estimate['amount_minor'] / 100, 2) }} <span class="text-base font-bold text-blue-700" x-show="featuresMinor > 0">+ <span x-text="(featuresMinor / 100).toFixed(2)"></span> features</span></p>

                    <button class="mt-4 rounded-xl bg-blue-900 px-5 py-3 text-sm font-bold text-white">{{ auth()->user()->hasRole('admin') ? 'Generate invoice' : 'Request invoice' }}</button>
                </form>
                @role('admin')
                    <p class="mt-3 text-xs text-gray-400">You'll review and approve it yourself on the next step, same as any other invoice.</p>
                @else
                    <p class="mt-3 text-xs text-gray-400">An admin reviews the figures — including any advanced features and possible discount — before the invoice is finalized and sent to you.</p>
                @endrole
            </section>
        @elseif($charge->isAwaitingReview())
            <section data-tour="event-billing-charge" class="rounded-3xl border border-amber-200 bg-amber-50/60 p-7 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-lg font-black text-amber-900">Invoice requested — awaiting review</h3>
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black uppercase text-amber-800">pending review</span>
                </div>
                <p class="mt-1 text-sm text-amber-800">Requested {{ $charge->finalized_at->format('M j, Y g:i A') }} for {{ $charge->registered_count }} confirmed registration(s). Nothing is payable yet.</p>

                <div class="mt-5 overflow-hidden rounded-2xl border border-amber-100 bg-white">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs font-black uppercase text-gray-500"><tr><th class="p-3">Band</th><th class="p-3">Attendees</th><th class="p-3">Rate</th><th class="p-3 text-right">Subtotal</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($charge->tier_breakdown as $band)
                                <tr><td class="p-3">{{ $band['band_from'] }}–{{ $band['band_to'] ?? '∞' }}</td><td class="p-3">{{ $band['count_in_band'] }}</td><td class="p-3">{{ number_format($band['rate_minor'] / 100, 2) }}</td><td class="p-3 text-right font-bold">{{ number_format($band['subtotal_minor'] / 100, 2) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-4 text-lg font-bold text-gray-700">Attendee subtotal: {{ $charge->currency }} {{ number_format(($charge->amount_minor - $charge->features_amount_minor) / 100, 2) }}</p>

                @role('admin')
                    <form method="POST" action="{{ route('events.billing.approve', $event) }}" class="mt-6" x-data="{ featuresMinor: {{ $charge->features_amount_minor }}, discount: 0 }">
                        @csrf
                        @if($reviewFeatures->isNotEmpty())
                            <div class="rounded-2xl border border-gray-100 bg-white p-5">
                                <h4 class="font-black text-gray-900">Advanced features requested</h4>
                                <p class="mt-1 text-xs text-gray-500">Uncheck any the company shouldn't be billed for, or add one they need.</p>
                                <div class="mt-4 space-y-3">
                                    @foreach($reviewFeatures as $feature)
                                        @php $checked = collect($charge->feature_breakdown ?? [])->contains('key', $feature->key); @endphp
                                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-100 p-3 hover:bg-gray-50">
                                            <input type="checkbox" name="features[]" value="{{ $feature->key }}" data-cost="{{ $feature->cost_minor }}" @checked($checked)
                                                   x-on:change="featuresMinor = Array.from($el.closest('form').querySelectorAll('input[name=\'features[]\']:checked')).reduce((sum, el) => sum + Number(el.dataset.cost), 0)"
                                                   class="mt-1 h-4 w-4 rounded border-gray-300">
                                            <span class="flex-1">
                                                <span class="flex items-center justify-between gap-3">
                                                    <span class="font-bold text-gray-900">{{ $feature->name }}</span>
                                                    <span class="font-black text-blue-700">+{{ number_format($feature->cost_minor / 100, 2) }}</span>
                                                </span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="mt-4 rounded-2xl border border-gray-100 bg-white p-5">
                            <h4 class="font-black text-gray-900">Discount</h4>
                            <p class="mt-1 text-xs text-gray-500">Optional. Reduces the total — for a courtesy, error correction, or negotiated rate.</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="text-xs font-bold text-gray-500">Amount ({{ $charge->currency }})</label>
                                    <input type="number" name="discount" step="0.01" min="0" x-model.number="discount" class="mt-1 w-full rounded-xl border-gray-200 text-sm">
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-gray-500">Reason</label>
                                    <input type="text" name="discount_reason" placeholder="e.g. Repeat customer courtesy" class="mt-1 w-full rounded-xl border-gray-200 text-sm">
                                </div>
                            </div>
                            @error('discount_reason')<p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <p class="mt-5 text-2xl font-black">New total: {{ $charge->currency }} <span x-text="(({{ $charge->amount_minor - $charge->features_amount_minor }} + featuresMinor - (discount * 100 || 0)) / 100).toFixed(2)"></span></p>

                        <button class="mt-4 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white">Approve & send invoice</button>
                    </form>
                @else
                    <p class="mt-6 text-sm font-semibold text-amber-800">An admin will review this and send you the final invoice to download or by email.</p>
                @endrole
            </section>
        @else
            <section data-tour="event-billing-charge" class="rounded-3xl border border-gray-100 bg-white p-7 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-lg font-black">Attendee bill{{ $charge->invoice_number ? ' · '.$charge->invoice_number : '' }}</h3>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black uppercase text-slate-700">{{ str_replace('_', ' ', $charge->status) }}</span>
                </div>
                <p class="mt-1 text-sm text-gray-500">Finalized {{ $charge->finalized_at->format('M j, Y g:i A') }} for {{ $charge->registered_count }} confirmed registration(s).</p>

                <div class="mt-5 overflow-hidden rounded-2xl border border-gray-100">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs font-black uppercase text-gray-500"><tr><th class="p-3">Band</th><th class="p-3">Attendees</th><th class="p-3">Rate</th><th class="p-3 text-right">Subtotal</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($charge->tier_breakdown as $band)
                                <tr><td class="p-3">{{ $band['band_from'] }}–{{ $band['band_to'] ?? '∞' }}</td><td class="p-3">{{ $band['count_in_band'] }}</td><td class="p-3">{{ number_format($band['rate_minor'] / 100, 2) }}</td><td class="p-3 text-right font-bold">{{ number_format($band['subtotal_minor'] / 100, 2) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if(!empty($charge->feature_breakdown))
                    <div class="mt-5 overflow-hidden rounded-2xl border border-gray-100">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-xs font-black uppercase text-gray-500"><tr><th class="p-3">Feature</th><th class="p-3 text-right">Cost</th></tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($charge->feature_breakdown as $feature)
                                    <tr><td class="p-3">{{ $feature['name'] }}</td><td class="p-3 text-right font-bold">{{ number_format($feature['cost_minor'] / 100, 2) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if($charge->discount_minor > 0)
                    <p class="mt-3 text-sm font-bold text-emerald-700">Discount applied: -{{ $charge->currency }} {{ number_format($charge->discount_minor / 100, 2) }}{{ $charge->discount_reason ? ' — '.$charge->discount_reason : '' }}</p>
                @endif

                <p class="mt-4 text-2xl font-black">Total: {{ $charge->currency }} {{ number_format($charge->amount_minor / 100, 2) }}</p>

                @role('admin')
                    @if($charge->needsInvoice())
                        <div class="mt-6 rounded-2xl border border-blue-200 bg-blue-50/60 p-5" x-data="{ open: false }">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="font-black text-blue-900">No formal invoice yet</p>
                                    <p class="mt-1 text-xs text-blue-800">This bill was created automatically and hasn't been reviewed. Generate a numbered invoice — optionally adjusting features or applying a discount first — and it'll be emailed to the manager.</p>
                                </div>
                                <button type="button" @click="open = !open" class="shrink-0 rounded-xl bg-blue-900 px-4 py-2.5 text-xs font-bold text-white" x-text="open ? 'Close' : 'Generate invoice'"></button>
                            </div>

                            <form x-show="open" x-cloak method="POST" action="{{ route('events.billing.approve', $event) }}" class="mt-5" x-data="{ featuresMinor: {{ $charge->features_amount_minor }}, discount: 0 }">
                                @csrf
                                @if($reviewFeatures->isNotEmpty())
                                    <div class="rounded-2xl border border-blue-100 bg-white p-5">
                                        <h4 class="font-black text-gray-900">Advanced features</h4>
                                        <div class="mt-4 space-y-3">
                                            @foreach($reviewFeatures as $feature)
                                                @php $checked = collect($charge->feature_breakdown ?? [])->contains('key', $feature->key); @endphp
                                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-100 p-3 hover:bg-gray-50">
                                                    <input type="checkbox" name="features[]" value="{{ $feature->key }}" data-cost="{{ $feature->cost_minor }}" @checked($checked)
                                                           x-on:change="featuresMinor = Array.from($el.closest('form').querySelectorAll('input[name=\'features[]\']:checked')).reduce((sum, el) => sum + Number(el.dataset.cost), 0)"
                                                           class="mt-1 h-4 w-4 rounded border-gray-300">
                                                    <span class="flex-1">
                                                        <span class="flex items-center justify-between gap-3">
                                                            <span class="font-bold text-gray-900">{{ $feature->name }}</span>
                                                            <span class="font-black text-blue-700">+{{ number_format($feature->cost_minor / 100, 2) }}</span>
                                                        </span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div class="mt-4 rounded-2xl border border-blue-100 bg-white p-5">
                                    <h4 class="font-black text-gray-900">Discount</h4>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label class="text-xs font-bold text-gray-500">Amount ({{ $charge->currency }})</label>
                                            <input type="number" name="discount" step="0.01" min="0" x-model.number="discount" class="mt-1 w-full rounded-xl border-gray-200 text-sm">
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-gray-500">Reason</label>
                                            <input type="text" name="discount_reason" placeholder="e.g. Repeat customer courtesy" class="mt-1 w-full rounded-xl border-gray-200 text-sm">
                                        </div>
                                    </div>
                                    @error('discount_reason')<p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>@enderror
                                </div>

                                <p class="mt-5 text-xl font-black">New total: {{ $charge->currency }} <span x-text="(({{ $charge->amount_minor - $charge->features_amount_minor }} + featuresMinor - (discount * 100 || 0)) / 100).toFixed(2)"></span></p>

                                <button class="mt-4 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white">Approve & send invoice</button>
                            </form>
                        </div>
                    @endif
                @endrole

                @if($charge->hasApprovedInvoice())
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('events.billing.invoice', $event) }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50">Download invoice PDF</a>
                        <form method="POST" action="{{ route('events.billing.invoice.email', $event) }}">
                            @csrf
                            <button class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50">Email me this invoice</button>
                        </form>
                    </div>
                    @if($charge->invoice_emailed_at)<p class="mt-2 text-xs text-gray-400">Last emailed {{ $charge->invoice_emailed_at->diffForHumans() }}.</p>@endif
                @endif

                @if($errors->has('payment'))
                    <div class="mt-6 rounded-2xl border border-red-300 bg-red-50 p-4 text-sm font-bold text-red-900">{{ $errors->first('payment') }}</div>
                @endif
                @if($charge->status === 'payment_failed')
                    <p class="mt-6 text-sm font-bold text-red-700">Your last payment attempt failed.</p>
                @endif
                @if(in_array($charge->status, ['pending_payment', 'payment_failed']))
                    <form method="POST" action="{{ route('events.billing.pay', $event) }}" class="mt-4">
                        @csrf
                        <button class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white">{{ $charge->status === 'payment_failed' ? 'Try again' : 'Pay with Paystack' }}</button>
                    </form>
                @elseif($charge->status === 'paid')
                    <p class="mt-4 text-sm text-gray-500">Paid {{ $charge->paid_at->format('M j, Y g:i A') }} · reference <span class="font-mono">{{ $charge->payment_reference }}</span>. Reconciliation happens automatically once the event closes.</p>
                @endif

                @if(in_array($charge->status, ['reconciled', 'refund_due', 'refunded']))
                    <div class="mt-6 border-t border-gray-100 pt-6">
                        <h4 class="font-black text-gray-900">Reconciliation</h4>
                        <p class="mt-1 text-sm text-gray-500">{{ $charge->checked_in_count }} of {{ $charge->registered_count }} registered attendee(s) checked in.</p>
                        @if($charge->refund_amount_minor)
                            <p class="mt-2 text-xl font-black">Refund: {{ $charge->currency }} {{ number_format($charge->refund_amount_minor / 100, 2) }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $charge->status === 'refunded' ? 'Refunded '.$charge->refunded_at->format('M j, Y g:i A').'.' : 'Awaiting refund from our team.' }}</p>
                        @else
                            <p class="mt-2 text-sm text-gray-500">No refund due — every registered attendee checked in.</p>
                        @endif
                    </div>
                @endif
            </section>
        @endif
    </div></div>
</x-app-layout>
