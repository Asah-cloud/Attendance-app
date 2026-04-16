@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-2 bg-white'])

@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
    'top' => 'origin-top',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0',
};

$width = match ($width) {
    '48' => 'w-48',
    '56' => 'w-56',
    '64' => 'w-64',
    default => $width,
};
@endphp

<div class="relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
    <div @click="open = ! open" class="cursor-pointer">
        {{ $trigger }}
    </div>

    <div x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute z-50 mt-3 {{ $width }} rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.1)] {{ $alignmentClasses }}"
            style="display: none;"
            @click="open = false">
        
        <div class="rounded-2xl ring-1 ring-black/5 overflow-hidden {{ $contentClasses }} border border-gray-100 shadow-inner">
            {{ $content }}
        </div>
    </div>
</div>