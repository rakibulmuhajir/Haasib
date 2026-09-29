{{-- A saved consolidated invoice as a PDF (dompdf). $doc comes from ConsolidatedInvoiceService::document(). --}}
@php
    $qty = fn ($n) => $n === null ? '' : rtrim(rtrim(number_format($n, 2), '0'), '.');
    $issuer = $doc['issuer'];
    $billTo = $doc['bill_to'];
    $billedBy = array_filter($doc['billed_by'] ?? []);
@endphp
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
    .number { text-align: right; font-family: DejaVu Sans Mono, monospace; color: #666; }
    .parties { width: 100%; margin-bottom: 14px; border-top: 1px solid #1c1c1c; border-bottom: 1px solid #ccc; }
    .parties td { padding: 8px 0; vertical-align: top; }
    .label { font-size: 8px; text-transform: uppercase; letter-spacing: .06em; color: #666; }
    table.lines { width: 100%; border-collapse: collapse; }
    table.lines th { text-align: left; font-size: 9px; border-bottom: 1px solid #1c1c1c; padding: 4px 5px; }
    table.lines td { padding: 3px 5px; border-bottom: 1px solid #e3e3e3; vertical-align: top; }
    .num, table.lines th.num { text-align: right; white-space: nowrap; }
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
                @if (!empty($doc['logo_data']))<img src="{{ $doc['logo_data'] }}" style="max-height: 44px; max-width: 160px; margin-bottom: 6px" alt="">@endif
                <div class="issuer-name">{{ $issuer['name'] ?? '' }}</div>
                @foreach (($issuer['lines'] ?? []) as $line)<div class="muted">{{ $line }}</div>@endforeach
                @if (!empty($issuer['phone']))<div class="muted">{{ $issuer['phone'] }}</div>@endif
                @if (!empty($issuer['email']))<div class="muted">{{ $issuer['email'] }}</div>@endif
                @if (!empty($issuer['taxId']))<div class="muted">{{ $issuer['taxIdLabel'] ?? 'NTN' }}: {{ $issuer['taxId'] }}</div>@endif
            </td>
            <td>
                <div class="title">{{ $doc['title'] }}</div>
                <div class="number">{{ $doc['number'] }}</div>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td style="width: 60%">
                <div class="label">Bill to</div>
                <div style="font-weight: bold">{{ $billTo['name'] ?? '' }}</div>
                @if (!empty($billTo['attention']))<div>{{ $billTo['attention'] }}</div>@endif
                @if (!empty($billTo['address']))<div class="muted">{{ $billTo['address'] }}</div>@endif
                @foreach (($billTo['lines'] ?? []) as $line)<div class="muted">{{ $line }}</div>@endforeach
                @if (!empty($billTo['phone']))<div class="muted">{{ $billTo['phone'] }}</div>@endif
            </td>
            <td>
                <div class="label">Date</div><div>{{ $doc['date'] }}</div>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Date</th>
                <th>Coupon no.</th>
                <th>Fuel</th>
                <th class="num">Litres</th>
                <th class="num">Rate</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc['lines'] as $line)
                <tr>
                    <td>{{ $line['date'] }}</td>
                    <td>{{ $line['reference'] ?? '' }}</td>
                    <td>{{ $line['item'] ?? '' }}</td>
                    <td class="num">{{ $qty($line['quantity']) }}</td>
                    <td class="num">{{ $qty($line['rate']) }}</td>
                    <td class="num">{{ number_format($line['amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="total">
        <tr>
            <td style="width: 60%"></td>
            <td class="label" style="text-align: right">Total ({{ $doc['currency'] }})</td>
            <td class="amount">{{ number_format($doc['total'], 2) }}</td>
        </tr>
    </table>

    @if (!empty($billedBy))
        <div class="billed-by">
            <div class="sign">
                <div class="label">Billed by</div>
                @if (!empty($billedBy['name']))<div style="font-weight: bold">{{ $billedBy['name'] }}</div>@endif
                @if (!empty($billedBy['designation']))<div class="muted">{{ $billedBy['designation'] }}</div>@endif
                @if (!empty($billedBy['phone']))<div class="muted">{{ $billedBy['phone'] }}</div>@endif
                @if (!empty($billedBy['address']))<div class="muted">{{ $billedBy['address'] }}</div>@endif
            </div>
        </div>
    @endif
</body>
</html>
