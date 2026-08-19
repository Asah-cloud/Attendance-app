<x-public-layout :title="$event->title.' registration'">
    <section class="min-h-screen bg-gradient-to-b from-slate-50 via-slate-50 to-blue-50/50 px-5 pb-20 pt-32">
        <div class="mx-auto max-w-2xl">

            @if($event->company?->logo_path || $event->company?->name)
                <div class="mb-5 flex items-center justify-center gap-2.5">
                    @if($event->company?->logo_path)
                        <img src="{{ Storage::url($event->company->logo_path) }}" alt="{{ $event->company->name }} logo" class="h-8 w-8 rounded-lg border border-slate-200 bg-white object-contain p-1 shadow-sm">
                    @endif
                    @if($event->company?->name)
                        <span class="text-sm font-bold text-slate-500">Hosted by {{ $event->company->name }}</span>
                    @endif
                </div>
            @endif

            <div class="mb-8 flex flex-col items-center gap-5 text-center sm:flex-row sm:text-left">
                @if($event->logo_path)
                    <img src="{{ Storage::url($event->logo_path) }}" alt="{{ $event->title }} logo" class="h-20 w-20 shrink-0 rounded-2xl border border-slate-200 bg-white object-contain p-2 shadow-md">
                @endif
                <div>
                    <p class="text-xs font-black uppercase tracking-widest text-blue-600">Event registration</p>
                    <h1 class="mt-2 text-3xl font-black leading-tight text-slate-950 sm:text-4xl">{{ $event->title }}</h1>
                    <p class="mt-2 text-sm font-semibold text-slate-500">{{ $event->location }} · {{ $event->event_date->format('M d, Y') }}</p>
                </div>
            </div>

            @if(!$isOpen)
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center font-bold text-amber-900 shadow-sm">Registration is not currently open.</div>
            @else
                <form method="POST" action="{{ route('events.register.store', $event) }}" class="relative space-y-6 overflow-hidden rounded-3xl border border-slate-200 bg-white p-7 shadow-xl shadow-slate-200/70 sm:p-10">
                    <div class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-blue-600 via-indigo-500 to-violet-600"></div>
                    @csrf

                    @if($errors->has('registration'))
                        <div class="rounded-xl border border-red-100 bg-red-50 p-4 text-sm font-bold text-red-700">{{ $errors->first('registration') }}</div>
                    @endif

                    @php $labels = $fields->where('is_system', true)->keyBy('field_key'); @endphp

                    <div class="grid gap-5 sm:grid-cols-2">
                        @foreach(['name' => 'full_name', 'email' => 'email', 'phone' => 'phone', 'category' => 'category'] as $name => $key)
                            <div class="{{ $key === 'full_name' ? 'sm:col-span-2' : '' }}">
                                <label class="text-xs font-extrabold uppercase tracking-wide text-slate-500">{{ $labels[$key]->label }}</label>
                                <input type="{{ $key === 'email' ? 'email' : ($key === 'phone' ? 'tel' : 'text') }}" name="{{ $name }}" value="{{ old($name) }}" required
                                       class="mt-2 w-full rounded-xl border-slate-200 bg-slate-50/60 px-4 py-3 text-sm font-medium shadow-sm transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10">
                                <x-input-error :messages="$errors->get($name)" class="mt-1.5" />
                            </div>
                        @endforeach
                    </div>

                    @if($fields->where('is_system', false)->isNotEmpty())
                        <div class="space-y-5 border-t border-slate-100 pt-6">
                            @foreach($fields->where('is_system', false) as $field)
                                <div>
                                    <label class="text-xs font-extrabold uppercase tracking-wide text-slate-500">{{ $field->label }} @if($field->is_required)<span class="text-red-500">*</span>@endif</label>
                                    @if($field->field_type === 'textarea')
                                        <textarea name="custom[{{ $field->field_key }}]" rows="3" class="mt-2 w-full rounded-xl border-slate-200 bg-slate-50/60 px-4 py-3 text-sm font-medium shadow-sm transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10">{{ old('custom.'.$field->field_key) }}</textarea>
                                    @elseif($field->field_type === 'select')
                                        <select name="custom[{{ $field->field_key }}]" class="mt-2 w-full rounded-xl border-slate-200 bg-slate-50/60 px-4 py-3 text-sm font-medium shadow-sm transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10">
                                            <option value="">Select one</option>
                                            @foreach($field->options ?? [] as $option)<option @selected(old('custom.'.$field->field_key) === $option)>{{ $option }}</option>@endforeach
                                        </select>
                                    @elseif($field->field_type === 'radio')
                                        <div class="mt-2 space-y-2">
                                            @foreach($field->options ?? [] as $option)
                                                <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-2.5 text-sm font-semibold text-slate-700">
                                                    <input type="radio" name="custom[{{ $field->field_key }}]" value="{{ $option }}" @checked(old('custom.'.$field->field_key) === $option)>
                                                    <span>{{ $option }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @elseif($field->field_type === 'checkbox')
                                        <input type="hidden" name="custom[{{ $field->field_key }}]" value="0">
                                        <label class="mt-2 flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-2.5 text-sm font-semibold text-slate-700">
                                            <input type="checkbox" name="custom[{{ $field->field_key }}]" value="1">
                                            <span>Yes</span>
                                        </label>
                                    @else
                                        <input type="{{ $field->field_type }}" name="custom[{{ $field->field_key }}]" value="{{ old('custom.'.$field->field_key) }}" class="mt-2 w-full rounded-xl border-slate-200 bg-slate-50/60 px-4 py-3 text-sm font-medium shadow-sm transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10">
                                    @endif
                                    <x-input-error :messages="$errors->get('custom.'.$field->field_key)" class="mt-1.5" />
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-5">
                        <div class="prose prose-sm max-w-none text-slate-600">{{ $event->registration_terms }}</div>
                        <label class="mt-4 flex items-start gap-3">
                            <input type="checkbox" name="consent" value="1" required class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm font-bold text-slate-700">{{ $labels['consent']->label }}</span>
                        </label>
                        <x-input-error :messages="$errors->get('consent')" class="mt-1.5" />
                    </div>

                    <button class="w-full rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 text-sm font-black uppercase tracking-widest text-white shadow-lg shadow-blue-200 transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-blue-300 active:translate-y-0">
                        Submit registration
                    </button>
                </form>
            @endif
        </div>
    </section>
</x-public-layout>
