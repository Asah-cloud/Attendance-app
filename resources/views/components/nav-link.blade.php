@props(['active' => false])

@php
$isActive = $active ?? false;

$classes = ($isActive)
            ? 'inline-flex items-center px-4 border-b-2 border-blue-400 text-xs font-extrabold leading-5 text-white focus:outline-none transition'
            : 'inline-flex items-center px-4 border-b-2 border-transparent text-xs font-bold leading-5 text-slate-300 hover:text-white hover:border-white/30 focus:outline-none transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    <span class="relative py-2">
        {{ $slot }}
    </span>
</a>
