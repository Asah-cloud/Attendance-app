<x-app-layout>
    <x-slot name="header">
        Companies
        <div class="hidden">
            <h2 class="font-black text-2xl text-white uppercase tracking-tight">Manage Companies</h2>
            <a href="{{ route('companies.create') }}" class="bg-white text-blue-900 px-6 py-2 rounded-xl font-black text-xs uppercase shadow-lg hover:bg-gray-100 transition-all">
                Add New Company
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-6 flex items-end justify-between gap-4"><div><p class="text-xs font-extrabold uppercase tracking-wider text-blue-600">Platform tenants</p><h2 class="mt-1 text-2xl font-black">Manage companies</h2></div><div class="flex items-center gap-3"><a href="{{ route('companies.history.index') }}" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-xs font-extrabold uppercase tracking-wider text-slate-600 shadow-sm hover:border-blue-300 hover:text-blue-700">History</a><a href="{{ route('companies.create') }}" class="rounded-xl bg-blue-600 px-5 py-3 text-xs font-extrabold uppercase tracking-wider text-white shadow-lg shadow-blue-200 hover:-translate-y-0.5 hover:bg-blue-700">Add company</a></div></div>
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-2xl">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-[10px] uppercase text-gray-400 font-black tracking-widest">
                            <th class="p-6">Company Name</th>
                            <th class="p-6">Status</th>
                            <th class="p-6">Sub Ends</th>
                            <th class="p-6">Event Limit</th>
                            <th class="p-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($companies as $company)
                        <tr>
                            <td class="p-6 font-bold text-gray-900">{{ $company->name }}</td>
                            <td class="p-6">
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase {{ $company->is_active ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600' }}">
                                    {{ $company->is_active ? 'Active' : 'Disabled' }}
                                </span>
                            </td>
                            <td class="p-6 text-sm text-gray-500">{{ $company->subscription_ends_at }}</td>
                            <td class="p-6 text-sm font-bold">{{ $company->event_limit }}</td>
                            <td class="p-6 text-right">
                                <form action="{{ route('companies.destroy', $company->id) }}" method="POST" onsubmit="return confirm('Archive this company? Its managers and staff will immediately lose access. It will stay in History until permanently deleted from there.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs bg-red-50 text-red-600 hover:bg-red-100 px-3 py-1.5 rounded-xl font-black uppercase tracking-wider transition-all">
                                        Archive
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
