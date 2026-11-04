<x-app-layout>
    <x-slot name="header">Staff messages</x-slot>
    @php $companyQuery = auth()->user()->hasRole('admin') ? ['company_id' => $company->id] : []; @endphp
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <section class="overflow-hidden rounded-3xl bg-[#071426] p-7 text-white shadow-xl">
            <p class="text-xs font-black uppercase tracking-[.24em] text-amber-300">Team</p>
            <h1 class="mt-2 text-3xl font-black">Staff messages</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">Send instructions to your ushers, meal-station and audit staff. They see them on the bell at the top of every page, including the scanners, and can read but not reply. Urgent messages appear across their screen until they tap "Got it".</p>
        </section>

        @if(session('error'))<div class="rounded-2xl bg-rose-50 p-4 font-bold text-rose-800">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="rounded-2xl bg-rose-50 p-4 text-rose-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

        @if(auth()->user()->hasRole('admin'))
            <form method="GET" action="{{ route('staff-messages.index') }}" class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <label for="company_id" class="text-sm font-bold text-slate-700">Company</label>
                <select id="company_id" name="company_id" class="mt-2 block w-full rounded-xl border-slate-300" onchange="this.form.submit()">
                    @foreach($companies as $option)<option value="{{ $option->id }}" @selected($option->id === $company->id)>{{ $option->name }}</option>@endforeach
                </select>
            </form>
        @endif

        <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200" x-data="{ audience: @js(old('audience', 'all')) }">
            <h2 class="text-xl font-black text-slate-900">New message</h2>
            @if($staff->isEmpty())
                <p class="mt-3 text-sm text-slate-500">{{ $company->name }} has no usher, meal-station or audit accounts yet. Add them from the Team page first.</p>
            @else
                <form method="POST" action="{{ route('staff-messages.store', $companyQuery) }}" class="mt-5 space-y-5" onsubmit="this.querySelector('button[type=submit]').disabled = true">@csrf
                    <div>
                        <p class="text-sm font-bold text-slate-700">Send to</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach(['all' => 'All staff ('.$staff->count().')', 'event' => 'Staff on one event', 'user' => 'One person'] as $value => $label)
                                <label class="cursor-pointer rounded-xl border px-4 py-2 text-sm font-bold transition" :class="audience === @js($value) ? 'border-blue-600 bg-blue-50 text-blue-800' : 'border-slate-200 text-slate-600 hover:border-slate-300'">
                                    <input type="radio" name="audience" value="{{ $value }}" x-model="audience" class="sr-only"> {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div x-show="audience === 'event'" x-cloak>
                        <label for="event_id" class="text-sm font-bold text-slate-700">Event</label>
                        <select id="event_id" name="event_id" class="mt-2 block w-full rounded-xl border-slate-300" :disabled="audience !== 'event'">
                            @foreach($events as $event)<option value="{{ $event->id }}" @selected(old('event_id') == $event->id)>{{ $event->title }} · {{ $event->event_date?->format('j M Y') }} ({{ $event->staff_count }} staff)</option>@endforeach
                        </select>
                    </div>
                    <div x-show="audience === 'user'" x-cloak>
                        <label for="user_id" class="text-sm font-bold text-slate-700">Staff member</label>
                        <select id="user_id" name="user_id" class="mt-2 block w-full rounded-xl border-slate-300" :disabled="audience !== 'user'">
                            @foreach($staff as $person)<option value="{{ $person->id }}" @selected(old('user_id') == $person->id)>{{ $person->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="body" class="text-sm font-bold text-slate-700">Message</label>
                        <textarea id="body" name="body" rows="4" maxlength="2000" required class="mt-2 block w-full rounded-xl border-slate-300" placeholder="e.g. Gate B is closed. Send everyone to Gate A.">{{ old('body') }}</textarea>
                    </div>
                    <label class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm">
                        <input type="checkbox" name="urgent" value="1" class="mt-0.5 rounded border-amber-400 text-amber-600" @checked(old('urgent'))>
                        <span><strong class="text-amber-900">Urgent</strong><span class="block text-amber-800">Shows across their screen, even mid-scan, until they tap "Got it".</span></span>
                    </label>
                    <button type="submit" class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-black text-white disabled:opacity-50">Send message</button>
                </form>
            @endif
        </section>

        <section class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="border-b border-slate-200 p-6"><h2 class="text-xl font-black text-slate-900">Sent</h2></div>
            <div class="divide-y divide-slate-100">
                @forelse($messages as $message)
                    <div class="flex flex-wrap items-start justify-between gap-4 p-6">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2 text-xs font-bold text-slate-500">
                                @if($message->urgent)<span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-amber-800">Urgent</span>@endif
                                <span>To {{ $message->audienceLabel() }}</span>
                                <span>· {{ $message->sender?->name ?? 'Deleted user' }}</span>
                                <span>· {{ $message->created_at->format('M j, g:i A') }}</span>
                            </div>
                            <p class="mt-2 whitespace-pre-line text-sm text-slate-800">{{ $message->body }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <a href="{{ route('staff-messages.show', $message) }}" class="rounded-lg px-3 py-1.5 text-xs font-black {{ $message->read_count === $message->recipients_count ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">Seen by {{ $message->read_count }} of {{ $message->recipients_count }}</a>
                            <form method="POST" action="{{ route('staff-messages.destroy', $message) }}" onsubmit="return confirm('Delete this message? Staff will no longer see it.')">@csrf @method('DELETE')
                                <button class="rounded-lg px-3 py-1.5 text-xs font-bold text-red-700 hover:bg-red-50">Delete</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="p-6 text-center text-sm text-slate-500">No messages sent yet.</p>
                @endforelse
            </div>
            <div class="p-5">{{ $messages->links() }}</div>
        </section>
    </div>
</x-app-layout>
