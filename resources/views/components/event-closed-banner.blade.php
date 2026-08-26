@props(['event'])

@if(in_array($event->status, ['closed', 'cancelled'], true))
    <div class="mb-8 flex items-center gap-4 rounded-2xl border border-slate-600/30 bg-gradient-to-r from-slate-700 to-slate-600 p-5 text-white shadow-xl shadow-slate-100">
        <div class="rounded-full bg-white/10 p-3">
            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
        </div>
        <div>
            <span class="mb-1 block text-[10px] font-black uppercase leading-none tracking-[0.2em] text-slate-300">
                {{ $event->status === 'cancelled' ? 'Event Cancelled' : 'Event Closed' }}
            </span>
            <span class="text-sm font-bold">
                @if($event->status === 'cancelled')
                    This event was cancelled. Attendance, food distribution, and new registrations are disabled.
                @else
                    This event has ended. Attendance and food distribution are locked, and new registrations can no longer be added.
                @endif
            </span>
        </div>
    </div>
@endif
