<div>
    {{-- Search & Walk-in Action Section --}}
    <div class="mb-8 flex flex-col md:flex-row gap-4 items-center justify-between">
        <div class="relative group w-full md:flex-1">
            <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none">
                <svg class="h-6 w-6 text-gray-400 group-focus-within:text-blue-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Search by name, category, or phone..."
                   class="block w-full pl-14 pr-12 py-5 border-2 border-gray-100 rounded-2xl leading-5 bg-gray-50/50 placeholder-gray-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 focus:bg-white text-lg transition-all shadow-sm">
            
            {{-- Loading Spinner for Search --}}
            <div wire:loading wire:target="search" class="absolute inset-y-0 right-0 pr-5 flex items-center">
                <svg class="animate-spin h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>

        {{-- Walk-in Form Trigger Button --}}
        @can('update', $event)
        @if(!in_array($mode, ['arrival', 'staff'], true))
        <button type="button" wire:click="$dispatch('openWalkInModal')" class="w-full md:w-auto h-full px-6 py-5 bg-blue-900 border border-blue-700 text-white rounded-2xl font-black text-sm uppercase tracking-wider shadow-md hover:bg-blue-950 transition-all transform hover:-translate-y-0.5 active:scale-95 whitespace-nowrap flex items-center justify-center gap-2">
            <span>➕ Add Walk-in Member</span>
        </button>
        @endif
        @else
        <a href="{{ match($mode) { 'arrival' => route('events.arrival.scanner', $event), 'staff' => route('support-staff.checkin.scanner', $event), default => route('events.scanner', $event) } }}" class="flex w-full items-center justify-center whitespace-nowrap rounded-2xl bg-blue-900 px-6 py-5 text-sm font-black uppercase tracking-wider text-white shadow-md hover:bg-blue-950 md:w-auto">Open QR scanner</a>
        @endcan
    </div>

    {{-- Table Section --}}
    <div class="bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.02)] overflow-hidden border border-gray-100">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-50">
                <thead>
                    <tr class="bg-gray-50/80">
                        <th scope="col" class="px-8 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">
                            Member Details
                        </th>
                        <th scope="col" class="px-8 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">
                            Contact
                        </th>
                        <th scope="col" class="px-8 py-4 text-center text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">
                            Registry Action
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-50">
                    @forelse($users as $user)
                        @php $isPresent = in_array($user->id, $attendedUserIds); @endphp
                        <tr wire:key="user-{{ $user->id }}-day-{{ $selectedDay }}" 
                            class="transition-all duration-200 {{ $isPresent ? 'bg-green-50/30' : 'hover:bg-blue-50/50' }}">
                            
                            <td class="px-8 py-5 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full flex items-center justify-center font-bold text-sm {{ $isPresent ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">
                                        {{ substr($user->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-black text-gray-900">{{ $user->name }}</div>
                                        <div class="mt-0.5">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-black bg-white border border-gray-100 text-gray-500 uppercase tracking-wider">
                                                {{ $user->category ?? 'General Member' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                @can('update', $event)
                                <div class="ml-2 mt-2 flex items-center gap-3">
                                    @if($mode === 'staff')
                                        <button type="button" wire:click="startEditingStaff({{ $user->id }})" class="text-xs font-bold text-blue-600 hover:text-blue-800">Edit</button>
                                    @endif
                                    <button type="button" wire:click="deleteUser({{ $user->id }})"
                                            wire:confirm="Are you sure you want to permanently remove this member from the event?"
                                            class="text-xs font-bold text-red-500 hover:text-red-700">Delete</button>
                                </div>
                                @endcan
                            </td>

                            <td class="px-8 py-5 whitespace-nowrap">
                                <div class="flex items-center text-gray-500 font-mono text-sm tracking-tighter">
                                    <svg class="w-3 h-3 me-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                    {{ $user->phone }}
                                </div>
                            </td>

                            <td class="px-8 py-5 whitespace-nowrap text-center">
                                @can('update', $event)
                                @if($isPresent)
                                    <div class="flex flex-col items-center gap-2">
                                        <span class="inline-flex items-center px-6 py-2 rounded-xl text-xs font-black bg-green-600 text-white shadow-lg shadow-green-100 animate-in fade-in zoom-in duration-300">
                                            <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                            </svg>
                                            {{ match($mode) { 'arrival' => 'ARRIVED', 'staff' => 'CHECKED IN', default => 'PRESENT' } }}
                                        </span>
                                        <button wire:click="toggleAttendance({{ $user->id }})" 
                                                wire:loading.attr="disabled"
                                                class="text-red-400 text-[10px] font-black uppercase tracking-widest hover:text-red-600 transition-colors disabled:opacity-50">
                                            Undo Action
                                        </button>
                                    </div>
                                @else
                                    <button wire:click="toggleAttendance({{ $user->id }})" 
                                            wire:loading.attr="disabled"
                                            class="group relative inline-flex items-center justify-center px-8 py-3 bg-white border-2 border-blue-600 text-blue-600 rounded-xl font-black text-xs uppercase tracking-widest overflow-hidden transition-all hover:bg-blue-600 hover:text-white active:scale-95 disabled:opacity-50">
                                        
                                        <span wire:loading.remove wire:target="toggleAttendance({{ $user->id }})">
                                            {{ match($mode) { 'arrival' => 'Check in arrival', 'staff' => 'Check In', default => 'Mark Present' } }}
                                        </span>
                                        
                                        <span wire:loading wire:target="toggleAttendance({{ $user->id }})" class="flex items-center">
                                            <svg class="animate-spin h-4 w-4 me-2" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            Saving...
                                        </span>
                                    </button>
                                @endif
                                @else
                                    <span class="inline-flex rounded-xl bg-slate-100 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-500">{{ $isPresent ? ($mode === 'staff' ? 'Checked in' : 'Present') : 'Use QR scanner' }}</span>
                                @endcan
                            </td>
                        </tr>
                        @if($mode === 'staff' && $editingStaffId === $user->id)
                            <tr wire:key="staff-editor-{{ $user->id }}" class="bg-blue-50/70">
                                <td colspan="3" class="px-4 py-4 sm:px-8">
                                    <form wire:submit="saveStaff" class="grid min-w-0 gap-3 rounded-xl border border-blue-100 bg-white p-4 shadow-sm sm:grid-cols-2">
                                        <div class="min-w-0">
                                            <label for="staff-name-{{ $user->id }}" class="text-[10px] font-black uppercase tracking-wider text-slate-500">Staff name</label>
                                            <input id="staff-name-{{ $user->id }}" type="text" wire:model="editName" class="mt-1 block w-full min-w-0 rounded-lg border-slate-300 text-sm" required>
                                            @error('editName')<p class="mt-1 whitespace-normal text-xs font-bold text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="min-w-0">
                                            <label for="staff-category-{{ $user->id }}" class="text-[10px] font-black uppercase tracking-wider text-slate-500">Category</label>
                                            <input id="staff-category-{{ $user->id }}" type="text" wire:model="editCategory" class="mt-1 block w-full min-w-0 rounded-lg border-slate-300 text-sm" placeholder="e.g. Usher" required>
                                            @error('editCategory')<p class="mt-1 whitespace-normal text-xs font-bold text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="flex flex-wrap gap-2 sm:col-span-2">
                                            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-xs font-black text-white" wire:loading.attr="disabled" wire:target="saveStaff">Save changes</button>
                                            <button type="button" wire:click="cancelEditingStaff" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-black text-slate-600">Cancel</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="3" class="px-8 py-20 text-center bg-gray-50/30">
                                <div class="flex flex-col items-center">
                                    <div class="p-4 bg-gray-100 rounded-full mb-4">
                                        <svg class="h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </div>
                                    <p class="text-gray-400 font-black uppercase text-xs tracking-widest">No members found</p>
                                    <p class="text-gray-400 text-sm mt-1">Try adjusting your search for "{{ $search }}"</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($users->hasPages())
            <div class="px-8 py-6 bg-gray-50 border-t border-gray-100">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    {{-- Walk-in Modal Subcomponent --}}
    @can('update', $event)
        @if(!in_array($mode, ['arrival', 'staff'], true))<livewire:add-walk-in-modal :event="$event" />@endif
    @endcan
</div>
