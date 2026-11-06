{{--
    Asks staff to turn on phone notifications for staff messages, so they pop up even when the app
    is closed or they are logged out. iPhones only allow this from the Home Screen app, so they get
    instructions instead. Hidden for a day after "Not now".
--}}
@php $vapidKey = config('services.webpush.public_key'); @endphp
@if($vapidKey)
<div x-data="{
        state: 'checking',
        busy: false,
        hiddenUntil: 0,
        get isIos() { return /iPhone|iPad|iPod/.test(navigator.userAgent) },
        get standalone() { return window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true },
        get show() { return ['off', 'ios-install'].includes(this.state) && Date.now() > this.hiddenUntil },
        async init() {
            try { this.hiddenUntil = Number(localStorage.getItem('pushPromptHiddenUntil') || 0) } catch (e) {}
            if (! ('serviceWorker' in navigator) || ! ('PushManager' in window) || ! ('Notification' in window)) {
                this.state = this.isIos && ! this.standalone ? 'ios-install' : 'unsupported';
                return;
            }
            if (Notification.permission === 'denied') { this.state = 'denied'; return; }
            try {
                const registration = await navigator.serviceWorker.register('/sw.js');
                const subscription = await registration.pushManager.getSubscription();
                if (subscription && Notification.permission === 'granted') {
                    await this.save(subscription);
                    this.state = 'on';
                } else {
                    this.state = 'off';
                }
            } catch (e) { this.state = 'unsupported' }
        },
        async enable() {
            this.busy = true;
            try {
                if (await Notification.requestPermission() !== 'granted') { this.state = 'denied'; return; }
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: this.key(@js($vapidKey)) });
                await this.save(subscription);
                this.state = 'on';
                window.dispatchEvent(new CustomEvent('notify', { detail: { message: 'Notifications are on. Messages will pop up on this phone.' } }));
            } catch (e) {
                window.dispatchEvent(new CustomEvent('notify', { detail: { message: 'Could not turn on notifications on this device.', type: 'error' } }));
            } finally { this.busy = false }
        },
        save(subscription) {
            return fetch(@js(route('push-subscriptions.store')), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify(subscription.toJSON()),
            });
        },
        later() {
            this.hiddenUntil = Date.now() + 24 * 60 * 60 * 1000;
            try { localStorage.setItem('pushPromptHiddenUntil', String(this.hiddenUntil)) } catch (e) {}
        },
        key(base64) {
            const padded = (base64 + '='.repeat((4 - base64.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/');
            return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
        },
     }" x-cloak x-show="show" x-transition
     class="fixed inset-x-3 bottom-3 z-[80] mx-auto max-w-md rounded-2xl border border-blue-200 bg-white p-4 shadow-2xl sm:inset-x-auto sm:right-4" role="dialog" aria-label="Turn on notifications">
    <div class="flex gap-3">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-600 text-white">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-4-5.7V5a2 2 0 1 0-4 0v.3A6 6 0 0 0 6 11v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 1 1-6 0m6 0H9"/></svg>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-black text-slate-900">Get messages on your phone</p>
            <template x-if="state === 'off'">
                <p class="mt-1 text-sm text-slate-600">Coordinators' messages will pop up on this phone, even when the app is closed.</p>
            </template>
            <template x-if="state === 'ios-install'">
                <p class="mt-1 text-sm text-slate-600">On iPhone, first tap <strong>Share</strong> <span aria-hidden="true">(the square with an arrow)</span>, then <strong>Add to Home Screen</strong>. Open Asah Apex from your Home Screen and turn notifications on there.</p>
            </template>
            <div class="mt-3 flex items-center gap-2">
                <button x-show="state === 'off'" type="button" @click="enable()" :disabled="busy" class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-black text-white disabled:opacity-50" x-text="busy ? 'Turning on…' : 'Turn on notifications'">Turn on notifications</button>
                <button type="button" @click="later()" class="rounded-xl px-3 py-2 text-sm font-bold text-slate-500 hover:bg-slate-100">Not now</button>
            </div>
        </div>
    </div>
</div>
@endif
