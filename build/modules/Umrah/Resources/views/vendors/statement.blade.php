<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $vendor->name }} Statement</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 10px; }
        h1 { margin: 0 0 4px; font-size: 18px; }
        .muted { color: #6b7280; }
        .summary { width: 100%; margin: 18px 0; border-collapse: collapse; }
        .summary td { width: 25%; padding: 8px; border: 1px solid #d1d5db; }
        table.records { width: 100%; border-collapse: collapse; }
        .records th, .records td { padding: 6px; border-bottom: 1px solid #d1d5db; text-align: left; }
        .records th { background: #f3f4f6; }
        .number { text-align: right !important; white-space: nowrap; }
    </style>
</head>
<body>
    @php
        $logoSource = $company->logo_url && str_starts_with($company->logo_url, 'http')
            ? $company->logo_url
            : ($company->logo_url ? public_path(ltrim($company->logo_url, '/')) : null);
    @endphp
    <div style="text-align:center">
        @if($logoSource)<img src="{{ $logoSource }}" style="max-height:55px;max-width:150px" alt="">@endif
        <h1>{{ $company->name }}</h1>
        <div>{{ $vendor->name }} Supplier Statement</div>
        <div class="muted">{{ $filters['date_from'] ?? 'Beginning' }} to {{ $filters['date_to'] ?? 'Present' }}</div>
    </div>
    <table class="summary"><tr>
        <td>Opening<br><strong>{{ number_format($statement['opening_balance'], 2) }} {{ $company->base_currency }}</strong></td>
        <td>Costs<br><strong>{{ number_format($statement['charges'], 2) }} {{ $company->base_currency }}</strong></td>
        <td>Payments<br><strong>{{ number_format($statement['payments'], 2) }} {{ $company->base_currency }}</strong></td>
        <td>Closing Payable<br><strong>{{ number_format($statement['closing_balance'], 2) }} {{ $company->base_currency }}</strong></td>
    </tr></table>
    <table class="records">
        <thead><tr><th>Date</th><th>Reference</th><th>Description</th><th class="number">Cost</th><th class="number">Paid</th><th class="number">Allocated</th><th class="number">Advance</th><th class="number">Balance</th></tr></thead>
        <tbody>
        @forelse($statement['rows'] as $row)
            <tr><td>{{ $row['date'] }}</td><td>{{ $row['reference'] }}</td><td>{{ $row['description'] }}</td><td class="number">{{ number_format($row['charge'], 2) }}</td><td class="number">{{ number_format($row['payment'], 2) }}</td><td class="number">{{ number_format($row['allocated'], 2) }}</td><td class="number">{{ number_format($row['advance'], 2) }}</td><td class="number">{{ number_format($row['balance'], 2) }}</td></tr>
        @empty
            <tr><td colspan="8">No supplier activity in this period.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
