<?php

namespace App\Modules\Umrah\Handlers;

use App\Models\Company;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\InvoiceLineItem;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\TicketPostingService;
use App\Modules\Accounting\Services\TicketSaleAmounts;
use App\Modules\Umrah\Commands\CreateTicketBooking;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Ticket;
use App\Modules\Umrah\Models\TicketBooking;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The load-bearing handler of Plan B: one transaction, one idempotency
 * key, both documents or neither.
 *
 * Supplier cost is converted to base exactly once, here, and that stored
 * value is what both TicketPostingService::postTicketInvoice() (via the
 * CLEARING credit) and postTicketBill() (via its CLEARING debit) are
 * given -- not recomputed independently -- which is why clearing nets to
 * exactly zero rather than "close enough".
 */
final class CreateTicketBookingHandler
{
    public function __construct(private readonly TicketPostingService $postingService) {}

    public function handle(CreateTicketBooking $command): TicketBooking
    {
        // Idempotency comes first, before any write, and outside the
        // transaction: a replay must not even open one.
        $existing = TicketBooking::where('company_id', $command->companyId)
            ->where('idempotency_key', $command->idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        if ($command->agentId !== null) {
            $agent = Agent::where('company_id', $command->companyId)->find($command->agentId);

            if (! $agent) {
                throw new \InvalidArgumentException('Agent not found for this company.');
            }

            if ($agent->customer_id !== $command->customerId) {
                // An agent billed to somebody else's account is a
                // wrong-party error, not a validation nicety.
                throw new \InvalidArgumentException(
                    'This agent is not linked to the customer named as the buyer on this booking.'
                );
            }
        }

        if (empty($command->tickets)) {
            throw new \InvalidArgumentException('A booking needs at least one ticket.');
        }

        return DB::transaction(function () use ($command) {
            $company = Company::findOrFail($command->companyId);
            $baseCurrency = $company->base_currency;

            // Locking the vendor row here also protects the numbering
            // scheme below from a second concurrent booking creeping in
            // between the read and the write.
            $vendor = Vendor::where('company_id', $command->companyId)
                ->lockForUpdate()
                ->find($command->supplierVendorId);

            if (! $vendor) {
                throw new \InvalidArgumentException('Supplier vendor not found for this company.');
            }

            if (! $vendor->is_active) {
                throw new \RuntimeException('Supplier vendor is not active; cannot raise a bill against it.');
            }

            [$convertedTickets, $totals] = $this->convertTickets($command->tickets, $baseCurrency);

            $invoice = $this->createInvoice($company, $command, $totals);
            $bill = $this->createBill($company, $vendor, $command, $convertedTickets, $totals);

            $booking = TicketBooking::create([
                'company_id' => $company->id,
                'customer_id' => $command->customerId,
                'agent_id' => $command->agentId,
                'supplier_vendor_id' => $vendor->id,
                'invoice_id' => $invoice->id,
                'bill_id' => $bill->id,
                'booking_reference' => $this->nextBookingReference($company->id),
                'pnr' => $command->pnr,
                'booking_date' => $command->bookingDate,
                'status' => 'confirmed',
                'idempotency_key' => $command->idempotencyKey,
                'created_by_user_id' => Auth::id(),
            ]);

            foreach ($convertedTickets as $ticketData) {
                Ticket::create(array_merge($ticketData, [
                    'company_id' => $company->id,
                    'ticket_booking_id' => $booking->id,
                    'ticket_number' => $this->nextTicketNumber($company->id),
                    'base_currency' => $baseCurrency,
                    'status' => 'issued',
                ]));
            }

            // Both documents or neither: if either posting throws, the
            // whole transaction -- booking, tickets, invoice and bill --
            // rolls back together.
            $this->postingService->postTicketInvoice($invoice, new TicketSaleAmounts(
                supplierCostBase: $totals['supplierCostBase'],
                commissionBase: $totals['commissionBase'],
                serviceFeeBase: $totals['serviceFeeBase'],
                discountBase: $totals['discountBase'],
            ));

            $this->postingService->postTicketBill($bill, $totals['supplierCostBase']);

            return $booking->fresh(['invoice', 'bill', 'tickets']);
        });
    }

    /**
     * Converts every ticket to base exactly once. `*_base` figures
     * computed here are what gets stored on the ticket row and summed
     * into the postings -- nothing downstream reconverts.
     *
     * @param  array<int, array<string, mixed>>  $tickets
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, float>}
     */
    private function convertTickets(array $tickets, string $baseCurrency): array
    {
        $converted = [];

        $sumGrossFareBase = 0.0;
        $sumTaxesBase = 0.0;
        $sumDiscountBase = 0.0;
        $sumServiceFeeBase = 0.0;
        $sumSupplierCostBase = 0.0;
        $sumSupplierCost = 0.0;

        foreach ($tickets as $ticket) {
            $saleCurrency = $ticket['sale_currency'];
            $saleRate = isset($ticket['sale_exchange_rate']) ? (float) $ticket['sale_exchange_rate'] : null;
            $supplierCurrency = $ticket['supplier_currency'];
            $supplierRate = isset($ticket['supplier_exchange_rate']) ? (float) $ticket['supplier_exchange_rate'] : null;

            $grossFare = (float) $ticket['gross_fare'];
            $taxes = (float) ($ticket['taxes'] ?? 0);
            $discount = (float) ($ticket['discount'] ?? 0);
            $serviceFee = (float) ($ticket['service_fee'] ?? 0);
            $supplierCost = (float) $ticket['supplier_cost'];

            $saleIsBase = $saleCurrency === $baseCurrency;
            $supplierIsBase = $supplierCurrency === $baseCurrency;

            if (! $saleIsBase && ! ($saleRate > 0)) {
                throw new \InvalidArgumentException('sale_exchange_rate is required when sale_currency differs from the company base currency.');
            }

            if (! $supplierIsBase && ! ($supplierRate > 0)) {
                throw new \InvalidArgumentException('supplier_exchange_rate is required when supplier_currency differs from the company base currency.');
            }

            $grossFareBase = $saleIsBase ? $grossFare : round($grossFare * $saleRate, 2);
            $taxesBase = $saleIsBase ? $taxes : round($taxes * $saleRate, 2);
            $discountBase = $saleIsBase ? $discount : round($discount * $saleRate, 2);
            $serviceFeeBase = $saleIsBase ? $serviceFee : round($serviceFee * $saleRate, 2);
            $supplierCostBase = $supplierIsBase ? $supplierCost : round($supplierCost * $supplierRate, 2);

            $converted[] = [
                'passenger_id' => $ticket['passenger_id'] ?? null,
                'passenger_name' => $ticket['passenger_name'],
                'passport_number' => $ticket['passport_number'] ?? null,
                'airline' => $ticket['airline'] ?? null,
                'route' => $ticket['route'] ?? null,
                'travel_date' => $ticket['travel_date'] ?? null,
                'airline_ticket_number' => $ticket['airline_ticket_number'] ?? null,
                'sale_currency' => $saleCurrency,
                'sale_exchange_rate' => $saleIsBase ? null : $saleRate,
                'gross_fare' => $grossFare,
                'taxes' => $taxes,
                'discount' => $discount,
                'service_fee' => $serviceFee,
                'gross_fare_base' => $grossFareBase,
                'taxes_base' => $taxesBase,
                'discount_base' => $discountBase,
                'service_fee_base' => $serviceFeeBase,
                'supplier_currency' => $supplierCurrency,
                'supplier_exchange_rate' => $supplierIsBase ? null : $supplierRate,
                'supplier_cost' => $supplierCost,
                'supplier_cost_base' => $supplierCostBase,
            ];

            $sumGrossFareBase += $grossFareBase;
            $sumTaxesBase += $taxesBase;
            $sumDiscountBase += $discountBase;
            $sumServiceFeeBase += $serviceFeeBase;
            $sumSupplierCostBase += $supplierCostBase;
            $sumSupplierCost += $supplierCost;
        }

        $sumGrossFareBase = round($sumGrossFareBase, 2);
        $sumTaxesBase = round($sumTaxesBase, 2);
        $sumDiscountBase = round($sumDiscountBase, 2);
        $sumServiceFeeBase = round($sumServiceFeeBase, 2);
        $sumSupplierCostBase = round($sumSupplierCostBase, 2);

        $lineTotal = round($sumGrossFareBase + $sumTaxesBase + $sumServiceFeeBase, 2);
        $totalAmount = round($lineTotal - $sumDiscountBase, 2);
        $commissionBase = round($sumGrossFareBase + $sumTaxesBase - $sumSupplierCostBase, 2);

        return [$converted, [
            'grossFareBase' => $sumGrossFareBase,
            'taxesBase' => $sumTaxesBase,
            'discountBase' => $sumDiscountBase,
            'serviceFeeBase' => $sumServiceFeeBase,
            'supplierCostBase' => $sumSupplierCostBase,
            'supplierCost' => round($sumSupplierCost, 2),
            'lineTotal' => $lineTotal,
            'totalAmount' => $totalAmount,
            'commissionBase' => $commissionBase,
        ]];
    }

    /**
     * The buyer's invoice carries one line and no trace of the
     * supplier's price -- see the spec's "how this posts, precisely".
     * The invoice is created in the company's base currency: nothing in
     * TicketPostingService::postTicketInvoice() converts it, it trusts
     * invoice->total_amount as already-base, so a booking whose buyer
     * leg is meant to print in a third currency is out of this task's
     * scope (every ticket's sale_currency still converts to base
     * correctly on the ticket row itself; only the invoice document is
     * base-only for now).
     */
    private function createInvoice(Company $company, CreateTicketBooking $command, array $totals): Invoice
    {
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $command->customerId,
            'invoice_number' => Invoice::generateInvoiceNumber($company->id),
            'invoice_date' => $command->bookingDate,
            'due_date' => $command->bookingDate,
            'status' => 'sent',
            'currency' => $company->base_currency,
            'base_currency' => $company->base_currency,
            'exchange_rate' => 1,
            'subtotal' => $totals['lineTotal'],
            'tax_amount' => 0,
            'discount_amount' => $totals['discountBase'],
            'total_amount' => $totals['totalAmount'],
            'paid_amount' => 0,
            'balance' => $totals['totalAmount'],
            'base_amount' => $totals['totalAmount'],
        ]);

