<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; color: #17323b; font-size: 12px; }
    .platform-header { display: flex; align-items: center; gap: 14px; margin-bottom: 18px; }
    .platform-header img { width: 48px; height: 48px; object-fit: contain; }
    .platform-name { font-size: 16px; font-weight: bold; color: #071426; }
    .platform-tagline { margin: 2px 0 0; font-size: 10px; color: #64748b; }
    .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #071426; padding-bottom: 16px; margin-bottom: 24px; }
    .bill-to .eyebrow { margin: 0; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; }
    .bill-to .company-name { margin: 3px 0 0; font-size: 16px; font-weight: bold; color: #071426; }
    .bill-to .company-email { margin: 2px 0 0; font-size: 11px; color: #64748b; }
    .invoice-title { text-align: right; }
    .invoice-title h1 { font-size: 22px; margin: 0; color: #071426; }
    .invoice-title p { margin: 4px 0 0; color: #64748b; }
    .meta { width: 100%; margin-bottom: 24px; }
    .meta td { padding: 2px 0; vertical-align: top; }
    .meta .label { color: #64748b; width: 120px; }
    table.items { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    table.items th { text-align: left; background: #f1f5f9; padding: 8px 10px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; }
    table.items td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
    table.items td.amount, table.items th.amount { text-align: right; }
    .totals { width: 260px; margin-left: auto; }
    .totals td { padding: 4px 10px; }
    .totals .label { color: #64748b; }
    .totals .amount { text-align: right; }
    .totals .grand { font-size: 15px; font-weight: bold; color: #071426; border-top: 2px solid #071426; }
    .status { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 10px; text-transform: uppercase; font-weight: bold; }
    .status.paid { background: #d1fae5; color: #065f46; }
    .status.pending { background: #fef3c7; color: #92400e; }
    .footer { margin-top: 32px; font-size: 10px; color: #94a3b8; }
</style>
</head>
<body>
    <div class="platform-header">
        @if($platformLogo)<img src="{{ $platformLogo }}" alt="Asah Apex Attendance">@endif
        <div>
            <div class="platform-name">Asah Apex Attendance</div>
            <p class="platform-tagline">Event attendance, registration &amp; billing platform</p>
        </div>
    </div>

    <div class="header">
        <div class="bill-to">
            <p class="eyebrow">Billed to</p>
            <div class="company-name">{{ $company->name }}</div>
            @if($company->email)<p class="company-email">{{ $company->email }}</p>@endif
        </div>
        <div class="invoice-title">
            <h1>INVOICE</h1>
            <p>{{ $charge->invoice_number }}</p>
        </div>
    </div>

    <table class="meta">
        <tr><td class="label">Event</td><td>{{ $event->title }}</td></tr>
        <tr><td class="label">Event date</td><td>{{ $event->event_date->format('M j, Y') }}{{ $event->end_date ? ' – '.$event->end_date->format('M j, Y') : '' }}</td></tr>
        <tr><td class="label">Invoice date</td><td>{{ $charge->reviewed_at?->format('M j, Y') ?? $charge->finalized_at->format('M j, Y') }}</td></tr>
        <tr><td class="label">Status</td><td><span class="status {{ in_array($charge->status, ['paid', 'reconciled', 'refund_due', 'refunded']) ? 'paid' : 'pending' }}">{{ str_replace('_', ' ', $charge->status) }}</span></td></tr>
    </table>

    <table class="items">
        <thead>
            <tr><th>Description</th><th class="amount">Amount</th></tr>
        </thead>
        <tbody>
            @foreach($charge->tier_breakdown as $band)
                <tr><td>Attendees {{ $band['band_from'] }}–{{ $band['band_to'] ?? '∞' }} ({{ $band['count_in_band'] }} at {{ number_format($band['rate_minor'] / 100, 2) }})</td><td class="amount">{{ number_format($band['subtotal_minor'] / 100, 2) }}</td></tr>
            @endforeach
            @foreach($charge->feature_breakdown ?? [] as $feature)
                <tr><td>{{ $feature['name'] }}</td><td class="amount">{{ number_format($feature['cost_minor'] / 100, 2) }}</td></tr>
            @endforeach
            @if($charge->discount_minor > 0)
                <tr><td>Discount{{ $charge->discount_reason ? ' — '.$charge->discount_reason : '' }}</td><td class="amount">-{{ number_format($charge->discount_minor / 100, 2) }}</td></tr>
            @endif
        </tbody>
    </table>

    <table class="totals">
        <tr class="grand"><td class="label">Total ({{ $charge->currency }})</td><td class="amount">{{ number_format($charge->amount_minor / 100, 2) }}</td></tr>
    </table>

    <div class="footer">
        <p>{{ $charge->registered_count }} confirmed attendee(s) at time of invoicing.</p>
        @if($charge->paid_at)<p>Paid {{ $charge->paid_at->format('M j, Y g:i A') }} · reference {{ $charge->payment_reference }}</p>@endif
        <p>Issued by Asah Apex Attendance{{ config('mail.from.address') ? ' · '.config('mail.from.address') : '' }}</p>
    </div>
</body>
</html>
