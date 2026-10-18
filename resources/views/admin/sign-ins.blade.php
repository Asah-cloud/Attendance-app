<x-app-layout>
    <x-slot name="header">Sign-ins</x-slot>
    <div class="mx-auto max-w-7xl">
        <div class="mb-6">
            <h1 class="text-2xl font-black text-slate-900">Sign-in activity</h1>
            <p class="text-sm text-slate-500">Everyone who signed in, and failed sign-in attempts, from the last {{ App\Models\LoginActivity::KEEP_DAYS }} days.</p>
        </div>

        <form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-[2fr_1.5fr_1fr_1fr_auto]">
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name or email" class="rounded-xl border-slate-200 text-sm">
            <select name="company" class="rounded-xl border-slate-200 text-sm">
                <option value="">All organisations</option>
                @foreach($companies as $company)<option value="{{ $company->id }}" @selected(($filters['company'] ?? null) == $company->id)>{{ $company->name }}</option>@endforeach
            </select>
            <select name="role" class="rounded-xl border-slate-200 text-sm">
                <option value="">All roles</option>
                @foreach(['admin' => 'Admin', 'manager' => 'Manager', 'usher' => 'Usher', 'audit_head' => 'Audit Head', 'audit_staff' => 'Audit Staff', 'form_viewer' => 'Response viewer'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['role'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-xl border-slate-200 text-sm">
                <option value="">Signed in & failed</option>
                <option value="successful" @selected(($filters['status'] ?? null) === 'successful')>Signed in</option>
                <option value="failed" @selected(($filters['status'] ?? null) === 'failed')>Failed attempts</option>
            </select>
            <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white">Filter</button>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wider text-slate-500">
                    <tr><th class="px-5 py-3">Time</th><th class="px-5 py-3">Person</th><th class="px-5 py-3">Role</th><th class="px-5 py-3">Organisation</th><th class="px-5 py-3">Result</th><th class="px-5 py-3">Device</th><th class="px-5 py-3">IP address</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($activities as $activity)
                        <tr class="{{ $activity->alert ? 'bg-red-50' : '' }}">
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $activity->created_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</td>
                            <td class="px-5 py-3"><div class="font-bold text-slate-900">{{ $activity->user?->name ?? 'Unknown account' }}</div><div class="text-xs text-slate-500">{{ $activity->email ?? '—' }}</div></td>
                            <td class="px-5 py-3 text-slate-600">{{ $activity->roles ? $activity->roleLabel() : '—' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $activity->company?->name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                @if($activity->successful)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">Signed in</span>
                                @else
                                    <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-bold text-red-700">Failed{{ $activity->alert ? ' · alert' : '' }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $activity->deviceLabel() }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-slate-500">{{ $activity->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-10 text-center text-slate-500">No sign-ins match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div>
        <div class="mt-5">{{ $activities->links() }}</div>
    </div>
</x-app-layout>
