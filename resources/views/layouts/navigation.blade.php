@php
    $user = auth()->user();
    $items = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'M3 13h8V3H3v10Zm10 8h8V11h-8v10ZM3 21h8v-6H3v6Zm10-12h8V3h-8v6Z'],
        ['label' => 'Events', 'route' => 'events.index', 'active' => ['events.*', 'reports.*'], 'icon' => 'M6 2v3m12-3v3M3 9h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z'],
    ];

    if ($user->hasAnyRole(['admin', 'manager'])) {
        $items[] = ['label' => 'Team', 'route' => 'admin.users.index', 'active' => ['admin.users.*', 'admin.register-*'], 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87m-2-11.26a4 4 0 0 1 0 7.75'];
    }
    if ($user->hasRole('admin')) {
        $items[] = ['label' => 'Companies', 'route' => 'companies.index', 'active' => 'companies.*', 'icon' => 'M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1m-6 4h1m4 0h1m-6 4h1m4 0h1'];
        $items[] = ['label' => 'Pricing', 'route' => 'pricing.plans.index', 'active' => ['pricing.plans.*', 'attendee-pricing.*', 'pricing.companies.*', 'attendee-billing.*'], 'icon' => 'M12 8v8m-4-4h8M3 6h18M5 21V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v15l-7-4-7 4Z'];
        $items[] = ['label' => 'Integrations', 'route' => 'integrations.edit', 'active' => 'integrations.*', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7Z'];
    }
    if ($user->hasRole('manager')) {
        $items[] = ['label' => 'Merge Duplicates', 'route' => 'participants.duplicates.index', 'active' => 'participants.duplicates.*', 'icon' => 'M17 8V4a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v4M4 8h16v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8Zm4 5h8'];
        $items[] = ['label' => 'Billing', 'route' => 'billing.index', 'active' => 'billing.*', 'icon' => 'M3 6h18v12H3V6Zm0 4h18M7 15h3'];
        $items[] = ['label' => 'Organization', 'route' => 'organization.branding.edit', 'active' => 'organization.*', 'icon' => 'M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7.4-3.5a7.3 7.3 0 0 0-.1-1l2-1.6-2-3.4-2.5 1a8 8 0 0 0-1.8-1L14.6 3h-4L10 6a8 8 0 0 0-1.8 1L5.7 6 3.7 9.4l2 1.6a7.3 7.3 0 0 0 0 2L3.7 14.6l2 3.4 2.5-1a8 8 0 0 0 1.8 1l.6 3h4l.6-3a8 8 0 0 0 1.8-1l2.5 1 2-3.4-2-1.6a7.3 7.3 0 0 0 .1-1Z'];
    }
@endphp

<div x-cloak x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false" class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm lg:hidden"></div>

<aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-[#071426] text-white shadow-2xl transition-transform duration-300 lg:translate-x-0">
    <div class="flex h-20 items-center justify-between border-b border-white/10 px-6">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-gradient-to-br from-blue-500 to-blue-700 text-xl font-black shadow-lg shadow-blue-950">A</span>
            <span><span class="block text-[10px] font-bold uppercase tracking-[0.3em] text-amber-300">Asah Apex</span><span class="block text-base font-extrabold">Attendance</span></span>
        </a>
        <button type="button" @click="sidebarOpen = false" class="grid h-9 w-9 place-items-center rounded-lg text-slate-300 hover:bg-white/10 lg:hidden" aria-label="Close navigation">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="m6 6 12 12M18 6 6 18" /></svg>
        </button>
    </div>

    <div class="px-5 pb-3 pt-6"><p class="px-3 text-[10px] font-extrabold uppercase tracking-[0.22em] text-slate-500">Workspace</p></div>
    <nav class="flex-1 space-y-1 overflow-y-auto px-4">
        @foreach($items as $item)
            @php $isActive = collect((array) $item['active'])->contains(fn ($pattern) => request()->routeIs($pattern)); @endphp
            <a href="{{ route($item['route']) }}" @if($isActive) aria-current="page" @endif @click="sidebarOpen = false" class="group flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold transition {{ $isActive ? 'bg-blue-600 text-white shadow-lg shadow-blue-950/40' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $item['icon'] }}" /></svg>
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="border-t border-white/10 p-4">
        <a href="{{ route('profile.edit') }}" @if(request()->routeIs('profile.*')) aria-current="page" @endif class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold {{ request()->routeIs('profile.*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M20 21a8 8 0 0 0-16 0m8-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" /></svg>Profile
        </a>
        <form method="POST" action="{{ route('logout') }}">@csrf
            <button class="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold text-red-300 hover:bg-red-500/10 hover:text-red-200">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M10 17l5-5-5-5m5 5H3m10-9h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6" /></svg>Log out
            </button>
        </form>
    </div>
</aside>
