<x-app-layout>
    <x-slot name="header">Add Plan</x-slot>
    <div class="py-10"><div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center justify-between gap-4"><div><p class="text-xs font-extrabold uppercase tracking-wider text-blue-600">Pricing</p><h2 class="mt-1 text-2xl font-black">Add plan</h2></div><a href="{{ route('pricing.plans.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-extrabold text-slate-600 hover:border-blue-300 hover:text-blue-700">Back to plans</a></div>

        <div class="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100 p-8">
            <form method="POST" action="{{ route('pricing.plans.store') }}" class="space-y-6">
                @csrf
                @include('superadmin.pricing.plans._fields')
                <div class="flex justify-end gap-3">
                    <a href="{{ route('pricing.plans.index') }}" class="px-5 py-3 bg-gray-100 text-gray-600 rounded-xl font-bold text-xs uppercase tracking-wider hover:bg-gray-200 transition">Cancel</a>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-700 to-blue-800 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-md hover:shadow-blue-100 hover:scale-[1.02] transition-all">Create plan</button>
                </div>
            </form>
        </div>
    </div></div>
</x-app-layout>
