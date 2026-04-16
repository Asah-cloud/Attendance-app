<x-app-layout>
    <x-slot name="header">
        <h2 class="font-black text-2xl text-white leading-tight tracking-tight">
            {{ __('Edit Member') }}
        </h2>
    </x-slot>

    <div class="py-12 bg-[#f8fafc]">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-8 rounded-[2.5rem] shadow-[0_15px_50px_rgba(0,0,0,0.03)] border border-gray-100">
                
                <div class="mb-8">
                    <h2 class="text-xl font-black text-gray-900 uppercase tracking-tight">Modify Account</h2>
                    <p class="text-[10px] font-black text-blue-500 uppercase tracking-[0.3em]">Updating: {{ $user->email }}</p>
                </div>

                <form action="{{ route('admin.users.update', $user) }}" method="POST" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Full Name</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" 
                               class="w-full border-gray-100 bg-gray-50/50 rounded-2xl focus:ring-blue-500 focus:border-blue-500 font-bold text-gray-700">
                        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Email Address</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" 
                               class="w-full border-gray-100 bg-gray-50/50 rounded-2xl focus:ring-blue-500 focus:border-blue-500 font-bold text-gray-700">
                        @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">System Access Level</label>
                        <select name="role" class="w-full border-gray-100 bg-gray-50/50 rounded-2xl focus:ring-blue-500 focus:border-blue-500 font-bold text-gray-700">
                            <option value="regular" {{ $user->hasRole('regular') ? 'selected' : '' }}>Regular (Usher/Member)</option>
                            <option value="admin" {{ $user->hasRole('admin') ? 'selected' : '' }}>Administrator (Full Access)</option>
                        </select>
                        @error('role') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-4 pt-4">
                        <button type="submit" class="bg-blue-900 text-white px-8 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-black transition-all shadow-lg shadow-blue-100">
                            Save Changes
                        </button>
                        
                        <a href="{{ route('admin.users.index') }}" class="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-gray-600 transition-colors">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>