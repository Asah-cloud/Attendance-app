<x-public-layout :title="$form->title" :noindex="true">
    <section class="min-h-screen bg-gradient-to-b from-slate-50 via-slate-50 to-blue-50/50 px-5 pb-20 pt-32">
        <div class="mx-auto max-w-2xl">

            <div class="mb-8 flex flex-col items-center gap-5 text-center sm:flex-row sm:text-left">
                <div>
                    <p class="text-xs font-black uppercase tracking-widest text-blue-600">{{ $event->title }}</p>
                    <h1 class="mt-2 text-3xl font-black leading-tight text-slate-950 sm:text-4xl">{{ $form->title }}</h1>
                    @if($form->description)<p class="mt-2 text-sm font-semibold text-slate-500">{{ $form->description }}</p>@endif
                </div>
            </div>

            @if(!$isOpen)
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center font-bold text-amber-900 shadow-sm">This form is not currently accepting responses.</div>
            @else
                <form method="POST" action="{{ route('forms.store', [$event->slug, $form->slug]) }}" class="relative space-y-6 overflow-hidden rounded-3xl border border-slate-200 bg-white p-7 shadow-xl shadow-slate-200/70 sm:p-10">
                    <div class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-blue-600 via-indigo-500 to-violet-600"></div>
                    @csrf

                    @if($fields->isEmpty())
                        <p class="text-sm text-slate-500">This form has no questions yet.</p>
                    @endif

                    <div class="space-y-5">
                        @foreach($fields as $field)
                            <div>
                                <label class="text-xs font-extrabold uppercase tracking-wide text-slate-500">{{ $field->label }} @if($field->is_required)<span class="text-red-500">*</span>@endif</label>
                                @if($field->field_type === 'textarea')
                                    <textarea name="answers[{{ $field->field_key }}]" rows="3" class="mt-2 w-full rounded-xl border-slate-200 bg-slate-50/60 px-4 py-3 text-sm font-medium shadow-sm transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10">{{ old('answers.'.$field->field_key) }}</textarea>
                                @elseif($field->field_type === 'select')
                                    <select name="answers[{{ $field->field_key }}]" class="mt-2 w-full rounded-xl border-slate-200 bg-slate-50/60 px-4 py-3 text-sm font-medium shadow-sm transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10">
                                        <option value="">Select one</option>
                                        @foreach($field->options ?? [] as $option)<option @selected(old('answers.'.$field->field_key) === $option)>{{ $option }}</option>@endforeach
                                    </select>
                                @elseif($field->field_type === 'radio')
                                    <div class="mt-2 space-y-2">
                                        @foreach($field->options ?? [] as $option)
                                            <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-2.5 text-sm font-semibold text-slate-700">
                                                <input type="radio" name="answers[{{ $field->field_key }}]" value="{{ $option }}" @checked(old('answers.'.$field->field_key) === $option)>
                                                <span>{{ $option }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @elseif($field->field_type === 'checkbox')
                                    <input type="hidden" name="answers[{{ $field->field_key }}]" value="0">
                                    <label class="mt-2 flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-2.5 text-sm font-semibold text-slate-700">
                                        <input type="checkbox" name="answers[{{ $field->field_key }}]" value="1">
                                        <span>Yes</span>
                                    </label>
                                @else
                                    <input type="{{ $field->field_type }}" name="answers[{{ $field->field_key }}]" value="{{ old('answers.'.$field->field_key) }}" class="mt-2 w-full rounded-xl border-slate-200 bg-slate-50/60 px-4 py-3 text-sm font-medium shadow-sm transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10">
                                @endif
                                <x-input-error :messages="$errors->get('answers.'.$field->field_key)" class="mt-1.5" />
                            </div>
                        @endforeach
                    </div>

                    <button class="w-full rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 text-sm font-black uppercase tracking-widest text-white shadow-lg shadow-blue-200 transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-blue-300 active:translate-y-0">
                        Submit
                    </button>
                </form>
            @endif
        </div>
    </section>
</x-public-layout>
