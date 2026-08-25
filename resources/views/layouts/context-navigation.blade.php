@php
    $routeName = request()->route()?->getName();
    $routeEvent = request()->route('event');
    $contextEvent = $routeEvent instanceof \App\Models\Event ? $routeEvent : null;
    $user = auth()->user();

    $section = match (true) {
        request()->routeIs('events.*', 'reports.*') => ['Events', route('events.index')],
        request()->routeIs('admin.users.*', 'admin.register-*') => ['Team', route('admin.users.index')],
        request()->routeIs('companies.history.*') => ['Company history', route('companies.history.index')],
        request()->routeIs('companies.*') => ['Companies', route('companies.index')],
        request()->routeIs('billing.*') => ['Billing', route('billing.index')],
        request()->routeIs('organization.*') => ['Organization', route('organization.branding.edit')],
        request()->routeIs('participants.duplicates.*') => ['Merge duplicates', route('participants.duplicates.index')],
        request()->routeIs('profile.*') => ['Profile', route('profile.edit')],
        default => null,
    };

    $pageLabel = match (true) {
        request()->routeIs('events.create') => 'Create event',
        request()->routeIs('events.edit') => 'Edit event',
        request()->routeIs('events.attendance') => 'Attendance',
        request()->routeIs('events.scanner') => 'QR scanner',
        request()->routeIs('events.registrations.index') => 'Attendees',
        request()->routeIs('events.registration-form.*') => 'Registration form',
        request()->routeIs('events.confirmations.*') => 'Confirmations',
        request()->routeIs('events.meals.*') => 'Food distribution',
        request()->routeIs('events.badges') => 'Badges',
        request()->routeIs('events.forms.create') => 'Create form',
        request()->routeIs('events.forms.responses') => 'Form responses',
        request()->routeIs('events.forms.*') => 'Forms',
        request()->routeIs('events.billing.*') => 'Billing',
        request()->routeIs('events.registrations.participant.history') => 'Edit history',
        request()->routeIs('reports.summary*') => 'Summary report',
        request()->routeIs('reports.*') => 'Attendance report',
        request()->routeIs('admin.users.edit') => 'Edit member',
        request()->routeIs('admin.register-person') => 'Add team member',
        request()->routeIs('companies.create') => 'Add company',
        request()->routeIs('companies.edit') => 'Edit company',
        request()->routeIs('companies.history.show') => 'Archived company',
        request()->routeIs('billing.checkout') => 'Checkout',
        request()->routeIs('participants.duplicates.compare') => 'Compare records',
        default => null,
    };

    $eventLinks = $contextEvent ? array_values(array_filter([
        ['label' => 'Attendance', 'route' => route('events.attendance', $contextEvent), 'active' => request()->routeIs('events.attendance', 'events.scanner')],
        $user->can('update', $contextEvent) ? ['label' => 'Attendees', 'route' => route('events.registrations.index', $contextEvent), 'active' => request()->routeIs('events.registrations.*', 'events.badges')] : null,
        $user->can('update', $contextEvent) ? ['label' => 'Registration form', 'route' => route('events.registration-form.edit', $contextEvent), 'active' => request()->routeIs('events.registration-form.*')] : null,
        $user->can('update', $contextEvent) ? ['label' => 'Confirmations', 'route' => route('events.confirmations.index', $contextEvent), 'active' => request()->routeIs('events.confirmations.*')] : null,
        $user->can('update', $contextEvent) ? ['label' => 'Forms', 'route' => route('events.forms.index', $contextEvent), 'active' => request()->routeIs('events.forms.*')] : null,
        ['label' => 'Food', 'route' => route('events.meals.index', $contextEvent), 'active' => request()->routeIs('events.meals.*')],
        ['label' => 'Reports', 'route' => route('reports.event', $contextEvent), 'active' => request()->routeIs('reports.*')],
        $user->can('update', $contextEvent) ? ['label' => 'Billing', 'route' => route('events.billing.show', $contextEvent), 'active' => request()->routeIs('events.billing.*')] : null,
        $user->can('update', $contextEvent) ? ['label' => 'Settings', 'route' => route('events.edit', $contextEvent), 'active' => request()->routeIs('events.edit')] : null,
    ])) : [];
@endphp

@if($section && ! request()->routeIs('dashboard'))
    <div class="mb-6 space-y-3" aria-label="Page navigation">
        <nav class="flex min-w-0 items-center gap-2 overflow-x-auto whitespace-nowrap text-xs font-bold text-slate-500" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}" class="hover:text-blue-700">Dashboard</a>
            <span class="text-slate-300">/</span>
            @if($contextEvent)
                <a href="{{ $section[1] }}" class="hover:text-blue-700">{{ $section[0] }}</a>
                <span class="text-slate-300">/</span>
                <a href="{{ route('events.attendance', $contextEvent) }}" class="max-w-48 truncate text-slate-700 hover:text-blue-700">{{ $contextEvent->title }}</a>
                @if($pageLabel)<span class="text-slate-300">/</span><span class="text-blue-700" aria-current="page">{{ $pageLabel }}</span>@endif
            @elseif($pageLabel)
                <a href="{{ $section[1] }}" class="hover:text-blue-700">{{ $section[0] }}</a>
                <span class="text-slate-300">/</span><span class="text-blue-700" aria-current="page">{{ $pageLabel }}</span>
            @else
                <span class="text-blue-700" aria-current="page">{{ $section[0] }}</span>
            @endif
        </nav>

        @if($eventLinks)
            <nav class="flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Event workspace">
                @foreach($eventLinks as $link)
                    <a href="{{ $link['route'] }}" @if($link['active']) aria-current="page" @endif class="shrink-0 rounded-xl px-4 py-2.5 text-xs font-extrabold transition {{ $link['active'] ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-blue-700' }}">{{ $link['label'] }}</a>
                @endforeach
            </nav>
        @endif
    </div>
@endif
