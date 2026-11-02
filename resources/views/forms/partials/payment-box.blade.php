{{-- What the person pays, shown above the submit button. Needs $payable (Form or Event); $askEmail for forms with no email question of their own. --}}
@php
    $cedis = fn ($minor) => rtrim(rtrim(number_format($minor / 100, 2, '.', ''), '0'), '.');
@endphp
<div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-5">
    <div class="flex items-start gap-3">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-emerald-400 to-teal-600 text-white">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h2m4 0h4M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-xs font-black uppercase tracking-wide text-emerald-700">{{ $payable->paymentLabel() }}</p>
            @if($payable->payerSetsAmount())
                <label for="payment_amount" class="mt-2 block text-sm font-bold text-slate-700">Amount you'll pay (GH₵)</label>
                <input id="payment_amount" name="payment_amount" type="number" step="0.01" min="{{ $cedis($payable->paymentMinimumMinor()) }}" required
                       value="{{ old('payment_amount', $payable->paymentAmountMinor() ? $cedis($payable->paymentAmountMinor()) : '') }}"
                       class="mt-1.5 w-full rounded-xl border-emerald-200 bg-white px-4 py-3 text-lg font-black text-slate-900">
                <p class="mt-1 text-xs text-slate-500">At least GH₵ {{ $cedis($payable->paymentMinimumMinor()) }}.</p>
                <x-input-error :messages="$errors->get('payment_amount')" class="mt-1.5" />
            @else
                <p class="mt-1 text-2xl font-black text-slate-950">GH₵ {{ number_format($payable->paymentAmountMinor() / 100, 2) }}</p>
            @endif
        </div>
    </div>
    @if($askEmail ?? false)
        <div class="mt-4">
            <label for="payer_email" class="text-xs font-extrabold uppercase tracking-wide text-slate-500">Your email (for the receipt) <span class="text-red-500">*</span></label>
            <input id="payer_email" name="payer_email" type="email" required value="{{ old('payer_email') }}" class="mt-2 w-full rounded-xl border-emerald-200 bg-white px-4 py-3 text-sm font-medium">
            <x-input-error :messages="$errors->get('payer_email')" class="mt-1.5" />
        </div>
    @endif
    <p class="mt-4 text-xs font-semibold text-slate-500">After you submit, you'll pay securely with Mobile Money or card on Paystack.</p>
</div>
