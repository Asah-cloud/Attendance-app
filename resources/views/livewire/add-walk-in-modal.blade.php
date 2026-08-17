<div>
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl relative border border-gray-100 animate-fade-in">
                <h3 class="text-lg font-black text-gray-900 uppercase tracking-tight mb-4">Register Walk-In Attendee</h3>
                
                <form wire:submit.prevent="registerWalkIn" class="space-y-4">
                    <div>
                        <label class="block text-xs font-black text-gray-400 uppercase mb-1">Full Name *</label>
                        <input type="text" wire:model="name" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none" required>
                        @error('name') <span class="text-red-500 text-xs font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-black text-gray-400 uppercase mb-1">Phone Number (Optional)</label>
                        <input type="text" wire:model="phone" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-black text-gray-400 uppercase mb-1">Email Address (Optional)</label>
                        <input type="email" wire:model="email" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Leave blank to auto-generate">
                        @error('email') <span class="text-red-500 text-xs font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-black text-gray-400 uppercase mb-1">Category (Optional)</label>
                        <input type="text" wire:model="category" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Defaults to 'Member' if blank">
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 text-xs font-black uppercase text-gray-400 hover:text-gray-600 transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2.5 bg-blue-900 text-white rounded-xl text-xs font-black uppercase tracking-wider shadow-md hover:bg-blue-950 transition">
                            Save & Add
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>