<x-app-layout>
    <x-slot name="header">Register Company</x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-2xl p-8 border border-gray-100">
                <form action="{{ route('companies.store') }}" method="POST" class="space-y-6">
                    @csrf
                    
                    <div>
                        <label class="block text-xs font-black uppercase text-gray-400 tracking-widest mb-2">Company Name</label>
                        <input type="text" name="name" required class="w-full border-gray-200 rounded-xl focus:ring-blue-500 focus:border-blue-500 shadow-sm" placeholder="e.g. Global Worship Center">
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-gray-400 tracking-widest mb-2">Company Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="w-full border-gray-200 rounded-xl focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-black uppercase text-gray-400 tracking-widest mb-2">Subscription End Date</label>
                            <input type="date" name="subscription_ends_at" required class="w-full border-gray-200 rounded-xl focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase text-gray-400 tracking-widest mb-2">Event Limit</label>
                            <input type="number" name="event_limit" value="5" required class="w-full border-gray-200 rounded-xl focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full bg-blue-900 text-white font-black uppercase py-4 rounded-xl shadow-lg hover:bg-blue-800 transition-all transform hover:-translate-y-1">
                            Register & Activate Company
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
