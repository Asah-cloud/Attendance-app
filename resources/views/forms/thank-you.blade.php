<x-public-layout :title="$form->title.' - Thank you'" :noindex="true" :branded-for="$event">
    <section class="relative isolate min-h-[calc(100vh-8.5rem)] px-5 pb-20 pt-12 sm:pt-16">
        @include('forms.partials.backdrop')
        <div class="mx-auto max-w-lg text-center">
            <div class="rounded-3xl border border-slate-200 bg-white p-10 shadow-xl shadow-slate-200/70">
                <p class="text-xs font-black uppercase tracking-widest text-blue-600">{{ $event->title }}</p>
                <h1 class="mt-3 text-2xl font-black text-slate-950">Thanks for your response!</h1>
                <p class="mt-3 text-sm font-semibold text-slate-500">Your answers to "{{ $form->title }}" have been recorded.</p>
            </div>
        </div>
    </section>
</x-public-layout>
