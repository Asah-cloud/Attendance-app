<x-app-layout>
    <x-slot name="header">
        <h2 class="font-black text-2xl text-white leading-tight tracking-tight">
            {{ __('Member Directory') }}
        </h2>
    </x-slot>

    <div class="py-12 bg-[#f8fafc]">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:member-directory />
        </div>
    </div>
</x-app-layout>