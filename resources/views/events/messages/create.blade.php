<x-app-layout>
    <x-slot name="header">Compose message</x-slot>

    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-slate-900">Compose a custom message</h1>
                <p class="text-sm text-slate-500">{{ $event->title }}</p>
            </div>
            <a href="{{ route('events.messages.index', $event) }}" class="text-xs font-bold text-slate-500 underline">Back to message history</a>
        </div>

        @if($errors->any())
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">
                <ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('events.messages.store', $event) }}" enctype="multipart/form-data" class="space-y-8"
              x-data="{ selected: new Set(), search: '' }">
            @csrf

            {{-- 1. Recipients from existing registrants --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-black uppercase tracking-widest text-slate-500">1. Pick from registrants</h2>
                <p class="mt-1 text-xs text-slate-400">{{ $registrants->count() }} people registered for this event.</p>

                <div class="mt-4 flex items-center gap-3">
                    <input type="text" x-model="search" placeholder="Search by name..." class="w-full max-w-xs rounded-xl border-gray-200 text-sm">
                    <button type="button" class="text-xs font-bold text-blue-700" @click="$refs.list.querySelectorAll('input[type=checkbox]').forEach(cb => { if (cb.offsetParent !== null) { cb.checked = true; selected.add(cb.value); } })">Select all visible</button>
                    <button type="button" class="text-xs font-bold text-slate-500" @click="$refs.list.querySelectorAll('input[type=checkbox]').forEach(cb => { cb.checked = false; }); selected = new Set()">Clear</button>
                    <span class="ml-auto text-xs font-bold text-slate-500" x-text="selected.size + ' selected'"></span>
                </div>

                <div x-ref="list" class="mt-4 max-h-72 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-100">
                    @forelse($registrants as $participant)
                        <label x-show="search === '' || '{{ strtolower($participant->name) }}'.includes(search.toLowerCase())" class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50">
                            <input type="checkbox" name="participant_ids[]" value="{{ $participant->id }}"
                                   @change="$event.target.checked ? selected.add('{{ $participant->id }}') : selected.delete('{{ $participant->id }}')"
                                   class="rounded border-gray-300">
                            <span class="text-sm font-bold text-slate-800">{{ $participant->name }}</span>
                            <span class="text-xs text-slate-400">{{ $participant->email ?: '—' }} · {{ $participant->phone ?: '—' }}</span>
                        </label>
                    @empty
                        <p class="p-6 text-center text-sm text-slate-400">No registrants yet.</p>
                    @endforelse
                </div>
            </section>

            {{-- 2. Extra recipients via upload --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-black uppercase tracking-widest text-slate-500">2. Add extra recipients (optional)</h2>
                <p class="mt-1 text-xs text-slate-400">Upload a spreadsheet for people not already registered. Columns in order: Name, Email, Phone.</p>
                <input type="file" name="recipients_file" accept=".csv,.xlsx,.xls" class="mt-4 block w-full rounded-xl border border-gray-200 bg-gray-50/30 p-3 text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-xs file:font-extrabold file:text-blue-700">
            </section>

            {{-- 3. Email content --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-black uppercase tracking-widest text-slate-500">3. Email content</h2>
                <p class="mt-1 text-xs text-slate-400">Leave blank if this campaign won't send any email.</p>
                <div class="mt-4"><label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Subject</label>
                    <input type="text" name="subject" value="{{ old('subject') }}" class="w-full rounded-xl border-gray-200 text-sm" placeholder="e.g. Important update about the event">
                </div>
                <div class="mt-4"><label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Email body</label>
                    <textarea name="email_body" rows="6" class="w-full rounded-xl border-gray-200 text-sm" placeholder="Type the email message...">{{ old('email_body') }}</textarea>
                </div>
                <div class="mt-4">
                    <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Attachments (email only, up to 5 files)</label>
                    <input type="file" name="attachments[]" multiple class="block w-full rounded-xl border border-gray-200 bg-gray-50/30 p-3 text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-xs file:font-extrabold file:text-blue-700">
                    <p class="mt-2 text-[10px] text-gray-400 italic">PDF, images, Office docs, or CSV/text. Max 10MB per file. Never sent with SMS.</p>
                </div>
            </section>

            {{-- 4. SMS content --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-black uppercase tracking-widest text-slate-500">4. SMS content</h2>
                <p class="mt-1 text-xs text-slate-400">Leave blank if this campaign won't text anyone. Keep it short — this is separate from the email body above.</p>
                <div class="mt-4"><label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">SMS message</label>
                    <textarea name="sms_body" rows="3" maxlength="1000" class="w-full rounded-xl border-gray-200 text-sm" placeholder="Type the SMS message...">{{ old('sms_body') }}</textarea>
                </div>
            </section>

            {{-- 5. Channel mode --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-black uppercase tracking-widest text-slate-500">5. Choose how to send</h2>
                <div class="mt-4 space-y-3">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-blue-100 bg-blue-50/60 p-4">
                        <input type="radio" name="mode" value="smart" checked class="mt-1">
                        <span><span class="block text-sm font-black text-slate-900">Smart routing (recommended)</span><span class="mt-1 block text-xs text-slate-600">Ghana numbers get SMS, everyone else gets email. Each person receives exactly one channel.</span></span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <input type="radio" name="mode" value="both" class="mt-1">
                        <span><span class="block text-sm font-black text-slate-900">Both, no routing</span><span class="mt-1 block text-xs text-slate-600">Everyone with a valid email gets the email AND everyone with a Ghana number gets the SMS — independently, not either/or.</span></span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <input type="radio" name="mode" value="email_only" class="mt-1">
                        <span><span class="block text-sm font-black text-slate-900">Email only</span><span class="mt-1 block text-xs text-slate-600">Send to everyone with a valid email address, Ghana or not. No SMS sent.</span></span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <input type="radio" name="mode" value="sms_only" class="mt-1">
                        <span><span class="block text-sm font-black text-slate-900">SMS only</span><span class="mt-1 block text-xs text-slate-600">Only recipients with a Ghana number are texted. Everyone else is skipped — no email sent.</span></span>
                    </label>
                </div>
            </section>

            <div class="flex justify-end">
                <button type="submit" class="rounded-xl bg-blue-900 px-8 py-3.5 text-sm font-black uppercase tracking-widest text-white shadow-lg hover:bg-blue-800">Send message</button>
            </div>
        </form>
    </div>
</x-app-layout>
