<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Church Attendance') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-gray-900 selection:bg-red-500 selection:text-white">
        {{-- Main Background: Soft White to ensure the Red/Blue accents pop --}}
        <div class="min-h-screen bg-[#f8fafc]">
            
            {{-- Navigation --}}
            <div class="sticky top-0 z-50 shadow-sm">
                @include('layouts.navigation')
            </div>

            @isset($header)
                <header class="bg-gradient-to-r from-blue-700 via-blue-800 to-red-600 shadow-lg border-b border-white/10">
                    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
                        <div class="flex items-center space-x-4">
                            {{-- This makes the header text white to pop against the blue/red --}}
                            <div class="text-white font-bold text-2xl tracking-tight drop-shadow-md">
                                {{ $header }}
                            </div>
                        </div>
                    </div>
                </header>
            @endisset

            <main class="py-12">
                <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>