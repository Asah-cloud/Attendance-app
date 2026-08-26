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
    </div></div>
</x-app-layout>
