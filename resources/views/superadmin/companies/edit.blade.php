<x-app-layout>
    <x-slot name="header">
        Edit Company
        <div class="hidden">
            <div>
                <h2 class="font-bold text-2xl text-white leading-tight">
                    {{ __('Edit Company Profile') }}
                </h2>
                <p class="text-blue-100 text-sm mt-1">
                    Update corporate profile settings and active tracking registration thresholds for {{ $company->name }}
                </p>
            </div>
            <a href="{{ route('companies.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-900 border border-blue-700 text-white rounded-xl font-bold text-xs shadow-md hover:bg-blue-950 transition">
                ← Back to Tenant Registry
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-6 flex items-center justify-between gap-4"><div><p class="text-xs font-extrabold uppercase tracking-wider text-blue-600">Company settings</p><h2 class="mt-1 text-2xl font-black">{{ $company->name }}</h2></div><a href="{{ route('companies.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-extrabold text-slate-600 hover:border-blue-300 hover:text-blue-700">Back to companies</a></div>
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100 p-8">
                
                <form action="{{ route('companies.update', $company->id) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    @method('PUT')

                    {{-- Company Name Input --}}
                    <div>
                        <label for="name" class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Company / Organization Name</label>
                        <input type="text" name="name" id="name"
                               value="{{ old('name', $company->name) }}"
                               class="w-full border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl shadow-sm text-sm font-medium p-3"
                               required>
                        @error('name')
                            <p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Company Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email', $company->email) }}" class="w-full border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl shadow-sm text-sm font-medium p-3">
                    </div>

                    <div>
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Company Logo</label>
                        @if($company->logo_path)
                            <div class="mb-3 flex items-center gap-4">
                                <img src="{{ Storage::url($company->logo_path) }}" alt="{{ $company->name }} logo" class="h-16 w-16 rounded-xl border border-gray-200 object-contain p-2">
                                <label class="flex items-center gap-2 text-xs font-bold text-gray-500">
                                    <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                    Remove current logo
                                </label>
                            </div>
                        @endif
                        <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="w-full rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-xs file:font-extrabold file:text-blue-700">
                        <p class="mt-1 text-xs text-gray-400">PNG, JPG or WebP, max 2 MB.</p>
                        @error('logo')<p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Event Tracking Limit --}}
                        <div>
                            <label for="event_limit" class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Event Registration Limit</label>
                            <input type="number" name="event_limit" id="event_limit" 
                                   value="{{ old('event_limit', $company->event_limit) }}" 
                                   min="1" 
                                   class="w-full border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl shadow-sm text-sm font-medium p-3" 
                                   required>
                            <p class="text-gray-400 text-[11px] mt-1 font-medium">Maximum concurrent attendance rosters allowed.</p>
                            @error('event_limit')
                                <p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Subscription Expiry Date --}}
                        <div>
                            <label for="subscription_ends_at" class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Subscription Term End Date</label>
                            <input type="date" name="subscription_ends_at" id="subscription_ends_at" 
                                   value="{{ old('subscription_ends_at', $company->subscription_ends_at ? \Carbon\Carbon::parse($company->subscription_ends_at)->format('Y-m-d') : '') }}" 
                                   class="w-full border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl shadow-sm text-sm font-medium p-3" 
                                   required>
                            @error('subscription_ends_at')
                                <p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <hr class="border-gray-100 my-6">

                    <div>
                        <p class="text-xs font-black text-gray-400 uppercase tracking-widest">Messaging identity approval</p>
                        <p class="mt-1 text-xs text-gray-500">Approve only after the email domain or SMS sender ID has been verified with the provider.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label for="email_sender_status" class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Email: {{ $company->email_from_address ?: 'Not configured' }}</label>
                            <select id="email_sender_status" name="email_sender_status" class="w-full rounded-xl border-gray-200 p-3 text-sm font-bold">
                                @foreach(['unconfigured', 'pending', 'approved', 'rejected'] as $status)<option value="{{ $status }}" @selected(old('email_sender_status', $company->email_sender_status) === $status)>{{ ucfirst($status) }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label for="sms_sender_status" class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">SMS: {{ $company->sms_sender_id ?: 'Not configured' }}</label>
                            <select id="sms_sender_status" name="sms_sender_status" class="w-full rounded-xl border-gray-200 p-3 text-sm font-bold">
                                @foreach(['unconfigured', 'pending', 'approved', 'rejected'] as $status)<option value="{{ $status }}" @selected(old('sms_sender_status', $company->sms_sender_status) === $status)>{{ ucfirst($status) }}</option>@endforeach
                            </select>
                        </div>
                    </div>

                    <hr class="border-gray-100 my-6">

                    <div>
                        <input type="hidden" name="is_active" value="0">
                        <label class="inline-flex items-center gap-3 font-bold text-sm text-gray-700">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $company->is_active)) class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            Company account is active
                        </label>
                    </div>

                    {{-- Form Submission Actions --}}
                    <div class="flex justify-end gap-3">
                        <a href="{{ route('companies.index') }}" class="px-5 py-3 bg-gray-100 text-gray-600 rounded-xl font-bold text-xs uppercase tracking-wider hover:bg-gray-200 transition">
                            Cancel
                        </a>
                        <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-700 to-blue-800 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-md hover:shadow-blue-100 hover:scale-[1.02] transition-all">
                            Save Profile Changes
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>
