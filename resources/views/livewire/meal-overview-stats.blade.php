<div wire:poll.5s class="mb-6 flex flex-wrap gap-4 rounded-xl bg-blue-50 p-5">
    <span><strong>{{ $confirmed }}</strong> confirmed participants</span>
    <span><strong>{{ $checkedIn }}</strong> checked-in participants</span>
    <span>Every confirmed participant is eligible for meals, including confirmed event staff.</span>
    <a class="font-bold text-blue-700" href="{{ route('audit.approvals.index', $event) }}">Approval codes / restricted sections</a>
</div>
