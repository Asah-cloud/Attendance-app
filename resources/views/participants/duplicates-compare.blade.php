<x-app-layout>
    <x-slot name="header">Compare & Merge</x-slot>
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="mb-6">
            <h1 class="text-2xl font-black text-slate-900">Compare & merge</h1>
            <p class="mt-1 text-sm text-slate-500">Pick which record should survive. It keeps its own details, filling in only the fields it's missing from the other. The other record's registrations, attendance history, and edit history all move over, then it's deleted.</p>
        </div>

        <form method="POST" action="{{ route('participants.duplicates.merge') }}" onsubmit="return confirm('Merge these two records? This cannot be undone.')">
            @csrf
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                @foreach($participants as $participant)
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <label class="mb-4 flex items-center gap-2 text-sm font-black text-slate-900">
                            <input type="radio" name="primary_id" value="{{ $participant->id }}" required class="text-blue-600">
                            Keep this record
                        </label>
                        <dl class="space-y-2 text-sm">
                            <div><dt class="text-xs font-bold uppercase text-slate-400">Name</dt><dd class="font-bold text-slate-900">{{ $participant->name }}</dd></div>
                            <div><dt class="text-xs font-bold uppercase text-slate-400">Email</dt><dd>{{ $participant->email ?: '—' }}</dd></div>
                            <div><dt class="text-xs font-bold uppercase text-slate-400">Phone</dt><dd>{{ $participant->phone ?: '—' }}</dd></div>
                            <div><dt class="text-xs font-bold uppercase text-slate-400">Member ID</dt><dd>{{ $participant->member_id ?: '—' }}</dd></div>
                            <div><dt class="text-xs font-bold uppercase text-slate-400">Category</dt><dd>{{ $participant->category ?: '—' }}</dd></div>
                            <div><dt class="text-xs font-bold uppercase text-slate-400">Gender</dt><dd>{{ $participant->gender ?: '—' }}</dd></div>
                            <div><dt class="text-xs font-bold uppercase text-slate-400">Registrations</dt><dd>{{ $participant->registrations_count }}</dd></div>
                            <div><dt class="text-xs font-bold uppercase text-slate-400">Attendance records</dt><dd>{{ $participant->attendances_count }}</dd></div>
                            <div><dt class="text-xs font-bold uppercase text-slate-400">Created</dt><dd>{{ $participant->created_at->format('M j, Y') }}</dd></div>
                        </dl>
                        <input type="hidden" name="all_ids[]" value="{{ $participant->id }}">
                    </div>
                @endforeach
            </div>
            <button class="mt-6 rounded-xl bg-red-600 px-6 py-3 text-xs font-black uppercase tracking-wider text-white">Merge records</button>
            <a href="{{ route('participants.duplicates.index') }}" class="mt-6 ml-3 text-xs font-bold text-slate-500">Cancel</a>
        </form>
    </div>
</x-app-layout>
