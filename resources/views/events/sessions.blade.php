<x-app-layout>
    <x-slot name="header">Manage sessions</x-slot>

    <div class="py-10 bg-[#f8fafc]">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <a href="{{ route('events.attendance', $event) }}" class="text-xs font-bold text-slate-500 hover:text-slate-800">&larr; Back to attendance</a>
                <h1 class="mt-2 text-2xl font-black text-slate-900">{{ $event->title }} &middot; Sessions</h1>
                <p class="mt-1 text-sm text-slate-500">By default each day has one session. Add more if a day has, say, a morning and an evening meeting — each can be checked into separately.</p>
            </div>

            @if(session('success'))
                <div class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{{ session('error') }}</div>
            @endif

            <div class="space-y-4">
                @foreach($days as $row)
                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <h2 class="text-sm font-black text-slate-900">Day {{ $row['day'] }}</h2>
                        <ul class="mt-3 space-y-2">
                            @foreach($row['sessions'] as $s)
                                <li class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-2.5 text-sm">
                                    <span class="font-bold text-slate-700">{{ $s['label'] ?: 'Session '.$s['session'] }}</span>
                                    @if($s['id'])
                                        <form method="POST" action="{{ route('events.sessions.destroy', [$event, $s['id']]) }}" onsubmit="return confirm('Remove this session?')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs font-bold text-rose-600 hover:text-rose-800">Remove</button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        <form method="POST" action="{{ route('events.sessions.store', $event) }}" class="mt-4 flex flex-wrap items-end gap-2">
                            @csrf
                            <input type="hidden" name="day" value="{{ $row['day'] }}">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">New session label (optional)</label>
                                <input type="text" name="label" maxlength="100" placeholder="e.g. Evening" class="mt-1 rounded-lg border-slate-300 text-sm">
                            </div>
                            <button class="rounded-lg bg-blue-900 px-3.5 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-blue-800">Add session to Day {{ $row['day'] }}</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
