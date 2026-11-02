<x-public-layout title="Payment not finished" :noindex="true" :branded-for="$payment->event">
    <section class="relative isolate min-h-[calc(100vh-8.5rem)] px-5 pb-20 pt-12 sm:pt-16">
        @include('forms.partials.backdrop', ['form' => $payment->payable instanceof App\Models\FormResponse ? $payment->payable->form : null])
        <div class="mx-auto max-w-lg text-center">
            <div class="rounded-3xl border border-slate-200 bg-white p-10 shadow-xl shadow-slate-200/70">
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-amber-50 text-amber-600">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="mt-5 text-xs font-black uppercase tracking-widest text-blue-600">{{ $payment->event->title }}</p>
                <h1 class="mt-3 text-2xl font-black text-slate-950">Your payment hasn't gone through yet</h1>
                <p class="mt-3 text-sm font-semibold text-slate-500">Your details are saved. Pay {{ App\Models\AttendeePayment::money($payment->amount_minor, $payment->currency) }} to finish.</p>
                <p class="mt-2 text-xs text-slate-400">If you've just approved it on your phone, give it a moment and tap "Check again".</p>
                @if(session('error'))<p class="mt-4 rounded-xl bg-red-50 p-3 text-sm font-bold text-red-700">{{ session('error') }}</p>@endif
                <div class="mt-7 flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <form method="POST" action="{{ route('payments.retry', $payment->reference) }}">@csrf
                        <button class="w-full rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-3.5 text-sm font-black text-white shadow-lg shadow-blue-200">Pay now</button>
                    </form>
                    <a href="{{ route('payments.return', $payment->reference) }}" class="rounded-2xl border border-slate-200 px-6 py-3.5 text-sm font-black text-slate-700 hover:bg-slate-50">Check again</a>
                </div>
            </div>
        </div>
    </section>
</x-public-layout>
