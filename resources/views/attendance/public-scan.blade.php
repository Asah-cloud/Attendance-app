<x-public-layout :title="'Check in | '.$event->title" :noindex="true">
    <section class="min-h-screen bg-slate-50 px-5 pb-20 pt-28">
        <div class="mx-auto max-w-md overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/70">
            <div class="bg-gradient-to-br from-blue-700 to-blue-900 px-7 py-8 text-center text-white">
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-white/15 text-2xl">✓</div>
                <p class="mt-5 text-xs font-black uppercase tracking-[0.2em] text-blue-200">Event check-in</p>
                <h1 class="mt-2 text-2xl font-black">{{ $event->title }}</h1>
                <p class="mt-2 text-sm font-bold text-blue-100">{{ $event->attendanceSessionLabel($session) }}</p>
            </div>

            <div class="p-7 sm:p-8">
                @if(session('success'))
                    <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-center font-bold text-emerald-800" role="status">{{ session('success') }}</div>
                @endif

                @if(session('error'))
                    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-5 text-center font-bold text-rose-800" role="alert">{{ session('error') }}</div>
                @endif

                @if($registration)
                    <div class="mb-6 rounded-2xl bg-blue-50 p-4 text-center text-sm font-bold text-blue-800">
                        Welcome, {{ $registration->participant->name }}. Enter your registered phone number to complete your check-in.
                    </div>
                @else
                    <p class="mb-6 text-center text-sm leading-6 text-slate-600">Enter the phone number used for your confirmed attendance. Members, ushers, and managers can all use this page.</p>
                @endif

                <form action="{{ URL::signedRoute($session === 0 ? 'arrival.check' : 'attendance.check', ['event' => $event->slug]) }}" method="POST" class="space-y-5">
                    @csrf
                    <div>
                        <label for="phone" class="block text-xs font-black uppercase tracking-widest text-slate-500">Registered phone number</label>
                        <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" placeholder="e.g. 020 123 4567" required autofocus autocomplete="tel" inputmode="tel" class="mt-3 w-full rounded-2xl border-slate-300 px-5 py-4 text-center text-xl font-black tracking-wide focus:border-blue-600 focus:ring-blue-600">
                        @error('phone')<p class="mt-2 text-sm font-bold text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit" class="w-full rounded-2xl bg-blue-600 px-5 py-4 text-sm font-black uppercase tracking-widest text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700 active:scale-[0.98]">Check in now</button>
                </form>

                <p class="mt-6 text-center text-xs leading-5 text-slate-400">Your number is only used to find your confirmed registration for this event.</p>
            </div>
        </div>
    </section>
</x-public-layout>
