@php use App\Support\AccommodationPriority as Priority; @endphp
<x-app-layout>
    <x-slot name="header">Send to attendees</x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl space-y-7 sm:px-6 lg:px-8">
        @include('accommodation.sections._header', ['title' => 'Send to attendees', 'intro' => 'Email people their rooms, or invite them to choose one.'])
        @if($errors->any())<div class="rounded-2xl bg-red-50 p-4 text-sm font-bold text-red-800">{{ $errors->first() }}</div>@endif
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black text-slate-900">Tell people their rooms</h2>
                    <p class="mt-1 max-w-2xl text-sm text-slate-500">Write your own message in Messages, where {room}, {building}, {floor}, {location} and {room_link} fill in each person's own room, or send the standard room message. Either way each person is marked as told; moving someone marks them as not told again.</p>
                </div>
                @include('accommodation._tell-rooms')
            </div>
        </section>
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black text-slate-900">Let attendees pick their own room</h2>
            <p class="mt-1 text-sm text-slate-500">Emails a link where each attendee who needs a room chooses one. Anyone who has not chosen by the cutoff is placed automatically.</p>
            <form method="POST" action="{{ route('events.accommodation.invite-self-select', $event) }}" class="mt-4">@csrf<button class="rounded-xl border border-indigo-200 px-4 py-2 text-sm font-black text-indigo-700">Send selection link</button></form>
            <p class="mt-3 text-xs font-bold {{ $event->accommodationSelfSelectOpen() ? 'text-emerald-600' : 'text-slate-400' }}">
                @if($event->accommodationSelfSelectOpen())
                    Attendees can pick their own room until {{ $event->accommodation_self_select_closes_at->format('D j M Y, g:ia') }} — "Send selection link" emails them the link.
                @else
                    "Send selection link" is inactive: tick <strong>Use accommodation</strong> and set a future <strong>"Let attendees pick until"</strong> time in the header, then <strong>Save</strong>.
                @endif
            </p>
        </section>
    </div></div>
</x-app-layout>
