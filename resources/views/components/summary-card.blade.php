@props(['title', 'value', 'color' => 'blue', 'icon' => 'chart-bar'])

@php
    $colorMap = [
        'blue' => [
            'bg' => 'bg-blue-50',
            'text' => 'text-blue-600',
            'iconBg' => 'bg-blue-600',
            'shadow' => 'shadow-blue-100'
        ],
        'green' => [
            'bg' => 'bg-emerald-50',
            'text' => 'text-emerald-600',
            'iconBg' => 'bg-emerald-600',
            'shadow' => 'shadow-emerald-100'
        ],
        'red' => [
            'bg' => 'bg-red-50',
            'text' => 'text-red-600',
            'iconBg' => 'bg-red-600',
            'shadow' => 'shadow-red-100'
        ],
        'yellow' => [
            'bg' => 'bg-amber-50',
            'text' => 'text-amber-600',
            'iconBg' => 'bg-amber-600',
            'shadow' => 'shadow-amber-100'
        ],
    ];

    $theme = $colorMap[$color] ?? $colorMap['blue'];
@endphp

<div {{ $attributes->merge(['class' => "relative overflow-hidden bg-white p-6 rounded-[2rem] border border-gray-100 shadow-xl {$theme['shadow']} transition-all hover:-translate-y-1"]) }}>
    {{-- Decorative Background Pattern --}}
    <div class="absolute -right-4 -top-4 w-24 h-24 {{ $theme['bg'] }} rounded-full opacity-50 blur-2xl"></div>

    <div class="relative flex items-center justify-between">
        <div>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-1">
                {{ $title }}
            </p>
            <h3 class="text-3xl font-black text-gray-900 tracking-tighter">
                {{ $value }}
            </h3>
        </div>

        {{-- Icon Container --}}
        <div class="p-3 {{ $theme['iconBg'] }} rounded-2xl text-white shadow-lg {{ $theme['shadow'] }}">
            @if($icon === 'users')
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            @elseif($icon === 'check')
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            @else
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            @endif
        </div>
    </div>

    {{-- Bottom Indicator --}}
    <div class="mt-4 flex items-center gap-1">
        <span class="w-8 h-1 {{ $theme['iconBg'] }} rounded-full"></span>
        <span class="w-2 h-1 {{ $theme['iconBg'] }} rounded-full opacity-30"></span>
    </div>
</div>