<x-app-layout>
    <x-slot name="header">Forms</x-slot>
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div><h1 class="text-2xl font-black text-slate-900">Forms</h1><p class="text-sm text-slate-500">Manage registration, confirmations, feedback, and surveys for this event.</p></div>
            <a href="{{ route('events.forms.create', $event) }}" class="rounded-xl bg-blue-900 px-4 py-2 text-xs font-black uppercase tracking-wider text-white">Create form</a>
        </div>

        @can('manageWhenOpen', $event)
            <div class="mb-8 grid gap-4 md:grid-cols-2">
                <a href="{{ route('events.registration-form.edit', $event) }}" class="group rounded-2xl border border-blue-200 bg-blue-50 p-6 transition hover:border-blue-400 hover:shadow-md">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-black uppercase tracking-widest text-blue-600">Registration</p>
                            <h2 class="mt-2 text-lg font-black text-slate-900">Registration form</h2>
                            <p class="mt-2 text-sm text-slate-600">Configure the public registration page, custom fields, registration link, and QR code.</p>
                        </div>
                        <span class="text-2xl font-black text-blue-700 transition group-hover:translate-x-1">&rarr;</span>
                    </div>
                </a>

                <a href="{{ route('events.confirmations.index', $event) }}" class="group rounded-2xl border border-emerald-200 bg-emerald-50 p-6 transition hover:border-emerald-400 hover:shadow-md">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-black uppercase tracking-widest text-emerald-700">Communication</p>
                            <h2 class="mt-2 text-lg font-black text-slate-900">Confirmations</h2>
                            <p class="mt-2 text-sm text-slate-600">Import contacts, customize the confirmation message, and send attendance confirmations.</p>
                        </div>
                        <span class="text-2xl font-black text-emerald-700 transition group-hover:translate-x-1">&rarr;</span>
                    </div>
                </a>
            </div>
        @endcan

        <div class="mb-4">
            <h2 class="text-lg font-black text-slate-900">Custom forms</h2>
            <p class="text-sm text-slate-500">Feedback forms, surveys, and other information you want to collect.</p>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-4">Form</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Responses</th><th class="px-5 py-4">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100">@forelse($forms as $form)
                <tr>
                    <td class="px-5 py-4 font-bold text-slate-900">{{ $form->title }}</td>
                    <td class="px-5 py-4"><span class="rounded-full px-3 py-1 text-xs font-black uppercase {{ $form->isOpen() ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-800' }}">{{ $form->isOpen() ? 'Open' : 'Closed' }}</span></td>
                    <td class="px-5 py-4 text-slate-600">{{ $form->responses_count }}</td>
                    <td class="px-5 py-4"><div class="flex flex-wrap gap-3">
                        <a href="{{ route('events.forms.edit', [$event, $form]) }}" class="text-xs font-bold text-slate-700">Edit</a>
                        <a href="{{ route('events.forms.responses', [$event, $form]) }}" class="text-xs font-bold text-blue-700">Responses</a>
                        <a href="{{ route('events.forms.print-qr', [$event, $form]) }}" target="_blank" class="text-xs font-bold text-slate-500">QR</a>
                        <form method="POST" action="{{ route('events.forms.destroy', [$event, $form]) }}" onsubmit="return confirm('Delete this form and all its responses?')">@csrf @method('DELETE')<button class="text-xs font-bold text-red-700">Delete</button></form>
                    </div></td>
                </tr>
            @empty<tr><td colspan="4" class="px-5 py-12 text-center text-slate-500">No forms yet.</td></tr>@endforelse</tbody>
        </table></div></div>
    </div>
</x-app-layout>
