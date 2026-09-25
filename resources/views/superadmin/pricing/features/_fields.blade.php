@php $feature = $feature ?? null; @endphp
<div>
    <label for="name" class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Feature name</label>
    <input type="text" name="name" id="name" value="{{ old('name', $feature->name ?? '') }}" class="w-full border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl shadow-sm text-sm font-medium p-3" required>
    @error('name')<p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p>@enderror
    @if($feature)<p class="mt-1 text-[11px] text-gray-400">Key: <code>{{ $feature->key }}</code> — set once at creation, used internally to gate access.</p>@endif
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <label for="cost" class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Cost (GHS, per event)</label>
        <input type="number" step="0.01" min="0" name="cost" id="cost" value="{{ old('cost', isset($feature) ? number_format($feature->cost_minor / 100, 2, '.', '') : '') }}" class="w-full border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl shadow-sm text-sm font-medium p-3" required>
        @error('cost')<p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="sort_order" class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Display order</label>
        <input type="number" min="0" name="sort_order" id="sort_order" value="{{ old('sort_order', $feature->sort_order ?? '') }}" class="w-full border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl shadow-sm text-sm font-medium p-3" placeholder="Lower shows first">
        @error('sort_order')<p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p>@enderror
    </div>
</div>

<div>
    <label for="description" class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Description</label>
    <textarea name="description" id="description" rows="3" class="w-full rounded-xl border-gray-200 p-3 text-sm" placeholder="Shown to managers when they pick features for an event">{{ old('description', $feature->description ?? '') }}</textarea>
    @error('description')<p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p>@enderror
</div>

<div>
    <label class="inline-flex items-center gap-3 font-bold text-sm text-gray-700">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $feature->is_active ?? true)) class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        Active — offered to managers when they finalize an event's bill
    </label>
</div>
