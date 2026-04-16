@props(['active' => false])

@php
// Define a safe local variable so we don't have to keep checking
$isActive = $active ?? false;

$classes = ($isActive)
            ? 'block w-full ps-4 pe-4 py-3 border-l-4 border-blue-600 text-start text-[11px] font-black uppercase tracking-[0.2em] text-blue-700 bg-blue-50/50 rounded-r-2xl focus:outline-none transition-all duration-200 ease-in-out'
            : 'block w-full ps-4 pe-4 py-3 border-l-4 border-transparent text-start text-[11px] font-black uppercase tracking-[0.2em] text-gray-500 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-200 rounded-r-2xl focus:outline-none transition-all duration-200 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    <div class="flex items-center gap-3">
        {{-- Use the safe $isActive variable here --}}
        @if($isActive)
            <span class="w-1.5 h-1.5 bg-blue-600 rounded-full animate-pulse"></span>
        @endif
        {{ $slot }}
    </div>
</a>