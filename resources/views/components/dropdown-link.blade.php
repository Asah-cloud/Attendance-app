<a {{ $attributes->merge([
    'class' => 'group block w-full px-5 py-2.5 text-start text-[11px] font-black uppercase tracking-widest text-gray-600 hover:text-blue-600 hover:bg-blue-50/50 focus:outline-none focus:bg-blue-50 transition-all duration-200 border-l-4 border-transparent hover:border-blue-600'
]) }}>
    <span class="flex items-center gap-2">
        {{ $slot }}
    </span>
</a>