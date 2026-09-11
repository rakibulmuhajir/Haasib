<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $voucher->voucher_number }}</title>
    <style>
@include('print.sheet')
        @page { size: A4 portrait; margin: 9mm 10mm; }
        body { font-size: 10px; line-height: 1.25; }
        .masthead td { width: 25%; text-align: center !important; }
        .party-logo { height: 35px; max-width: 100px; margin: 0 auto 3px; }
        .party-name { font-size: 10px; }
        .document-title { font-size: 15px; padding: 4px; }
        .metadata { font-size: 8px; margin: 4px 0 8px; }
        .section { text-align: left; padding: 3px 5px; margin: 6px 0 0; }
        .grid td { padding: 3px 5px; overflow-wrap: anywhere; }
        .grid th { padding: 4px 5px; font-size: 8px; letter-spacing: 0; }
        .grid { table-layout: fixed; }
        .grid .nights { width: 7%; text-align: center; }
        .grid .map { width: 11%; text-align: center; }
        .map img { width: 52px; height: 52px; }
        .flights { table-layout: fixed; }
        .flights > tbody > tr > td { width: 50%; vertical-align: top; padding: 0; }
        .flights > tbody > tr > td:first-child { padding-right: 4px; }
        .flights > tbody > tr > td:last-child { padding-left: 4px; }
        .flights .grid { font-size: 8px; }
        .flights .grid th { font-size: 7px; }
        .footer-note { white-space: pre-wrap; overflow-wrap: anywhere; }
        tr { page-break-inside: avoid; }
        thead { display: table-header-group; }
        tfoot { display: table-row-group; }
        h3 { page-break-after: avoid; }
        @media screen { body { max-width: 190mm; padding: 9mm 10mm; margin: auto; background: white; } }
        @media print { body { padding: 0; margin: 0; max-width: none; } }
    </style>
</head>
<body data-voucher-print="{{ $voucher->id }}">
@php
    $totalNights = collect($voucher->hotel_stays ?? [])->sum(fn ($stay) => (int) ($stay['night_count'] ?? 0));
    $contacts = $voucher->print_details['contacts'] ?? [];
    $footerText = $voucher->print_details['footer_text'] ?? '';
    $ageDate = \Illuminate\Support\Carbon::parse($voucher->onward_departure_at ?? $voucher->group?->travel_date ?? $voucher->created_at);
    $hasTransport = in_array($voucher->service_bundle, ['transport', 'transport_hotel', 'visa_transport', 'visa_transport_hotel'], true);
@endphp
@if($voucher->status === 'draft')<div class="watermark draft">DRAFT</div>@endif
@if($voucher->status === 'cancelled')<div class="watermark cancelled">CANCELLED</div>@endif
@if($voucher->superseded_at)<div class="watermark draft">SUPERSEDED</div>@endif
<table class="masthead"><tr>
@foreach($parties as $party)
    <td>
        @if($party['logo'])<img class="party-logo" src="{{ $party['logo'] }}" alt="{{ $party['role'] }} logo">@endif
        <div class="party-name">{{ $party['name'] ?: '—' }}</div>
        <div class="secondary">{{ $party['role'] }}</div>
    </td>
@endforeach
</tr></table>
<div class="document-title">Journey Voucher · {{ $voucher->voucher_number }}</div>
<div class="metadata">
    <strong>{{ $voucher->title }}</strong> · {{ str($voucher->service_bundle)->replace('_', ' ')->title() }} · {{ strtoupper($voucher->status) }}<br>
    Group leader: {{ $voucher->passengers->firstWhere('id', $voucher->leader_passenger_id)?->full_name ?: 'Not selected' }}
    · Agent: {{ $voucher->agent?->name ?: '—' }}
    · PAX: {{ $voucher->passengers->count() }}
    · Group: {{ $voucher->group?->group_number }}
    · Issued: {{ $voucher->created_at?->format('d M Y') }}
    · Version: {{ $voucher->version_number ?: 1 }}
</div>
@if($voucher->cancellation_reason)<div class="footer-note">Cancellation reason: {{ $voucher->cancellation_reason }}</div>@endif

