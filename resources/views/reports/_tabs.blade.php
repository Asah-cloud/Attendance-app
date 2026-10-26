{{-- Report tabs: three attendance views, then registrations. $active is the current tab; $day / $session keep the attendance period. --}}
@php
    $tabs = ['simple' => ['Simple', 'The headline numbers'], 'detailed' => ['Detailed', 'Every day, area and person'], 'advanced' => ['Advanced', 'Patterns, follow-ups and data checks'], 'registrations' => ['Registrations', 'Who is coming, at any time']];
@endphp
<nav data-tour="event-reports-views" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 print:hidden" aria-label="Report detail">
    @foreach($tabs as $key => [$label, $hint])
        <a href="{{ $key === 'registrations' ? route('reports.registrations', $event) : route('reports.event', array_filter(['event' => $event->id, 'day' => $day ?? 'all', 'session' => $session ?? null, 'view' => $key === 'simple' ? null : $key], fn ($value) => $value !== null)) }}"
           @if($active === $key) aria-current="page" @endif
           class="rounded-2xl border px-5 py-3 transition {{ $active === $key ? 'border-blue-600 bg-blue-600 text-white shadow-md' : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300' }}">
            <span class="block text-sm font-black">{{ $label }}</span>
            <span class="block text-xs {{ $active === $key ? 'text-blue-100' : 'text-slate-500' }}">{{ $hint }}</span>
        </a>
    @endforeach
</nav>
