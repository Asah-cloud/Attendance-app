{{-- One day/session cell in a PDF attendance table. --}}
@if($value === \App\Services\AttendanceGrid::NOT_PRESENT)
    <span class="no">—</span>
@elseif($value === 'Staff')
    <span class="staff">STAFF</span>
@elseif($value !== '')
    <span class="yes">✓</span> <span class="time">{{ $value }}</span>
@endif
