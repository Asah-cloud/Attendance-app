<div class="grid gap-3 px-5 pt-4 md:grid-cols-2">
    @if($selected->email_body)
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">Email</p>
            @if($selected->subject)<p class="mt-2 text-sm font-black text-slate-800">{{ $selected->subject }}</p>@endif
            <p class="mt-2 max-h-48 overflow-y-auto whitespace-pre-line break-words text-sm text-slate-700">{{ $selected->email_body }}</p>
            @if(!empty($selected->attachments))
                <div class="mt-3 flex flex-wrap gap-1.5">@foreach($selected->attachments as $attachment)<span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600">{{ $attachment['name'] }}</span>@endforeach</div>
            @endif
        </div>
    @endif
    @if($selected->sms_body)
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">SMS</p>
            <div class="mt-2 inline-block max-h-48 max-w-full overflow-y-auto rounded-2xl rounded-bl-sm bg-blue-600 px-3.5 py-2 text-sm text-white"><p class="whitespace-pre-line break-words">{{ $selected->sms_body }}</p></div>
        </div>
    @endif
    @if(! $selected->email_body && ! $selected->sms_body)
        <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-6 text-center text-sm text-slate-400 md:col-span-2">Nothing written yet.</p>
    @endif
</div>
