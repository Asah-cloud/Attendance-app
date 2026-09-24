@php
    $inModal = $inModal ?? false;
    // Three ways to open this panel: a brand-new message, a saved draft / scheduled message being
    // edited in place, or a message that was already sent being resent as a new one.
    $pending = isset($message) && $message->isPending();
    $editing = isset($message) && ! $pending;
    $initial = [
        'selected' => array_map('strval', (array) old('participant_ids', $selectedParticipantIds ?? [])),
        'mode' => old('mode', $message->mode ?? 'smart'),
        'subject' => old('subject', $message->subject ?? ''),
        'email_body' => old('email_body', $message->email_body ?? ''),
        'sms_body' => old('sms_body', $message->sms_body ?? ''),
        'seedCount' => count($seedRecipients ?? []),
        'draftId' => $pending && $message->isDraft() ? $message->id : null,
        'autosave' => ! $editing && ! ($pending && $message->isScheduled()),
        'scheduledAt' => old('scheduled_at', $pending && $message->scheduled_at ? $message->scheduled_at->timezone(config('app.timezone'))->format('Y-m-d\TH:i') : ''),
        'minSchedule' => now()->addMinutes(2)->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
    ];
@endphp

<div class="flex max-h-full min-h-0 flex-col overflow-hidden bg-white {{ $inModal ? 'rounded-none sm:rounded-2xl' : 'rounded-2xl border border-slate-200 shadow-sm' }}">
    <div class="flex items-center justify-between bg-slate-900 px-5 py-3 text-white">
        <div>
            <p class="text-sm font-black">{{ $editing ? 'Edit and resend message' : ($pending ? ($message->isScheduled() ? 'Edit scheduled message' : 'Edit draft') : 'New message') }}</p>
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
          action="{{ $pending ? route('events.messages.update', [$event, $message]) : ($editing ? route('events.messages.resend', [$event, $message]) : route('events.messages.store', $event)) }}"
          enctype="multipart/form-data"
          class="flex min-h-0 flex-1 flex-col"
          x-data="messageComposer(@js($composeConfig), @js($initial))"
          @submit="onSubmit($event)">
        @csrf
        @if($pending)@method('PUT')@endif
        <input type="hidden" name="_compose" value="1">
        @unless($editing)<input type="hidden" name="draft_id" :value="draftId ?? ''">@endunless
        {{-- Pressing Enter in a text box submits the form using the first submit button, so that
             first button must be the harmless one (save a draft), never "send". --}}
        @unless($editing)<button type="submit" name="intent" value="draft" class="hidden" tabindex="-1" aria-hidden="true"></button>@endunless

        <div class="min-h-0 flex-1 overflow-y-auto p-5">
            @if($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-700">
                    <ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            {{-- Templates and draft status --}}
            <div class="mb-5 flex flex-wrap items-center gap-2">
                <div class="relative" @click.outside="templatesOpen = false">
                    <button type="button" @click="templatesOpen = !templatesOpen; saveTemplateOpen = false" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-black uppercase tracking-wide text-slate-700 hover:bg-slate-100">
                        Templates <span class="rounded-full bg-slate-100 px-1.5 text-[10px]" x-text="templates.length"></span>
                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                    </button>
                    <div x-show="templatesOpen" x-cloak x-transition.opacity.duration.100ms class="absolute left-0 z-20 mt-1 w-72 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                        <p x-show="templates.length === 0" class="px-3 py-4 text-center text-xs text-slate-400">No templates yet. Write a message, then choose “Save as template”.</p>
                        <ul class="max-h-60 divide-y divide-slate-50 overflow-y-auto">
                            <template x-for="t in templates" :key="t.id">
                                <li class="flex items-center gap-1 px-2 py-1.5 hover:bg-slate-50">
                                    <button type="button" @click="applyTemplate(t)" class="min-w-0 flex-1 px-1 py-1 text-left"><span class="block truncate text-sm font-bold text-slate-800" x-text="t.name"></span><span class="block truncate text-[11px] text-slate-400" x-text="t.subject || t.email_body || t.sms_body"></span></button>
                                    <button type="button" @click="deleteTemplate(t)" :aria-label="'Delete template ' + t.name" class="rounded-full px-2 py-1 text-slate-300 hover:bg-rose-50 hover:text-rose-600">&times;</button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>

                <div class="relative" @click.outside="saveTemplateOpen = false">
                    <button type="button" @click="saveTemplateOpen = !saveTemplateOpen; templatesOpen = false; templateError = ''" :disabled="!emailBody.trim() && !smsBody.trim()" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-black uppercase tracking-wide text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40">Save as template</button>
                    <div x-show="saveTemplateOpen" x-cloak x-transition.opacity.duration.100ms class="absolute left-0 z-20 mt-1 w-72 rounded-xl border border-slate-200 bg-white p-3 shadow-xl">
                        <label class="text-[11px] font-bold text-slate-500">Template name</label>
                        <input type="text" x-model="templateName" maxlength="80" placeholder="e.g. Event reminder" @keydown.enter.prevent="saveTemplate()" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <p x-show="templateError" class="mt-1 text-[11px] font-semibold text-rose-600" x-text="templateError"></p>
                        <p class="mt-1 text-[10px] text-slate-400">Saving under an existing name replaces that template.</p>
                        <button type="button" @click="saveTemplate()" :disabled="!templateName.trim() || templateSaving" class="mt-2 w-full rounded-lg bg-blue-900 px-3 py-2 text-xs font-black uppercase tracking-wide text-white disabled:opacity-40">Save template</button>
                    </div>
                </div>

                <span x-show="templateNotice" x-cloak class="text-[11px] font-bold text-emerald-600" x-text="templateNotice"></span>
                <span class="ml-auto text-[11px] text-slate-400" x-text="autosaveLabel"></span>
            </div>

            <div class="grid gap-6" :class="showPreview ? 'lg:grid-cols-5' : ''">
                <div class="space-y-6" :class="showPreview ? 'lg:col-span-3' : ''">
                    {{-- To --}}
                    <section>
                        <div class="mb-2 flex items-center justify-between">
                            <label class="text-xs font-black uppercase tracking-widest text-slate-400">To</label>
                            <span class="text-xs font-bold text-slate-500" x-text="recipientTotal + (fileName ? ' + file' : '') + ' selected'"></span>
                        </div>

                        {{-- Every selected id is posted, however many chips are drawn. --}}
                        <template x-for="id in selectedIds" :key="id"><input type="hidden" name="participant_ids[]" :value="id"></template>

                        {{-- The box and its dropdown share one outside-click boundary, so clicking the box never closes the list. --}}
                        <div @click.outside="pickerOpen = false">
                            <div class="rounded-xl border border-slate-200 p-2 focus-within:border-blue-400 focus-within:ring-2 focus-within:ring-blue-100" @click="pickerOpen = true; $refs.search.focus()">
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="p in visibleChips" :key="p.id">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 py-1 pl-3 pr-1.5 text-xs font-bold text-blue-800">
                                            <span x-text="p.name"></span>
                                            <template x-for="c in channelsFor(p)" :key="c"><span class="rounded bg-white px-1 text-[9px] font-black uppercase text-blue-700" x-text="c === 'sms' ? 'SMS' : 'Email'"></span></template>
                                            <span x-show="channelsFor(p).length === 0" class="rounded bg-amber-100 px-1 text-[9px] font-black uppercase text-amber-700">no channel</span>
                                            <button type="button" @click.stop="toggle(p.id)" :aria-label="'Remove ' + p.name" class="rounded-full px-1.5 text-blue-400 hover:bg-blue-100 hover:text-blue-700">&times;</button>
                                        </span>
                                    </template>
                                    <button type="button" x-show="hiddenChipCount > 0" @click.stop="showAllChips = true" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 hover:bg-slate-200" x-text="'+ ' + hiddenChipCount + ' more'"></button>
                                    <button type="button" x-show="showAllChips && selected.size > chipLimit" @click.stop="showAllChips = false" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 hover:bg-slate-200">Show fewer</button>
                                    <input type="text" x-ref="search" x-model="search" autocomplete="off"
                                           @focus="pickerOpen = true" @input="pickerOpen = true"
                                           @keydown.enter.prevent="addFirst()" @keydown.escape.stop="pickerOpen = false"
                                           @keydown.backspace="if (search === '' && list.length) toggle(list[list.length - 1].id)"
                                           placeholder="Add registrants — search by name, email or phone"
                                           class="min-w-[14rem] flex-1 border-0 bg-transparent px-2 py-1 text-sm text-slate-700 placeholder-slate-400 focus:ring-0">
                                </div>
                            </div>

                            <div x-show="pickerOpen" x-cloak x-transition.opacity.duration.100ms class="mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-3 py-2 text-xs">
                                    <span class="font-bold text-slate-500" x-text="matches.length + ' of ' + totalParticipants + ' registrants'"></span>
                                    <span class="flex gap-3">
                                        <button type="button" class="font-bold text-blue-700" @click="addAll()" x-text="search.trim() ? 'Add all ' + matches.length + ' matching' : 'Add everyone'"></button>
                                        <button type="button" class="font-bold text-slate-500" @click="clearAll()">Clear</button>
                                        <button type="button" class="font-bold text-slate-500" @click="pickerOpen = false">Done</button>
                                    </span>
                                </div>
                                <div class="max-h-56 divide-y divide-slate-50 overflow-y-auto">
                                    <template x-for="p in shown" :key="p.id">
                                        <button type="button" @click="toggle(p.id)" class="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-slate-50">
                                            <span class="flex h-4 w-4 items-center justify-center rounded border text-[10px] font-black" :class="selected.has(p.id) ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300'"><span x-show="selected.has(p.id)">&#10003;</span></span>
                                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold text-slate-800" x-text="p.name"></span><span class="block truncate text-[11px] text-slate-400" x-text="(p.email || '—') + ' · ' + (p.phone || '—')"></span></span>
                                            <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase" :class="p.ghana ? 'bg-emerald-50 text-emerald-700' : (p.phone ? 'bg-slate-100 text-slate-600' : 'bg-slate-50 text-slate-400')" x-text="p.ghana ? 'Ghana' : (p.phone ? 'Foreign' : 'No phone')"></span>
                                        </button>
                                    </template>
                                    <p x-show="matches.length === 0" class="px-3 py-6 text-center text-xs text-slate-400">No registrants match.</p>
                                    <p x-show="matches.length > shown.length" class="bg-slate-50 px-3 py-2 text-center text-[11px] text-slate-400" x-text="'Showing the first ' + shown.length + ' of ' + matches.length + ' — keep typing to narrow it down, or use “Add all” above.'"></p>
                                </div>
                            </div>
                        </div>

                        @if(!empty($seedRecipients))
                            <div class="mt-3" x-ref="seed">
                                @if($pending)<input type="hidden" name="seed_shown" value="1">@endif
                                <p class="mb-1.5 text-xs font-bold text-slate-500">{{ $pending ? 'From the uploaded list you saved (untick to leave out):' : 'From the original upload (untick to leave out):' }}</p>
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
                        <div class="mb-3 flex flex-wrap items-center gap-1.5">
                            <span class="text-[11px] font-bold text-slate-500">Personalise:</span>
                            <template x-for="(label, key) in cfg.tokens" :key="key">
                                <button type="button" @click="insertToken('{' + key + '}')" :title="'Inserts {' + key + '} — becomes each person\'s ' + label.toLowerCase()"
                                        class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-600 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-800" x-text="'{' + key + '}'"></button>
                            </template>
                        </div>
                        <p x-show="unknownTokens.length" x-cloak class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-[11px] font-semibold text-amber-800">
                            Not a known field, so it will be sent exactly as typed: <span x-text="unknownTokens.join(', ')"></span>
                        </p>

                        <div class="mb-3 flex gap-1 rounded-xl bg-slate-100 p-1">
                            <button type="button" @click="tab = 'email'" class="flex-1 rounded-lg px-3 py-2 text-xs font-black uppercase tracking-wide transition" :class="tab === 'email' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'">Email <span x-show="emailBody.trim()" class="ml-1 inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span></button>
                            <button type="button" @click="tab = 'sms'" class="flex-1 rounded-lg px-3 py-2 text-xs font-black uppercase tracking-wide transition" :class="tab === 'sms' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'">SMS <span x-show="smsBody.trim()" class="ml-1 inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span></button>
                        </div>

                        <div x-show="tab === 'email'" class="space-y-3">
                            <input type="text" name="subject" x-model="subject" x-ref="subjectEl" @focus="lastField = 'subject'" placeholder="Subject" class="w-full rounded-xl border-gray-200 text-sm">
                            <textarea name="email_body" x-model="emailBody" x-ref="emailBodyEl" @focus="lastField = 'emailBody'" rows="9" placeholder="Write the email. Leave empty if this message won't send any email." class="w-full rounded-xl border-gray-200 text-sm"></textarea>
                            <div>
                                <label class="text-[11px] font-bold text-slate-500">Attachments <span class="font-normal text-slate-400">(email only · up to 5 files · 10MB each)</span></label>
                                <input type="file" name="attachments[]" multiple @change="attachNames = Array.from($event.target.files).map(f => f.name)"
                                       class="mt-1 block w-full rounded-xl border border-gray-200 bg-gray-50/30 p-2 text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-extrabold file:text-blue-700">
                                @if($editing && !empty($message->attachments))<p class="mt-1 text-[11px] text-slate-400">The original attachments ({{ collect($message->attachments)->pluck('name')->implode(', ') }}) are kept unless you choose new files.</p>@endif
                            </div>
                        </div>

                        <div x-show="tab === 'sms'" x-cloak class="space-y-2">
                            <textarea name="sms_body" x-model="smsBody" x-ref="smsBodyEl" @focus="lastField = 'smsBody'" rows="5" maxlength="1000" placeholder="Write the text message. Keep it short. Leave empty if this message won't send any SMS." class="w-full rounded-xl border-gray-200 text-sm"></textarea>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-500"><span x-text="smsInfo.len"></span> characters (as one person will receive it) · <span x-text="smsInfo.parts"></span> <span x-text="smsInfo.parts === 1 ? 'message' : 'messages'"></span> per person <span x-show="smsInfo.unicode" class="text-amber-600">(special characters shorten each message)</span></span>
                                <span x-show="!cfg.smsEnabled" class="font-bold text-amber-600">SMS is switched off on the platform</span>
                            </div>
                        </div>
                    </section>
                </div>

                {{-- Live preview --}}
                <aside x-show="showPreview" x-cloak x-transition.opacity class="space-y-4 lg:col-span-2">
                    <div class="lg:sticky lg:top-0">
                        <p class="mb-2 text-xs font-black uppercase tracking-widest text-slate-400">Preview</p>
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 p-3">
                            <div class="rounded-xl bg-white p-4 shadow-sm">
                                <p class="text-center text-[11px] font-bold text-slate-500" x-text="cfg.organization"></p>
                                <p class="mt-1 text-center text-sm font-black text-slate-900" x-text="cfg.eventTitle"></p>
                                <p class="mt-3 text-xs font-black text-slate-500" x-text="merge(subject) || '(no subject)'"></p>
                                <h4 class="mt-2 text-sm font-black text-slate-900" x-text="'Hello ' + sample.name + '!'"></h4>
                                <template x-for="(line, i) in emailLines" :key="i"><p class="mt-2 text-xs leading-5 text-slate-600" x-text="line"></p></template>
                                <p x-show="emailLines.length === 0" class="mt-2 text-xs italic text-slate-300">Your email will appear here.</p>
                                <div x-show="attachNames.length" class="mt-3 flex flex-wrap gap-1"><template x-for="n in attachNames" :key="n"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500" x-text="n"></span></template></div>
                                <p class="mt-4 text-[11px] text-slate-400" x-text="'Regards, ' + cfg.organization"></p>
                            </div>
                            <p class="mt-1.5 text-center text-[10px] text-slate-400">Email</p>

                            <div class="mt-3 flex justify-end">
                                <div class="max-w-[85%] rounded-2xl rounded-br-sm bg-blue-600 px-3.5 py-2 text-xs leading-5 text-white shadow-sm">
                                    <span x-show="smsBody.trim()" class="whitespace-pre-line" x-text="merge(smsBody)"></span>
                                    <span x-show="!smsBody.trim()" class="italic text-blue-200">Your text message will appear here.</span>
                                </div>
                            </div>
                            <p class="mt-1.5 text-center text-[10px] text-slate-400">SMS</p>
                            <p class="mt-2 text-center text-[10px] text-slate-400" x-text="'Shown as ' + sample.name + ' would see it' + (list.length ? '' : ' (pick a recipient to preview with a real name)')"></p>
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
                <span x-show="summary.sms > 0 && smsInfo.parts > 0" class="rounded-full bg-slate-200 px-2.5 py-1 text-slate-600" x-text="'≈ ' + (summary.sms * smsInfo.parts) + ' SMS credit' + (summary.sms * smsInfo.parts === 1 ? '' : 's')" title="An estimate: one credit per message part per person texted"></span>
                <span x-show="fileName || seedCount > 0" class="rounded-full bg-slate-200 px-2.5 py-1 text-slate-600">+ file &amp; earlier recipients counted on send</span>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" @click="showPreview = !showPreview" :aria-pressed="showPreview.toString()"
                        class="inline-flex items-center gap-1.5 rounded-xl border px-4 py-2.5 text-xs font-black uppercase tracking-widest transition"
                        :class="showPreview ? 'border-blue-600 bg-blue-50 text-blue-800' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span x-text="showPreview ? 'Hide preview' : 'Preview'"></span>
                </button>
                @if($inModal)<button type="button" @click="$dispatch('close-compose')" class="text-xs font-bold text-slate-500 hover:text-slate-800">Close</button>@endif

                @unless($editing)
                    <button type="submit" name="intent" value="draft" :disabled="sending" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-black uppercase tracking-widest text-slate-700 transition hover:bg-slate-100 disabled:opacity-40">Save draft</button>

                    <div class="relative" @click.outside="scheduleOpen = false">
                        <button type="button" @click="scheduleOpen = !scheduleOpen" :disabled="!canSend || sending" class="inline-flex items-center gap-1.5 rounded-xl border border-blue-300 bg-blue-50 px-4 py-2.5 text-xs font-black uppercase tracking-widest text-blue-800 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-40">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Schedule
                        </button>
                        <div x-show="scheduleOpen" x-cloak x-transition.opacity.duration.100ms class="absolute bottom-full right-0 z-30 mb-2 w-72 rounded-xl border border-slate-200 bg-white p-4 shadow-2xl">
                            <label class="text-[11px] font-black uppercase tracking-widest text-slate-400">Send at</label>
                            <input type="datetime-local" name="scheduled_at" x-model="scheduledAt" :min="minSchedule" class="mt-1.5 w-full rounded-lg border-slate-200 text-sm">
                            <p class="mt-1.5 text-[10px] text-slate-400" x-text="'Times are in ' + cfg.timezone + '. Sent within a minute of this time.'"></p>
                            <button type="submit" name="intent" value="schedule" :disabled="!scheduledAt || !canSend || sending" class="mt-3 w-full rounded-lg bg-blue-900 px-3 py-2 text-xs font-black uppercase tracking-widest text-white disabled:opacity-40">Schedule message</button>
                        </div>
                    </div>
                @endunless

                <button type="submit" name="intent" value="send" :disabled="!canSend || sending" class="rounded-xl bg-blue-900 px-6 py-2.5 text-xs font-black uppercase tracking-widest text-white shadow transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-40" x-text="sending ? 'Working…' : '{{ $editing ? 'Resend message' : 'Send now' }}'"></button>
            </div>
        </div>
    </form>
</div>

@once
    @verbatim
    <script>
        // Two rules are mirrored here from the server so the panel can preview them before sending;
        // keep them in step with their PHP originals:
        //   - which channel each person gets     -> CustomMessageService::determineChannels()
        //   - how {name}-style fields are filled -> App\Support\MergeFields
        window.messageComposer = function (config, init) {
            // The registrant list can be thousands long and never changes, so it lives outside
            // Alpine's reactive state (making every read cheap), with each person's searchable
            // text worked out once here rather than on every keystroke.
            const participants = config.participants.map(p => Object.assign({}, p, {
                haystack: [p.name, p.email || '', p.phone || ''].join(' ').toLowerCase(),
                digits: (p.phone || '').replace(/\D+/g, ''),
            }));
            const cfg = {
                organization: config.organization,
                eventTitle: config.eventTitle,
                smsEnabled: config.smsEnabled,
                timezone: config.timezone,
                tokens: config.tokens,
                urls: config.urls,
            };
            const SHOWN_LIMIT = 60;
            const AUTOSAVE_PAUSE_MS = 1500;
            const TOKEN_PATTERN = /\{\s*([A-Za-z_]+)\s*\}/g;

            return {
                cfg: cfg,
                totalParticipants: participants.length,
                chipLimit: 30,
                showAllChips: false,
                showPreview: false,
                selected: new Set(init.selected),
                search: '',
                pickerOpen: false,
                tab: (!init.email_body && init.sms_body) ? 'sms' : 'email',
                mode: init.mode,
                subject: init.subject,
                emailBody: init.email_body,
                smsBody: init.sms_body,
                lastField: 'emailBody',
                fileName: '',
                attachNames: [],
                seedCount: init.seedCount,
                sending: false,

                // Drafts and scheduling
                draftId: init.draftId,
                canAutosave: init.autosave,
                autosaveLabel: '',
                autosaveTimer: null,
                autosaving: false,
                autosavePromise: null,
                lastSaved: '',
                scheduleOpen: false,
                scheduledAt: init.scheduledAt,
                minSchedule: init.minSchedule,

                // Templates
                templates: config.templates,
                templatesOpen: false,
                saveTemplateOpen: false,
                templateName: '',
                templateError: '',
                templateNotice: '',
                templateSaving: false,

                init() {
                    if (!this.canAutosave) return;
                    ['subject', 'emailBody', 'smsBody', 'mode', 'selected'].forEach(key => this.$watch(key, () => this.queueAutosave()));
                },

                // ----- recipients -------------------------------------------------------------
                get list() { return participants.filter(p => this.selected.has(p.id)); },
                get selectedIds() { return this.list.map(p => p.id); },
                get visibleChips() { return this.showAllChips ? this.list : this.list.slice(0, this.chipLimit); },
                get hiddenChipCount() { return this.showAllChips ? 0 : Math.max(0, this.selected.size - this.chipLimit); },
                get matches() {
                    // Every word typed must appear somewhere in the name, email or phone, in any order;
                    // a typed phone number also matches with or without spaces, dashes or a leading zero.
                    const words = this.search.trim().toLowerCase().split(/\s+/).filter(Boolean);
                    if (words.length === 0) return participants;
                    return participants.filter(p => words.every(w => {
                        if (p.haystack.includes(w)) return true;
                        const digits = w.replace(/\D+/g, '');
                        return digits.length >= 3 && p.digits.includes(digits.replace(/^0/, ''));
                    }));
                },
                get shown() { return this.matches.slice(0, SHOWN_LIMIT); },
                get recipientTotal() { return this.selected.size + this.seedCount; },

                toggle(id) {
                    const next = new Set(this.selected);
                    next.has(id) ? next.delete(id) : next.add(id);
                    this.selected = next;
                },
                addAll() {
                    const next = new Set(this.selected);
                    this.matches.forEach(p => next.add(p.id));
                    this.selected = next;
                },
                addFirst() {
                    const first = this.matches[0];
                    if (first && !this.selected.has(first.id)) this.toggle(first.id);
                    this.search = '';
                },
                clearAll() { this.selected = new Set(); this.showAllChips = false; },

                // ----- merge fields -----------------------------------------------------------
                // Preview values come from the first chosen recipient, or a made-up one.
                get sample() {
                    const person = this.list[0];
                    const name = person ? person.name : 'Ama Mensah';
                    return { name: name, first_name: name.split(/\s+/)[0], event: cfg.eventTitle, organization: cfg.organization };
                },
                merge(text) {
                    const values = this.sample;
                    return (text || '').replace(TOKEN_PATTERN, (whole, key) => {
                        const value = values[key.toLowerCase()];
                        return value === undefined ? whole : value;
                    });
                },
                get unknownTokens() {
                    const found = new Set();
                    [this.subject, this.emailBody, this.smsBody].forEach(text => {
                        (text || '').replace(TOKEN_PATTERN, (whole, key) => {
                            if (!(key.toLowerCase() in cfg.tokens)) found.add(whole);
                            return whole;
                        });
                    });
                    return Array.from(found);
                },
                insertToken(token) {
                    // Goes into whichever text box was used last, or the box for the tab on show.
                    const field = this.tab === 'sms' ? 'smsBody' : (this.lastField === 'subject' ? 'subject' : 'emailBody');
                    const el = this.$refs[field + 'El'];
                    const value = this[field];
                    const start = el && el.selectionStart !== undefined ? el.selectionStart : value.length;
                    const end = el && el.selectionEnd !== undefined ? el.selectionEnd : start;
                    this[field] = value.slice(0, start) + token + value.slice(end);
                    this.$nextTick(() => {
                        if (!el) return;
                        el.focus();
                        el.setSelectionRange(start + token.length, start + token.length);
                    });
                },

                // ----- preview ---------------------------------------------------------------
                get emailLines() { return this.merge(this.emailBody).split(/\r?\n/).filter(l => l.trim() !== ''); },
                get smsInfo() {
                    const text = this.merge(this.smsBody);
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

                // ----- submitting ------------------------------------------------------------
                onSubmit(event) {
                    const intent = event.submitter ? event.submitter.value : 'send';
                    clearTimeout(this.autosaveTimer);

                    // Let a background save that is still running finish first, so it cannot create a
                    // stray draft after the message has been sent.
                    if (this.autosaving && this.autosavePromise) {
                        event.preventDefault();
                        const submitter = event.submitter;
                        this.autosavePromise.finally(() => this.$nextTick(() => this.$root.requestSubmit(submitter)));
                        return;
                    }

                    if (intent === 'send') {
                        if (!this.canSend) { event.preventDefault(); return; }
                        const s = this.summary;
                        const also = (this.fileName || this.seedCount) ? ', plus everyone from the file / earlier recipients.' : '.';
                        if (!window.confirm('Send this message now?\n\n' + s.mail + ' email(s), ' + s.sms + ' text(s) to the selected registrants' + also)) {
                            event.preventDefault();
                            return;
                        }
                    } else if (intent === 'schedule') {
                        if (!this.canSend || !this.scheduledAt) { event.preventDefault(); return; }
                        if (!window.confirm('Schedule this message for ' + this.scheduledAt.replace('T', ' ') + ' (' + cfg.timezone + ')?')) {
                            event.preventDefault();
                            return;
                        }
                    }

                    this.sending = true;
                },

                // ----- drafts (background save) ----------------------------------------------
                autosavePayload() {
                    return {
                        draft_id: this.draftId,
                        subject: this.subject,
                        email_body: this.emailBody,
                        sms_body: this.smsBody,
                        mode: this.mode,
                        participant_ids: this.selectedIds,
                    };
                },
                queueAutosave() {
                    if (!this.canAutosave || this.sending) return;
                    clearTimeout(this.autosaveTimer);
                    this.autosaveLabel = 'Unsaved changes…';
                    this.autosaveTimer = setTimeout(() => this.autosave(), AUTOSAVE_PAUSE_MS);
                },
                autosave() {
                    if (!this.canAutosave || this.sending || this.autosaving) return this.autosavePromise;
                    const payload = this.autosavePayload();
                    const signature = JSON.stringify(payload);
                    if (signature === this.lastSaved) return null;

                    this.autosaving = true;
                    this.autosaveLabel = 'Saving draft…';
                    this.autosavePromise = fetch(cfg.urls.autosave, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: this.jsonHeaders(),
                        body: signature,
                    }).then(async response => {
                        if (response.status === 409) { this.canAutosave = false; this.autosaveLabel = ''; return; }
                        if (!response.ok) throw new Error('Draft save failed (' + response.status + ')');
                        const data = await response.json();
                        if (data.saved) {
                            this.draftId = data.id;
                            this.lastSaved = JSON.stringify(Object.assign({}, payload, { draft_id: data.id }));
                            this.autosaveLabel = 'Draft saved ' + data.at;
                        } else {
                            this.autosaveLabel = '';
                        }
                    }).catch(() => {
                        this.autosaveLabel = 'Could not save the draft yet — will try again';
                        setTimeout(() => this.queueAutosave(), 5000);
                    }).finally(() => { this.autosaving = false; });

                    return this.autosavePromise;
                },
                jsonHeaders() {
                    const token = this.$root.querySelector('input[name="_token"]');
                    return {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token ? token.value : '',
                    };
                },

                // ----- templates -------------------------------------------------------------
                flashTemplate(message) {
                    this.templateNotice = message;
                    setTimeout(() => { this.templateNotice = ''; }, 3500);
                },
                applyTemplate(template) {
                    const hasWork = this.subject.trim() || this.emailBody.trim() || this.smsBody.trim();
                    if (hasWork && !window.confirm('Replace what you have written with the template “' + template.name + '”?')) return;
                    this.subject = template.subject || '';
                    this.emailBody = template.email_body || '';
                    this.smsBody = template.sms_body || '';
                    this.templatesOpen = false;
                    this.flashTemplate('Loaded “' + template.name + '”');
                },
                async saveTemplate() {
                    const name = this.templateName.trim();
                    if (!name || this.templateSaving) return;
                    this.templateSaving = true;
                    this.templateError = '';
                    try {
                        const response = await fetch(cfg.urls.templates, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: this.jsonHeaders(),
                            body: JSON.stringify({ name: name, subject: this.subject, email_body: this.emailBody, sms_body: this.smsBody }),
                        });
                        const data = await response.json();
                        if (!response.ok) {
                            this.templateError = (data.errors && Object.values(data.errors)[0][0]) || 'Could not save the template.';
                            return;
                        }
                        const index = this.templates.findIndex(t => t.id === data.id);
                        if (index >= 0) this.templates.splice(index, 1, data); else this.templates.push(data);
                        this.templates.sort((a, b) => a.name.localeCompare(b.name));
                        this.templateName = '';
                        this.saveTemplateOpen = false;
                        this.flashTemplate(data.updated ? 'Template updated' : 'Template saved');
                    } catch (error) {
                        this.templateError = 'Could not reach the server. Try again.';
                    } finally {
                        this.templateSaving = false;
                    }
                },
                async deleteTemplate(template) {
                    if (!window.confirm('Delete the template “' + template.name + '”? Messages already sent from it are not affected.')) return;
                    try {
                        const response = await fetch(template.url, { method: 'DELETE', credentials: 'same-origin', headers: this.jsonHeaders() });
                        if (response.ok) {
                            this.templates = this.templates.filter(t => t.id !== template.id);
                            this.flashTemplate('Template deleted');
                        }
                    } catch (error) { /* leave the list unchanged */ }
                },
            };
        };
    </script>
    @endverbatim
@endonce
