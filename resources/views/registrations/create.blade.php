<x-public-layout :title="$event->title.' registration'">
    <section class="min-h-screen bg-slate-50 px-5 pb-20 pt-32"><div class="mx-auto max-w-2xl">
        <div class="mb-7"><p class="text-xs font-black uppercase tracking-widest text-blue-600">Event registration</p><h1 class="mt-3 text-4xl font-black text-slate-950">{{ $event->title }}</h1><p class="mt-3 text-slate-600">{{ $event->location }} · {{ $event->event_date->format('M d, Y') }}</p></div>
        @if(!$isOpen)<div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 font-bold text-amber-900">Registration is not currently open.</div>@else
        <form method="POST" action="{{ route('events.register.store', $event) }}" class="space-y-5 rounded-3xl border border-slate-200 bg-white p-8 shadow-xl">@csrf
            @if($errors->has('registration'))<div class="rounded-xl bg-red-50 p-4 text-sm font-bold text-red-700">{{ $errors->first('registration') }}</div>@endif
            @php $labels = $fields->where('is_system', true)->keyBy('field_key'); @endphp
            @foreach(['name' => 'full_name', 'email' => 'email', 'phone' => 'phone', 'category' => 'category'] as $name => $key)<div><label class="text-sm font-bold text-slate-700">{{ $labels[$key]->label }}</label><input type="{{ $key === 'email' ? 'email' : ($key === 'phone' ? 'tel' : 'text') }}" name="{{ $name }}" value="{{ old($name) }}" required class="mt-2 w-full rounded-xl border-slate-200"><x-input-error :messages="$errors->get($name)" /></div>@endforeach
            @foreach($fields->where('is_system', false) as $field)<div><label class="text-sm font-bold text-slate-700">{{ $field->label }} @if($field->is_required)<span class="text-red-500">*</span>@endif</label>
                @if($field->field_type === 'textarea')<textarea name="custom[{{ $field->field_key }}]" class="mt-2 w-full rounded-xl border-slate-200">{{ old('custom.'.$field->field_key) }}</textarea>
                @elseif($field->field_type === 'select')<select name="custom[{{ $field->field_key }}]" class="mt-2 w-full rounded-xl border-slate-200"><option value="">Select one</option>@foreach($field->options ?? [] as $option)<option @selected(old('custom.'.$field->field_key) === $option)>{{ $option }}</option>@endforeach</select>
                @elseif($field->field_type === 'radio')<div class="mt-2 space-y-2">@foreach($field->options ?? [] as $option)<label class="flex gap-2"><input type="radio" name="custom[{{ $field->field_key }}]" value="{{ $option }}" @checked(old('custom.'.$field->field_key) === $option)><span>{{ $option }}</span></label>@endforeach</div>
                @elseif($field->field_type === 'checkbox')<input type="hidden" name="custom[{{ $field->field_key }}]" value="0"><label class="mt-2 flex gap-2"><input type="checkbox" name="custom[{{ $field->field_key }}]" value="1"><span>Yes</span></label>
                @else<input type="{{ $field->field_type }}" name="custom[{{ $field->field_key }}]" value="{{ old('custom.'.$field->field_key) }}" class="mt-2 w-full rounded-xl border-slate-200">@endif<x-input-error :messages="$errors->get('custom.'.$field->field_key)" /></div>@endforeach
            <div class="rounded-2xl bg-slate-50 p-5"><div class="prose prose-sm max-w-none text-slate-600">{{ $event->registration_terms }}</div><label class="mt-4 flex items-start gap-3"><input type="checkbox" name="consent" value="1" required class="mt-1"><span class="text-sm font-bold">{{ $labels['consent']->label }}</span></label><x-input-error :messages="$errors->get('consent')" /></div>
            <button class="w-full rounded-2xl bg-blue-600 px-6 py-4 font-black text-white">Submit registration</button>
        </form>@endif
    </div></section>
</x-public-layout>
