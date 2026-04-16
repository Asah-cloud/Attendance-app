<button {{ $attributes->merge([
    'type' => 'submit', 
    'class' => 'inline-flex items-center px-6 py-3 bg-gray-900 border border-transparent rounded-2xl font-black text-[10px] text-white uppercase tracking-[0.2em] shadow-lg shadow-gray-200 hover:bg-black hover:shadow-gray-300 hover:-translate-y-0.5 active:scale-95 active:translate-y-0 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 transition-all duration-200 ease-in-out'
]) }}>
    <span class="flex items-center gap-2">
        {{ $slot }}
        {{-- Subtle trailing arrow for action --}}
        <svg class="w-3 h-3 opacity-50 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
        </svg>
    </span>
</button>