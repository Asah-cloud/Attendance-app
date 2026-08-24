<x-app-layout>
    <x-slot name="header">Organization settings</x-slot>

    <div class="mx-auto max-w-3xl">
        @if(session('success'))
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-bold text-red-800">{{ session('error') }}</div>
        @endif

        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-6 border-b border-slate-100 pb-7 sm:flex-row sm:items-center">
                @if($company->logo_path)
                    <img src="{{ Storage::url($company->logo_path) }}" alt="{{ $company->name }} logo" class="h-24 w-24 rounded-2xl border border-slate-200 object-contain p-2">
                @else
                    <div class="grid h-24 w-24 place-items-center rounded-2xl bg-blue-600 text-3xl font-black text-white">{{ strtoupper(substr($company->name, 0, 1)) }}</div>
                @endif
                <div><p class="text-xs font-extrabold uppercase tracking-[0.2em] text-blue-600">Organization identity</p><h2 class="mt-2 text-2xl font-black text-slate-950">{{ $company->name }}</h2><p class="mt-2 text-sm leading-6 text-slate-500">This name and logo appear in the manager workspace.</p></div>
            </div>

            <form method="POST" action="{{ route('organization.branding.update') }}" enctype="multipart/form-data" class="mt-7 space-y-6">
                @csrf @method('PATCH')
                <div><label for="name" class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Organization name</label><input id="name" name="name" value="{{ old('name', $company->name) }}" required class="mt-2 w-full rounded-xl border-slate-200 px-4 py-3 text-sm font-bold focus:border-blue-500 focus:ring-blue-500">@error('name')<p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>@enderror</div>
                <div><label for="logo" class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Organization logo</label><input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="mt-2 block w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-xs file:font-extrabold file:text-blue-700"><p class="mt-2 text-xs text-slate-400">PNG, JPG or WebP. Maximum size: 2 MB. A square image works best.</p>@error('logo')<p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>@enderror</div>
                @if($company->logo_path)<label class="flex items-center gap-3 text-sm font-semibold text-slate-600"><input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 text-red-600 focus:ring-red-500">Remove current logo</label>@endif
                <div class="flex justify-end"><button class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-extrabold text-white shadow-lg shadow-blue-200 hover:bg-blue-700">Save branding</button></div>
            </form>
        </div>

        <div id="email-domain-setup" class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="border-b border-slate-100 pb-6">
                <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-blue-600">Messaging identity</p>
                <h2 class="mt-2 text-2xl font-black text-slate-950">Send as your organization</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Requested identities use the platform defaults until an administrator confirms provider approval.</p>
            </div>

            <form method="POST" action="{{ route('organization.messaging.update') }}" class="mt-7 space-y-6">
                @csrf @method('PATCH')
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="email_from_name" class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Email sender name</label>
                        <input id="email_from_name" name="email_from_name" value="{{ old('email_from_name', $company->email_from_name) }}" placeholder="{{ $company->name }}" class="mt-2 w-full rounded-xl border-slate-200 px-4 py-3 text-sm font-bold">
                        @error('email_from_name')<p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="email_from_address" class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Email from address</label>
                        <input id="email_from_address" name="email_from_address" type="email" value="{{ old('email_from_address', $company->email_from_address) }}" placeholder="events@yourcompany.com" class="mt-2 w-full rounded-xl border-slate-200 px-4 py-3 text-sm font-bold">
                        <p class="mt-2 text-xs text-slate-400">Use an address on a domain your organization controls.</p>
                        @error('email_from_address')<p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="rounded-xl bg-slate-50 px-4 py-3 text-xs font-bold text-slate-600">Email status: <span class="uppercase text-blue-700">{{ $company->email_sender_status }}</span></div>

                @if($company->email_sender_status === 'approved')
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">Verified — {{ $company->email_from_address }} is active for company event emails.</div>
                @elseif(in_array($company->resend_domain_status, ['failed', 'temporary_failure']))
                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">Action required — Resend could not verify the DNS records. Review every value below or contact whoever manages your domain.</div>
                @elseif($company->resend_delayed_notice_sent_at)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800">Delayed — this setup has remained unverified for more than 72 hours. Please review the DNS records below.</div>
                @elseif($company->resend_first_reminder_sent_at)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800">Action needed — your DNS setup is still incomplete. Add the records below and check verification.</div>
                @endif

                @if($company->resend_domain_records)
                    <div class="overflow-hidden rounded-2xl border border-slate-200">
                        <div class="bg-slate-50 px-4 py-3">
                            <p class="text-sm font-black text-slate-800">DNS records for {{ $company->resend_domain_name }}</p>
                            <p class="mt-1 text-xs text-slate-500">Add every record at the service where this domain is managed. You can also forward the emailed copy to your IT team.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                                <thead class="bg-white text-slate-400"><tr><th class="px-4 py-3">Type</th><th class="px-4 py-3">Name</th><th class="px-4 py-3">Value</th><th class="px-4 py-3">Priority</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($company->resend_domain_records as $record)
                                        <tr><td class="px-4 py-3 font-bold">{{ $record['type'] ?? '' }}</td><td class="px-4 py-3 font-mono">{{ $record['name'] ?? '' }}</td><td class="max-w-md break-all px-4 py-3 font-mono">{{ $record['value'] ?? '' }}</td><td class="px-4 py-3">{{ $record['priority'] ?? '—' }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-xs text-slate-500">Resend status: <span class="font-black uppercase text-blue-700">{{ $company->resend_domain_status }}</span>@if($company->resend_last_checked_at) · Checked {{ $company->resend_last_checked_at->diffForHumans() }}@endif</p>
                        @if($company->email_sender_status !== 'approved')
                            <button form="check-email-domain" class="rounded-xl border border-blue-200 bg-blue-50 px-5 py-3 text-xs font-black text-blue-700 hover:bg-blue-100">I added the records — check verification</button>
                        @endif
                    </div>
                @elseif($company->resend_setup_error)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-800">Domain setup has not started successfully. Save the messaging identity again to retry.</div>
                @endif

                <div>
                    <label for="sms_sender_id" class="text-xs font-extrabold uppercase tracking-wider text-slate-500">SMS sender ID</label>
                    <input id="sms_sender_id" name="sms_sender_id" value="{{ old('sms_sender_id', $company->sms_sender_id) }}" maxlength="11" placeholder="MYCOMPANY" class="mt-2 w-full rounded-xl border-slate-200 px-4 py-3 text-sm font-bold uppercase sm:max-w-sm">
                    <p class="mt-2 text-xs text-slate-400">3–11 letters, numbers, or spaces. Approval depends on the SMS provider and mobile networks.</p>
                    @error('sms_sender_id')<p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="rounded-xl bg-slate-50 px-4 py-3 text-xs font-bold text-slate-600">SMS status: <span class="uppercase text-blue-700">{{ $company->sms_sender_status }}</span></div>

                <div class="flex justify-end"><button class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-extrabold text-white shadow-lg shadow-blue-200 hover:bg-blue-700">Save messaging identity</button></div>
            </form>
            @if($company->resend_domain_id)
                <form id="check-email-domain" method="POST" action="{{ route('organization.messaging.email-domain.check') }}">@csrf</form>
            @endif
        </div>
    </div>
</x-app-layout>
