<x-app-layout>
    <x-slot name="header">Registration Form</x-slot>
    <div class="py-10"><div class="mx-auto max-w-5xl space-y-8 px-4 sm:px-6 lg:px-8">
        @if(session('success'))<div class="rounded-2xl bg-green-50 p-4 font-bold text-green-700">{{ session('success') }}</div>@endif

        @php $registrationUrl = route('events.register', $event); @endphp
        <section x-data="{ copied: false, link: @js($registrationUrl) }" class="rounded-3xl border border-blue-100 bg-gradient-to-br from-blue-950 to-blue-800 p-7 text-white shadow-xl">
            <div class="grid items-center gap-7 md:grid-cols-[1fr_auto]">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h3 class="text-xl font-black">Share registration</h3>
                        <span class="rounded-full px-3 py-1 text-xs font-black uppercase {{ $event->registrationIsOpen() ? 'bg-green-400/20 text-green-300' : 'bg-amber-300/20 text-amber-200' }}">
                            {{ $event->registrationIsOpen() ? 'Open' : ($event->registration_enabled ? 'Scheduled or closed' : 'Disabled') }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-blue-100">Share this link or QR code with attendees.</p>
                    <div class="mt-5 flex overflow-hidden rounded-xl bg-white/10 ring-1 ring-white/15">
                        <input readonly value="{{ $registrationUrl }}" class="min-w-0 flex-1 border-0 bg-transparent px-4 py-3 text-sm text-white focus:ring-0">
                        <button type="button" @click="navigator.clipboard.writeText(link).then(() => { copied = true; setTimeout(() => copied = false, 2000) })" class="bg-white px-5 text-sm font-black text-blue-900"><span x-show="!copied">Copy link</span><span x-cloak x-show="copied">Copied!</span></button>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <a href="{{ $registrationUrl }}" target="_blank" class="rounded-xl bg-blue-500 px-4 py-3 text-sm font-bold text-white">Open public form</a>
                        <a href="{{ route('events.registration-form.print-qr', $event) }}" target="_blank" class="rounded-xl bg-white/10 px-4 py-3 text-sm font-bold text-white ring-1 ring-white/20">Print QR</a>
                        <a href="{{ route('events.registration-form.download-qr', $event) }}" class="rounded-xl bg-white/10 px-4 py-3 text-sm font-bold text-white ring-1 ring-white/20">Download SVG</a>
                    </div>
                    @unless($event->registration_enabled)<p class="mt-4 text-xs font-bold text-amber-200">Enable and save registration before sharing; the public page returns unavailable while disabled.</p>@endunless
                </div>
                <div class="rounded-2xl bg-white p-4 text-blue-950">{!! QrCode::size(170)->generate($registrationUrl) !!}</div>
            </div>
        </section>

        <section class="rounded-3xl border border-gray-100 bg-white p-7 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="text-lg font-black">Registration settings</h3><p class="text-sm text-gray-500">Registration is disabled until you publish it.</p></div>@if($event->registration_enabled)<a target="_blank" href="{{ route('events.register', $event) }}" class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-bold text-white">Preview public form</a>@endif</div>
            <form method="POST" action="{{ route('events.registration-form.update', $event) }}" class="mt-6 grid gap-5 md:grid-cols-2">@csrf @method('PATCH')
                <label class="flex items-center gap-3 rounded-2xl bg-gray-50 p-4"><input type="hidden" name="registration_enabled" value="0"><input type="checkbox" name="registration_enabled" value="1" @checked($event->registration_enabled)><span class="font-bold">Enable public registration</span></label>
                <label class="flex items-center gap-3 rounded-2xl bg-gray-50 p-4"><input type="hidden" name="registration_requires_approval" value="0"><input type="checkbox" name="registration_requires_approval" value="1" @checked($event->registration_requires_approval)><span class="font-bold">Require manager approval</span></label>
                <div><label class="text-xs font-black uppercase text-gray-500">Opens at</label><input type="datetime-local" name="registration_opens_at" value="{{ old('registration_opens_at', $event->registration_opens_at?->format('Y-m-d\TH:i')) }}" class="mt-2 w-full rounded-xl border-gray-200"></div>
                <div><label class="text-xs font-black uppercase text-gray-500">Closes at</label><input type="datetime-local" name="registration_closes_at" value="{{ old('registration_closes_at', $event->registration_closes_at?->format('Y-m-d\TH:i')) }}" class="mt-2 w-full rounded-xl border-gray-200"></div>
                <div><label class="text-xs font-black uppercase text-gray-500">Capacity</label><input type="number" min="1" name="registration_capacity" value="{{ old('registration_capacity', $event->registration_capacity) }}" placeholder="Unlimited" class="mt-2 w-full rounded-xl border-gray-200"></div>
                <div><label class="text-xs font-black uppercase text-gray-500">Terms version</label><input type="text" name="registration_terms_version" value="{{ old('registration_terms_version', $event->registration_terms_version) }}" class="mt-2 w-full rounded-xl border-gray-200"></div>
                <div class="md:col-span-2"><label class="text-xs font-black uppercase text-gray-500">Event terms and privacy notice</label><textarea name="registration_terms" rows="5" required class="mt-2 w-full rounded-xl border-gray-200">{{ old('registration_terms', $event->registration_terms) }}</textarea><x-input-error :messages="$errors->get('registration_terms')" /></div>
                <button class="rounded-xl bg-gray-900 px-5 py-3 font-bold text-white md:col-span-2">Save settings</button>
            </form>
        </section>

        <section class="rounded-3xl border border-gray-100 bg-white p-7 shadow-sm">
            <h3 class="text-lg font-black">Protected system fields</h3><p class="mt-1 text-sm text-gray-500">You may rename these labels, but they cannot be disabled, reordered, or removed.</p>
            <div class="mt-5 space-y-3">
                @foreach($event->registrationFields->where('is_system', true) as $field)
                    @if($field->field_key === 'category')
                        <form method="POST" action="{{ route('events.registration-fields.update', [$event, $field]) }}" class="grid gap-3 rounded-2xl border border-gray-100 p-4 md:grid-cols-2">
                            @csrf @method('PATCH')
                            <div><label class="text-xs font-black uppercase text-gray-500">Label</label><input name="label" value="{{ old('label', $field->label) }}" class="mt-2 w-full rounded-xl border-gray-200"></div>
                            <div>
                                <label class="text-xs font-black uppercase text-gray-500">Field type</label>
                                <select name="field_type" class="mt-2 w-full rounded-xl border-gray-200">
                                    <option value="text" @selected($field->field_type === 'text')>Free text</option>
                                    <option value="select" @selected($field->field_type === 'select')>Dropdown</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-xs font-black uppercase text-gray-500">Dropdown options (one per line, used only when field type is Dropdown)</label>
                                <textarea name="options" rows="3" class="mt-2 w-full rounded-xl border-gray-200">{{ old('options', collect($field->options)->implode("\n")) }}</textarea>
                                <x-input-error :messages="$errors->get('options')" />
                            </div>
                            <button class="rounded-xl bg-gray-900 px-4 py-3 text-sm font-bold text-white md:col-span-2 md:w-fit">Save</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('events.registration-fields.update', [$event, $field]) }}" class="flex gap-3">@csrf @method('PATCH')<input name="label" value="{{ $field->label }}" class="flex-1 rounded-xl border-gray-200"><span class="self-center rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">Locked · {{ $field->field_type }}</span><button class="rounded-xl bg-gray-900 px-4 text-sm font-bold text-white">Save</button></form>
                    @endif
                @endforeach
            </div>
        </section>

        <section class="rounded-3xl border border-gray-100 bg-white p-7 shadow-sm">
            <h3 class="text-lg font-black">Custom fields</h3>
            <div class="mt-5 space-y-3">@forelse($event->registrationFields->where('is_system', false) as $field)<div class="flex items-center justify-between rounded-2xl border border-gray-100 p-4"><div><p class="font-bold">{{ $field->label }}</p><p class="text-xs text-gray-500">{{ $field->field_type }} · {{ $field->is_required ? 'Required' : 'Optional' }}</p></div><form method="POST" action="{{ route('events.registration-fields.destroy', [$event, $field]) }}">@csrf @method('DELETE')<button class="text-sm font-bold text-red-600">Remove</button></form></div>@empty<p class="text-sm text-gray-500">No custom questions yet.</p>@endforelse</div>
            <form method="POST" action="{{ route('events.registration-fields.store', $event) }}" class="mt-7 grid gap-4 md:grid-cols-2">@csrf
                <div><label class="text-xs font-black uppercase text-gray-500">Question label</label><input name="label" value="{{ old('label') }}" required class="mt-2 w-full rounded-xl border-gray-200"></div>
                <div><label class="text-xs font-black uppercase text-gray-500">Field type</label><select name="field_type" class="mt-2 w-full rounded-xl border-gray-200">@foreach($fieldTypes as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach</select></div>
                <div class="md:col-span-2"><label class="text-xs font-black uppercase text-gray-500">Options (one per line, for select/radio)</label><textarea name="options" rows="3" class="mt-2 w-full rounded-xl border-gray-200">{{ old('options') }}</textarea><x-input-error :messages="$errors->get('options')" /></div>
                <label class="flex items-center gap-2"><input type="checkbox" name="is_required" value="1"><span class="font-bold">Required field</span></label>
                <button class="rounded-xl bg-blue-600 px-5 py-3 font-bold text-white">Add custom field</button>
            </form>
        </section>
    </div></div>
</x-app-layout>
