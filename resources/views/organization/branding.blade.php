<x-app-layout>
    <x-slot name="header">Organization settings</x-slot>

    <div class="mx-auto max-w-3xl">
        @if(session('success'))
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">{{ session('success') }}</div>
        @endif

        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-6 border-b border-slate-100 pb-7 sm:flex-row sm:items-center">
                @if($company->logo_path)
                    <img src="{{ Storage::url($company->logo_path) }}" alt="{{ $company->name }} logo" class="h-24 w-24 rounded-2xl border border-slate-200 object-contain p-2">
                @else
                    <div class="grid h-24 w-24 place-items-center rounded-2xl bg-blue-600 text-3xl font-black text-white">{{ strtoupper(substr($company->name, 0, 1)) }}</div>
                @endif
                <div><p class="text-xs font-extrabold uppercase tracking-[0.2em] text-blue-600">Organization identity</p><h2 class="mt-2 text-2xl font-black text-slate-950">{{ $company->name }}</h2><p class="mt-2 text-sm leading-6 text-slate-500">This name and logo appear in the manager workspace.</p></div>
            </div>

            <form method="POST" action="{{ route('organization.branding.update') }}" enctype="multipart/form-data" class="mt-7 space-y-6">
                @csrf @method('PATCH')
                <div><label for="name" class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Organization name</label><input id="name" name="name" value="{{ old('name', $company->name) }}" required class="mt-2 w-full rounded-xl border-slate-200 px-4 py-3 text-sm font-bold focus:border-blue-500 focus:ring-blue-500">@error('name')<p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>@enderror</div>
                <div><label for="logo" class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Organization logo</label><input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="mt-2 block w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-xs file:font-extrabold file:text-blue-700"><p class="mt-2 text-xs text-slate-400">PNG, JPG or WebP. Maximum size: 2 MB. A square image works best.</p>@error('logo')<p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>@enderror</div>
                @if($company->logo_path)<label class="flex items-center gap-3 text-sm font-semibold text-slate-600"><input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 text-red-600 focus:ring-red-500">Remove current logo</label>@endif
                <div class="flex justify-end"><button class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-extrabold text-white shadow-lg shadow-blue-200 hover:bg-blue-700">Save branding</button></div>
            </form>
        </div>
    </div>
</x-app-layout>
