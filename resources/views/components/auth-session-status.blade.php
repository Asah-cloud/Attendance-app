@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'flex items-center gap-3 p-4 mb-6 bg-green-50 border border-green-100 rounded-2xl animate-in fade-in slide-in-from-top-2 duration-500']) }}>
        <div class="flex-shrink-0 w-8 h-8 bg-green-600 rounded-full flex items-center justify-center shadow-lg shadow-green-200">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>

        <div class="font-black text-xs text-green-800 uppercase tracking-widest">
            {{ $status }}
        </div>
    </div>
@endif