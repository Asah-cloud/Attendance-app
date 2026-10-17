<x-app-layout>
    <x-slot name="header">Shared forms</x-slot>
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-black text-slate-900">Forms shared with you</h1>
        <p class="mt-1 text-sm text-slate-500">You can view these forms' responses and download them as Excel or PDF.</p>

        <div class="mt-6 grid gap-4">
            @forelse($forms as $form)
                <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="min-w-0">
                        <p class="text-xs font-black uppercase tracking-widest text-blue-600">{{ $form->event->title }}</p>
                        <p class="mt-1 text-lg font-black text-slate-900">{{ $form->title }}</p>
                        <p class="text-sm text-slate-500">{{ $form->responses_count }} {{ Str::plural('response', $form->responses_count) }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('shared-forms.show', $form) }}" class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-black uppercase tracking-wider text-white">View responses</a>
                        <a href="{{ route('shared-forms.export', $form) }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-black uppercase tracking-wider text-white">Excel</a>
                        <a href="{{ route('shared-forms.pdf', $form) }}" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-black uppercase tracking-wider text-white">PDF</a>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
                    No forms are shared with you right now.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
