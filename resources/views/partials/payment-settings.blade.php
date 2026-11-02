{{-- "Collect payment" card for a custom form or the registration form. Needs $payable (Form or Event) and $action. --}}
@php
    $company = $payable->paymentCompany();
    $prefix = $payable instanceof App\Models\Event ? 'registration_payment_' : 'payment_';
    $cedis = fn ($minor) => $minor ? rtrim(rtrim(number_format($minor / 100, 2, '.', ''), '0'), '.') : '';
    $feeOn = App\Services\AttendeePaymentService::feeEnabled() && App\Services\AttendeePaymentService::feePercent() > 0;
@endphp
<section class="rounded-3xl border border-gray-100 bg-white p-7 shadow-sm" data-no-lift
         x-data="{ on: @js((bool) old('payment_enabled', $payable->{$prefix.'enabled'})), flexible: @js((bool) old('payment_payer_sets_amount', $payable->{$prefix.'payer_sets_amount'})), choices: @js(old('payment_options', $payable->paymentOptionsText())) }">
    <div class="flex items-start gap-4">
        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-600 text-white shadow-lg">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h2m4 0h4M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-lg font-black">Collect payment</h3>
                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-black uppercase" :class="on ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'" x-text="on ? 'On' : 'Off'"></span>
            </div>
            <p class="mt-1 text-sm text-gray-500">People fill in the form, then pay with Mobile Money or card on Paystack. Their answers are saved either way and show as Paid or Unpaid.</p>
        </div>
    </div>

    @unless($company?->payout_subaccount_code)
        <div class="mt-5 rounded-2xl border border-amber-100 bg-amber-50 p-4 text-sm text-amber-900">
            <span class="font-black">Add your payout account first.</span> Payments go straight to your organisation's Mobile Money or bank account.
            @role('manager')<a href="{{ route('organization.payouts.edit') }}" class="ml-1 font-black text-amber-900 underline">Set up payouts →</a>@endrole
        </div>
    @endunless

    <form method="POST" action="{{ $action }}" class="mt-6 space-y-5">
        @csrf @method('PATCH')
        <input type="hidden" name="payment_enabled" :value="on ? 1 : 0">
        <button type="button" @click="on = ! on" role="switch" :aria-checked="on.toString()" class="flex w-full items-center justify-between rounded-2xl bg-gray-50 p-4 text-left">
            <span class="font-bold">Ask people to pay</span>
            <span class="relative h-7 w-12 rounded-full transition" :class="on ? 'bg-emerald-500' : 'bg-gray-300'"><span class="absolute top-1 h-5 w-5 rounded-full bg-white shadow transition-all" :class="on ? 'left-6' : 'left-1'"></span></span>
        </button>
        <x-input-error :messages="$errors->get('payment_enabled')" />

        <div x-show="on" x-cloak class="grid gap-5 md:grid-cols-2">
            <div><label class="text-xs font-black uppercase text-gray-500">What it's for</label><input name="payment_label" value="{{ old('payment_label', $payable->{$prefix.'label'}) }}" placeholder="e.g. Registration fee" maxlength="100" class="mt-2 w-full rounded-xl border-gray-200"></div>
            <div class="md:col-span-2">
                <label class="text-xs font-black uppercase text-gray-500">Choices people pay for (optional)</label>
                <textarea name="payment_options" rows="4" x-model="choices" placeholder="Adult: 200&#10;Student: 100&#10;Child: 50" class="mt-2 w-full rounded-xl border-gray-200 font-mono text-sm"></textarea>
                <p class="mt-1 text-xs text-gray-400">One per line, as "Name: price". People pick one and pay its price. Leave empty for a single amount.</p>
                <x-input-error :messages="$errors->get('payment_options')" class="mt-1.5" />
            </div>
            <div x-show="! choices.trim()">
                <label class="text-xs font-black uppercase text-gray-500">Amount (GH₵)</label>
                <input name="payment_amount" type="number" step="0.01" min="1" value="{{ old('payment_amount', $cedis($payable->{$prefix.'amount_minor'})) }}" placeholder="e.g. 200" class="mt-2 w-full rounded-xl border-gray-200">
                <p class="mt-1 text-xs text-gray-400">You can change it at any time; new payments use the new amount.</p>
                <x-input-error :messages="$errors->get('payment_amount')" class="mt-1.5" />
            </div>
            <input type="hidden" name="payment_payer_sets_amount" :value="flexible ? 1 : 0">
            <label x-show="! choices.trim()" class="flex items-start gap-3 rounded-2xl bg-gray-50 p-4 md:col-span-2">
                <input type="checkbox" x-model="flexible" class="mt-1 rounded text-emerald-600">
                <span><span class="block font-bold">Let people change the amount</span><span class="text-xs text-gray-500">For donations or "pay what you can". The amount above is filled in for them to change.</span></span>
            </label>
            <div x-show="flexible && ! choices.trim()" x-cloak>
                <label class="text-xs font-black uppercase text-gray-500">Least they can pay (GH₵)</label>
                <input name="payment_minimum" type="number" step="0.01" min="1" value="{{ old('payment_minimum', $cedis($payable->{$prefix.'minimum_minor'})) }}" placeholder="1" class="mt-2 w-full rounded-xl border-gray-200">
                <x-input-error :messages="$errors->get('payment_minimum')" class="mt-1.5" />
            </div>
            @if($feeOn)
                <p class="text-xs text-gray-500 md:col-span-2">A platform fee of {{ App\Services\AttendeePaymentService::feePercent() }}% is taken from each payment, and Paystack's own charges apply.</p>
            @else
                <p class="text-xs text-gray-500 md:col-span-2">Paystack's own charges are taken from each payment.</p>
            @endif
        </div>

        <button class="rounded-xl bg-gray-900 px-5 py-3 font-bold text-white">Save payment settings</button>
    </form>
</section>
