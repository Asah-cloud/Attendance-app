{{-- Category, gender, area and country counts side by side. --}}
<table class="split"><tr>
    @foreach(['By category' => $categoryBreakdown, 'By gender' => $genderBreakdown, 'By area' => $areaBreakdown ?? null, 'By country' => $countryBreakdown ?? null] as $heading => $breakdown)
        @continue($breakdown === null)
        <td>
            <h2>{{ $heading }}</h2>
            <table class="list">
                @forelse($breakdown->take(15) as $label => $count)
                    <tr><td>{{ $label }}</td><td class="c" style="width: 34px"><strong>{{ $count }}</strong></td></tr>
                @empty
                    <tr><td class="muted">No attendance recorded.</td></tr>
                @endforelse
                @if($breakdown->count() > 15)
                    <tr><td class="muted">+ {{ $breakdown->count() - 15 }} more</td><td class="c">{{ $breakdown->skip(15)->sum() }}</td></tr>
                @endif
            </table>
        </td>
    @endforeach
</tr></table>
