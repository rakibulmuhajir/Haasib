<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>{{ $payment->payment_number }}</title><style>
body{font-family:DejaVu Sans,sans-serif;color:#111827;font-size:11px;margin:30px}h1{font-size:20px;margin:0}.center{text-align:center}.muted{color:#6b7280}table{border-collapse:collapse;width:100%;margin-top:18px}th,td{border:1px solid #d1d5db;padding:8px;text-align:left}th{background:#f3f4f6}.amount{font-size:18px;font-weight:bold}.reversed{color:#b91c1c;font-weight:bold}
</style></head>
<body>
<div class="center"><h1>{{ $company->trade_name ?: $company->name }}</h1><div class="muted">Payment Receipt</div></div>
<table><tr><th>Payment</th><td>{{ $payment->payment_number }}</td><th>Date</th><td>{{ $payment->payment_date?->format('d M Y') }}</td></tr>
<tr><th>Party</th><td>{{ $payment->agent?->name ?: $payment->visaVendor?->name ?: $payment->transportVendor?->name ?: $payment->hotelVendor?->name }}</td><th>Direction</th><td>{{ $payment->direction === 'received' ? 'Received' : 'Paid' }}</td></tr>
<tr><th>Amount</th><td class="amount">{{ $payment->currency }} {{ number_format((float)$payment->amount, 2) }}</td><th>Base Amount</th><td>{{ $payment->base_currency }} {{ number_format((float)$payment->base_amount, 2) }}</td></tr>
<tr><th>Exchange Rate</th><td>{{ $payment->exchange_rate ?: 1 }}</td><th>Account</th><td>{{ $payment->account?->code }} {{ $payment->account?->name }}</td></tr>
<tr><th>Method</th><td>{{ ucfirst(str_replace('_',' ',$payment->method)) }}</td><th>Reference</th><td>{{ $payment->reference ?: '-' }}</td></tr></table>
@if($payment->status === 'reversed')<p class="reversed">REVERSED: {{ $payment->reversal_reason }}</p>@endif
<h3>Allocations</h3><table><thead><tr><th>Group</th><th>Amount</th><th>Status</th></tr></thead><tbody>
@forelse($payment->allAllocations as $allocation)<tr><td>{{ $allocation->group?->group_number }} - {{ $allocation->group?->name }}</td><td>{{ $payment->base_currency }} {{ number_format((float)$allocation->base_amount,2) }}</td><td>{{ $allocation->reversed_at ? 'Reversed' : 'Posted' }}</td></tr>@empty<tr><td colspan="3">Unallocated advance</td></tr>@endforelse
</tbody></table>
@if($payment->notes)<p><strong>Notes:</strong> {{ $payment->notes }}</p>@endif
</body></html>
