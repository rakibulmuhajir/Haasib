{{-- "Print invoice" from a customer statement, as a PDF (dompdf). Same content as StatementInvoicePrint.vue. --}}
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28px 32px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1c1c1c; }
    .head { width: 100%; margin-bottom: 18px; }
    .head td { vertical-align: top; }
    .issuer-name { font-size: 15px; font-weight: bold; }
    .muted { color: #666; }
    .title { font-size: 22px; font-weight: bold; text-align: right; }
    .parties { width: 100%; margin-bottom: 14px; border-top: 1px solid #1c1c1c; border-bottom: 1px solid #ccc; }
    .parties td { padding: 8px 0; vertical-align: top; }
    .label { font-size: 8px; text-transform: uppercase; letter-spacing: .06em; color: #666; }
    table.lines { width: 100%; border-collapse: collapse; }
    table.lines th { text-align: left; font-size: 9px; border-bottom: 1px solid #1c1c1c; padding: 4px 5px; }
    table.lines td { padding: 3px 5px; border-bottom: 1px solid #e3e3e3; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .total { margin-top: 10px; width: 100%; }
    .total td { padding: 4px 5px; }
    .total .amount { font-size: 15px; font-weight: bold; text-align: right; border-top: 2px solid #1c1c1c; }
    .billed-by { margin-top: 48px; width: 240px; }
    .billed-by .sign { border-top: 1px solid #1c1c1c; padding-top: 4px; }
</style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <div class="issuer-name">{{ $issuer['name'] ?? '' }}</div>
                @foreach (($issuer['lines'] ?? []) as $line)<div class="muted">{{ $line }}</div>@endforeach
                @if (!empty($issuer['phone']))<div class="muted">{{ $issuer['phone'] }}</div>@endif
                @if (!empty($issuer['email']))<div class="muted">{{ $issuer['email'] }}</div>@endif
                @if (!empty($issuer['taxId']))<div class="muted">{{ $issuer['taxIdLabel'] ?? 'NTN' }}: {{ $issuer['taxId'] }}</div>@endif
            </td>
            <td class="title">{{ $title }}</td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td style="width: 60%">
                <div class="label">Bill to</div>
                <div style="font-weight: bold">{{ $billTo['name'] ?? '' }}</div>
                @foreach (($billTo['lines'] ?? []) as $line)<div class="muted">{{ $line }}</div>@endforeach
                @if (!empty($billTo['phone']))<div class="muted">{{ $billTo['phone'] }}</div>@endif
            </td>
            <td>
                <div class="label">Period</div><div>{{ $from }} to {{ $to }}</div>
                <div class="label" style="margin-top: 6px">Date</div><div>{{ $today }}</div>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Date</th>
                <th>Invoice</th>
                @if ($showReference)<th>Reference</th>@endif
                <th>Description</th>
                @if ($showQuantity)<th class="num">Qty</th><th class="num">Rate</th>@endif
                @foreach ($columns as $column)<th>{{ $column['label'] }}</th>@endforeach
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['invoice_number'] }}</td>
                    @if ($showReference)<td>{{ $row['reference'] }}</td>@endif
                    <td>{{ $row['description'] }}</td>
                    @if ($showQuantity)
                        <td class="num">{{ $row['quantity'] === null ? '' : rtrim(rtrim(number_format($row['quantity'], 2), '0'), '.') }}</td>
                        <td class="num">{{ $row['rate'] === null ? '' : rtrim(rtrim(number_format($row['rate'], 2), '0'), '.') }}</td>
                    @endif
                    @foreach ($columns as $column)<td>{{ $column['values'][$row['key']] ?? '' }}</td>@endforeach
                    <td class="num">{{ number_format($row['amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="total">
        <tr>
            <td style="width: 60%"></td>
            <td class="label" style="text-align: right">Total ({{ $currency }})</td>
            <td class="amount">{{ number_format($total, 2) }}</td>
        </tr>
    </table>

    @if (!empty($billedBy))
        <div class="billed-by">
            <div class="sign">
                <div class="label">Billed by</div>
                @if (!empty($billedBy['name']))<div style="font-weight: bold">{{ $billedBy['name'] }}</div>@endif
                @if (!empty($billedBy['designation']))<div class="muted">{{ $billedBy['designation'] }}</div>@endif
                @if (!empty($billedBy['phone']))<div class="muted">{{ $billedBy['phone'] }}</div>@endif
            </div>
        </div>
    @endif
</body>
</html>
