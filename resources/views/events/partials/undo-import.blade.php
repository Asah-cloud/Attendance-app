{{-- Undo one participant import: removes the people it added (check-ins are kept). --}}
@php
    $count = $import->addedToEventCount();
    $question = "Undo the import of {$import->file_name}?\n\nThis removes the {$count} ".Str::plural('person', $count)." it added to this event. Anyone who has already checked in is kept. Messages already sent can't be taken back.";
@endphp
<form method="POST" action="{{ route('events.import.undo', [$event, $import]) }}" class="shrink-0" onsubmit="return confirm(@js($question))">
    @csrf
    @method('DELETE')
    <button type="submit" class="rounded-xl border border-red-200 bg-white px-3 py-2 text-xs font-extrabold text-red-700 hover:bg-red-50">Undo import</button>
</form>
