<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }} - {{ $company->name }}</title>
    <style>
        @page { margin: 16mm 13mm 15mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #18201d; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.35; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; justify-content: center; gap: 8px; padding: 10px; border-bottom: 1px solid #d8ddd9; background: #fff; }
        .toolbar a, .toolbar button { border: 1px solid #aeb8b1; border-radius: 5px; background: #fff; color: #18201d; cursor: pointer; font: 600 13px DejaVu Sans, sans-serif; padding: 8px 13px; text-decoration: none; }
        .toolbar .primary { border-color: #215f4b; background: #215f4b; color: #fff; }
        .report { max-width: 920px; margin: 0 auto; }
        .masthead { width: 100%; margin-bottom: 13px; border-bottom: 2px solid #215f4b; padding-bottom: 10px; }
        .masthead td { vertical-align: middle; }
        .logo { max-width: 120px; max-height: 42px; }
        .company { margin: 0; font-size: 16px; font-weight: 700; }
        .document-title { margin: 0 0 2px; color: #163f33; font-size: 19px; font-weight: 700; text-align: right; }
        .right { text-align: right; }
        .muted { color: #69736d; }
        .local-note { margin: 4px 0 13px; padding: 7px 9px; border-left: 3px solid #c7953e; background: #faf7ef; color: #554a35; }
        .summary { width: 100%; margin: 0 0 14px; border-collapse: separate; border-spacing: 5px 0; table-layout: fixed; }
        .summary td { border: 1px solid #d8ddd9; border-radius: 4px; background: #f7f9f7; padding: 8px; vertical-align: top; }
        .summary-label { color: #69736d; font-size: 7px; font-weight: 700; letter-spacing: .55px; text-transform: uppercase; }
        .summary-value { margin-top: 2px; font-size: 17px; font-weight: 700; }
        .section-title { margin: 16px 0 7px; color: #163f33; font-size: 12px; font-weight: 700; }
        .overview-grid { width: 100%; border-collapse: separate; border-spacing: 6px 0; table-layout: fixed; }
        .overview-grid > tbody > tr > td { width: 50%; vertical-align: top; }
        table.data { width: 100%; border-collapse: collapse; }
        .data th { border-bottom: 1px solid #9ba69f; background: #edf2ef; color: #344039; font-size: 7px; letter-spacing: .35px; padding: 5px; text-align: left; text-transform: uppercase; }
        .data td { border-bottom: 1px solid #e0e4e1; padding: 5px; vertical-align: top; }
        .data th.number, .data td.number { text-align: right; }
        .date-heading { margin: 17px 0 7px; border-bottom: 1px solid #9ba69f; padding-bottom: 4px; color: #163f33; font-size: 12px; font-weight: 700; page-break-after: avoid; }
        .date-count { float: right; color: #69736d; font-size: 8px; font-weight: 400; }
        .event-card { margin: 0 0 10px; border: 1px solid #cfd6d1; border-radius: 5px; page-break-inside: avoid; }
        .event-head { width: 100%; border-collapse: collapse; background: #f2f6f3; page-break-inside: avoid; }
        .event-head td { padding: 7px 8px; vertical-align: middle; }
        .event-time { width: 72px; color: #163f33; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .event-type { color: #69736d; font-size: 7px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; }
        .event-route { margin-top: 1px; font-size: 11px; font-weight: 700; }
        .pax { font-size: 11px; font-weight: 700; text-align: right; white-space: nowrap; }
        .status { display: inline-block; margin-top: 2px; border-radius: 8px; padding: 2px 6px; font-size: 7px; font-weight: 700; text-transform: uppercase; }
        .status-ready { background: #dcefe6; color: #15563e; }
        .status-needs_attention { background: #f9e4df; color: #963b2d; }
        .status-self_arranged { background: #e7eaed; color: #48515a; }
        .event-meta { width: 100%; border-collapse: collapse; table-layout: fixed; page-break-inside: avoid; }
        .event-meta td { width: 25%; padding: 6px 8px; border-top: 1px solid #e0e4e1; vertical-align: top; }
        .label { display: block; margin-bottom: 1px; color: #778079; font-size: 7px; font-weight: 700; letter-spacing: .3px; text-transform: uppercase; }
        .issues { margin: 0; border-top: 1px solid #efd0c8; background: #fff7f4; color: #7d3026; padding: 6px 8px; page-break-inside: avoid; }
        .manifest { width: 100%; border-collapse: collapse; }
        .manifest thead { display: table-header-group; }
        .manifest th { border-top: 1px solid #cfd6d1; border-bottom: 1px solid #cfd6d1; background: #fafbfa; color: #69736d; font-size: 7px; letter-spacing: .35px; padding: 4px 8px; text-align: left; text-transform: uppercase; }
        .manifest td { border-bottom: 1px solid #edf0ee; padding: 4px 8px; }
        .manifest tr:last-child td { border-bottom: 0; }
        .empty { margin-top: 18px; border: 1px dashed #aeb8b1; padding: 24px; color: #69736d; text-align: center; }
        .footer { position: fixed; right: 0; bottom: -9mm; left: 0; color: #7a837d; font-size: 7px; text-align: center; }
        .page-number:after { content: counter(page); }
        .preview { background: #eef1ef; }
        .preview .report { margin: 24px auto; padding: 34px 38px; background: #fff; box-shadow: 0 8px 30px rgba(24, 32, 29, .12); }
        .preview .footer { display: none; }
        @media print {
            .toolbar { display: none; }
            .preview .report { max-width: none; margin: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body class="{{ $forPdf ? 'pdf' : 'preview' }}">
@php
    $plain = static fn ($value) => str_replace(['→', '—'], ['to', '-'], (string) $value);
    $eventGroups = collect($report['events'])->groupBy('scheduled_date');
    $pdfUrl = route('umrah.operations.report.pdf', ['company' => $company->slug])
        . (request()->getQueryString() ? '?'.request()->getQueryString() : '');
@endphp

@unless($forPdf)
    <div class="toolbar">
        <button class="primary" type="button" onclick="window.print()">Print report</button>
        <a href="{{ $pdfUrl }}">Download PDF</a>
        <a href="{{ route('umrah.operations.report.csv', ['company' => $company->slug, ...$report['filters']]) }}">Export CSV</a>
    </div>
@endunless

<main class="report">
    <table class="masthead">
        <tr>
            <td>
                @if($logoSource)
                    <img class="logo" src="{{ $logoSource }}" alt="">
                @else
                    <p class="company">{{ $company->name }}</p>
                @endif
                @if($logoSource)<div class="muted">{{ $company->name }}</div>@endif
            </td>
            <td class="right">
                <h1 class="document-title">{{ $report['title'] }}</h1>
                <div>{{ $plain($report['period_label']) }}</div>
                <div class="muted">Generated {{ $report['generated_at'] }}</div>
            </td>
        </tr>
    </table>

    <div class="local-note">
        Times are printed exactly as scheduled in each event's local clock. No viewer-timezone conversion is applied.
    </div>

    @foreach(array_chunk($report['summary'], 4) as $summaryRow)
        <table class="summary"><tr>
            @foreach($summaryRow as $item)
                <td>
                    <div class="summary-label">{{ $plain($item['label']) }}</div>
                    <div class="summary-value">{{ number_format($item['value']) }}</div>
                    <div class="muted">{{ $item['key'] === 'needs_attention' ? 'events' : 'people scheduled' }}</div>
                </td>
            @endforeach
            @for($index = count($summaryRow); $index < 4; $index++)<td></td>@endfor
        </tr></table>
    @endforeach

    @if($report['shows_details'] && (count($report['agent_summary']) || count($report['group_summary'])))
        <h2 class="section-title">Movement overview</h2>
        <table class="overview-grid"><tr>
            <td>
                <table class="data">
                    <thead><tr><th>Agent</th><th class="number">Events</th><th class="number">Pax movements</th></tr></thead>
                    <tbody>
                    @foreach($report['agent_summary'] as $row)
                        <tr><td>{{ $row['label'] }}</td><td class="number">{{ $row['event_count'] }}</td><td class="number">{{ $row['passenger_movements'] }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </td>
            <td>
                <table class="data">
                    <thead><tr><th>Group</th><th class="number">Events</th><th class="number">Pax movements</th></tr></thead>
                    <tbody>
                    @foreach($report['group_summary'] as $row)
                        <tr><td>{{ $row['label'] }}</td><td class="number">{{ $row['event_count'] }}</td><td class="number">{{ $row['passenger_movements'] }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </td>
        </tr></table>
    @endif

    @if($report['shows_details'])
        @forelse($eventGroups as $date => $events)
            <h2 class="date-heading">
                {{ \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $date)->format('l, d M Y') }}
                <span class="date-count">{{ $events->count() }} {{ $events->count() === 1 ? 'event' : 'events' }}</span>
            </h2>

            @foreach($events as $event)
                @php
                    $time = $event['is_all_day'] || ! $event['scheduled_time']
                        ? match ($event['type']) {
                            'airport_arrival' => 'Arrival time missing',
                            'airport_departure' => 'Departure time missing',
                            'city_transfer', 'transport_pickup' => 'Pickup time missing',
                            'hotel_check_in', 'hotel_check_out' => 'Time not specified',
                            default => 'Time not set',
                        }
                        : \Illuminate\Support\Carbon::createFromFormat('H:i', $event['scheduled_time'])->format('g:i A');
                    $route = $event['origin'] && $event['destination']
                        ? $plain($event['origin']).' to '.$plain($event['destination'])
                        : $plain($event['location'] ?? 'Location not set');
                    $transport = $event['transport'] ?? null;
                @endphp
                <section class="event-card">
                    <table class="event-head"><tr>
                        <td class="event-time">{{ $time }}</td>
                        <td>
                            <div class="event-type">{{ $event['type_label'] }}</div>
                            <div class="event-route">{{ $route }}</div>
                        </td>
                        <td class="pax">
                            {{ number_format($event['passenger_count']) }} pax<br>
                            <span class="status status-{{ $event['readiness'] }}">{{ $event['readiness_label'] }}</span>
                        </td>
                    </tr></table>

                    <table class="event-meta"><tr>
                        <td><span class="label">Movement</span>{{ $plain($event['headline']) }}</td>
                        <td><span class="label">Flight / airport</span>{{ $event['flight'] ?: '-' }}{{ $event['airport'] ? ' / '.$event['airport'] : '' }}</td>
                        <td><span class="label">Hotel / city</span>{{ $event['hotel'] ?: ($event['city'] ?: '-') }}</td>
                        <td><span class="label">Reference</span>{{ $event['voucher']['number'] ?? '-' }}{{ !empty($event['group']['number']) ? ' / '.$event['group']['number'] : '' }}</td>
                    </tr><tr>
                        <td><span class="label">Agent</span>{{ $event['agent']['name'] ?? '-' }}</td>
                        <td><span class="label">Vehicle</span>{{ $transport['vehicle'] ?? '-' }}{{ !empty($transport['quantity']) && $transport['quantity'] > 1 ? ' x '.$transport['quantity'] : '' }}</td>
                        <td><span class="label">Driver</span>{{ $transport['driver'] ?? '-' }}{{ !empty($transport['driver_phone']) ? ' / '.$transport['driver_phone'] : '' }}</td>
                        <td><span class="label">Terminal / capacity</span>{{ $transport['terminal'] ?? '-' }}{{ isset($transport['capacity']) ? ' / '.$transport['capacity'].' seats' : '' }}</td>
                    </tr></table>

                    @if(count($event['readiness_issues']))
                        <p class="issues"><strong>Needs attention:</strong> {{ implode('; ', $event['readiness_issues']) }}</p>
                    @endif

                    @if(count($event['passengers'] ?? []))
                        <table class="manifest">
                            <thead><tr><th style="width:7%">#</th><th>Passenger</th><th style="width:25%">Passport</th><th style="width:22%">Nationality</th></tr></thead>
                            <tbody>
                            @foreach($event['passengers'] as $index => $passenger)
                                <tr><td>{{ $index + 1 }}</td><td>{{ $passenger['name'] }}</td><td>{{ $passenger['passport'] ?: '-' }}</td><td>{{ $passenger['nationality'] ?: '-' }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </section>
            @endforeach
        @empty
            <div class="empty">No events match the selected report filters.</div>
        @endforelse
    @else
        <div class="empty">This role receives movement totals only. Operational manifests remain restricted to authorized staff.</div>
    @endif
</main>

<div class="footer">{{ $company->name }} - Movement Report - Page <span class="page-number"></span></div>
</body>
</html>
