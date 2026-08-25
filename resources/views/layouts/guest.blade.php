<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Asah Apex Attendance') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <x-compiled-assets />
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <x-toast />
        {{-- Background with Church Gradient and subtle pattern overlay --}}
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-br from-blue-900 via-blue-800 to-red-700">
            
            {{-- Logo Section --}}
            <div class="mb-8 transform transition hover:scale-110 duration-300">
                <a href="/">
                    {{-- Logo set to white to stand out against the dark gradient --}}
                    <x-application-logo class="w-24 h-24 fill-current text-white drop-shadow-2xl" />
                </a>
            </div>

            {{-- The Login/Register Card --}}
            <div class="w-full sm:max-w-md mt-2 px-8 py-10 bg-white shadow-[0_20px_50px_rgba(0,0,0,0.3)] overflow-hidden sm:rounded-2xl border border-white/20">
                
                {{-- Decorative top bar for the card --}}
                <div class="h-1.5 w-32 bg-gradient-to-r from-blue-600 to-red-600 mx-auto rounded-full mb-8"></div>

                <div class="space-y-6">
                    {{ $slot }}
                </div>
            </div>

            {{-- Footer Note (Optional) --}}
            <footer class="mt-8 text-white/60 text-sm font-medium">
                &copy; {{ date('Y') }} {{ config('app.name', 'Asah Apex Attendance') }}
            </footer>
        </div>
    </body>
</html>
