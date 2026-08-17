<x-public-layout title="Event check-in">
    <section class="min-h-screen bg-slate-50 px-5 pb-20 pt-32">
        <div class="mx-auto max-w-lg rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-xl">
            <div class="mx-auto grid h-16 w-16 place-items-center rounded-full {{ $successful ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }} text-3xl font-black">
                {{ $successful ? '✓' : '!' }}
            </div>
            <p class="mt-5 text-xs font-black uppercase tracking-widest {{ $successful ? 'text-emerald-600' : 'text-rose-600' }}">
                {{ $successful ? 'Check-in complete' : 'Staff scan required' }}
            </p>
            <h1 class="mt-3 text-3xl font-black text-slate-900">{{ $registration->participant->name }}</h1>
            <p class="mt-2 font-semibold text-slate-500">{{ $registration->event->title }}</p>
            <div class="mt-6 rounded-2xl {{ $successful ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800' }} p-5 font-bold">
                {{ $message }}
            </div>
        </div>
    </section>
</x-public-layout>
