<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $voucher->voucher_number }}</title>
    <style>
@include('print.sheet')
    </style>
</head>
<body>
@php
    $agent = $voucher->agent;
    $transportPartner = $voucher->group?->mandatoryTransportVendor;
    $partner = $transportPartner ?: $voucher->group?->vendor;
    $partnerRole = $transportPartner ? 'Transport Partner' : 'Visa Partner';
    $familyHead = $voucher->passengers->first();
    $totalNights = collect($voucher->hotel_stays ?? [])->sum(fn ($stay) => (int) ($stay['night_count'] ?? 0));
    $resolveLogo = function (?string $url): ?string {
        if (! $url || str_starts_with($url, 'data:') || str_starts_with($url, 'http')) {
            return $url;
        }

        $path = public_path(ltrim($url, '/'));

        return is_file($path) ? $path : null;
    };
    $agentLogo = $resolveLogo($agent?->logo_url);
    $partnerLogo = $resolveLogo($partner?->logo_url);
@endphp
@if($voucher->status === 'draft')<div class="watermark draft">DRAFT</div>@endif
@if($voucher->status === 'cancelled')<div class="watermark cancelled">CANCELLED</div>@endif

<table class="masthead"><tr>
    <td>
        @if($agentLogo)<img class="party-logo" src="{{ $agentLogo }}" alt="Agent logo">@endif
        <div class="party-name">{{ $agent?->name ?: 'Agent' }}</div>
        <div class="secondary">{{ collect([$agent?->city, $agent?->country])->filter()->join(', ') }}</div>
        <div class="secondary">{{ $agent?->phone }}</div>
    </td>
    <td>
        @if($logoPath)<img class="party-logo center-logo" src="{{ $logoPath }}" alt="Company logo">@elseif(str_starts_with((string) $company->logo_url, 'data:'))<img class="party-logo center-logo" src="{{ $company->logo_url }}" alt="Company logo">@endif
        <div class="main-name">{{ $company->trade_name ?: $company->name }}</div>
        {{-- The same lines the on-screen voucher prints, from the same
             assembler, so the downloaded copy and the screen agree. --}}
        @if($letterhead['legalName'] ?? null)<div class="secondary">{{ $letterhead['legalName'] }}</div>@endif
        @foreach($letterhead['lines'] ?? [] as $line)<div class="secondary">{{ $line }}</div>@endforeach
        @if($letterhead['email'] ?? null)<div class="secondary">{{ $letterhead['email'] }}</div>@endif
        @if(($letterhead['phone'] ?? null) ?: data_get($company->settings, 'contact_phone'))<div class="secondary">Helpline: {{ ($letterhead['phone'] ?? null) ?: data_get($company->settings, 'contact_phone') }}</div>@endif
        @if($letterhead['taxId'] ?? null)<div class="secondary">{{ $letterhead['taxIdLabel'] ?? 'Tax no.' }} {{ $letterhead['taxId'] }}</div>@endif
    </td>
    <td>
        @if($partnerLogo)<img class="party-logo right-logo" src="{{ $partnerLogo }}" alt="{{ $partnerRole }} logo">@endif
        <div class="party-name">{{ $partner?->name ?: $partnerRole }}</div>
        <div class="secondary">{{ $partnerRole }}</div>
        <div class="secondary">{{ collect([$partner?->city, $partner?->phone])->filter()->join(' | ') }}</div>
    </td>
</tr></table>

<div class="document-title">{{ $voucher->title ?: 'Travel Voucher' }}</div>
<table class="grid identity"><tr>
    <td><span class="label">Family Head</span><span class="focus">{{ $familyHead?->full_name ?: 'Not assigned' }}</span></td>
    <td><span class="label">Voucher No.</span><span class="focus">{{ $voucher->voucher_number }}</span></td>
    <td><span class="label">Group</span>{{ $voucher->group?->group_number }} - {{ $voucher->group?->name }}</td>
    <td><span class="label">PAX / Nights</span><span class="focus">{{ $voucher->passengers->count() }} / {{ $totalNights }}</span></td>
    <td><span class="label">Status / Service</span>{{ strtoupper($voucher->status) }} | {{ str($voucher->service_bundle)->replace('_', ' ')->title() }}</td>
</tr></table>

@if($voucher->cancellation_reason)<div class="footer-note"><strong>Cancellation reason:</strong> {{ $voucher->cancellation_reason }}</div>@endif

