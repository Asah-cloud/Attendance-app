<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <div>
                <h2 class="font-bold text-2xl text-white leading-tight">
                    {{ __('Church Events') }}
                </h2>
                <p class="text-blue-100 text-sm mt-1">Manage and track attendance for all services</p>
            </div>
            
            @role('admin')
                <a href="{{ route('events.create') }}" class="inline-flex items-center px-6 py-3 bg-white text-blue-800 rounded-xl font-bold text-sm shadow-lg hover:bg-red-50 hover:text-red-700 transition-all transform hover:-translate-y-1 active:scale-95">
                    <svg class="w-5 h-5 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Create New Event
                </a>
            @endrole
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Search & Filter Section (Visual Placeholder) --}}
            <div class="mb-6 flex justify-between items-center">
                <h3 class="text-lg font-bold text-blue-900">Active Schedule</h3>
            </div>

            <div class="bg-white overflow-hidden shadow-[0_10px_40px_rgba(0,0,0,0.04)] sm:rounded-2xl border border-gray-100">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50/50 border-b border-gray-100 text-[11px] uppercase text-gray-400 font-black tracking-widest">
                                <th class="p-6">Event Details</th>
                                <th class="p-6">Live Status</th>
                                <th class="p-6">Calendar</th>
                                <th class="p-6 text-right">Management</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($events as $event)
                            <tr class="hover:bg-blue-50/30 transition-colors group">
                                <td class="p-6">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center font-bold me-4 group-hover:bg-blue-600 group-hover:text-white transition">
                                            {{ substr($event->title, 0, 1) }}
                                        </div>
                                        <div>
                                            <div class="font-bold text-gray-900 text-lg">{{ $event->title }}</div>
                                            <div class="text-xs text-gray-400 italic font-medium">{{ Str::limit($event->description, 60) }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-6">
                                    @php $status = strtolower($event->status); @endphp
                                    <span class="px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-tighter border
                                        {{ $status === 'active' ? 'bg-green-50 text-green-600 border-green-100' : '' }}
                                        {{ $status === 'upcoming' ? 'bg-blue-50 text-blue-600 border-blue-100' : '' }}
                                        {{ $status === 'closed' ? 'bg-red-50 text-red-600 border-red-100' : '' }}">
                                        ● {{ $status }}
                                    </span>
                                </td>
                                <td class="p-6 text-sm">
                                    <div class="flex flex-col">
                                        <span class="text-gray-700 font-bold uppercase text-xs">
                                            {{ \Carbon\Carbon::parse($event->event_date)->format('D, M d') }}
                                        </span>
                                        @if($event->end_date)
                                            <span class="text-gray-400 text-[11px]">Ends {{ \Carbon\Carbon::parse($event->end_date)->format('M d, Y') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="p-6">
                                    <div class="flex justify-end items-center gap-4">
                                        {{-- Attendance Button --}}
                                        <a href="{{ route('events.attendance', $event->id) }}" 
                                           class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-blue-700 to-blue-800 text-white rounded-xl font-bold text-[11px] uppercase tracking-wider shadow-md hover:shadow-blue-200 hover:scale-105 transition-all">
                                            <svg class="w-4 h-4 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                            </svg>
                                            Take Attendance
                                        </a>

                                        @role('admin')
                                        <a href="{{ route('events.edit', $event->id) }}" class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Edit">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </a>
                                        @endrole

                                        @role('admin')
                                        <form action="{{ route('events.destroy', $event->id) }}" method="POST" onsubmit="return confirm('Are you sure?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="p-2 text-gray-300 hover:text-red-600 hover:bg-red-50 rounded-lg transition">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </form>
                                        @endrole
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>