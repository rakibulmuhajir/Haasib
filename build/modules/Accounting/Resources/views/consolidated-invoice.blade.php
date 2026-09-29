{{--
    A saved consolidated invoice as a PDF (dompdf). $doc comes from ConsolidatedInvoiceService::document().
    Same faces and grammar as the on-screen LedgerDocument: Public Sans for text, Zilla Slab for the
    document's name and the amount owed, IBM Plex Mono for numbers and labels. dompdf reads TrueType
    only, so the app's woff2 faces are kept as .ttf in resources/fonts/pdf.
--}}
@php
    $qty = fn ($n) => $n === null ? '' : rtrim(rtrim(number_format($n, 2), '0'), '.');
    $font = fn (string $file) => str_replace('\\', '/', resource_path("fonts/pdf/{$file}.ttf"));
    $issuer = $doc['issuer'];
    $billTo = $doc['bill_to'];
    $billedBy = array_filter($doc['billed_by'] ?? []);
    $label = $doc['labels'];
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @font-face { font-family: 'Public Sans'; font-weight: 400; src: url('{{ $font('public-sans-latin-400-normal') }}') format('truetype'); }
    @font-face { font-family: 'Public Sans'; font-weight: 600; src: url('{{ $font('public-sans-latin-600-normal') }}') format('truetype'); }
    @font-face { font-family: 'Public Sans'; font-weight: 700; src: url('{{ $font('public-sans-latin-700-normal') }}') format('truetype'); }
    @font-face { font-family: 'Zilla Slab'; font-weight: 600; src: url('{{ $font('zilla-slab-latin-600-normal') }}') format('truetype'); }
    @font-face { font-family: 'Zilla Slab'; font-weight: 700; src: url('{{ $font('zilla-slab-latin-700-normal') }}') format('truetype'); }
    @font-face { font-family: 'IBM Plex Mono'; font-weight: 400; src: url('{{ $font('ibm-plex-mono-latin-400-normal') }}') format('truetype'); }
    @font-face { font-family: 'IBM Plex Mono'; font-weight: 500; src: url('{{ $font('ibm-plex-mono-latin-500-normal') }}') format('truetype'); }

    @page { margin: 36px 40px; }
    body { font-family: 'Public Sans', DejaVu Sans, sans-serif; font-size: 11px; color: #1c1c1c; }
    .mono { font-family: 'IBM Plex Mono', DejaVu Sans Mono, monospace; }

    .letterhead { width: 100%; border-bottom: 1.5px solid #1c1c1c; padding-bottom: 18px; }
    .letterhead td { vertical-align: top; }
    .issuer-name { font-family: 'Zilla Slab', serif; font-size: 17px; font-weight: 700; }
    .issuer-line { font-size: 11px; line-height: 1.5; color: #555; }
    .masthead { text-align: right; }
    .doc-type { font-family: 'Zilla Slab', serif; font-size: 28px; font-weight: 700; text-transform: uppercase; letter-spacing: -0.02em; line-height: 1; }
    .doc-number { margin-top: 6px; font-family: 'IBM Plex Mono', monospace; font-size: 12px; font-weight: 500; color: #555; }
    .date-label { margin-top: 12px; font-family: 'IBM Plex Mono', monospace; font-size: 8.5px; letter-spacing: 0.1em; text-transform: uppercase; color: #777; }
    .date-value { font-family: 'IBM Plex Mono', monospace; font-size: 11px; }

    .parties { width: 100%; border-bottom: 1px solid #ddd; }
    .parties td { vertical-align: top; padding: 18px 0; }
    .label { font-family: 'IBM Plex Mono', monospace; font-size: 8.5px; font-weight: 500; letter-spacing: 0.1em; text-transform: uppercase; color: #777; margin-bottom: 5px; }
    .party-name { font-size: 13px; font-weight: 600; }
    .party-line { font-size: 11px; line-height: 1.5; color: #555; }

    table.lines { width: 100%; border-collapse: collapse; margin-top: 18px; }
    table.lines th { text-align: left; font-family: 'IBM Plex Mono', monospace; font-size: 8.5px; font-weight: 500; letter-spacing: 0.08em; text-transform: uppercase; color: #777; border-bottom: 1px solid #1c1c1c; padding: 5px 6px; }
    table.lines td { padding: 5px 6px; border-bottom: 1px solid #e6e6e6; vertical-align: top; }
    .num, table.lines th.num { text-align: right; white-space: nowrap; }
    td.num { font-family: 'IBM Plex Mono', monospace; }

    .reckoning { width: 100%; margin-top: 14px; }
    .reckoning td { padding: 3px 6px; }
    .grand-label { text-align: right; font-family: 'IBM Plex Mono', monospace; font-size: 9px; letter-spacing: 0.1em; text-transform: uppercase; color: #777; }
    .grand-amount { text-align: right; font-family: 'Zilla Slab', serif; font-size: 20px; font-weight: 700; border-top: 3px double #1c1c1c; padding-top: 6px; }

    .billed-by { margin-top: 44px; width: 240px; border-top: 1px solid #1c1c1c; padding-top: 5px; }
</style>
</head>
<body>
    <table class="letterhead">
        <tr>
            <td>
                @if (!empty($doc['logo_data']))<img src="{{ $doc['logo_data'] }}" style="max-height: 44px; max-width: 180px; margin-bottom: 8px" alt="">@endif
                <div class="issuer-name">{{ $issuer['name'] ?? '' }}</div>
                @foreach (($issuer['lines'] ?? []) as $line)<div class="issuer-line">{{ $line }}</div>@endforeach
                @if (!empty($issuer['phone']))<div class="issuer-line">{{ $issuer['phone'] }}</div>@endif
                @if (!empty($issuer['email']))<div class="issuer-line">{{ $issuer['email'] }}</div>@endif
                @if (!empty($issuer['taxId']))<div class="issuer-line mono">{{ $issuer['taxIdLabel'] ?? 'NTN' }} {{ $issuer['taxId'] }}</div>@endif
            </td>
            <td class="masthead">
                <div class="doc-type">{{ $doc['title'] }}</div>
                <div class="doc-number">{{ $doc['number'] }}</div>
                <div class="date-label">Date</div>
                <div class="date-value">{{ $doc['date'] }}</div>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="label">Bill to</div>
                <div class="party-name">{{ $billTo['name'] ?? '' }}</div>
                @if (!empty($billTo['attention']))<div class="party-line">{{ $billTo['attention'] }}</div>@endif
                @if (!empty($billTo['address']))<div class="party-line">{{ $billTo['address'] }}</div>@endif
                @foreach (($billTo['lines'] ?? []) as $line)<div class="party-line">{{ $line }}</div>@endforeach
                @if (!empty($billTo['phone']))<div class="party-line">{{ $billTo['phone'] }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ $label['date'] }}</th>
                @if ($doc['show_reference'])<th>{{ $label['reference'] }}</th>@endif
                @if ($doc['show_physical'])<th>{{ $label['physical'] }}</th>@endif
                <th>{{ $label['item'] }}</th>
                <th class="num">{{ $label['quantity'] }}</th>
                <th class="num">{{ $label['rate'] }}</th>
                <th class="num">{{ $label['amount'] }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc['lines'] as $line)
                <tr>
                    <td class="mono">{{ $line['date'] }}</td>
                    @if ($doc['show_reference'])<td>{{ $line['reference'] ?? '' }}</td>@endif
                    @if ($doc['show_physical'])<td>{{ $line['physical_invoice'] ?? '' }}</td>@endif
                    <td>{{ $line['item'] ?? '' }}</td>
                    <td class="num">{{ $qty($line['quantity']) }}</td>
                    <td class="num">{{ $qty($line['rate']) }}</td>
                    <td class="num">{{ number_format($line['amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="reckoning">
        <tr>
            <td style="width: 55%"></td>
            <td class="grand-label">Total · {{ $doc['currency'] }}</td>
            <td class="grand-amount">{{ number_format($doc['total'], 2) }}</td>
        </tr>
    </table>

    @if (!empty($billedBy))
        <div class="billed-by">
            <div class="label">Billed by</div>
            @if (!empty($billedBy['name']))<div class="party-name">{{ $billedBy['name'] }}</div>@endif
            @if (!empty($billedBy['designation']))<div class="party-line">{{ $billedBy['designation'] }}</div>@endif
            @if (!empty($billedBy['phone']))<div class="party-line">{{ $billedBy['phone'] }}</div>@endif
            @if (!empty($billedBy['address']))<div class="party-line">{{ $billedBy['address'] }}</div>@endif
        </div>
    @endif
</body>
</html>
