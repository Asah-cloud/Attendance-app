@php
    // The same page serves managers and people the form was shared with (SharedFormController).
    $exportExcelUrl ??= route('events.forms.responses.export', [$event, $form]);
    $exportPdfUrl ??= route('events.forms.responses.pdf', [$event, $form]);
    $backUrl ??= route('events.forms.edit', [$event, $form]);
    $backLabel ??= 'Back to form';
    $canDelete ??= true;
    // A payment column once the form has ever asked for payment.
    $showPayment = $form->payment_enabled || $form->responses()->whereNotNull('payment_status')->exists();
    $paidTotal = $showPayment ? (int) $form->responses()->where('payment_status', 'paid')->sum('amount_paid_minor') : 0;
    $paidCount = $showPayment ? $form->responses()->where('payment_status', 'paid')->count() : 0;
@endphp
<x-app-layout>
    <x-slot name="header">{{ $form->title }} · Responses</x-slot>
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div><h1 class="text-2xl font-black text-slate-900">Responses</h1><p class="text-sm text-slate-500">{{ $form->title }} · {{ $responses->total() }} response(s)</p></div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ $exportExcelUrl }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-black uppercase tracking-wider text-white">Export Excel</a>
                <a href="{{ $exportPdfUrl }}" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-black uppercase tracking-wider text-white">Export PDF</a>
                <a href="{{ $backUrl }}" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-black uppercase tracking-wider text-white">{{ $backLabel }}</a>
            </div>
        </div>

        @if($showPayment)
            <div class="mb-5 grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4"><p class="text-xs font-black uppercase text-emerald-700">Collected</p><p class="mt-1 text-2xl font-black text-emerald-950">{{ App\Models\AttendeePayment::money($paidTotal) }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-black uppercase text-slate-500">Paid</p><p class="mt-1 text-2xl font-black">{{ $paidCount }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-black uppercase text-slate-500">Not paid yet</p><p class="mt-1 text-2xl font-black">{{ $form->responses()->where('payment_status', 'unpaid')->count() }}</p></div>
            </div>
            @php $byChoice = $form->responses()->where('payment_status', 'paid')->whereNotNull('payment_option')->selectRaw('payment_option, count(*) as people, sum(amount_paid_minor) as total')->groupBy('payment_option')->orderBy('payment_option')->get(); @endphp
            @if($byChoice->isNotEmpty())
                <div class="-mt-2 mb-5 flex flex-wrap gap-2">
                    @foreach($byChoice as $choice)
                        <span class="rounded-full border border-emerald-100 bg-white px-3 py-1.5 text-xs font-bold text-slate-700">{{ $choice->payment_option }}: <span class="font-black">{{ $choice->people }}</span> paid · {{ App\Models\AttendeePayment::money((int) $choice->total) }}</span>
                    @endforeach
                </div>
            @endif
        @endif

        @if(session('success'))<p class="mb-5 rounded-2xl bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('success') }}</p>@endif
        @if($errors->any())<p class="mb-5 rounded-2xl bg-red-50 p-4 text-sm font-bold text-red-700">{{ $errors->first() }}</p>@endif

        @if($canDelete && $responses->total() > 0)
            @php
                $allPaid = $showPayment ? $form->responses()->where('payment_status', 'paid')->count() : 0;
                $clearWarning = 'Delete all '.$responses->total().' responses to "'.$form->title.'"?'.($allPaid ? ' '.$allPaid.' of them are paid; the payments stay in Paystack, only the answers are removed.' : '').' This can\'t be undone.';
            @endphp
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3" x-data>
                <p class="text-xs font-bold text-slate-500"><span x-text="$store.responsePick.count">0</span> selected</p>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" form="delete-responses" :disabled="$store.responsePick.count === 0"
                            onclick="return confirm('Delete the selected responses? Paid ones keep their payment in Paystack; only the answers are removed. This can\'t be undone.')"
                            class="rounded-xl border border-red-200 bg-white px-4 py-2 text-xs font-black uppercase tracking-wider text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40">Delete selected</button>
                    <form method="POST" action="{{ route('events.forms.responses.destroy', [$event, $form]) }}" onsubmit="return confirm(@js($clearWarning))">
                        @csrf @method('DELETE')
                        <input type="hidden" name="all" value="1">
                        <button class="rounded-xl bg-red-600 px-4 py-2 text-xs font-black uppercase tracking-wider text-white hover:bg-red-700">Clear all responses</button>
                    </form>
                </div>
            </div>
            <form id="delete-responses" method="POST" action="{{ route('events.forms.responses.destroy', [$event, $form]) }}">@csrf @method('DELETE')</form>
            <script>
                document.addEventListener('alpine:init', () => Alpine.store('responsePick', { count: 0 }));
            </script>
        @endif

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" data-no-lift><div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wider text-slate-500"><tr>
                @if($canDelete)<th class="w-10 px-5 py-4"><input type="checkbox" aria-label="Select all on this page" class="rounded border-slate-300 text-red-600"
                    @change="document.querySelectorAll('[data-response-pick]').forEach(box => box.checked = $event.target.checked); $store.responsePick.count = document.querySelectorAll('[data-response-pick]:checked').length" x-data></th>@endif
                <th class="px-5 py-4">Submitted</th>
                @if($showPayment)<th class="px-5 py-4">Payment</th>@endif
                @foreach($fields as $field)<th class="px-5 py-4">{{ $field->label }}</th>@endforeach
            </tr></thead>
            <tbody class="divide-y divide-slate-100">@forelse($responses as $response)
                <tr>
                    @if($canDelete)<td class="px-5 py-4"><input type="checkbox" name="response_ids[]" value="{{ $response->id }}" form="delete-responses" data-response-pick aria-label="Select this response" class="rounded border-slate-300 text-red-600"
                        x-data @change="$store.responsePick.count = document.querySelectorAll('[data-response-pick]:checked').length"></td>@endif
                    <td class="px-5 py-4 text-slate-500">{{ $response->created_at->format('M j, Y g:i A') }}</td>
                    @if($showPayment)<td class="whitespace-nowrap px-5 py-4">@include('partials.payment-badge', ['status' => $response->payment_status, 'amount' => $response->amount_paid_minor, 'option' => $response->payment_option])</td>@endif
                    @foreach($fields as $field)<td class="px-5 py-4 text-slate-700">{{ $response->answers[$field->field_key] ?? '—' }}</td>@endforeach
                </tr>
            @empty<tr><td colspan="{{ $fields->count() + ($showPayment ? 2 : 1) + ($canDelete ? 1 : 0) }}" class="px-5 py-12 text-center text-slate-500">No responses yet.</td></tr>@endforelse</tbody>
        </table></div></div><div class="mt-5">{{ $responses->links() }}</div>
    </div>
</x-app-layout>