<div class="section">Mutamers / Passengers</div>
<table class="grid">
    <thead><tr><th>#</th><th>Mutamer Name</th><th>Passport</th><th>Nationality</th><th>DOB / Age</th><th>Visa Status</th></tr></thead>
    <tbody>
    @forelse($voucher->passengers as $passenger)
        <tr><td>{{ $loop->iteration }}</td><td class="primary">{{ $passenger->full_name }}</td><td>{{ $passenger->passport_number ?: '-' }}</td><td>{{ $passenger->nationality ?: '-' }}</td><td>{{ $passenger->date_of_birth?->format('d-m-Y') ?: ($passenger->imported_age !== null ? 'Age '.$passenger->imported_age : '-') }}</td><td>{{ ucfirst($passenger->visa_status) }}</td></tr>
    @empty
        <tr><td colspan="6">No passengers assigned.</td></tr>
    @endforelse
    </tbody>
</table>

<div class="section">Accommodation</div>
<table class="grid">
    <thead><tr><th>City</th><th>Hotel</th><th>Room</th><th>Check-in</th><th>Checkout</th><th>Nights</th></tr></thead>
    <tbody>
    @forelse($voucher->hotel_stays ?? [] as $stay)
        <tr><td>{{ $stay['city'] ?? '-' }}</td><td class="primary">{{ $stay['hotel_name'] ?? '-' }}</td><td>{{ $stay['room_count'] ?? 1 }} {{ ucfirst($stay['room_type'] ?? '') }}</td><td>{{ filled($stay['check_in_date'] ?? null) ? \Illuminate\Support\Carbon::parse($stay['check_in_date'])->format('d-m-Y') : '-' }}</td><td>{{ filled($stay['check_out_date'] ?? null) ? \Illuminate\Support\Carbon::parse($stay['check_out_date'])->format('d-m-Y') : '-' }}</td><td>{{ $stay['night_count'] ?? 0 }}</td></tr>
    @empty
        <tr><td colspan="6">No hotel stays added.</td></tr>
    @endforelse
    </tbody>
</table>

@if($voucher->service_bundle !== 'hotel')
<div class="section">Transport / Services</div>
<table class="grid">
    <thead><tr><th>Schedule</th><th>Vehicle</th><th>Sector</th><th>Driver</th><th>Contact</th></tr></thead>
    <tbody>
    @forelse($voucher->group?->transportItems ?? [] as $item)
        <tr><td>{{ $item->scheduled_at?->format('d-m-Y H:i') ?: '-' }}</td><td>{{ $item->service?->name ?: ($item->service?->vehicle_type ?: '-') }}</td><td class="primary">{{ $item->sector?->name ?: ($item->description ?: 'Transport') }}</td><td>{{ $item->driver?->name ?: ($item->service?->driver_name ?: '-') }}</td><td>{{ $item->driver?->phone ?: ($item->service?->driver_contact ?: '-') }}</td></tr>
    @empty
        <tr><td colspan="5">{{ $voucher->group?->transport_mode === 'none' ? 'Self-arranged transport' : ($voucher->group?->transport_mode === 'specialized' ? 'Specialized transport' : 'Standard bus transport') }}</td></tr>
    @endforelse
    </tbody>
</table>

<div class="section">Flight Schedule</div>
<table class="grid">
    <thead><tr><th>Journey</th><th>Flight</th><th>Sector</th><th>Departure</th><th>Arrival</th></tr></thead>
    <tbody>
        <tr><td class="primary">Departure</td><td>{{ $voucher->onward_airline }} {{ $voucher->onward_flight_number }}</td><td>{{ $voucher->onward_departure_city }} - {{ $voucher->onward_arrival_city }}</td><td>{{ $voucher->onward_departure_at?->format('d-M-Y H:i') ?: '-' }}</td><td>{{ $voucher->onward_arrival_at?->format('d-M-Y H:i') ?: '-' }}</td></tr>
        <tr><td class="primary">Return</td><td>{{ $voucher->return_airline }} {{ $voucher->return_flight_number }}</td><td>{{ $voucher->return_departure_city }} - {{ $voucher->return_arrival_city }}</td><td>{{ $voucher->return_departure_at?->format('d-M-Y H:i') ?: '-' }}</td><td>{{ $voucher->return_arrival_at?->format('d-M-Y H:i') ?: '-' }}</td></tr>
    </tbody>
</table>
@endif

@if($voucher->notes)<div class="footer-note"><strong>Special instructions:</strong> {{ $voucher->notes }}</div>@endif
</body>
</html>
