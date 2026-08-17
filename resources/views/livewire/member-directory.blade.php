<div>
    {{-- Search and Header Section --}}
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <div class="flex items-center gap-4">
            <h3 class="text-sm font-black text-blue-900 uppercase tracking-widest">
                Total Registered: {{ $users->total() }}
            </h3>
            
            <a href="{{ route('admin.register-person') }}" class="bg-green-600 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-black transition-all shadow-lg">
                + Register New Person
            </a>
        </div>
        
        <div class="flex gap-2">
            <input type="text" 
                   wire:model.live="search" 
                   placeholder="Search name or email..." 
                   class="text-xs border-gray-200 rounded-xl focus:ring-blue-500 w-64 shadow-sm">
            
            <button type="button" wire:click="searchNow" wire:loading.attr="disabled" wire:target="searchNow" class="bg-blue-900 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-black transition-all disabled:cursor-wait disabled:opacity-60">
                Search
            </button>
        </div>
    </div>

    {{-- Table Section --}}
    <div class="bg-white shadow-[0_15px_50px_rgba(0,0,0,0.03)] border border-gray-100 rounded-3xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50/50 border-b border-gray-100">
                    <tr class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">
                        <th class="px-6 py-4">Full Name</th>
                        <th class="px-6 py-4">Contact Info</th>
                        <th class="px-6 py-4">Spatie Role</th>
                        <th class="px-6 py-4">Category</th>
                        <th class="px-6 py-4 text-right">Management</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($users as $user)
                        <tr class="hover:bg-blue-50/30 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-gray-900">{{ $user->name }}</div>
                                <div class="text-[10px] text-gray-400 uppercase font-medium">Joined {{ $user->created_at->format('M d, Y') }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <div class="flex flex-col">
                                    <span>{{ $user->email }}</span>
                                    <span class="text-xs font-mono text-blue-600">{{ $user->phone }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @foreach($user->roles as $role)
                                    <span class="px-2 py-1 bg-blue-100 text-blue-700 text-[9px] font-black uppercase rounded-md tracking-tighter">
                                        {{ $role->name }}
                                    </span>
                                @endforeach
                            </td>
                            <td class="px-6 py-4 text-xs font-bold text-gray-500 italic">
                                {{ $user->category ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2 items-center">
                                    <a href="{{ route('admin.users.edit', $user) }}" 
                                       class="text-[9px] font-black uppercase tracking-widest text-blue-600 bg-blue-50 hover:bg-blue-600 hover:text-white px-3 py-2 rounded-lg transition-all">
                                        Edit
                                    </a>
                                    
                                    <form action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('Are you sure?')">
                                        @csrf 
                                        @method('DELETE')
                                        <button type="submit" class="text-[9px] font-black uppercase tracking-widest text-red-500 bg-red-50 hover:bg-red-600 hover:text-white px-3 py-2 rounded-lg transition-all">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-gray-400 text-sm">
                                No members found matching "{{ $search }}".
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination Section --}}
        <div class="p-6 bg-gray-50/50 border-t border-gray-100">
            {{ $users->links() }}
        </div>
    </div>
</div>
