<x-public-layout :title="$form->title" :noindex="true">
    @php($flyers = $form->flyers())
    <section class="min-h-screen bg-gradient-to-b from-slate-50 via-slate-50 to-blue-50/50 px-5 pb-20 pt-28 sm:pt-32">
        {{-- With flyers the page splits in two: flyers on one side, the form on the other (stacked on phones). --}}
        <div class="mx-auto {{ $flyers ? 'grid max-w-7xl grid-cols-1 items-start gap-8 lg:grid-cols-2 lg:gap-12' : 'max-w-2xl' }}">

            @if($flyers)
                <div class="lg:sticky lg:top-28">
                    <div x-data="{
                            current: 0,
                            count: {{ count($flyers) }},
                            paused: false,
                            start() {
                                if (this.count < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                                setInterval(() => { if (! this.paused && ! document.hidden) this.next() }, 5000);
                            },
                            next() { this.current = (this.current + 1) % this.count },
                            prev() { this.current = (this.current + this.count - 1) % this.count },
                         }"
                         x-init="start()"
                         @mouseenter="paused = true" @mouseleave="paused = false" @focusin="paused = true" @focusout="paused = false"
                         class="group relative aspect-[4/5] w-full overflow-hidden rounded-3xl border border-slate-200 bg-slate-900 shadow-2xl shadow-slate-300/60 transition duration-500 hover:-translate-y-1 hover:shadow-blue-300/50 motion-reduce:transition-none motion-reduce:hover:translate-y-0 lg:aspect-auto lg:h-[calc(100vh-9rem)]"
                         role="region" aria-roledescription="carousel" aria-label="{{ $form->title }} flyers">
                        @foreach($flyers as $index => $flyer)
                            <a href="{{ Storage::url($flyer) }}" target="_blank" rel="noopener"
                               x-show="current === {{ $index }}" @if($index > 0) x-cloak @endif
                               x-transition:enter="transition duration-700 ease-out motion-reduce:transition-none"
                               x-transition:enter-start="opacity-0 translate-x-16"
                               x-transition:enter-end="opacity-100 translate-x-0"
                               x-transition:leave="transition duration-500 ease-in motion-reduce:transition-none"
                               x-transition:leave-start="opacity-100 translate-x-0"
                               x-transition:leave-end="opacity-0 -translate-x-16"
                               class="absolute inset-0 flex items-center justify-center p-3 sm:p-4"
                               aria-label="Open flyer {{ $index + 1 }} of {{ count($flyers) }} full size">
                                {{-- A blurred copy fills the frame behind flyers that aren't the frame's shape. --}}
                                <img src="{{ Storage::url($flyer) }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full scale-110 object-cover opacity-40 blur-2xl">
                                <img src="{{ Storage::url($flyer) }}" alt="{{ $form->title }} flyer{{ count($flyers) > 1 ? ' '.($index + 1) : '' }}"
                                     class="relative max-h-full max-w-full rounded-2xl object-contain shadow-xl transition duration-700 ease-out group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100">
                            </a>
                        @endforeach

                        @if(count($flyers) > 1)
                            <button type="button" @click="prev()" aria-label="Previous flyer"
                                    class="absolute left-3 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/85 text-slate-900 shadow-lg backdrop-blur transition hover:bg-white focus:opacity-100 lg:opacity-0 lg:group-hover:opacity-100">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <button type="button" @click="next()" aria-label="Next flyer"
                                    class="absolute right-3 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/85 text-slate-900 shadow-lg backdrop-blur transition hover:bg-white focus:opacity-100 lg:opacity-0 lg:group-hover:opacity-100">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </button>
                            <div class="absolute inset-x-0 bottom-4 flex justify-center gap-2">
                                @foreach($flyers as $index => $flyer)
                                    <button type="button" @click="current = {{ $index }}" aria-label="Show flyer {{ $index + 1 }}"
                                            class="h-2.5 rounded-full bg-white shadow transition-all duration-300"
                                            :class="current === {{ $index }} ? 'w-8 opacity-100' : 'w-2.5 opacity-60 hover:opacity-90'"></button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <p class="mt-3 text-center text-xs font-semibold text-slate-400">Tap a flyer to view it full size.</p>
                </div>
            @endif

            <div class="min-w-0">
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
        </div>
    </section>
</x-public-layout>
