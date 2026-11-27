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
        input[type=number] { width: 80px; }
        .apply-btn { padding: 8px 16px; border: 0; border-radius: 8px; background: white; color: var(--ink); font-weight: 800; cursor: pointer; }
        .problems { margin: 12px 0 0; padding: 10px 14px; border-radius: 8px; background: #fee2e2; color: #991b1b; font-weight: 700; font-size: 13px; }

        main { padding: 10mm 0; }
        /* On screen each page is drawn as a sheet of A4 paper. */
        .sheet { width: 210mm; margin: 0 auto 8mm; padding: 8mm; background: white; box-shadow: 0 2px 10px rgba(15, 23, 42, .15); }
        .page { display: grid; align-content: start; }
        .set { position: relative; outline: .35mm solid var(--ink); display: flex; flex-direction: column; overflow: hidden; break-inside: avoid; }
        .set-head { display: flex; justify-content: space-between; align-items: center; gap: 3mm; padding: 0 3mm; border-bottom: .2mm solid #cbd5e1; background: #f8fafc; }
        .set-head strong { font-size: 3.4mm; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        .set-head span { flex-shrink: 0; color: var(--muted); font-size: 2.6mm; }
        .tickets { display: grid; flex: 1; }
        .ticket { padding: 2.2mm 3mm; display: flex; flex-direction: column; justify-content: center; border-right: .25mm dashed var(--line); border-bottom: .25mm dashed var(--line); overflow: hidden; break-inside: avoid; }
        .ticket.last-col { border-right: 0; }
        .ticket.last-row { border-bottom: 0; }
        .ticket.blank { background: repeating-linear-gradient(135deg, #f8fafc 0 2mm, #f1f5f9 2mm 4mm); }
        .t-event { color: var(--brand); font-size: 2.3mm; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .t-meal { margin-top: .6mm; font-size: 4.6mm; font-weight: 900; text-transform: uppercase; line-height: 1.05; }
        .t-date { margin-top: .5mm; font-size: 3mm; font-weight: 700; }
        .t-time { font-size: 2.8mm; color: #334155; }
        .t-name { margin-top: 1mm; font-size: 2.6mm; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .empty { max-width: 194mm; margin: 0 auto; padding: 30px; border-radius: 16px; background: white; text-align: center; }

        @page { size: A4 portrait; margin: 8mm; }
        @media print {
            body { background: white; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .panel { display: none; }
            main { padding: 0; }
            .sheet { width: auto; margin: 0; padding: 0; box-shadow: none; break-after: page; }
            .sheet:last-child { break-after: auto; }
        }
    </style>
</head>
<body>
    <div class="panel">
        <div class="panel-head">
            <div>
                <strong>{{ $event->title }} &middot; Meal tickets</strong>
                <small>
                    @if($problems->isEmpty() && $layout)
                        {{ $sets->count() }} set(s) &times; {{ $slots->count() }} ticket(s) each
                        @unless($layout['spansPages'])
                            &middot; {{ $layout['perPage'] }} set(s) per page, {{ (int) ceil($sets->count() / $layout['perPage']) }} page(s)
                        @endunless
                        &mdash; cut along the solid lines between sets, then the dashed lines between tickets.
                    @else
                        Fix the settings below to see the tickets.
                    @endif
                </small>
            </div>
            <button type="button" class="print-btn" onclick="window.print()" @disabled($problems->isNotEmpty() || $slots->isEmpty() || $sets->isEmpty())>Print tickets</button>
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
            <fieldset>
                <legend>Names</legend>
                <input type="hidden" name="show_names" value="0">
                <label class="check"><input type="checkbox" name="show_names" value="1" @checked($showNames) onchange="document.getElementById('sets-field').hidden = this.checked">Print attendee names</label>
                <label id="sets-field" @if($showNames) hidden @endif>Number of sets<input type="number" name="sets" min="1" max="2000" value="{{ $settings['sets'] }}"></label>
            </fieldset>
            <button type="submit" class="apply-btn">Update tickets</button>
        </form>

        @if($problems->isNotEmpty())
            <p class="problems">{{ $problems->first() }}</p>
        @endif
    </div>

    <main>
        @if($problems->isEmpty() && $layout)
            @if($sets->isEmpty())
                <p class="empty">No confirmed attendees yet. Untick "Print attendee names" to print unnamed sets.</p>
            @endif
            @foreach($sets->chunk($layout['spansPages'] ? 1 : $layout['perPage']) as $pageSets)
                <div class="sheet">
                    <div class="page" style="grid-template-columns: repeat({{ $layout['across'] }}, {{ round($layout['setWidth'], 2) }}mm); grid-auto-rows: {{ round($layout['setHeight'], 2) }}mm;">
                        @foreach($pageSets as $set)
                            <section class="set">
                                @if($showNames)
                                    <div class="set-head" style="height: {{ $layout['headerHeight'] }}mm;">
                                        <strong>{{ $set['name'] }}</strong>
                                        <span>{{ $set['category'] }}</span>
                                    </div>
                                @endif
                                <div class="tickets" style="grid-template-columns: repeat({{ $layout['columns'] }}, 1fr); grid-template-rows: repeat({{ $layout['rows'] }}, {{ round($layout['ticketHeight'], 2) }}mm);">
                                    @foreach($days as $rowIndex => $day)
                                        @foreach(array_values($day['cells']) as $colIndex => $slot)
                                            @php($edge = ($colIndex === $layout['columns'] - 1 ? ' last-col' : '').($rowIndex === $layout['rows'] - 1 ? ' last-row' : ''))
                                            @if($slot)
                                                <article class="ticket{{ $edge }}">
                                                    <span class="t-event">{{ $event->title }}</span>
                                                    <span class="t-meal">{{ $slot['label'] }}</span>
                                                    <span class="t-date">{{ $slot['date']->format('D j M Y') }}</span>
                                                    @if($slot['from'])
                                                        <span class="t-time">{{ $slot['from']->format('g:i A') }}@if($slot['to']) &ndash; {{ $slot['to']->format('g:i A') }}@endif</span>
                                                    @endif
                                                    @if($showNames)
                                                        <span class="t-name">{{ $set['name'] }}</span>
                                                    @endif
                                                </article>
                                            @else
                                                <div class="ticket blank{{ $edge }}" aria-hidden="true"></div>
                                            @endif
                                        @endforeach
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </main>
</body>
</html>
