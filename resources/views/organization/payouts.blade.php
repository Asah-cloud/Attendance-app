<x-app-layout>
    <x-slot name="header">Organization settings</x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        <a href="{{ route('organization.branding.edit') }}" class="inline-flex items-center gap-1 text-sm font-bold text-blue-600 hover:text-blue-800">← Organization settings</a>

        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" x-data="{ type: @js(old('payout_type', $company->payout_type ?? 'mobile_money')) }">
            <div class="flex flex-col gap-5 border-b border-slate-100 pb-7 sm:flex-row sm:items-center">
                <span class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-600 text-white shadow-lg">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h2m4 0h4M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </span>
                <div>
                    <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-emerald-600">Payouts</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">Where your payments go</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">When people pay on your forms or registration form, Paystack pays the money into this account.</p>
                </div>
            </div>

            @if($company->payout_subaccount_code)
                <div class="mt-6 flex items-center gap-3 rounded-2xl border border-emerald-100 bg-emerald-50 p-4 text-sm">
                    <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <p class="text-emerald-900"><span class="font-black">Ready to receive payments.</span> {{ $company->payout_bank_name }} · ••••{{ substr((string) $company->payout_account_number, -4) }} · {{ $company->payout_business_name }}</p>
                </div>
            @else
                <div class="mt-6 rounded-2xl border border-amber-100 bg-amber-50 p-4 text-sm font-bold text-amber-900">Add your account below before switching on payments on a form.</div>
            @endif

            @if($errors->any())<div class="mt-6 rounded-2xl bg-red-50 p-4 text-sm font-bold text-red-700">{{ $errors->first() }}</div>@endif

            <form method="POST" action="{{ route('organization.payouts.update') }}" class="mt-7 space-y-6">
                @csrf @method('PUT')
                <div>
                    <label class="text-xs font-black uppercase tracking-widest text-slate-500">Paid into</label>
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        @foreach(['mobile_money' => 'Mobile Money', 'bank' => 'Bank account'] as $value => $label)
                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl border p-4 text-sm font-bold" :class="type === '{{ $value }}' ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : 'border-slate-200 text-slate-700'">
                                <input type="radio" name="payout_type" value="{{ $value }}" x-model="type" class="text-emerald-600"> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label for="payout_business_name" class="text-xs font-black uppercase tracking-widest text-slate-500">Name on the account</label>
                    <input id="payout_business_name" name="payout_business_name" value="{{ old('payout_business_name', $company->payout_business_name ?? $company->name) }}" required class="mt-2 w-full rounded-xl border-slate-200">
                </div>

                @foreach(['mobile_money' => 'Mobile Money network', 'bank' => 'Bank'] as $value => $label)
                    <div x-show="type === '{{ $value }}'" @if(($company->payout_type ?? 'mobile_money') !== $value) x-cloak @endif>
                        <label class="text-xs font-black uppercase tracking-widest text-slate-500">{{ $label }}</label>
                        @if($providers[$value])
                            <select name="payout_bank_code" :disabled="type !== '{{ $value }}'" class="mt-2 w-full rounded-xl border-slate-200">
                                <option value="">Choose one</option>
                                @foreach($providers[$value] as $code => $name)<option value="{{ $code }}" @selected(old('payout_bank_code', $company->payout_bank_code) === $code)>{{ $name }}</option>@endforeach
                            </select>
                        @else
                            <p class="mt-2 rounded-xl bg-amber-50 p-3 text-sm font-bold text-amber-800">We couldn't load the list from Paystack. Refresh the page to try again.</p>
                        @endif
                    </div>
                @endforeach

                <div>
                    <label for="payout_account_number" class="text-xs font-black uppercase tracking-widest text-slate-500"><span x-text="type === 'bank' ? 'Account number' : 'Mobile Money number'">Mobile Money number</span></label>
                    <input id="payout_account_number" name="payout_account_number" inputmode="numeric" value="{{ old('payout_account_number', $company->payout_account_number) }}" required placeholder="e.g. 0241234567" class="mt-2 w-full rounded-xl border-slate-200">
                </div>

                <button class="rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 px-6 py-3 text-sm font-black text-white shadow-lg shadow-emerald-100">{{ $company->payout_subaccount_code ? 'Update payout account' : 'Save payout account' }}</button>
            </form>
        </div>
    </div>
</x-app-layout>
