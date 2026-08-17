<x-guest-layout>
    <div class="mb-7 text-center">
        <p class="text-xs font-black uppercase tracking-[0.2em] text-blue-600">{{ $plan['name'] }} plan · GHS {{ number_format($payment['price_minor'] / 100, 2) }}/month</p>
        <h1 class="mt-3 text-2xl font-black text-gray-900">Create your manager workspace</h1>
        <p class="mt-2 text-sm text-gray-500">Your company and first manager account will be created together.</p>
    </div>
    @if(session('success'))<div class="mb-5 rounded-2xl bg-green-50 p-4 text-sm font-bold text-green-700">{{ session('success') }}</div>@endif
    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf
        <div><x-input-label for="company_name" :value="__('Company or organisation name')" /><x-text-input id="company_name" class="mt-1 block w-full" type="text" name="company_name" :value="old('company_name')" required autofocus /><x-input-error :messages="$errors->get('company_name')" class="mt-1" /></div>
        <div><x-input-label for="name" :value="__('Manager full name')" /><x-text-input id="name" class="mt-1 block w-full" type="text" name="name" :value="old('name')" required /><x-input-error :messages="$errors->get('name')" class="mt-1" /></div>
        <div><x-input-label for="email" :value="__('Work email')" /><x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required /><x-input-error :messages="$errors->get('email')" class="mt-1" /></div>
        <div><x-input-label for="password" :value="__('Password')" /><x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required /><x-input-error :messages="$errors->get('password')" class="mt-1" /></div>
        <div><x-input-label for="password_confirmation" :value="__('Confirm password')" /><x-text-input id="password_confirmation" class="mt-1 block w-full" type="password" name="password_confirmation" required /></div>
        <x-primary-button class="w-full justify-center py-4">Create manager workspace</x-primary-button>
    </form>
</x-guest-layout>