<h3 class="section">Passengers</h3>
<table class="grid">
    <thead><tr><th style="width:5%">#</th><th style="width:50%">Name</th><th style="width:30%">Passport</th><th style="width:15%">Age</th></tr></thead>
    <tbody>
    @forelse($voucher->passengers as $passenger)
        <tr><td>{{ $loop->iteration }}</td><td class="primary">{{ $passenger->full_name }}</td><td>{{ $passenger->passport_number ?: '—' }}</td><td>{{ $passenger->date_of_birth ? ($passenger->date_of_birth->lte($ageDate) ? (int) $passenger->date_of_birth->diffInYears($ageDate) : '—') : ($passenger->imported_age ?? '—') }}</td></tr>
    @empty
        <tr><td colspan="4">No passengers assigned.</td></tr>
    @endforelse
    </tbody>
</table>

@if(count($voucher->hotel_stays ?? []))
<h3 class="section">Accommodation</h3>
<table class="grid">
    <thead><tr><th style="width:10%">City</th><th style="width:29%">Hotel</th><th class="map">Location</th><th style="width:13%">Rooms</th><th style="width:15%">Check-in</th><th style="width:15%">Checkout</th><th class="nights">Nights</th></tr></thead>
    <tbody>
    @foreach($voucher->hotel_stays as $index => $stay)
        <tr>
            <td>{{ $stay['city'] ?? '—' }}</td>
            <td class="primary">{{ $stay['hotel_name'] ?? '—' }}</td>
            <td class="map">@if(isset($mapCodes[$index]))<a href="{{ $stay['map_url'] }}"><img src="{{ $mapCodes[$index] }}" alt="Hotel location QR"></a>@else — @endif</td>
            <td>{{ $stay['room_count'] ?? 1 }} {{ ucfirst($stay['room_type'] ?? '') }}</td>
            <td>{{ filled($stay['check_in_date'] ?? null) ? \Illuminate\Support\Carbon::parse($stay['check_in_date'])->format('d M Y') : '—' }}</td>
            <td>{{ filled($stay['check_out_date'] ?? null) ? \Illuminate\Support\Carbon::parse($stay['check_out_date'])->format('d M Y') : '—' }}</td>
            <td class="nights">{{ $stay['night_count'] ?? 0 }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot><tr><td colspan="6" style="text-align:right"><strong>Total nights</strong></td><td class="nights"><strong>{{ $totalNights }}</strong></td></tr></tfoot>
</table>
@endif

@if($voucher->service_bundle !== 'hotel')
<table class="flights"><tr>
@foreach(['onward' => 'Outbound flight', 'return' => 'Return flight'] as $prefix => $label)
    <td>
        <h3 class="section">{{ $label }}</h3>
        <table class="grid"><thead><tr><th>Flight</th><th>Sector</th><th>Departure</th><th>Arrival</th></tr></thead>
            <tbody><tr>
                <td>{{ $voucher->{$prefix.'_airline'} }} {{ $voucher->{$prefix.'_flight_number'} }}</td>
                <td>{{ $voucher->{$prefix.'_departure_city'} }} – {{ $voucher->{$prefix.'_arrival_city'} }}</td>
                <td>{{ $voucher->{$prefix.'_departure_at'}?->format('d M Y') ?: '—' }}<br><strong>{{ $voucher->{$prefix.'_departure_at'}?->format('H:i') }}</strong></td>
                <td>{{ $voucher->{$prefix.'_arrival_at'}?->format('d M Y') ?: '—' }}<br><strong>{{ $voucher->{$prefix.'_arrival_at'}?->format('H:i') }}</strong></td>
            </tr></tbody>
        </table>
    </td>
@endforeach
</tr></table>
<div class="secondary">Flight times are local to each airport.</div>
@endif

@if($hasExternalSources ?? false)
<h3 class="section">Passenger services</h3>
<table class="grid">
    <thead><tr><th style="width:20%">Passenger #</th><th style="width:40%">Visa provider</th><th style="width:40%">Transport provider</th></tr></thead>
    <tbody>@foreach($originServices as $origin)
        <tr><td>{{ $origin['passenger_numbers'] }}</td><td>{{ $origin['visa_provider'] }}</td><td>{{ $origin['transport_provider'] }}@if($origin['transport_mode'])<br><span class="secondary">{{ ucfirst($origin['transport_mode']) }}</span>@endif</td></tr>
    @endforeach</tbody>
</table>
@foreach($originServices as $origin)
@if(count($origin['transport_items']))
<h3 class="section">Transport · Passenger # {{ $origin['passenger_numbers'] }}</h3>
<table class="grid"><thead><tr><th>Schedule</th><th>Provider / vehicle</th><th style="width:30%">Route</th><th>Driver / contact</th></tr></thead><tbody>
@foreach($origin['transport_items'] as $item)
<tr><td>{{ $item['scheduled_at'] ?: 'Not scheduled' }}</td><td>{{ $item['provider'] ?: $origin['transport_provider'] }}<br>{{ $item['vehicle'] ?: 'Not assigned' }} × {{ $item['quantity'] ?: 1 }}</td><td>{{ $item['route'] ?: 'Transport' }}</td><td>{{ $item['driver'] ?: 'Not assigned' }}<br>{{ $item['phone'] ?: '—' }}</td></tr>
@endforeach
</tbody></table>
@endif
@endforeach
@elseif($hasTransport)
<h3 class="section">Transport</h3>
<table class="grid">
    <thead><tr><th>Schedule</th><th>Vehicle / quantity</th><th style="width:30%">Route</th><th>Driver</th><th>Contact</th></tr></thead>
    <tbody>
    @forelse($voucher->group?->transportItems ?? [] as $item)
        <tr><td>{{ $item->scheduled_at?->format('d M Y H:i') ?: '—' }}</td><td>{{ $item->service?->name ?: ($item->service?->vehicle_type ?: '—') }} × {{ $item->quantity ?: 1 }}</td><td>{{ $item->sector?->name ?: ($item->description ?: 'Transport') }}</td><td>{{ $item->driver?->name ?: ($item->service?->driver_name ?: '—') }}</td><td>{{ $item->driver?->phone ?: ($item->service?->driver_contact ?: '—') }}</td></tr>
    @empty
        <tr><td colspan="5">{{ $voucher->group?->mandatoryTransportVendor?->name ?: 'Provider not assigned' }} · {{ str($voucher->group?->transport_mode ?: 'not scheduled')->replace('_', ' ')->title() }}</td></tr>
    @endforelse
    </tbody>
</table>
@endif

@if(count($contacts))
<h3 class="section">Journey contacts</h3>
<table class="grid"><thead><tr><th>Responsibility / City</th><th>Representative</th><th>Company / Agent</th><th>Phone / WhatsApp</th></tr></thead>
<tbody>
@foreach($contacts as $contact)
<tr><td>{{ $contact['responsibility'] }}@if($contact['city'] ?? null)<br>{{ $contact['city'] }}@endif</td><td class="primary">{{ $contact['name'] }}</td><td>{{ ($contact['organization'] ?? '') ?: '—' }}</td><td>{{ $contact['phone'] }}@if(($contact['whatsapp'] ?? '') && $contact['whatsapp'] !== $contact['phone'])<br>WhatsApp: {{ $contact['whatsapp'] }}@endif</td></tr>
@endforeach
</tbody></table>
@endif
@foreach($voucher->hotel_stays ?? [] as $stay)
@if(filled($stay['notes'] ?? null))<div class="footer-note">{{ $stay['city'] ?? '' }} · {{ $stay['hotel_name'] ?? '' }}: {{ $stay['notes'] }}</div>@endif
@endforeach
@if($voucher->notes)<div class="footer-note"><strong>Journey instructions:</strong> {{ $voucher->notes }}</div>@endif
@if(filled($footerText))<div class="footer-note">{{ $footerText }}</div>@endif
<div class="footer-note secondary">{{ collect([$letterhead['legalName'] ?? null, ...($letterhead['lines'] ?? []), $letterhead['taxId'] ?? null])->filter()->join(' · ') }}</div>
</body>
</html>
