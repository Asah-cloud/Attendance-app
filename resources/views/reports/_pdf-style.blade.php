{{-- Shared look for the attendance PDFs. DejaVu Sans ships with dompdf and has the ✓ glyph. --}}
<style>
    @page { margin: 26px 28px 40px; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #1e293b; font-size: 9px; }
    .band { background: #1e3a8a; color: #fff; padding: 14px 16px; border-radius: 6px; }
    .band .kicker { font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; color: #bfdbfe; }
    .band h1 { font-size: 17px; margin: 3px 0 2px; }
    .band .meta { font-size: 8.5px; color: #dbeafe; }
    .stats { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 12px -6px 4px; }
    .stats td { border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 10px; background: #f8fafc; }
    .stats .label { font-size: 7.5px; text-transform: uppercase; letter-spacing: .8px; color: #64748b; }
    .stats .value { font-size: 17px; font-weight: bold; color: #0f172a; margin-top: 2px; }
    .stats .good .value { color: #15803d; }
    .stats .bad .value { color: #b91c1c; }
    h2 { font-size: 11px; color: #1e3a8a; margin: 16px 0 6px; padding-bottom: 3px; border-bottom: 1.5px solid #1e3a8a; }
    h2 .count { color: #64748b; font-weight: normal; }
    table.list { width: 100%; border-collapse: collapse; }
    table.list th { background: #1e3a8a; color: #fff; text-align: left; font-size: 7.5px; text-transform: uppercase; letter-spacing: .5px; padding: 5px 6px; }
    table.list td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
    table.list tr:nth-child(even) td { background: #f8fafc; }
    table.list .c { text-align: center; }
    table.list .n { color: #94a3b8; width: 18px; }
    .yes { color: #15803d; font-weight: bold; }
    .time { color: #475569; font-size: 7.5px; font-weight: normal; }
    .no { color: #cbd5e1; }
    .staff { color: #b45309; font-weight: bold; font-size: 7.5px; }
    .bar { height: 6px; background: #e2e8f0; border-radius: 3px; }
    .bar div { height: 6px; background: #16a34a; border-radius: 3px; }
    .split { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px; }
    .split > tbody > tr > td { vertical-align: top; width: 33%; }
    .muted { color: #64748b; }
    .footer { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 7.5px; color: #94a3b8; }
    .footer .page:after { content: "Page " counter(page); }
</style>
