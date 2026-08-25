<x-app-layout>
    <x-slot name="header">Register Company</x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-2xl p-8 border border-gray-100">
                <form action="{{ route('companies.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6" x-data="{ billingMode: '{{ old('billing_mode', 'subscription') }}' }">
                    @csrf

                    <div>
                        <label class="block text-xs font-black uppercase text-gray-400 tracking-widest mb-2">Company Name</label>
                        <input type="text" name="name" required class="w-full border-gray-200 rounded-xl focus:ring-blue-500 focus:border-blue-500 shadow-sm" placeholder="e.g. Global Worship Center">
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-gray-400 tracking-widest mb-2">Company Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="w-full border-gray-200 rounded-xl focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                    </div>

                    <div>
                        <label for="logo" class="block text-xs font-black uppercase text-gray-400 tracking-widest mb-2">Company Logo (Optional)</label>
                        <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="w-full rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-xs file:font-extrabold file:text-blue-700">
                        <p class="mt-1 text-xs text-gray-400">PNG, JPG or WebP, max 2 MB.</p>
                        @error('logo')<p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-gray-400 tracking-widest mb-2">Billing mode</label>
                        <select name="billing_mode" x-model="billingMode" class="w-full rounded-xl border-gray-200 focus:ring-blue-500 focus:border-blue-500 shadow-sm text-sm font-bold">
                            <option value="subscription">Monthly subscription</option>
                            <option value="pay_per_event">Pay per event (no subscription)</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Pay-per-event companies skip the event limit below and are billed per attendee on each event instead.</p>
                        @error('billing_mode')<p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6" :class="{ 'opacity-50': billingMode === 'pay_per_event' }">
                        <div>
                            <label class="block text-xs font-black uppercase text-gray-400 tracking-widest mb-2">Subscription End Date</label>
                            <input type="date" name="subscription_ends_at" :required="billingMode === 'subscription'" class="w-full border-gray-200 rounded-xl focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase text-gray-400 tracking-widest mb-2">Event Limit</label>
                            <input type="number" name="event_limit" value="5" :required="billingMode === 'subscription'" class="w-full border-gray-200 rounded-xl focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full bg-blue-900 text-white font-black uppercase py-4 rounded-xl shadow-lg hover:bg-blue-800 transition-all transform hover:-translate-y-1">
                            Register & Activate Company
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