        InvoiceLineItem::create([
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'line_number' => 1,
            'description' => $this->flightDescription($command->tickets),
            'quantity' => 1,
            'unit_price' => $totals['lineTotal'],
            'tax_rate' => 0,
            'discount_rate' => 0,
            'line_total' => $totals['lineTotal'],
            'tax_amount' => 0,
            'total' => $totals['lineTotal'],
        ]);

        return $invoice->fresh();
    }

    /**
     * The bill is a real document in the supplier's own currency --
     * postTicketBill() takes the base supplier cost as an explicit
     * argument rather than reading it off the bill, so the bill's own
     * currency and rate are free to be genuinely foreign.
     */
    private function createBill(Company $company, Vendor $vendor, CreateTicketBooking $command, array $convertedTickets, array $totals): Bill
    {
        $supplierCurrency = $convertedTickets[0]['supplier_currency'];
        $supplierIsBase = $supplierCurrency === $company->base_currency;
        $supplierRate = $supplierIsBase ? 1 : $convertedTickets[0]['supplier_exchange_rate'];

        $bill = Bill::create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'bill_number' => $this->nextBillNumber($company->id),
            'bill_date' => $command->bookingDate,
            'due_date' => $command->bookingDate,
            'status' => 'received',
            'currency' => $supplierCurrency,
            'base_currency' => $company->base_currency,
            'exchange_rate' => $supplierRate,
            'subtotal' => $totals['supplierCost'],
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => $totals['supplierCost'],
            'paid_amount' => 0,
            'balance' => $totals['supplierCost'],
            'base_amount' => $totals['supplierCostBase'],
        ]);

        BillLineItem::create([
            'company_id' => $company->id,
            'bill_id' => $bill->id,
            'line_number' => 1,
            'description' => 'Supplier cost, '.$this->flightDescription($command->tickets),
            'quantity' => 1,
            'unit_price' => $totals['supplierCost'],
            'tax_rate' => 0,
            'discount_rate' => 0,
            'line_total' => $totals['supplierCost'],
            'tax_amount' => 0,
            'total' => $totals['supplierCost'],
        ]);

        return $bill->fresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $tickets
     */
    private function flightDescription(array $tickets): string
    {
        if (count($tickets) === 1) {
            $ticket = $tickets[0];
            $airline = $ticket['airline'] ?? 'Air ticket';
            $route = $ticket['route'] ?? '';
            $travelDate = $ticket['travel_date'] ?? '';

            return trim("Air ticket, {$airline} {$route}, {$travelDate}", ' ,');
        }

        return count($tickets).' air tickets';
    }

    private function nextBookingReference(string $companyId): string
    {
        $last = TicketBooking::withTrashed()
            ->where('company_id', $companyId)
            ->whereNotNull('booking_reference')
            ->lockForUpdate()
            ->orderByDesc('booking_reference')
            ->value('booking_reference');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return 'TB-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    private function nextTicketNumber(string $companyId): string
    {
        $last = Ticket::withTrashed()
            ->where('company_id', $companyId)
            ->whereNotNull('ticket_number')
            ->lockForUpdate()
            ->orderByDesc('ticket_number')
            ->value('ticket_number');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return 'TKT-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    private function nextBillNumber(string $companyId): string
    {
        $last = Bill::where('company_id', $companyId)
            ->whereNotNull('bill_number')
            ->where('bill_number', 'like', 'TICKBILL-%')
            ->lockForUpdate()
            ->orderByDesc('bill_number')
            ->value('bill_number');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return 'TICKBILL-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
