<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <section class="overflow-hidden rounded-3xl bg-[#071426] p-7 text-white shadow-xl">
            <p class="text-xs font-black uppercase tracking-[.24em] text-amber-300">Company roster</p>
            <h1 class="mt-2 text-3xl font-black">Event support staff</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">Import people who support company events without giving them system accounts. Each person receives one permanent staff ID and QR code that works at every selected event.</p>
        </section>

        @if(session('success'))<div class="rounded-2xl bg-emerald-50 p-4 font-bold text-emerald-800">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="rounded-2xl bg-rose-50 p-4 text-rose-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

        @if(auth()->user()->hasRole('admin'))
            <form method="GET" action="{{ route('support-staff.index') }}" class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <label for="company_id" class="text-sm font-bold text-slate-700">Company</label>
                <div class="mt-2 flex gap-3">
                    <select id="company_id" name="company_id" class="block w-full rounded-xl border-slate-300" onchange="this.form.submit()">
                        @foreach($companies as $company)<option value="{{ $company->id }}" @selected($company->id === $selectedCompany->id)>{{ $company->name }}</option>@endforeach
                    </select>
                    <noscript><button class="rounded-xl bg-blue-700 px-5 py-2 font-bold text-white">Open</button></noscript>
                </div>
            </form>
        @endif

        <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-xl font-black text-slate-900">Import staff roster</h2>
            <p class="mt-2 text-sm text-slate-600">Columns: <strong>A Name</strong>, <strong>B Department</strong>, <strong>C Category</strong>. Category may be left blank and will default to Staff. Re-importing the same name and department updates the existing person.</p>
            <form method="POST" action="{{ route('support-staff.import') }}" enctype="multipart/form-data" class="mt-5 grid gap-5 lg:grid-cols-2">@csrf
                @if(auth()->user()->hasRole('admin'))<input type="hidden" name="company_id" value="{{ $selectedCompany->id }}">@endif
                <div><label class="text-sm font-bold text-slate-700">Spreadsheet</label><input class="mt-2 block w-full rounded-xl border-slate-300" type="file" name="file" accept=".xlsx,.xls,.csv" required></div>
                <div><p class="text-sm font-bold text-slate-700">Assign everyone in this file to</p><div class="mt-2 max-h-44 space-y-2 overflow-auto rounded-xl border border-slate-200 p-3">
                    @forelse($events as $event)<label class="flex items-center gap-3 text-sm"><input type="checkbox" name="event_ids[]" value="{{ $event->id }}"> <span><strong>{{ $event->title }}</strong> · {{ $event->event_date->format('j M Y') }}</span></label>@empty<p class="text-sm text-slate-500">There are no upcoming events.</p>@endforelse
                </div></div>
                <div class="lg:col-span-2"><button class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-black text-white disabled:opacity-50" @disabled($events->isEmpty())>Import and assign staff</button></div>
            </form>
        </section>

        <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-xl font-black text-slate-900">Manage staff by event</h2>
            <p class="mt-2 text-sm text-slate-600">Check staff in when they collect their badge, see who has checked in, and print their badges — separate from your attendee tools.</p>
            <div class="mt-5 divide-y divide-slate-100 rounded-2xl border border-slate-200">
                @forelse($events as $event)
                    <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <div><strong class="text-sm text-slate-900">{{ $event->title }}</strong><span class="ml-2 text-xs text-slate-500">{{ $event->event_date->format('j M Y') }}</span></div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('support-staff.checkin', $event) }}" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-bold text-white">Staff check-in</a>
                            <a href="{{ route('support-staff.report', $event) }}" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700">Report</a>
                            <a href="{{ route('events.badges', ['event' => $event, 'category' => 'Staff']) }}" class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-800">Print staff badges</a>
                        </div>
                    </div>
                @empty
                    <p class="p-4 text-sm text-slate-500">There are no upcoming events.</p>
                @endforelse
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="border-b border-slate-200 p-6"><h2 class="text-xl font-black text-slate-900">Company staff roster</h2><p class="mt-1 text-sm text-slate-500">{{ $staff->total() }} staff member(s)</p></div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-6 py-4">Staff</th><th class="px-6 py-4">Department</th><th class="px-6 py-4">Category</th><th class="px-6 py-4">Assigned events</th></tr></thead><tbody class="divide-y divide-slate-100">
                @forelse($staff as $person)<tr><td class="px-6 py-4"><strong class="text-slate-900">{{ $person->name }}</strong><div class="font-mono text-xs text-slate-500">{{ $person->staff_code }}</div></td><td class="px-6 py-4">{{ $person->department ?: '—' }}</td><td class="px-6 py-4">{{ $person->category }}</td><td class="px-6 py-4"><div class="flex flex-wrap gap-2">@foreach($person->registrations as $registration)<a class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-800" href="{{ route('events.badges', ['event' => $registration->event, 'category' => $person->category]) }}">{{ $registration->event->title }}</a>@endforeach</div></td></tr>
                @empty<tr><td colspan="4" class="px-6 py-12 text-center text-slate-500">No event support staff imported yet.</td></tr>@endforelse
            </tbody></table></div><div class="p-5">{{ $staff->links() }}</div>
        </section>
    </div>
</x-app-layout>
