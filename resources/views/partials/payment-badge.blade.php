@if($status === 'paid')
    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-black text-emerald-700">Paid · {{ App\Models\AttendeePayment::money($amount) }}</span>
@elseif($status === 'unpaid')
    <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-black text-amber-700">Not paid</span>
@else
    <span class="text-slate-300">—</span>
@endif
