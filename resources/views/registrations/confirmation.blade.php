<x-public-layout title="Registration confirmation">
    <section class="min-h-screen bg-slate-50 px-5 pb-20 pt-32"><div class="mx-auto max-w-xl rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-xl">
        @if(session('success'))<div class="mb-5 rounded-xl bg-green-50 p-4 font-bold text-green-700">{{ session('success') }}</div>@endif
        @if($registration->event->logo_path || $registration->event->company?->logo_path)<div class="mx-auto mb-5 flex items-center justify-center gap-4">
            @if($registration->event->logo_path)<img src="{{ Storage::url($registration->event->logo_path) }}" alt="{{ $registration->event->title }} logo" class="h-14 w-14 rounded-2xl border border-slate-200 object-contain p-2">@endif
            @if($registration->event->company?->logo_path)<img src="{{ Storage::url($registration->event->company->logo_path) }}" alt="{{ $registration->event->company->name }} logo" class="h-10 w-10 rounded-xl border border-slate-200 object-contain p-1.5">@endif
        </div>@endif
        <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-blue-50 text-2xl text-blue-600">✓</div><p class="mt-5 text-xs font-black uppercase tracking-widest text-blue-600">Registration received</p><h1 class="mt-3 text-3xl font-black">{{ $registration->event->title }}</h1><p class="mt-3 text-slate-600">{{ $registration->participant->name }}</p>
        <div class="mt-6 rounded-2xl bg-slate-50 p-5"><p class="text-xs uppercase text-slate-500">Status</p><p class="mt-2 text-xl font-black capitalize">{{ $registration->status }}</p></div>
        @if($registration->status === \App\Models\EventRegistration::STATUS_CONFIRMED)
            <div class="mt-6 rounded-2xl border border-blue-100 bg-blue-50 p-6">
                <p class="text-xs font-black uppercase tracking-widest text-blue-700">Your personal check-in QR</p>
                <div class="mx-auto mt-5 inline-block rounded-2xl bg-white p-4 shadow-sm">
                    {!! QrCode::size(240)->margin(1)->generate('ASAH-ATTENDANCE:'.$registration->registration_code) !!}
                </div>
                <p class="mt-4 text-sm text-slate-600">Keep this QR private and show it at the event entrance.</p>
            </div>
        @else
            <p class="mt-6 text-sm text-slate-600">Your check-in QR will appear here once your registration is confirmed.</p>
        @endif
        @if($registration->status !== 'cancelled')<form method="POST" action="{{ route('registrations.cancel', $registration->registration_code) }}" class="mt-7">@csrf<button class="text-sm font-bold text-red-600">Cancel registration</button></form>@endif
    </div></section>
</x-public-layout>
