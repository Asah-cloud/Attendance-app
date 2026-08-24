<x-public-layout :title="$form->title.' - Thank you'" :noindex="true">
    <section class="min-h-screen bg-gradient-to-b from-slate-50 via-slate-50 to-blue-50/50 px-5 pb-20 pt-32">
        <div class="mx-auto max-w-lg text-center">
            <div class="rounded-3xl border border-slate-200 bg-white p-10 shadow-xl shadow-slate-200/70">
                <p class="text-xs font-black uppercase tracking-widest text-blue-600">{{ $event->title }}</p>
                <h1 class="mt-3 text-2xl font-black text-slate-950">Thanks for your response!</h1>
                <p class="mt-3 text-sm font-semibold text-slate-500">Your answers to "{{ $form->title }}" have been recorded.</p>
            </div>
        </div>
    </section>
</x-public-layout>
