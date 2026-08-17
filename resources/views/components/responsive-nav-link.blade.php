@props(['active' => false])

@php
// Define a safe local variable so we don't have to keep checking
$isActive = $active ?? false;

$classes = ($isActive)
            ? 'block mx-3 rounded-xl bg-blue-600 px-4 py-3 text-start text-sm font-extrabold text-white focus:outline-none transition'
            : 'block mx-3 rounded-xl px-4 py-3 text-start text-sm font-bold text-slate-200 hover:bg-white/10 hover:text-white focus:outline-none transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    <div class="flex items-center gap-3">
        {{ $slot }}
    </div>
</a>
