<x-public-layout :title="$event->title.' attendance confirmation'" :noindex="true">
    <section class="relative min-h-screen overflow-hidden bg-slate-50 px-5 pb-20 pt-32">
        {{-- Generated decorative background --}}
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-blue-400/25 blur-3xl"></div>
            <div class="absolute -right-24 top-1/3 h-80 w-80 rounded-full bg-violet-400/25 blur-3xl"></div>
            <div class="absolute bottom-0 left-1/4 h-72 w-72 rounded-full bg-indigo-300/25 blur-3xl"></div>
        </div>

        <div class="relative mx-auto grid max-w-6xl grid-cols-1 items-stretch gap-8 lg:grid-cols-2">

            {{-- Event flyer panel --}}
            <div class="relative flex flex-col overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-8 text-white shadow-2xl shadow-indigo-950/30 sm:p-10">
                <div class="pointer-events-none absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 22px 22px;"></div>

                @if($event->company?->logo_path || $event->company?->name)
                    <div class="relative flex items-center gap-2.5">
                        @if($event->company?->logo_path)
                            <img src="{{ Storage::url($event->company->logo_path) }}" alt="{{ $event->company->name }} logo" class="h-8 w-8 rounded-lg border border-white/20 bg-white object-contain p-1 shadow-sm">
                        @endif
                        @if($event->company?->name)
                            <span class="text-sm font-bold text-blue-100">Hosted by {{ $event->company->name }}</span>
                        @endif
                    </div>
                @endif

                <div class="relative flex flex-1 flex-col items-center justify-center py-10 text-center">
                    @if($event->logo_path)
                        <img src="{{ Storage::url($event->logo_path) }}" alt="{{ $event->title }} logo" class="h-28 w-28 rounded-3xl border border-white/20 bg-white object-contain p-3 shadow-xl">
                    @else
                        <div class="grid h-28 w-28 place-items-center rounded-3xl border border-white/20 bg-white/10 text-4xl font-black">{{ strtoupper(substr($event->title, 0, 1)) }}</div>
                    @endif

                    <p class="mt-7 text-xs font-black uppercase tracking-[0.3em] text-blue-200">You're invited</p>
                    <h1 class="mt-3 text-3xl font-black leading-tight sm:text-4xl">{{ $event->title }}</h1>
                    <p class="mt-4 text-sm font-semibold text-blue-100">{{ $event->event_date->format('M d, Y') }}@if($event->location) · {{ $event->location }}@endif</p>
                </div>

                <div class="relative border-t border-white/15 pt-5 text-center text-xs font-bold uppercase tracking-widest text-blue-200">
                    Please confirm your attendance &rarr;
                </div>
            </div>

            {{-- Confirmation form --}}
            <form method="POST" action="{{ route('attendance.confirm.store', $registration->registration_code) }}" class="relative space-y-6 overflow-hidden rounded-3xl border border-slate-200 bg-white p-7 shadow-xl shadow-slate-200/70 sm:p-10">
                <div class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-blue-600 via-indigo-500 to-violet-600"></div>
                @csrf

                <p class="text-xs font-black uppercase tracking-widest text-blue-600">Attendance confirmation</p>

                <div class="rounded-2xl border border-blue-100 bg-blue-50 p-5 text-center">
                    <p class="text-sm font-bold text-blue-900">Hi {{ $registration->participant->name }},</p>
                    <p class="mt-2 text-sm leading-6 text-blue-800">{{ str_replace(['{name}', '{event}'], [$registration->participant->name, $event->title], $event->confirmation_message ?: \App\Notifications\AttendanceConfirmationRequest::DEFAULT_MESSAGE) }}</p>
                </div>

                @if($fields->isNotEmpty())
                    <div class="space-y-5 border-t border-slate-100 pt-6">
                        @foreach($fields as $field)
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
                        <span class="text-sm font-bold text-slate-700">I agree to the event terms and privacy notice</span>
                    </label>
                    <x-input-error :messages="$errors->get('consent')" class="mt-1.5" />
                </div>

                <button class="w-full rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 text-sm font-black uppercase tracking-widest text-white shadow-lg shadow-blue-200 transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-blue-300 active:translate-y-0">
                    Confirm my attendance
                </button>
            </form>
        </div>
    </section>
</x-public-layout>
