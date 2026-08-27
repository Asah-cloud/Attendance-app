<x-public-layout title="Registration confirmation" :noindex="true">
    <section class="min-h-screen bg-gradient-to-b from-slate-50 via-slate-50 to-blue-50/50 px-5 pb-20 pt-32">
        <div class="mx-auto max-w-xl overflow-hidden rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-xl shadow-slate-200/70">

            @if($registration->event->company?->logo_path || $registration->event->company?->name)
                <div class="mb-5 flex items-center justify-center gap-2">
                    @if($registration->event->company?->logo_path)
                        <img src="{{ Storage::url($registration->event->company->logo_path) }}" alt="{{ $registration->event->company->name }} logo" class="h-7 w-7 rounded-lg border border-slate-200 bg-white object-contain p-1">
                    @endif
                    @if($registration->event->company?->name)
                        <span class="text-sm font-bold text-slate-500">Hosted by {{ $registration->event->company->name }}</span>
                    @endif
                </div>
            @endif

            <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-blue-50 text-2xl text-blue-600">✓</div>
            <p class="mt-5 text-xs font-black uppercase tracking-widest text-blue-600">{{ $registration->source === 'hardcopy_import' ? 'Thanks for confirming!' : 'Registration received' }}</p>

            @if($registration->event->logo_path)
                <div class="mt-3 flex items-center justify-center gap-3">
                    <img src="{{ Storage::url($registration->event->logo_path) }}" alt="{{ $registration->event->title }} logo" class="h-11 w-11 shrink-0 rounded-xl border border-slate-200 bg-white object-contain p-1.5 shadow-sm">
                    <h1 class="text-3xl font-black">{{ $registration->event->title }}</h1>
                </div>
            @else
                <h1 class="mt-3 text-3xl font-black">{{ $registration->event->title }}</h1>
            @endif

            <p class="mt-3 text-slate-600">{{ $registration->participant->name }}</p>

            <div class="mt-6 rounded-2xl bg-slate-50 p-5"><p class="text-xs uppercase text-slate-500">Status</p><p class="mt-2 text-xl font-black capitalize">{{ $registration->status }}</p></div>

            @if($registration->status === \App\Models\EventRegistration::STATUS_CONFIRMED)
                <div class="mt-6 rounded-2xl border border-blue-100 bg-blue-50 p-6">
                    <p class="text-xs font-black uppercase tracking-widest text-blue-700">Your personal check-in QR</p>
                    <div class="mx-auto mt-5 inline-block rounded-2xl bg-white p-4 shadow-sm">
                        {!! QrCode::size(240)->margin(1)->generate(route('attendance.personal', $registration->registration_code)) !!}
                    </div>
                    <p class="mt-4 text-sm text-slate-600">Scan this QR when you arrive, then enter your registered phone number to check in. An usher can also help you.</p>
                </div>
            @else
                <p class="mt-6 text-sm text-slate-600">Your check-in QR will appear here once your registration is confirmed.</p>
            @endif

            @if($registration->status !== 'cancelled')
                <form method="POST" action="{{ route('registrations.cancel', $registration->registration_code) }}" class="mt-7">
                    @csrf
                    <button class="text-sm font-bold text-red-600">Cancel registration</button>
                </form>
            @endif
        </div>
    </section>
</x-public-layout>
