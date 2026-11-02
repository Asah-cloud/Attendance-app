<x-app-layout>
    <x-slot name="header">Integrations</x-slot>
    <div class="py-10"><div class="mx-auto max-w-2xl space-y-8 px-4 sm:px-6 lg:px-8">
        @if($errors->any())<div class="rounded-2xl bg-red-50 p-4 text-sm font-bold text-red-700">{{ $errors->first() }}</div>@endif

        <div class="rounded-3xl border border-gray-100 bg-white p-7 shadow-sm">
            <h3 class="text-lg font-black">Paystack</h3>
            <p class="mt-1 text-sm text-gray-500">Used for subscription checkout, per-event attendee billing, and guest signup payments. Only the secret key is currently used by the app; the public key is stored for future use. Saved keys take effect immediately, no redeploy needed.</p>

            <form method="POST" action="{{ route('integrations.update') }}" class="mt-5 space-y-5">
                @csrf @method('PUT')

                <div>
                    <label for="paystack_secret_key" class="block text-xs font-black uppercase tracking-widest text-gray-500">Secret key</label>
                    <p class="mt-1 text-xs text-gray-400">{{ $paystack['secret_key_preview'] ? 'Current: '.$paystack['secret_key_preview'] : 'Not configured — falling back to the server .env value, if any.' }}</p>
                    <input id="paystack_secret_key" name="paystack_secret_key" type="password" autocomplete="off" placeholder="Leave blank to keep the current key" class="mt-2 w-full rounded-xl border-gray-200 text-sm">
                </div>

                <div>
                    <label for="paystack_public_key" class="block text-xs font-black uppercase tracking-widest text-gray-500">Public key</label>
                    <p class="mt-1 text-xs text-gray-400">{{ $paystack['public_key_preview'] ? 'Current: '.$paystack['public_key_preview'] : 'Not configured — falling back to the server .env value, if any.' }}</p>
                    <input id="paystack_public_key" name="paystack_public_key" type="password" autocomplete="off" placeholder="Leave blank to keep the current key" class="mt-2 w-full rounded-xl border-gray-200 text-sm">
                </div>

                <button class="rounded-xl bg-gray-900 px-5 py-3 text-sm font-bold text-white">Save Paystack keys</button>
            </form>
        </div>

        <div class="rounded-3xl border border-gray-100 bg-white p-7 shadow-sm" x-data="{ on: @js((bool) old('fee_enabled', $feeEnabled)) }">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-black">Platform fee on attendee payments</h3>
                    <p class="mt-1 text-sm text-gray-500">When people pay on an organisation's form or registration form, the money goes straight to that organisation's payout account. Switch this on to keep a percentage of each payment for the platform.</p>
                </div>
                <span class="shrink-0 rounded-full px-3 py-1 text-xs font-black uppercase" :class="on ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'" x-text="on ? 'On' : 'Off'"></span>
            </div>

            <form method="POST" action="{{ route('integrations.attendee-fee') }}" class="mt-5 space-y-5">
                @csrf @method('PUT')
                <input type="hidden" name="fee_enabled" :value="on ? 1 : 0">
                <button type="button" @click="on = ! on" role="switch" :aria-checked="on.toString()" class="flex w-full items-center justify-between rounded-2xl bg-gray-50 p-4 text-left">
                    <span class="font-bold">Take a platform fee</span>
                    <span class="relative h-7 w-12 rounded-full transition" :class="on ? 'bg-emerald-500' : 'bg-gray-300'"><span class="absolute top-1 h-5 w-5 rounded-full bg-white shadow transition-all" :class="on ? 'left-6' : 'left-1'"></span></span>
                </button>
                <div x-show="on" x-cloak>
                    <label for="fee_percent" class="block text-xs font-black uppercase tracking-widest text-gray-500">Fee per payment (%)</label>
                    <input id="fee_percent" name="fee_percent" type="number" step="0.1" min="0" max="20" value="{{ old('fee_percent', $feePercent ?: '') }}" placeholder="e.g. 3" class="mt-2 w-40 rounded-xl border-gray-200 text-sm">
                    <p class="mt-1 text-xs text-gray-400">Taken from each new payment. Paystack's own charges are paid by the organisation.</p>
                </div>
                <button class="rounded-xl bg-gray-900 px-5 py-3 text-sm font-bold text-white">Save fee setting</button>
            </form>

            <div class="mt-6 grid grid-cols-3 gap-3 border-t border-gray-100 pt-5 text-center">
                <div><p class="text-xl font-black">{{ number_format($attendeePayments->count ?? 0) }}</p><p class="text-xs text-gray-500">payments</p></div>
                <div><p class="text-xl font-black">{{ App\Models\AttendeePayment::money((int) ($attendeePayments->amount ?? 0)) }}</p><p class="text-xs text-gray-500">paid to organisations</p></div>
                <div><p class="text-xl font-black">{{ App\Models\AttendeePayment::money((int) ($attendeePayments->fees ?? 0)) }}</p><p class="text-xs text-gray-500">platform fees</p></div>
            </div>
        </div>
    </div></div>
</x-app-layout>
