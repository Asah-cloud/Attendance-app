<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meal Tickets &middot; {{ $event->title }}</title>
    <style>
        * { box-sizing: border-box; }
        :root { --ink: #0f172a; --muted: #64748b; --brand: #b45309; --line: #94a3b8; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #e2e8f0; color: var(--ink); }
        .panel { position: sticky; z-index: 10; top: 0; padding: 14px 24px; background: var(--ink); color: white; }
        .panel-head { display: flex; gap: 20px; justify-content: space-between; align-items: center; }
        .panel-head small { display: block; margin-top: 3px; color: #cbd5e1; }
        .print-btn { padding: 10px 18px; border: 0; border-radius: 8px; background: #fbbf24; color: #451a03; font-weight: 800; cursor: pointer; }
        .print-btn:disabled { opacity: .5; cursor: not-allowed; }
        form { display: flex; flex-wrap: wrap; gap: 14px 22px; align-items: flex-end; margin-top: 12px; padding-top: 12px; border-top: 1px solid #334155; font-size: 13px; }
        fieldset { margin: 0; padding: 0; border: 0; display: flex; gap: 8px; align-items: flex-end; }
        legend { padding: 0; margin-bottom: 4px; color: #fbbf24; font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        label { display: flex; flex-direction: column; gap: 3px; color: #cbd5e1; font-size: 11px; }
        label.check { flex-direction: row; align-items: center; gap: 5px; color: white; font-size: 13px; }
        input, select { padding: 6px 8px; border: 1px solid #475569; border-radius: 6px; background: #1e293b; color: white; font: inherit; }
        input[type=checkbox] { width: 16px; height: 16px; }
        .apply-btn { padding: 8px 16px; border: 0; border-radius: 8px; background: white; color: var(--ink); font-weight: 800; cursor: pointer; }
        .problems { margin: 12px 0 0; padding: 10px 14px; border-radius: 8px; background: #fee2e2; color: #991b1b; font-weight: 700; font-size: 13px; }

        main { padding: 10mm; }
        .sheet { max-width: 194mm; margin: 0 auto 8mm; padding: 5mm; background: white; border-radius: 3mm; break-inside: avoid-page; }
        .sheet-head { display: flex; justify-content: space-between; align-items: baseline; gap: 4mm; margin-bottom: 3mm; padding-bottom: 2mm; border-bottom: 1px solid #e2e8f0; }
        .sheet-head strong { font-size: 4.2mm; }
        .sheet-head span { color: var(--muted); font-size: 2.8mm; }
        .tickets { display: grid; grid-template-columns: repeat(3, 1fr); }
        .ticket { min-height: 30mm; padding: 3mm 3.5mm; border: 1px dashed var(--line); margin: -0.5px 0 0 -0.5px; display: flex; flex-direction: column; break-inside: avoid; page-break-inside: avoid; }
        .t-event { color: var(--brand); font-size: 2.4mm; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; overflow-wrap: anywhere; }
        .t-meal { margin-top: 1mm; font-size: 5mm; font-weight: 900; text-transform: uppercase; }
        .t-date { margin-top: .5mm; font-size: 3.2mm; font-weight: 700; }
        .t-time { font-size: 3mm; color: #334155; }
        .t-name { margin-top: auto; padding-top: 1.5mm; font-size: 2.8mm; color: var(--muted); overflow-wrap: anywhere; }
        .empty { max-width: 194mm; margin: 0 auto; padding: 30px; border-radius: 16px; background: white; text-align: center; }

        @page { size: A4 portrait; margin: 8mm; }
        @media print {
            body { background: white; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .panel { display: none; }
            main { padding: 0; }
            .sheet { max-width: none; margin: 0 0 5mm; padding: 0; border-radius: 0; }
        }
    </style>
</head>
<body>
    <div class="panel">
        <div class="panel-head">
            <div>
                <strong>{{ $event->title }} &middot; Meal tickets</strong>
                <small>
                    @if($problems->isEmpty())
                        {{ $registrations->count() }} attendee(s) &times; {{ $slots->count() }} ticket(s) each &mdash; servers tear off and keep the ticket for each meal.
                    @else
                        Fix the settings below to see the tickets.
                    @endif
                </small>
            </div>
            <button type="button" class="print-btn" onclick="window.print()" @disabled($problems->isNotEmpty() || $slots->isEmpty() || $registrations->isEmpty())>Print tickets</button>
        </div>

        <form method="GET" action="{{ route('events.meals.tickets', $event) }}">
            <fieldset>
                <legend>First meal</legend>
                <label>Day<input type="date" name="start_date" value="{{ $settings['start_date'] }}" required></label>
                <label>Meal
                    <select name="start_meal">
                        @foreach($mealTypes as $key => $meal)
                            <option value="{{ $key }}" @selected($settings['start_meal'] === $key)>{{ $meal['label'] }}</option>
                        @endforeach
                    </select>
                </label>
            </fieldset>
            <fieldset>
                <legend>Last meal</legend>
                <label>Day<input type="date" name="end_date" value="{{ $settings['end_date'] }}" required></label>
                <label>Meal
                    <select name="end_meal">
                        @foreach($mealTypes as $key => $meal)
                            <option value="{{ $key }}" @selected($settings['end_meal'] === $key)>{{ $meal['label'] }}</option>
                        @endforeach
                    </select>
                </label>
            </fieldset>
            @foreach($mealTypes as $key => $meal)
                <fieldset>
                    <legend><label class="check"><input type="checkbox" name="meals[]" value="{{ $key }}" @checked(in_array($key, $settings['meals'], true))>{{ $meal['label'] }}</label></legend>
                    <label>From<input type="time" name="{{ $key }}_from" value="{{ $settings[$key.'_from'] }}"></label>
                    <label>To<input type="time" name="{{ $key }}_to" value="{{ $settings[$key.'_to'] }}"></label>
                </fieldset>
            @endforeach
            <button type="submit" class="apply-btn">Update tickets</button>
        </form>

        @if($problems->isNotEmpty())
            <p class="problems">{{ $problems->first() }}</p>
        @endif
    </div>

    <main>
        @if($problems->isEmpty())
            @forelse($registrations as $registration)
                <section class="sheet">
                    <div class="sheet-head">
                        <strong>{{ $registration->participant->name }}</strong>
                        <span>{{ $registration->participant->category ?: 'Attendee' }} &middot; {{ $slots->count() }} meal ticket(s)</span>
                    </div>
                    <div class="tickets">
                        @foreach($slots as $slot)
                            <article class="ticket">
                                <span class="t-event">{{ $event->title }}</span>
                                <span class="t-meal">{{ $slot['label'] }}</span>
                                <span class="t-date">{{ $slot['date']->format('D j M Y') }}</span>
                                @if($slot['from'])
                                    <span class="t-time">{{ $slot['from']->format('g:i A') }}@if($slot['to']) &ndash; {{ $slot['to']->format('g:i A') }}@endif</span>
                                @endif
                                <span class="t-name">{{ $registration->participant->name }}</span>
                            </article>
                        @endforeach
                    </div>
                </section>
            @empty
                <p class="empty">No confirmed attendees yet.</p>
            @endforelse
        @endif
    </main>
</body>
</html>
