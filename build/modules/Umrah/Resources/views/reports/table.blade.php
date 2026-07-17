<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page { margin: 24px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 8px; }
        h1 { margin: 3px 0; font-size: 16px; }
        .muted { color: #6b7280; }
        .header { text-align: center; margin-bottom: 12px; }
        .summary { width: 100%; margin: 10px 0 14px; border-collapse: collapse; }
        .summary td { padding: 6px; border: 1px solid #d1d5db; }
        table.records { width: 100%; border-collapse: collapse; table-layout: auto; }
        .records th, .records td { padding: 4px; border-bottom: 1px solid #d1d5db; vertical-align: top; }
        .records th { background: #f3f4f6; text-align: left; white-space: nowrap; }
        .number { text-align: right; white-space: nowrap; }
        .page-number:after { content: counter(page); }
        .footer { position: fixed; bottom: -12px; right: 0; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        @if($logoSource)<img src="{{ $logoSource }}" style="max-height:48px;max-width:140px" alt="">@endif
        <h1>{{ $company->name }}</h1>
        <strong>{{ $report['title'] }}</strong>
        <div class="muted">{{ $report['filters']['start'] }} to {{ $report['filters']['end'] }} | Date basis: {{ $report['date_basis'] }}</div>
    </div>
    @if(count($report['summary']))
        <table class="summary"><tr>
            @foreach($report['summary'] as $item)
                <td>{{ $item['label'] }}<br><strong>{{ $item['type'] === 'money' ? number_format((float) $item['value'], 2).' '.$company->base_currency : number_format((float) $item['value']) }}</strong></td>
            @endforeach
        </tr></table>
    @endif
    <table class="records">
        <thead><tr>@foreach($report['columns'] as $column)<th class="{{ in_array($column['type'], ['money', 'number'], true) ? 'number' : '' }}">{{ $column['label'] }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse($report['rows'] as $row)
            <tr>
                @foreach($report['columns'] as $column)
                    @php($value = $row[$column['key']] ?? null)
                    <td class="{{ in_array($column['type'], ['money', 'number'], true) ? 'number' : '' }}">
                        @if($column['type'] === 'money')
                            {{ number_format((float) $value, 2) }}
                        @elseif($column['type'] === 'number' && is_numeric($value))
                            {{ number_format((float) $value, 2) }}
                        @elseif($column['type'] === 'datetime' && $value)
                            {{ \Illuminate\Support\Carbon::parse($value)->format('d M Y H:i') }}
                        @elseif($column['type'] === 'date' && $value)
                            {{ \Illuminate\Support\Carbon::parse($value)->format('d M Y') }}
                        @else
                            {{ is_array($value) ? implode(', ', $value) : ($value ?? '-') }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($report['columns']) }}">No records found for this period.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="footer">Page <span class="page-number"></span></div>
</body>
</html>
