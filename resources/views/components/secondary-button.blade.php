<button {{ $attributes->merge([
    'type' => 'button', 
    'class' => 'inline-flex items-center px-6 py-2.5 bg-white border border-gray-200 rounded-xl font-black text-[10px] text-gray-500 uppercase tracking-[0.2em] shadow-sm hover:bg-gray-50 hover:text-gray-900 hover:border-gray-300 active:scale-95 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:ring-offset-2 disabled:opacity-25 transition-all duration-200 ease-in-out'
]) }}>
    {{ $slot }}
</button>