<x-app-layout>
    <x-slot name="header">{{ $form->title }}</x-slot>
    <div class="py-10"><div class="mx-auto max-w-5xl space-y-8 px-4 sm:px-6 lg:px-8">

        @php $formUrl = route('forms.show', [$event->slug, $form->slug]); @endphp
        <section x-data="{ copied: false, link: @js($formUrl) }" class="rounded-3xl border border-blue-100 bg-gradient-to-br from-blue-950 to-blue-800 p-7 text-white shadow-xl">
            <div class="grid items-center gap-7 md:grid-cols-[1fr_auto]">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h3 class="text-xl font-black">Share form</h3>
                        <span class="rounded-full px-3 py-1 text-xs font-black uppercase {{ $form->isOpen() ? 'bg-green-400/20 text-green-300' : 'bg-amber-300/20 text-amber-200' }}">
                            {{ $form->isOpen() ? 'Open' : 'Closed' }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-blue-100">Share this link or QR code with anyone you want a response from.</p>
                    <div class="mt-5 flex overflow-hidden rounded-xl bg-white/10 ring-1 ring-white/15">
                        <input readonly value="{{ $formUrl }}" class="min-w-0 flex-1 border-0 bg-transparent px-4 py-3 text-sm text-white focus:ring-0">
                        <button type="button" @click="navigator.clipboard.writeText(link).then(() => { copied = true; setTimeout(() => copied = false, 2000) })" class="bg-white px-5 text-sm font-black text-blue-900"><span x-show="!copied">Copy link</span><span x-cloak x-show="copied">Copied!</span></button>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <a href="{{ $formUrl }}" target="_blank" class="rounded-xl bg-blue-500 px-4 py-3 text-sm font-bold text-white">Open public form</a>
                        <a href="{{ route('events.forms.print-qr', [$event, $form]) }}" target="_blank" class="rounded-xl bg-white/10 px-4 py-3 text-sm font-bold text-white ring-1 ring-white/20">Print QR</a>
                        <a href="{{ route('events.forms.download-qr', [$event, $form]) }}" class="rounded-xl bg-white/10 px-4 py-3 text-sm font-bold text-white ring-1 ring-white/20">Download SVG</a>
                        <a href="{{ route('events.forms.responses', [$event, $form]) }}" class="rounded-xl bg-white/10 px-4 py-3 text-sm font-bold text-white ring-1 ring-white/20">View responses</a>
                    </div>
                </div>
                <div class="rounded-2xl bg-white p-4 text-blue-950">{!! QrCode::size(170)->generate($formUrl) !!}</div>
            </div>
        </section>

        <section class="rounded-3xl border border-gray-100 bg-white p-7 shadow-sm">
            <h3 class="text-lg font-black">Form settings</h3>
            <form method="POST" action="{{ route('events.forms.update', [$event, $form]) }}" class="mt-6 grid gap-5 md:grid-cols-2">@csrf @method('PATCH')
                <div class="md:col-span-2"><label class="text-xs font-black uppercase text-gray-500">Title</label><input name="title" value="{{ old('title', $form->title) }}" required class="mt-2 w-full rounded-xl border-gray-200"><x-input-error :messages="$errors->get('title')" class="mt-1.5" /></div>
                <div class="md:col-span-2"><label class="text-xs font-black uppercase text-gray-500">Description</label><textarea name="description" rows="3" class="mt-2 w-full rounded-xl border-gray-200">{{ old('description', $form->description) }}</textarea><x-input-error :messages="$errors->get('description')" class="mt-1.5" /></div>
                <label class="flex items-center gap-3 rounded-2xl bg-gray-50 p-4 md:col-span-2"><input type="hidden" name="is_open" value="0"><input type="checkbox" name="is_open" value="1" @checked(old('is_open', $form->is_open))><span class="font-bold">Accepting responses</span></label>
                <div><label class="text-xs font-black uppercase text-gray-500">Opens at</label><input type="datetime-local" name="opens_at" value="{{ old('opens_at', $form->opens_at?->format('Y-m-d\TH:i')) }}" class="mt-2 w-full rounded-xl border-gray-200"></div>
                <div><label class="text-xs font-black uppercase text-gray-500">Closes at</label><input type="datetime-local" name="closes_at" value="{{ old('closes_at', $form->closes_at?->format('Y-m-d\TH:i')) }}" class="mt-2 w-full rounded-xl border-gray-200"><x-input-error :messages="$errors->get('closes_at')" class="mt-1.5" /></div>
                <button class="rounded-xl bg-gray-900 px-5 py-3 font-bold text-white md:col-span-2">Save settings</button>
            </form>
        </section>

        <section class="rounded-3xl border border-gray-100 bg-white p-7 shadow-sm">
            <h3 class="text-lg font-black">Questions</h3>
            <div class="mt-5 space-y-3">@forelse($form->fields as $field)
                <form method="POST" action="{{ route('events.forms.fields.update', [$event, $form, $field]) }}" class="grid gap-3 rounded-2xl border border-gray-100 p-4 md:grid-cols-[1fr_auto_auto]">
                    @csrf @method('PATCH')
                    <div>
                        <input name="label" value="{{ $field->label }}" class="w-full rounded-xl border-gray-200">
                        <p class="mt-1 text-xs text-gray-500">{{ ucfirst($field->field_type) }}</p>
                        @if(in_array($field->field_type, ['select', 'radio']))
                            <textarea name="options" rows="2" placeholder="One option per line" class="mt-2 w-full rounded-xl border-gray-200 text-sm">{{ collect($field->options)->implode("\n") }}</textarea>
                        @endif
                    </div>
                    <label class="flex items-center gap-2 self-start text-xs font-bold text-gray-600"><input type="hidden" name="is_required" value="0"><input type="checkbox" name="is_required" value="1" @checked($field->is_required)> Required</label>
                    <div class="flex items-start gap-3">
                        <button class="rounded-xl bg-gray-900 px-4 py-2.5 text-xs font-bold text-white">Save</button>
                        <button form="delete-field-{{ $field->id }}" class="text-xs font-bold text-red-600">Remove</button>
                    </div>
                </form>
                <form id="delete-field-{{ $field->id }}" method="POST" action="{{ route('events.forms.fields.destroy', [$event, $form, $field]) }}">@csrf @method('DELETE')</form>
            @empty<p class="text-sm text-gray-500">No questions yet.</p>@endforelse</div>

            <form method="POST" action="{{ route('events.forms.fields.store', [$event, $form]) }}" class="mt-7 grid gap-4 md:grid-cols-2">@csrf
                <div><label class="text-xs font-black uppercase text-gray-500">Question label</label><input name="label" value="{{ old('label') }}" required class="mt-2 w-full rounded-xl border-gray-200"><x-input-error :messages="$errors->get('label')" class="mt-1.5" /></div>
                <div><label class="text-xs font-black uppercase text-gray-500">Field type</label><select name="field_type" class="mt-2 w-full rounded-xl border-gray-200">@foreach($fieldTypes as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach</select></div>
                <div class="md:col-span-2"><label class="text-xs font-black uppercase text-gray-500">Dropdown/radio options (one per line, used only for those types)</label><textarea name="options" rows="3" class="mt-2 w-full rounded-xl border-gray-200">{{ old('options') }}</textarea><x-input-error :messages="$errors->get('options')" class="mt-1.5" /></div>
                <label class="flex items-center gap-3 rounded-2xl bg-gray-50 p-4"><input type="hidden" name="is_required" value="0"><input type="checkbox" name="is_required" value="1" @checked(old('is_required'))><span class="font-bold">Required</span></label>
                <button class="rounded-xl bg-blue-900 px-5 py-3 font-bold text-white md:col-span-2 md:w-fit">Add question</button>
            </form>
        </section>
    </div></div>
</x-app-layout>
