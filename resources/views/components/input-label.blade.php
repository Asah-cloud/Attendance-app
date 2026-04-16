@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-black text-[10px] uppercase tracking-[0.2em] text-gray-500 mb-2 ml-1']) }}>
    <span class="flex items-center gap-1.5">
        {{-- Subtle accent dot --}}
        <span class="w-1 h-1 bg-blue-600 rounded-full opacity-50"></span>
        {{ $value ?? $slot }}
    </span>
</label>