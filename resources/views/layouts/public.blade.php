<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Asah Apex Attendance helps organisations run faster event check-ins, live attendance tracking, and clear reporting.">
    @if($noindex)
        <meta name="robots" content="noindex, nofollow">
    @else
        <link rel="canonical" href="{{ url()->current() }}">
    @endif
    <meta property="og:title" content="Asah Apex Attendance">
    <meta property="og:description" content="Attendance, made effortless. QR check-in, live insights, and clear reports for every event.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('og-asah-apex-attendance.webp') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ asset('og-asah-apex-attendance.webp') }}">
    <title>{{ $title ?? 'Asah Apex Attendance' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap" rel="stylesheet">
    <x-compiled-assets />
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-[#f7f8fb] text-slate-950 antialiased" style="font-family: Manrope, sans-serif">
    <x-toast />
    <header x-data="{ mobileMenuOpen: false }" @keydown.escape.window="mobileMenuOpen = false" class="fixed inset-x-0 top-0 z-50 border-b border-white/10 bg-[#071426]/90 text-white backdrop-blur-xl">
        <nav class="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 lg:px-8" aria-label="Main navigation">
            <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="Asah Apex Attendance home">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 text-lg font-extrabold shadow-lg shadow-blue-950/40">A</span>
                <span>
                    <span class="block text-[10px] font-bold uppercase tracking-[0.34em] text-amber-300">Asah Apex</span>
                    <span class="block text-sm font-extrabold tracking-tight">Attendance</span>
                </span>
            </a>

            <div class="hidden items-center gap-8 text-sm font-semibold text-slate-300 md:flex">
                <a href="{{ route('home') }}#features" class="transition hover:text-white">Features</a>
                <a href="{{ route('home') }}#how-it-works" class="transition hover:text-white">How it works</a>
                <a href="{{ route('pricing') }}" class="transition hover:text-white">Pricing</a>
            </div>

            <div class="hidden items-center gap-3 md:flex">
                @auth
                    <a href="{{ route('events.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-[#071426] transition hover:bg-blue-50">Open dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="hidden text-sm font-bold text-slate-200 transition hover:text-white sm:block">Sign in</a>
                    <a href="{{ route('pricing') }}" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-950/30 transition hover:bg-blue-500">Get started</a>
                @endauth
            </div>

            <button type="button" @click="mobileMenuOpen = !mobileMenuOpen" class="grid h-11 w-11 place-items-center rounded-full bg-blue-600 text-white shadow-lg shadow-blue-950/40 transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-300 md:hidden" :aria-label="mobileMenuOpen ? 'Close navigation menu' : 'Open navigation menu'" :aria-expanded="mobileMenuOpen.toString()" aria-controls="mobile-navigation">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" aria-hidden="true">
                    <path x-show="!mobileMenuOpen" d="M5 8h14M5 16h14" />
                    <path x-cloak x-show="mobileMenuOpen" d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </nav>

        <div id="mobile-navigation" x-cloak x-show="mobileMenuOpen" @click.outside="mobileMenuOpen = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="-translate-y-3 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-y-0 opacity-100"
            x-transition:leave-end="-translate-y-2 opacity-0"
            class="absolute inset-x-4 top-[72px] overflow-hidden rounded-3xl border border-slate-200 bg-white text-[#071426] shadow-2xl shadow-slate-950/25 md:hidden">
            <div class="grid grid-cols-2 gap-2 p-4 text-sm font-extrabold">
                <a @click="mobileMenuOpen = false" href="{{ route('home') }}" class="rounded-2xl bg-slate-50 px-4 py-4 transition hover:bg-blue-50 hover:text-blue-700">Home</a>
                <a @click="mobileMenuOpen = false" href="{{ route('home') }}#features" class="rounded-2xl bg-slate-50 px-4 py-4 transition hover:bg-blue-50 hover:text-blue-700">Features</a>
                <a @click="mobileMenuOpen = false" href="{{ route('home') }}#how-it-works" class="rounded-2xl bg-slate-50 px-4 py-4 transition hover:bg-blue-50 hover:text-blue-700">How it works</a>
                <a @click="mobileMenuOpen = false" href="{{ route('pricing') }}" class="rounded-2xl bg-slate-50 px-4 py-4 transition hover:bg-blue-50 hover:text-blue-700">Pricing</a>
            </div>

            <div class="grid gap-3 border-t border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
                @auth
                    <a href="{{ route('events.index') }}" class="rounded-2xl bg-blue-600 px-5 py-3.5 text-center text-sm font-extrabold text-white transition hover:bg-blue-500">Open dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-2xl border border-slate-300 bg-white px-5 py-3.5 text-center text-sm font-extrabold text-[#071426] transition hover:border-blue-300">Sign in</a>
                    <a href="{{ route('pricing') }}" class="rounded-2xl bg-blue-600 px-5 py-3.5 text-center text-sm font-extrabold text-white transition hover:bg-blue-500">Get started</a>
                @endauth
            </div>
        </div>
    </header>

    <main>{{ $slot }}</main>

    <footer class="bg-[#071426] text-slate-300">
        <div class="mx-auto grid max-w-7xl gap-10 px-5 py-14 md:grid-cols-[1.5fr_1fr_1fr] lg:px-8">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.32em] text-amber-300">An Asah Apex system</p>
                <h2 class="mt-3 text-2xl font-extrabold text-white">Better events begin with a clearer headcount.</h2>
                <p class="mt-4 max-w-md text-sm leading-7 text-slate-400">Purpose-built attendance technology for organisations that value speed, accountability, and useful insight.</p>
            </div>
            <div>
                <p class="font-bold text-white">Explore</p>
                <div class="mt-4 grid gap-3 text-sm">
                    <a href="{{ route('home') }}#features" class="hover:text-white">Features</a>
                    <a href="{{ route('pricing') }}" class="hover:text-white">Pricing</a>
                    <a href="{{ route('login') }}" class="hover:text-white">Sign in</a>
                </div>
            </div>
            <div>
                <p class="font-bold text-white">Built for growth</p>
                <p class="mt-4 text-sm leading-7 text-slate-400">Start with one event. Scale across teams, locations, and companies without losing visibility.</p>
            </div>
        </div>
        <div class="border-t border-white/10 px-5 py-6 text-center text-xs text-slate-500">&copy; {{ now()->year }} Asah Apex. All rights reserved.</div>
    </footer>
</body>
</html>
