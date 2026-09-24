@php
    $inModal = $inModal ?? false;
    $editing = isset($message);
    $initial = [
        'selected' => array_map('strval', (array) old('participant_ids', $selectedParticipantIds ?? [])),
        'mode' => old('mode', $message->mode ?? 'smart'),
        'subject' => old('subject', $message->subject ?? ''),
        'email_body' => old('email_body', $message->email_body ?? ''),
        'sms_body' => old('sms_body', $message->sms_body ?? ''),
        'seedCount' => count($seedRecipients ?? []),
    ];
@endphp

<div class="flex max-h-full min-h-0 flex-col overflow-hidden bg-white {{ $inModal ? 'rounded-none sm:rounded-2xl' : 'rounded-2xl border border-slate-200 shadow-sm' }}">
    <div class="flex items-center justify-between bg-slate-900 px-5 py-3 text-white">
        <div>
            <p class="text-sm font-black">{{ $editing ? 'Edit and resend message' : 'New message' }}</p>
            <p class="text-[11px] text-slate-300">{{ $event->title }}@if($editing) · the original stays in history; sending creates a new record @endif</p>
        </div>
        @if($inModal)
            <button type="button" @click="$dispatch('close-compose')" aria-label="Close" class="rounded-full p-1.5 text-slate-300 transition hover:bg-white/10 hover:text-white">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </button>
        @else
            <a href="{{ route('events.messages.index', $event) }}" class="text-xs font-bold text-slate-300 underline">Back to messages</a>
        @endif
    </div>

    <form method="POST"
          action="{{ $editing ? route('events.messages.resend', [$event, $message]) : route('events.messages.store', $event) }}"
          enctype="multipart/form-data"
          class="flex min-h-0 flex-1 flex-col"
          x-data="messageComposer(@js($composeConfig), @js($initial))"
          @submit="if (!confirmSend()) { $event.preventDefault() } else { sending = true }">
        @csrf
        <input type="hidden" name="_compose" value="1">

        <div class="min-h-0 flex-1 overflow-y-auto p-5">
            @if($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-700">
                    <ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-5">
                <div class="space-y-6 lg:col-span-3">
                    {{-- To --}}
                    <section>
                        <div class="mb-2 flex items-center justify-between">
                            <label class="text-xs font-black uppercase tracking-widest text-slate-400">To</label>
                            <span class="text-xs font-bold text-slate-500" x-text="recipientTotal + (fileName ? ' + file' : '') + ' selected'"></span>
                        </div>

                        <div class="rounded-xl border border-slate-200 p-2 focus-within:border-blue-400 focus-within:ring-2 focus-within:ring-blue-100">
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="p in list" :key="p.id">
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 py-1 pl-3 pr-1.5 text-xs font-bold text-blue-800">
                                        <span x-text="p.name"></span>
                                        <template x-for="c in channelsFor(p)" :key="c"><span class="rounded bg-white px-1 text-[9px] font-black uppercase text-blue-700" x-text="c === 'sms' ? 'SMS' : 'Email'"></span></template>
                                        <span x-show="channelsFor(p).length === 0" class="rounded bg-amber-100 px-1 text-[9px] font-black uppercase text-amber-700">no channel</span>
                                        <button type="button" @click="toggle(p.id)" :aria-label="'Remove ' + p.name" class="rounded-full px-1.5 text-blue-400 hover:bg-blue-100 hover:text-blue-700">&times;</button>
                                        <input type="hidden" name="participant_ids[]" :value="p.id">
                                    </span>
                                </template>
                                <input type="text" x-model="search" @focus="pickerOpen = true" @keydown.escape.stop="pickerOpen = false"
                                       placeholder="Add registrants — search by name, email or phone"
                                       class="min-w-[14rem] flex-1 border-0 bg-transparent px-2 py-1 text-sm text-slate-700 placeholder-slate-400 focus:ring-0">
                            </div>
                        </div>

                        <div x-show="pickerOpen" x-cloak x-transition @click.outside="pickerOpen = false" class="mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-3 py-2 text-xs">
                                <span class="font-bold text-slate-500" x-text="matches.length + ' of ' + participants.length + ' registrants'"></span>
                                <span class="flex gap-3">
                                    <button type="button" class="font-bold text-blue-700" @click="addAll()">Add all shown</button>
                                    <button type="button" class="font-bold text-slate-500" @click="clearAll()">Clear</button>
                                    <button type="button" class="font-bold text-slate-500" @click="pickerOpen = false">Done</button>
                                </span>
                            </div>
                            <div class="max-h-56 divide-y divide-slate-50 overflow-y-auto">
                                <template x-for="p in matches" :key="p.id">
                                    <button type="button" @click="toggle(p.id)" class="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-slate-50">
                                        <span class="flex h-4 w-4 items-center justify-center rounded border text-[10px] font-black" :class="selected.has(p.id) ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300'"><span x-show="selected.has(p.id)">&#10003;</span></span>
                                        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold text-slate-800" x-text="p.name"></span><span class="block truncate text-[11px] text-slate-400" x-text="(p.email || '—') + ' · ' + (p.phone || '—')"></span></span>
                                        <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase" :class="p.ghana ? 'bg-emerald-50 text-emerald-700' : (p.phone ? 'bg-slate-100 text-slate-600' : 'bg-slate-50 text-slate-400')" x-text="p.ghana ? 'Ghana' : (p.phone ? 'Foreign' : 'No phone')"></span>
                                    </button>
                                </template>
                                <p x-show="matches.length === 0" class="px-3 py-6 text-center text-xs text-slate-400">No registrants match.</p>
                            </div>
                        </div>

                        @if(!empty($seedRecipients))
                            <div class="mt-3" x-ref="seed">
                                <p class="mb-1.5 text-xs font-bold text-slate-500">From the original upload (untick to leave out):</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($seedRecipients as $recipient)
                                        <label class="flex items-center gap-2 rounded-full bg-slate-50 px-3 py-1.5 text-xs text-slate-600">
                                            <input type="checkbox" name="recipient_keys[]" value="{{ $recipient['id'] }}" checked @change="seedCount = $refs.seed.querySelectorAll('input:checked').length" class="rounded border-gray-300">
                                            {{ $recipient['name'] }} · {{ $recipient['email'] ?: '—' }} · {{ $recipient['phone'] ?: '—' }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="mt-3">
                            <label class="text-[11px] font-bold text-slate-500">Or add people from a spreadsheet <span class="font-normal text-slate-400">(columns: Name, Email, Phone)</span></label>
                            <input type="file" name="recipients_file" accept=".csv,.xlsx,.xls" @change="fileName = $event.target.files[0] ? $event.target.files[0].name : ''"
                                   class="mt-1 block w-full rounded-xl border border-gray-200 bg-gray-50/30 p-2 text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-extrabold file:text-blue-700">
                        </div>
                    </section>

                    {{-- How to send --}}
                    <section>
                        <label class="mb-2 block text-xs font-black uppercase tracking-widest text-slate-400">How to send</label>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach([
                                'smart' => ['Smart routing', 'Ghana numbers get SMS, everyone else email. One channel each.'],
                                'both' => ['Both, no routing', 'Email to everyone with an email AND SMS to every Ghana number.'],
                                'email_only' => ['Email only', 'Everyone with a valid email. No SMS.'],
                                'sms_only' => ['SMS only', 'Ghana numbers only. Everyone else is skipped.'],
                            ] as $value => [$label, $hint])
                                <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border p-3 transition" :class="mode === '{{ $value }}' ? 'border-blue-500 bg-blue-50/70' : 'border-slate-200 hover:border-slate-300'">
                                    <input type="radio" name="mode" value="{{ $value }}" x-model="mode" class="mt-0.5">
                                    <span><span class="block text-sm font-black text-slate-900">{{ $label }}</span><span class="mt-0.5 block text-[11px] leading-4 text-slate-500">{{ $hint }}</span></span>
                                </label>
                            @endforeach
                        </div>
                    </section>

                    {{-- Content --}}
                    <section>
                        <div class="mb-3 flex gap-1 rounded-xl bg-slate-100 p-1">
                            <button type="button" @click="tab = 'email'" class="flex-1 rounded-lg px-3 py-2 text-xs font-black uppercase tracking-wide transition" :class="tab === 'email' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'">Email <span x-show="emailBody.trim()" class="ml-1 inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span></button>
                            <button type="button" @click="tab = 'sms'" class="flex-1 rounded-lg px-3 py-2 text-xs font-black uppercase tracking-wide transition" :class="tab === 'sms' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'">SMS <span x-show="smsBody.trim()" class="ml-1 inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span></button>
                        </div>

                        <div x-show="tab === 'email'" class="space-y-3">
                            <input type="text" name="subject" x-model="subject" placeholder="Subject" class="w-full rounded-xl border-gray-200 text-sm">
                            <textarea name="email_body" x-model="emailBody" rows="9" placeholder="Write the email. Leave empty if this message won't send any email." class="w-full rounded-xl border-gray-200 text-sm"></textarea>
                            <div>
                                <label class="text-[11px] font-bold text-slate-500">Attachments <span class="font-normal text-slate-400">(email only · up to 5 files · 10MB each)</span></label>
                                <input type="file" name="attachments[]" multiple @change="attachNames = Array.from($event.target.files).map(f => f.name)"
                                       class="mt-1 block w-full rounded-xl border border-gray-200 bg-gray-50/30 p-2 text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-extrabold file:text-blue-700">
                                @if($editing && !empty($message->attachments))<p class="mt-1 text-[11px] text-slate-400">The original attachments ({{ collect($message->attachments)->pluck('name')->implode(', ') }}) are kept unless you choose new files.</p>@endif
                            </div>
                        </div>

                        <div x-show="tab === 'sms'" x-cloak class="space-y-2">
                            <textarea name="sms_body" x-model="smsBody" rows="5" maxlength="1000" placeholder="Write the text message. Keep it short. Leave empty if this message won't send any SMS." class="w-full rounded-xl border-gray-200 text-sm"></textarea>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-500"><span x-text="smsInfo.len"></span> characters · <span x-text="smsInfo.parts"></span> <span x-text="smsInfo.parts === 1 ? 'message' : 'messages'"></span> per person <span x-show="smsInfo.unicode" class="text-amber-600">(special characters shorten each message)</span></span>
                                <span x-show="!cfg.smsEnabled" class="font-bold text-amber-600">SMS is switched off on the platform</span>
                            </div>
                        </div>
                    </section>
                </div>

                {{-- Live preview --}}
                <aside class="space-y-4 lg:col-span-2">
                    <div class="lg:sticky lg:top-0">
                        <p class="mb-2 text-xs font-black uppercase tracking-widest text-slate-400">Preview</p>
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 p-3">
                            <div class="rounded-xl bg-white p-4 shadow-sm">
                                <p class="text-center text-[11px] font-bold text-slate-500" x-text="cfg.organization"></p>
                                <p class="mt-1 text-center text-sm font-black text-slate-900" x-text="cfg.eventTitle"></p>
                                <p class="mt-3 text-xs font-black text-slate-500" x-text="subject || '(no subject)'"></p>
                                <h4 class="mt-2 text-sm font-black text-slate-900" x-text="'Hello ' + firstName + '!'"></h4>
                                <template x-for="(line, i) in emailLines" :key="i"><p class="mt-2 text-xs leading-5 text-slate-600" x-text="line"></p></template>
                                <p x-show="emailLines.length === 0" class="mt-2 text-xs italic text-slate-300">Your email will appear here.</p>
                                <div x-show="attachNames.length" class="mt-3 flex flex-wrap gap-1"><template x-for="n in attachNames" :key="n"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500" x-text="n"></span></template></div>
                                <p class="mt-4 text-[11px] text-slate-400" x-text="'Regards, ' + cfg.organization"></p>
                            </div>
                            <p class="mt-1.5 text-center text-[10px] text-slate-400">Email</p>

                            <div class="mt-3 flex justify-end">
                                <div class="max-w-[85%] rounded-2xl rounded-br-sm bg-blue-600 px-3.5 py-2 text-xs leading-5 text-white shadow-sm">
                                    <span x-show="smsBody.trim()" class="whitespace-pre-line" x-text="smsBody"></span>
                                    <span x-show="!smsBody.trim()" class="italic text-blue-200">Your text message will appear here.</span>
                                </div>
                            </div>
                            <p class="mt-1.5 text-center text-[10px] text-slate-400">SMS</p>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        {{-- Send bar --}}
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-5 py-3">
            <div class="flex flex-wrap items-center gap-1.5 text-[11px] font-black uppercase">
                <span class="rounded-full bg-blue-100 px-2.5 py-1 text-blue-700" x-text="summary.mail + ' email' + (summary.mail === 1 ? '' : 's')"></span>
                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-emerald-700" x-text="summary.sms + ' text' + (summary.sms === 1 ? '' : 's')"></span>
                <span x-show="summary.skipped > 0" class="rounded-full bg-amber-100 px-2.5 py-1 text-amber-700" x-text="summary.skipped + ' can\'t be reached'"></span>
                <span x-show="fileName || seedCount > 0" class="rounded-full bg-slate-200 px-2.5 py-1 text-slate-600">+ file &amp; earlier recipients counted on send</span>
            </div>
            <div class="flex items-center gap-3">
                @if($inModal)<button type="button" @click="$dispatch('close-compose')" class="text-xs font-bold text-slate-500 hover:text-slate-800">Cancel</button>@endif
                <button type="submit" :disabled="!canSend || sending" class="rounded-xl bg-blue-900 px-6 py-2.5 text-xs font-black uppercase tracking-widest text-white shadow transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-40" x-text="sending ? 'Sending…' : '{{ $editing ? 'Resend message' : 'Send message' }}'"></button>
            </div>
        </div>
    </form>
</div>

@once
    @verbatim
    <script>
        // Mirrors CustomMessageService::determineChannels() so the panel can show, before sending,
        // which channel each person will get. Keep the two in step.
        window.messageComposer = function (cfg, init) {
            return {
                cfg: cfg,
                participants: cfg.participants,
                selected: new Set(init.selected),
                search: '',
                pickerOpen: false,
                tab: (!init.email_body && init.sms_body) ? 'sms' : 'email',
                mode: init.mode,
                subject: init.subject,
                emailBody: init.email_body,
                smsBody: init.sms_body,
                fileName: '',
                attachNames: [],
                seedCount: init.seedCount,
                sending: false,

                get list() { return this.participants.filter(p => this.selected.has(p.id)); },
                get matches() {
                    const q = this.search.trim().toLowerCase();
                    return this.participants.filter(p => !q
                        || p.name.toLowerCase().includes(q)
                        || (p.email || '').toLowerCase().includes(q)
                        || (p.phone || '').includes(q));
                },
                get recipientTotal() { return this.selected.size + this.seedCount; },
                get firstName() { return this.list.length ? this.list[0].name.split(' ')[0] : 'Ama'; },
                get emailLines() { return this.emailBody.split(/\r?\n/).filter(l => l.trim() !== ''); },
                get smsInfo() {
                    const text = this.smsBody;
                    const unicode = /[^\x00-\x7F]/.test(text);
                    const single = unicode ? 70 : 160, multi = unicode ? 67 : 153;
                    const len = text.length;
                    return { len: len, unicode: unicode, parts: len === 0 ? 0 : (len <= single ? 1 : Math.ceil(len / multi)) };
                },
                channelsFor(p) {
                    const hasEmail = this.emailBody.trim() !== '' && p.hasEmail;
                    const isGhana = this.smsBody.trim() !== '' && this.cfg.smsEnabled && p.ghana;
                    switch (this.mode) {
                        case 'email_only': return hasEmail ? ['mail'] : [];
                        case 'sms_only': return isGhana ? ['sms'] : [];
                        case 'both': return [hasEmail ? 'mail' : null, isGhana ? 'sms' : null].filter(Boolean);
                        default: return isGhana ? ['sms'] : (hasEmail ? ['mail'] : []);
                    }
                },
                get summary() {
                    const counts = { mail: 0, sms: 0, skipped: 0 };
                    this.list.forEach(p => {
                        const channels = this.channelsFor(p);
                        if (channels.length === 0) counts.skipped++;
                        channels.forEach(c => counts[c]++);
                    });
                    return counts;
                },
                get canSend() {
                    return (this.recipientTotal > 0 || this.fileName !== '')
                        && (this.emailBody.trim() !== '' || this.smsBody.trim() !== '');
                },
                toggle(id) { this.selected.has(id) ? this.selected.delete(id) : this.selected.add(id); this.selected = new Set(this.selected); },
                addAll() { this.matches.forEach(p => this.selected.add(p.id)); this.selected = new Set(this.selected); },
                clearAll() { this.selected = new Set(); },
                confirmSend() {
                    if (!this.canSend) return false;
                    const s = this.summary;
                    return window.confirm('Send this message now?\n\n' + s.mail + ' email(s), ' + s.sms + ' text(s) to the selected registrants'
                        + ((this.fileName || this.seedCount) ? ', plus everyone from the file / earlier recipients.' : '.'));
                },
            };
        };
    </script>
    @endverbatim
@endonce
