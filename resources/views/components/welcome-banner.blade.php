@props(['user'])

<div data-aos="fade-down" class="relative overflow-hidden bg-gradient-to-r from-blue-900 via-indigo-900 to-purple-900 rounded-3xl p-8 mb-8 shadow-2xl">
    {{-- Decorative circles --}}
    <div class="absolute top-0 right-0 -mr-20 -mt-20 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
    <div class="absolute bottom-0 left-0 -ml-10 -mb-10 w-40 h-40 bg-blue-500/20 rounded-full blur-2xl"></div>

    <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6">
        <div>
            <h1 class="text-3xl md:text-4xl font-black text-white leading-tight">
                Welcome back, <span class="text-blue-400">{{ explode(' ', $user->name)[0] }}!</span>
            </h1>
            <p class="text-blue-100/80 mt-2 text-lg font-medium">
                Your event management suite is ready. What are we tracking today?
            </p>
        </div>
        <div class="flex gap-4">
            <a href="{{ route('events.index') }}" class="px-6 py-3 bg-white text-blue-900 rounded-xl font-black text-xs uppercase tracking-widest hover:scale-105 transition-transform shadow-lg">
                View Events
            </a>
        </div>
    </div>
</div>