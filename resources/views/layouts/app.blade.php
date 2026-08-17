<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'Asah Apex Attendance') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap" rel="stylesheet" />
        @livewireStyles
        <x-compiled-assets />
        <style>[x-cloak] { display: none !important; }</style>
    </head>
    <body class="antialiased text-slate-950 selection:bg-blue-600 selection:text-white" style="font-family: Manrope, sans-serif">
        <div data-ui="app" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false" class="min-h-screen bg-slate-50">
            @include('layouts.navigation')

            <div class="lg:pl-72">
                <header class="sticky top-0 z-30 flex h-20 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button @click="sidebarOpen = true" type="button" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-slate-200 text-slate-600 lg:hidden" aria-label="Open navigation">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                        </button>
                        @role('manager')
                            @if(auth()->user()->company?->logo_path)
                                <img src="{{ Storage::url(auth()->user()->company->logo_path) }}" alt="{{ auth()->user()->company->name }} logo" class="hidden h-11 w-11 rounded-xl border border-slate-200 object-contain p-1 sm:block">
                            @else
                                <span class="hidden h-11 w-11 place-items-center rounded-xl bg-blue-50 text-base font-black text-blue-700 sm:grid">{{ strtoupper(substr(auth()->user()->company?->name ?? 'O', 0, 1)) }}</span>
                            @endif
                        @endrole
                        <div class="min-w-0">
                            @role('manager')
                                <h1 class="truncate text-lg font-black text-slate-950 sm:text-2xl">{{ auth()->user()->company?->name ?? 'Company workspace' }}</h1>
                                <p class="truncate text-[11px] font-bold uppercase tracking-[0.16em] text-blue-600">{{ isset($header) ? $header : 'Workspace' }}</p>
                            @else
                                <p class="truncate text-xs font-bold uppercase tracking-[0.18em] text-blue-600">@role('admin') Platform administration @else Staff workspace @endrole</p>
                                <h1 class="truncate text-lg font-extrabold text-slate-950 sm:text-xl">{{ isset($header) ? $header : 'Workspace' }}</h1>
                            @endrole
                        </div>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-xl px-2 py-1.5 transition hover:bg-slate-100">
                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-blue-600 text-sm font-extrabold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="hidden text-left sm:block"><span class="block text-sm font-bold text-slate-800">{{ auth()->user()->name }}</span><span class="block text-xs text-slate-500">View profile</span></span>
                    </a>
                </header>

                <main class="px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                    <div class="mx-auto max-w-7xl">{{ $slot }}</div>
                </main>
            </div>
        </div>
        @stack('scripts')
        @livewireScriptConfig
    </body>
</html>
