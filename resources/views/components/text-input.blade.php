@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge([
    'class' => 'w-full px-5 py-3 bg-white border-gray-200 text-gray-900 text-sm rounded-2xl shadow-sm 
                placeholder:text-gray-400 placeholder:font-medium
                hover:border-gray-300
                focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:shadow-md
                disabled:bg-gray-50 disabled:text-gray-400 disabled:cursor-not-allowed
                transition-all duration-200 ease-in-out'
]) }}>