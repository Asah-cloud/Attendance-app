<x-app-layout>
    <x-slot name="header">Staff messages</x-slot>
    @php $read = $recipients->whereNotNull('read_at')->count(); @endphp
    <div class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <a href="{{ route('staff-messages.index', auth()->user()->hasRole('admin') ? ['company_id' => $message->company_id] : []) }}" class="text-sm font-bold text-blue-700">&larr; All staff messages</a>

        <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="flex flex-wrap items-center gap-2 text-xs font-bold text-slate-500">
                @if($message->urgent)<span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-amber-800">Urgent</span>@endif
                <span>From {{ $message->sender?->name ?? 'Deleted user' }}</span>
                <span>· {{ $message->created_at->format('M j, g:i A') }}</span>
            </div>
            <p class="mt-3 whitespace-pre-line text-slate-900">{{ $message->body }}</p>
        </section>

        <section class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="border-b border-slate-200 p-6">
                <h2 class="text-xl font-black text-slate-900">Seen by {{ $read }} of {{ $recipients->count() }}</h2>
                @if($read < $recipients->count())<p class="mt-1 text-sm text-slate-500">{{ $recipients->count() - $read }} not read yet, listed first.</p>@endif
            </div>
            <div class="divide-y divide-slate-100">
                @foreach($recipients as $recipient)
                    <div class="flex items-center justify-between gap-3 px-6 py-3 text-sm">
                        <span class="font-bold text-slate-800">{{ $recipient->user?->name ?? 'Deleted user' }}</span>
                        @if($recipient->read_at)
                            <span class="text-emerald-700">Read {{ $recipient->read_at->format('M j, g:i A') }}</span>
                        @else
                            <span class="font-bold text-slate-400">Not read</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>
