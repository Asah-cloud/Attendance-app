<x-app-layout>
    <x-slot name="header">QR Scanner</x-slot>

    <div class="bg-slate-50 py-10">
        <div class="mx-auto max-w-3xl px-4 sm:px-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600">Secure staff scanner</p>
                        <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950">{{ $event->title }}</h1>
                        <p class="mt-2 text-sm text-slate-500">Welcome! Allow camera access, then point it at the attendee's personal QR code.</p>
                    </div>
                    <a href="{{ route('events.attendance', $event) }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-center text-xs font-extrabold text-slate-600 hover:bg-slate-50">Back to attendance</a>
                </div>

                <div id="qr-reader" class="mt-8 overflow-hidden rounded-2xl border border-slate-200"></div>
                <div id="scan-result" class="mt-5 hidden rounded-2xl p-5 font-bold" role="status" aria-live="polite"></div>

                <form id="manual-scan" class="mt-8 border-t border-slate-100 pt-6">
                    <label for="registration-code" class="text-xs font-black uppercase tracking-widest text-slate-500">Or enter the registration code</label>
                    <div class="mt-3 flex flex-col gap-3 sm:flex-row">
                        <input id="registration-code" class="min-w-0 flex-1 rounded-xl border-slate-300" placeholder="Paste registration code" required>
                        <button class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-extrabold text-white hover:bg-blue-700">Check in</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const result = document.getElementById('scan-result');
            const input = document.getElementById('registration-code');
            let busy = false;

            const showResult = (message, successful) => {
                result.textContent = message;
                result.className = `mt-5 rounded-2xl p-5 font-bold ${successful ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800'}`;
            };

            const checkIn = async (value) => {
                if (busy || !value) return;
                busy = true;
                try {
                    const response = await fetch(@js(route('events.scanner.check-in', $event)), {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token())},
                        body: JSON.stringify({registration_code: value.trim()}),
                    });
                    const data = await response.json();
                    showResult(data.message || 'We could not process that QR code. Please try again.', response.ok);
                    if (response.ok) input.value = '';
                } catch (error) {
                    showResult('We could not connect right now. Please check your internet connection and try again.', false);
                } finally {
                    window.setTimeout(() => busy = false, 1500);
                }
            };

            document.getElementById('manual-scan').addEventListener('submit', event => {
                event.preventDefault();
                checkIn(input.value);
            });

            if (window.loadHtml5Qrcode) {
                const Html5Qrcode = await window.loadHtml5Qrcode();
                const scanner = new Html5Qrcode('qr-reader');
                scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 240, height: 240 } }, checkIn)
                    .catch(() => showResult('We could not open the camera. Please allow camera access, or enter the registration code below.', false));
            }
        });
    </script>
    @endpush
</x-app-layout>
