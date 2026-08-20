<x-app-layout>
    <x-slot name="header">Find & Merge Duplicate Attendees</x-slot>
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
        @if(session('success'))<div class="mb-5 rounded-xl bg-green-50 p-4 font-bold text-green-700">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="mb-5 rounded-xl bg-red-50 p-4 font-bold text-red-700">{{ $errors->first() }}</div>@endif

        <div class="mb-6">
            <h1 class="text-2xl font-black text-slate-900">Find & merge duplicate attendees</h1>
            <p class="mt-1 text-sm text-slate-500">Search for a name, phone, or email, select two records that are really the same person, and choose which one should survive. Their registrations, attendance history, and edit history all move to the record you keep.</p>
        </div>

        <form method="GET" class="mb-6 flex gap-3">
            <input type="text" name="q" value="{{ $query }}" placeholder="Search by name, phone, or email" class="w-full rounded-xl border-slate-200">
            <button class="rounded-xl bg-blue-900 px-5 py-3 text-xs font-black uppercase tracking-wider text-white">Search</button>
        </form>

        @if($query !== '')
            <form method="GET" action="{{ route('participants.duplicates.compare') }}">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-4">Select</th><th class="px-5 py-4">Name</th><th class="px-5 py-4">Contact</th><th class="px-5 py-4">Category</th><th class="px-5 py-4">Registrations</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($participants as $participant)
                            <tr>
                                <td class="px-5 py-4"><input type="checkbox" name="ids[]" value="{{ $participant->id }}" class="rounded border-slate-300"></td>
                                <td class="px-5 py-4 font-bold text-slate-900">{{ $participant->name }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $participant->email ?: '—' }}<br>{{ $participant->phone ?: '' }}</td>
                                <td class="px-5 py-4">{{ $participant->category }}</td>
                                <td class="px-5 py-4">{{ $participant->registrations_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">No matches found.</td></tr>
                        @endforelse
                    </tbody>
                </table></div></div>
                @if($participants->isNotEmpty())
                    <button class="mt-5 rounded-xl bg-slate-900 px-5 py-3 text-xs font-black uppercase tracking-wider text-white">Compare selected (pick exactly 2)</button>
                @endif
            </form>
        @endif
    </div>
</x-app-layout>
