<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $voucher->voucher_number }}</title>
    <style>
        @page { margin: 22px; }
        body { color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 9px; margin: 0; }
        h1, h2, p { margin: 0; }
        .header { width: 100%; margin-bottom: 8px; }
        .header td { border: 0; vertical-align: top; }
        .company { color: #12358f; font-size: 18px; font-weight: bold; }
        .logo { max-height: 62px; max-width: 120px; }
        .title { font-size: 17px; font-weight: bold; text-align: center; }
        .meta { border: 1px solid #111827; margin: 6px 0; padding: 6px; }
        .section { background: #d1d5db; border: 1px solid #111827; font-size: 11px; font-weight: bold; padding: 3px; text-align: center; }
        table.grid { border-collapse: collapse; width: 100%; }
        .grid th { background: #f3f4f6; font-weight: bold; }
        .grid th, .grid td { border: 1px solid #111827; padding: 4px; vertical-align: top; }
        .compact td { padding: 3px; }
        .two { width: 100%; }
        .two td { vertical-align: top; width: 50%; }
        .muted { color: #4b5563; }
        .notes { min-height: 34px; padding: 5px; }
        .draft { color: rgba(22, 163, 74, .18); font-size: 64px; font-weight: bold; left: 185px; position: fixed; top: 325px; transform: rotate(-35deg); }
    </style>
</head>
<body>
@if($voucher->status === 'draft')<div class="draft">DRAFT</div>@endif
<table class="header">
    <tr>
        <td style="width:40%"><div class="company">{{ $company->trade_name ?: $company->name }}</div><div>Voucher Date: {{ $voucher->created_at?->format('d/m/y') }}</div></td>
        <td style="width:20%; text-align:center">@if($company->logo_url)<img class="logo" src="{{ $company->logo_url }}">@endif</td>
        <td style="width:40%; text-align:right"><strong>{{ $voucher->agent?->name }}</strong><br>{{ $voucher->agent?->city }} {{ $voucher->agent?->country }}<br>{{ $voucher->agent?->phone }}</td>
    </tr>
</table>
<div class="title">Travel Voucher</div>
<table class="grid compact" style="margin-top:4px">
    <tr><td><strong>Group:</strong> {{ $voucher->group?->group_number }} - {{ $voucher->group?->name }}</td><td><strong>Voucher:</strong> {{ $voucher->voucher_number }}</td><td><strong>PAX:</strong> {{ $voucher->passengers->count() }}</td><td><strong>Status:</strong> {{ strtoupper($voucher->status) }}</td></tr>
</table>

<div class="section">Mutamers</div>
<table class="grid compact">
    <thead><tr><th>#</th><th>Passport</th><th>Mutamer Name</th><th>Nationality</th><th>Service</th><th>Visa Status</th></tr></thead>
    <tbody>@foreach($voucher->passengers as $passenger)<tr><td>{{ $loop->iteration }}</td><td>{{ $passenger->passport_number }}</td><td>{{ $passenger->full_name }}</td><td>{{ $passenger->nationality }}</td><td>{{ $passenger->service_type === 'transport_only' ? 'Transport only' : 'Visa + transport' }}</td><td>{{ ucfirst($passenger->visa_status) }}</td></tr>@endforeach</tbody>
</table>

<div class="section">Accommodation</div>
<table class="grid compact">
    <thead><tr><th>City</th><th>Hotel Name</th><th>Source</th><th>Room Type</th><th>Qty</th><th>Check-in</th><th>Checkout</th><th>Nights</th></tr></thead>
    <tbody>@foreach($voucher->hotel_stays ?? [] as $stay)<tr><td>{{ $stay['city'] ?? '' }}</td><td>{{ $stay['hotel_name'] ?? '' }}</td><td>{{ ($stay['source'] ?? '') === 'company' ? 'Company' : 'Self' }}</td><td>{{ ucfirst($stay['room_type'] ?? '') }}</td><td>{{ $stay['room_count'] ?? 1 }}</td><td>{{ \Illuminate\Support\Carbon::parse($stay['check_in_date'])->format('d-m-y') }}</td><td>{{ \Illuminate\Support\Carbon::parse($stay['check_out_date'])->format('d-m-y') }}</td><td>{{ $stay['night_count'] ?? 0 }}</td></tr>@endforeach</tbody>
</table>

@if($voucher->service_bundle !== 'hotel')
<div class="section">Transport / Services</div>
<table class="grid compact">
    <thead><tr><th>Travel Date</th><th>Transport</th><th>Sector</th><th>Driver</th></tr></thead>
    <tbody>
    @forelse($voucher->group?->transportItems ?? [] as $item)<tr><td>{{ $item->scheduled_at?->format('d-m-y H:i') }}</td><td>{{ $item->service?->name ?: $item->service?->vehicle_type }}</td><td>{{ $item->sector?->name ?: $item->description }}</td><td>{{ $item->driver?->name ?: $item->service?->driver_name }}</td></tr>@empty<tr><td></td><td>{{ $voucher->group?->transportService?->name ?: 'Mandatory bus transport' }}</td><td>Complete Umrah journey</td><td>{{ $voucher->group?->driver?->name }}</td></tr>@endforelse
    </tbody>
</table>

<table class="two" cellspacing="5" style="margin-top:5px"><tr>
    <td><div class="section">Departure</div><table class="grid compact"><tr><th>Flight</th><th>Sector</th><th>Takeoff</th><th>Landing</th></tr><tr><td>{{ $voucher->onward_airline }}{{ $voucher->onward_flight_number }}</td><td>{{ $voucher->onward_departure_city }}-{{ $voucher->onward_arrival_city }}</td><td>{{ $voucher->onward_departure_at?->format('d-M H:i') }}</td><td>{{ $voucher->onward_arrival_at?->format('d-M H:i') }}</td></tr></table></td>
    <td><div class="section">Return</div><table class="grid compact"><tr><th>Flight</th><th>Sector</th><th>Takeoff</th><th>Landing</th></tr><tr><td>{{ $voucher->return_airline }}{{ $voucher->return_flight_number }}</td><td>{{ $voucher->return_departure_city }}-{{ $voucher->return_arrival_city }}</td><td>{{ $voucher->return_departure_at?->format('d-M H:i') }}</td><td>{{ $voucher->return_arrival_at?->format('d-M H:i') }}</td></tr></table></td>
</tr></table>
@endif

<div class="section" style="margin-top:5px">Special Instructions</div>
<div class="notes">{{ $voucher->notes }}</div>
</body>
</html>
