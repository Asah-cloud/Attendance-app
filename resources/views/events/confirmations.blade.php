<x-app-layout>
    <x-slot name="header">Attendance Confirmations</x-slot>
    <div class="py-10"><div class="mx-auto max-w-5xl space-y-8 px-4 sm:px-6 lg:px-8">
        @if($errors->any())<div class="rounded-2xl bg-red-50 p-4 font-bold text-red-700">{{ $errors->first() }}</div>@endif

        <div class="rounded-3xl border border-blue-100 bg-gradient-to-br from-blue-950 to-blue-800 p-7 text-white shadow-xl">
            <h3 class="text-xl font-black">Bring hard-copy attendees online</h3>
            <p class="mt-2 text-sm text-blue-100">Import the contact list you already collected on paper, then send each person a friendly message asking them to confirm their attendance. They only appear in this event's reports and exports once they confirm.</p>
        </div>

        <section class="rounded-3xl border border-gray-100 bg-white p-7 shadow-sm">
            <h3 class="text-lg font-black">1. Import your contact list</h3>
            <p class="mt-1 text-sm text-gray-500">Upload an Excel or CSV file with three columns, in this order: <strong>Name</strong>, <strong>Phone</strong>, <strong>Email</strong>. Name is optional if you have a phone or email.</p>
            <form method="POST" action="{{ route('events.confirmations.import', $event) }}" enctype="multipart/form-data" class="mt-5 flex flex-wrap items-center gap-3">
                @csrf
                <input type="file" name="file" required class="block text-sm text-gray-600 file:mr-4 file:rounded-xl file:border-0 file:bg-blue-50 file:px-4 file:py-2.5 file:text-xs file:font-black file:uppercase file:tracking-widest file:text-blue-700 hover:file:bg-blue-100">
                <button class="rounded-xl bg-blue-600 px-5 py-3 text-xs font-black uppercase tracking-widest text-white">Import contacts</button>
            </form>
        </section>

        <section class="rounded-3xl border border-gray-100 bg-white p-7 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-black">2. Customize the confirmation form</h3>
                    <p class="mt-1 text-sm text-gray-500">These are the only questions attendees answer — their name, phone, and email are already known, so we don't ask again.</p>
                </div>
                @if($previewCode)
                    <a href="{{ route('attendance.confirm.show', $previewCode) }}" target="_blank" class="rounded-xl bg-violet-100 px-4 py-2.5 text-xs font-black uppercase tracking-widest text-violet-900 hover:bg-violet-200">Preview live form</a>
                @endif
            </div>

            <div class="mt-5 space-y-3">
                @forelse($customFields as $field)
                    <div class="flex items-center justify-between rounded-2xl border border-gray-100 p-4">
                        <div><p class="font-bold">{{ $field->label }}</p><p class="text-xs text-gray-500">{{ $field->field_type }} · {{ $field->is_required ? 'Required' : 'Optional' }}</p></div>
                        <form method="POST" action="{{ route('events.registration-fields.destroy', [$event, $field]) }}">@csrf @method('DELETE')<button class="text-sm font-bold text-red-600">Remove</button></form>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No questions yet — attendees will only see the welcome message and the confirm button.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('events.registration-fields.store', $event) }}" class="mt-7 grid gap-4 md:grid-cols-2">
                @csrf
                <div><label class="text-xs font-black uppercase text-gray-500">Question label</label><input name="label" value="{{ old('label') }}" required class="mt-2 w-full rounded-xl border-gray-200"></div>
                <div><label class="text-xs font-black uppercase text-gray-500">Field type</label><select name="field_type" class="mt-2 w-full rounded-xl border-gray-200">@foreach($fieldTypes as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach</select></div>
                <div class="md:col-span-2"><label class="text-xs font-black uppercase text-gray-500">Options (one per line, for select/radio)</label><textarea name="options" rows="3" class="mt-2 w-full rounded-xl border-gray-200">{{ old('options') }}</textarea><x-input-error :messages="$errors->get('options')" /></div>
                <label class="flex items-center gap-2"><input type="checkbox" name="is_required" value="1"><span class="font-bold">Required question</span></label>
                <button class="rounded-xl bg-blue-600 px-5 py-3 font-bold text-white">Add question</button>
            </form>

            <p class="mt-5 text-xs text-gray-400">Note: these questions are shared with this event's public registration form, since both use the same form settings.</p>
        </section>

        <section class="rounded-3xl border border-gray-100 bg-white p-7 shadow-sm">
            <h3 class="text-lg font-black">3. Customize the welcome message</h3>
            <p class="mt-1 text-sm text-gray-500">This is what attendees receive by email and SMS. Use <code>{name}</code> and <code>{event}</code> as placeholders — they're swapped in automatically for each person.</p>
            <form method="POST" action="{{ route('events.confirmations.message.update', $event) }}" class="mt-5 space-y-4">
                @csrf @method('PATCH')
                <textarea name="confirmation_message" rows="4" required class="w-full rounded-xl border-gray-200">{{ old('confirmation_message', $event->confirmation_message ?: $defaultMessage) }}</textarea>
                <x-input-error :messages="$errors->get('confirmation_message')" />
                <button class="rounded-xl bg-gray-900 px-5 py-3 text-sm font-bold text-white">Save message</button>
            </form>
        </section>

        <section class="rounded-3xl border border-gray-100 bg-white p-7 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h3 class="text-lg font-black">4. Send confirmation requests</h3><p class="mt-1 text-sm text-gray-500">{{ $registrations->total() }} contact(s) awaiting confirmation.</p></div>
                @if($registrations->total() > 0)
                    <form method="POST" action="{{ route('events.confirmations.send', $event) }}">
                        @csrf
                        <button class="rounded-xl bg-emerald-600 px-5 py-3 text-xs font-black uppercase tracking-widest text-white">Send to everyone pending</button>
                    </form>
                @endif
            </div>

            <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-4">Name</th><th class="px-5 py-4">Contact</th><th class="px-5 py-4">Last sent</th><th class="px-5 py-4">Actions</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($registrations as $registration)
                        <tr>
                            <td class="px-5 py-4 font-bold text-slate-900">{{ $registration->participant->name }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $registration->participant->email ?: '—' }}<br>{{ $registration->participant->phone ?: '' }}</td>
                            <td class="px-5 py-4 text-slate-500">{{ $registration->confirmation_sent_at?->format('M j, Y g:i A') ?? 'Not sent yet' }}</td>
                            <td class="px-5 py-4">
                                <form method="POST" action="{{ route('events.confirmations.destroy', [$event, $registration]) }}" onsubmit="return confirm('Remove this contact from the confirmation list?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs font-bold text-red-700">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-12 text-center text-slate-500">No imported contacts are awaiting confirmation.</td></tr>
                    @endforelse
                </tbody>
            </table></div></div>
            <div class="mt-5">{{ $registrations->links() }}</div>
        </section>
    </div></div>
</x-app-layout>
