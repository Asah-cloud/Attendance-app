<x-app-layout>
    <x-slot name="header">{{ $form->title }} · Responses</x-slot>
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div><h1 class="text-2xl font-black text-slate-900">Responses</h1><p class="text-sm text-slate-500">{{ $form->title }} · {{ $responses->total() }} response(s)</p></div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('events.forms.responses.export', [$event, $form]) }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-black uppercase tracking-wider text-white">Export Excel</a>
                <a href="{{ route('events.forms.responses.pdf', [$event, $form]) }}" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-black uppercase tracking-wider text-white">Export PDF</a>
                <a href="{{ route('events.forms.edit', [$event, $form]) }}" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-black uppercase tracking-wider text-white">Back to form</a>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wider text-slate-500"><tr>
                <th class="px-5 py-4">Submitted</th>
                @foreach($fields as $field)<th class="px-5 py-4">{{ $field->label }}</th>@endforeach
            </tr></thead>
            <tbody class="divide-y divide-slate-100">@forelse($responses as $response)
                <tr>
                    <td class="px-5 py-4 text-slate-500">{{ $response->created_at->format('M j, Y g:i A') }}</td>
                    @foreach($fields as $field)<td class="px-5 py-4 text-slate-700">{{ $response->answers[$field->field_key] ?? '—' }}</td>@endforeach
                </tr>
            @empty<tr><td colspan="{{ $fields->count() + 1 }}" class="px-5 py-12 text-center text-slate-500">No responses yet.</td></tr>@endforelse</tbody>
        </table></div></div><div class="mt-5">{{ $responses->links() }}</div>
    </div>
</x-app-layout>
