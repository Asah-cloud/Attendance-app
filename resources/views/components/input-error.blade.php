@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'mt-2 space-y-1.5 animate-in fade-in slide-in-from-top-1 duration-300']) }}>
        @foreach ((array) $messages as $message)
            <li class="flex items-center gap-2 px-3 py-1.5 bg-red-50 border border-red-100 rounded-xl">
                <div class="flex-shrink-0 text-red-500">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                
                <span class="text-[10px] font-black text-red-700 uppercase tracking-widest leading-tight">
                    {{ $message }}
                </span>
            </li>
        @endforeach
    </ul>
@endif