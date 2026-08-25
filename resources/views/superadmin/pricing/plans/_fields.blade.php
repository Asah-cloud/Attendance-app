@php $plan = $plan ?? null; @endphp
<div>
    <label for="name" class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Plan name</label>
    <input type="text" name="name" id="name" value="{{ old('name', $plan->name ?? '') }}" class="w-full border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl shadow-sm text-sm font-medium p-3" required>
    @error('name')<p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p>@enderror
    @if($plan)<p class="mt-1 text-[11px] text-gray-400">Key: <code>{{ $plan->key }}</code> — set once at creation, used internally to link companies to this plan.</p>@endif
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div>
        <label for="price" class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Price (GHS/month)</label>
        <input type="number" step="0.01" min="0" name="price" id="price" value="{{ old('price', isset($plan) ? number_format($plan->price_minor / 100, 2, '.', '') : '') }}" class="w-full border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl shadow-sm text-sm font-medium p-3" required>
        @error('price')<p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="event_limit" class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Event limit</label>
        <input type="number" min="1" name="event_limit" id="event_limit" value="{{ old('event_limit', $plan->event_limit ?? '') }}" class="w-full border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl shadow-sm text-sm font-medium p-3" required>
        @error('event_limit')<p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="participant_limit" class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Participant limit</label>
        <input type="number" min="1" name="participant_limit" id="participant_limit" value="{{ old('participant_limit', $plan->participant_limit ?? '') }}" class="w-full border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl shadow-sm text-sm font-medium p-3" required>
        @error('participant_limit')<p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p>@enderror
    </div>
</div>

<div>
    <label for="description" class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Description</label>
    <input type="text" name="description" id="description" value="{{ old('description', $plan->description ?? '') }}" class="w-full border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl shadow-sm text-sm font-medium p-3" placeholder="Shown under the plan name on the pricing page">
    @error('description')<p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p>@enderror
</div>

<div>
    <label for="features" class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Feature bullets</label>
    <p class="mt-1 mb-2 text-xs text-gray-500">One feature per line — shown as a checklist on the pricing page.</p>
    <textarea name="features" id="features" rows="8" class="w-full rounded-xl border-gray-200 p-3 text-sm">{{ old('features', isset($plan) ? implode("\n", $plan->features ?? []) : '') }}</textarea>
    @error('features')<p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p>@enderror
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <label class="inline-flex items-center gap-3 font-bold text-sm text-gray-700">
            <input type="checkbox" name="featured" value="1" @checked(old('featured', $plan->featured ?? false)) class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            Mark as "Most popular" on the pricing page
        </label>
    </div>
    <div>
        <label for="sort_order" class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Display order</label>
        <input type="number" min="0" name="sort_order" id="sort_order" value="{{ old('sort_order', $plan->sort_order ?? '') }}" class="w-full border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl shadow-sm text-sm font-medium p-3" placeholder="Lower shows first">
        @error('sort_order')<p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p>@enderror
    </div>
</div>
