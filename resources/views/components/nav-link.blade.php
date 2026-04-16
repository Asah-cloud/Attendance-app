@props(['active' => false])

@php
$isActive = $active ?? false;

$classes = ($isActive)
            ? 'inline-flex items-center px-4 pt-1 border-b-4 border-blue-600 text-[11px] font-black uppercase tracking-[0.2em] leading-5 text-gray-900 focus:outline-none transition-all duration-300 ease-in-out'
            : 'inline-flex items-center px-4 pt-1 border-b-4 border-transparent text-[11px] font-black uppercase tracking-[0.2em] leading-5 text-gray-400 hover:text-gray-700 hover:border-gray-200 focus:outline-none focus:text-gray-700 transition-all duration-300 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    <span class="relative py-2">
        {{ $slot }}
        
        @if($isActive)
            {{-- Subtle glow under the active link --}}
            <span class="absolute -bottom-[22px] inset-x-0 h-1 bg-blue-600 blur-[2px] opacity-50 rounded-full"></span>
        @endif
    </span>
</a>