<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\InvoiceLineItem;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\FuelStation\Models\Investor;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\SaleMetadata;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FuelSaleService
{
    public function __construct(
        private AmanatService $amanatService
    ) {}

    /**
     * Create a fuel sale with appropriate sale type handling.
     */
    public function createSale(array $data): Invoice
    {
        // Retry the whole command (never a partial posting) on a concurrent-write
        // serialization failure/deadlock, same as Bill\CreateAction and
        // BillPayment\CreateAction: the Daily Close audit trigger raises 40001 when
        // this date's close is mid-finalization.
        return \App\Services\AccountingWriteTransaction::run(function () use ($data) {
            $company = app(CurrentCompany::class)->get();
            $saleType = $data['sale_type'];

            // Get current rate for the fuel item
            $currentRate = RateChange::getCurrentRate($company->id, $data['item_id']);
            if (! $currentRate) {
                throw new \InvalidArgumentException('No current rate found for this fuel item.');
            }

            // A blocked buyer is refused a new credit sale outright (an over-limit buyer
            // only warns, on the frontend, and is not enforced here).
            if ($saleType === SaleMetadata::TYPE_CREDIT && ! empty($data['customer_id'])) {
                $customer = Customer::where('company_id', $company->id)->find($data['customer_id']);
                if ($customer?->is_credit_blocked) {
                    throw new \InvalidArgumentException("{$customer->name} is blocked from further credit sales.");
                }
            }

            // Calculate amounts
            $quantity = $data['quantity'];
            $unitPrice = $this->determineUnitPrice($saleType, $currentRate, $data);
            $lineTotal = $quantity * $unitPrice;

            // Apply bulk discount if applicable
            $discount = 0;
            $discountReason = null;
            if ($saleType === SaleMetadata::TYPE_BULK && isset($data['discount_per_liter'])) {
                $discount = $quantity * $data['discount_per_liter'];
                $discountReason = SaleMetadata::DISCOUNT_BULK;
            }

            // Create invoice (using actual Invoice model columns)
            $invoice = Invoice::create([
                'company_id' => $company->id,
                'customer_id' => $this->resolveCustomerId($company, $saleType, $data),
                'invoice_number' => $this->generateInvoiceNumber($company->id),
                'invoice_date' => $data['sale_date'] ?? now()->toDateString(),
                'due_date' => $this->calculateDueDate($saleType, $data),
                'subtotal' => $lineTotal,
                'discount_amount' => $discount,
                'total_amount' => $lineTotal - $discount,
                'paid_amount' => $this->determineAmountPaid($saleType, $lineTotal - $discount),
                'balance' => ($lineTotal - $discount) - $this->determineAmountPaid($saleType, $lineTotal - $discount),
                'currency' => $company->base_currency ?: 'PKR',
                'base_currency' => $company->base_currency ?: 'PKR',
                'status' => $this->determineInvoiceStatus($saleType),
            ]);

            // Create invoice line item (using actual InvoiceLineItem model columns)
            InvoiceLineItem::create([
                'company_id' => $company->id,
                'invoice_id' => $invoice->id,
                'line_number' => 1,
                'description' => $data['description'] ?? 'Fuel sale - '.($data['item_id'] ?? 'Unknown'),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_rate' => $discount > 0 ? ($discount / $lineTotal) * 100 : 0,
                'line_total' => $lineTotal,
                'total' => $lineTotal - $discount,
            ]);

            // Create fuel sale metadata
            SaleMetadata::create([
                'company_id' => $company->id,
                'invoice_id' => $invoice->id,
                'sale_type' => $saleType,
                'pump_id' => $data['pump_id'] ?? null,
                'attendant_transit' => $this->isAttendantTransit($saleType),
                'discount_reason' => $discountReason,
            ]);

            // Handle sale type specific logic
            $this->handleSaleTypeLogic($saleType, $invoice, $data);

            // Decrement inventory for the fuel item
            $this->decrementInventory($company->id, $data['item_id'], $quantity, $data['pump_id'] ?? null, $invoice);

            // Every litre already went through a nozzle the Daily Close reads: a credit
            // sale is never additional revenue. If the close for this date has not posted
            // yet, it will pick this invoice up as a pending channel (see
            // DailyCloseCreditSaleService::pendingFuelInvoiceDetails). If it already has,
            // reclassify immediately: Dr AR / Cr the close's cash account.
            if ($saleType === SaleMetadata::TYPE_CREDIT) {
                $this->reclassifyIfCloseAlreadyPosted($company->id, $invoice, $data['sale_date'] ?? now()->toDateString());
            }

            return $invoice->load(['lineItems', 'customer']);
        });
    }

    private function resolveCustomerId(Company $company, string $saleType, array $data): string
    {
        if (! empty($data['customer_id'])) {
            $customer = Customer::where('company_id', $company->id)
                ->where('is_active', true)->find($data['customer_id']);
            if (! $customer) {
                throw new \InvalidArgumentException('Select an active customer from this company.');
            }

            return $customer->id;
        }

        if (! in_array($saleType, [SaleMetadata::TYPE_RETAIL, SaleMetadata::TYPE_BULK], true)) {
            throw new \InvalidArgumentException('Select a customer for this sale type.');
        }

        // Serialize first-use creation within the sale transaction. The invoice
        // still has a real tenant customer; no nullable FK or shared global party.
        Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
        $customer = Customer::firstOrCreate([
            'company_id' => $company->id,
            'customer_number' => 'CASH-FUEL',
        ], [
            'name' => 'Walk-in fuel customer',
            'customer_type' => Customer::TYPE_WALK_IN,
            'base_currency' => $company->base_currency ?: 'PKR',
            'payment_terms' => 0,
            'is_active' => true,
            'created_by_user_id' => Auth::id(),
        ]);
        if (! $customer->is_active) {
            throw new \InvalidArgumentException('Activate the walk-in fuel customer or select another customer.');
        }

        return $customer->id;
    }

    /**
     * A standalone credit fuel-sale invoice entered after the day's close already posted
     * cannot wait for the next close to reduce expected cash. Post the reclassification
     * journal now so it shows up as late channel activity on the posted close.
     */
    private function reclassifyIfCloseAlreadyPosted(string $companyId, Invoice $invoice, string $saleDate): void
    {
        $close = Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereDate('transaction_date', $saleDate)
            ->whereNotNull('metadata->posting_snapshot')
            ->first();
        if (! $close) {
            return;
        }

        $cashAccountId = $close->metadata['posting_snapshot']['cash_account_id'] ?? null;
        if (! $cashAccountId || ! $invoice->customer_id) {
            return;
        }

        $company = Company::findOrFail($companyId);
        $customer = Customer::where('company_id', $companyId)->find($invoice->customer_id);
        $arId = $customer?->ar_account_id ?: $company->default_ar_account_id;
        $ar = Account::where('company_id', $companyId)->where('is_active', true)->where('subtype', 'accounts_receivable')
            ->when($arId, fn ($q) => $q->whereKey($arId), fn ($q) => $q->orderBy('code'))->first();
        if (! $ar) {
            throw new \RuntimeException('Set up a base-currency receivables account for this buyer before recording this sale.');
        }

        $amount = round((float) $invoice->total_amount, 2);
        if ($amount <= 0) {
            return;
        }

        $transaction = app(GlPostingService::class)->postBalancedTransaction([
            'company_id' => $companyId,
            'transaction_type' => 'fuel_sale_reclass',
            'date' => $saleDate,
            'currency' => $company->base_currency ?: 'PKR',
            'description' => "Fuel sale reclassification - Invoice {$invoice->invoice_number}",
            'reference_type' => 'acct.invoices',
            'reference_id' => $invoice->id,
        ], [
            ['account_id' => $ar->id, 'type' => 'debit', 'amount' => $amount, 'description' => "Credit portion of meter sales — {$invoice->invoice_number}"],
            ['account_id' => $cashAccountId, 'type' => 'credit', 'amount' => $amount, 'description' => "Credit portion of meter sales — {$invoice->invoice_number}"],
        ]);

        $invoice->transaction_id = $transaction->id;
        $invoice->status = 'sent';
        $invoice->sent_at = now();
        $invoice->save();
    }

    /**
     * Decrement inventory for a fuel sale.
     * Creates a stock movement and updates item avg_cost tracking.
     */
    private function decrementInventory(
        string $companyId,
        string $itemId,
        float $quantity,
        ?string $pumpId,
        Invoice $invoice
    ): void {
        $item = Item::find($itemId);
        if (! $item) {
            return; // Item not found, skip inventory tracking
        }

        // Determine warehouse (tank) from pump if available
        $warehouseId = null;
        if ($pumpId) {
            $pump = Pump::find($pumpId);
            $warehouseId = $pump?->tank_id;
        }

        // If no warehouse from pump, try to find tank linked to this fuel item
        if (! $warehouseId) {
            $warehouseId = \App\Modules\Inventory\Models\Warehouse::where('company_id', $companyId)
                ->where('warehouse_type', 'tank')
                ->where('linked_item_id', $itemId)
                ->value('id');
        }

        if (! $warehouseId) {
            return; // No warehouse found, skip stock movement
        }

        // Create stock movement for the sale (negative quantity = out)
        $unitCost = (float) ($item->avg_cost ?? 0);
        $totalCost = $quantity * $unitCost;

        StockMovement::create([
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
            'movement_date' => now()->toDateString(),
            'movement_type' => 'sale',
            'quantity' => -abs($quantity), // Negative for outflow
            'unit_cost' => $unitCost,
            'total_cost' => -abs($totalCost),
            'reference_type' => 'acct.invoices',
            'reference_id' => $invoice->id,
            'notes' => "Fuel sale - Invoice #{$invoice->invoice_number}",
            'created_by_user_id' => Auth::id(),
        ]);

        // Update item's current stock (if tracked)
        if ($item->current_stock !== null) {
            $item->decrement('current_stock', $quantity);
        }
    }

    /**
     * Determine unit price based on sale type.
     */
    private function determineUnitPrice(string $saleType, RateChange $rate, array $data): float
    {
        // Investor sales use purchase rate (no margin)
        if ($saleType === SaleMetadata::TYPE_INVESTOR) {
            return $rate->purchase_rate;
        }

        // All other sales use regular sale rate
        return $rate->sale_rate;
    }

    /**
     * Calculate due date based on sale type.
     */
    private function calculateDueDate(string $saleType, array $data): string
    {
        // Credit sales have payment terms
        if ($saleType === SaleMetadata::TYPE_CREDIT) {
            $days = $data['payment_terms_days'] ?? 30;

            return now()->addDays($days)->toDateString();
        }

        // Cash sales due immediately
        return $data['sale_date'] ?? now()->toDateString();
    }

    /**
     * Determine amount paid based on sale type.
     */
    private function determineAmountPaid(string $saleType, float $total): float
    {
        // Credit and vendor card sales are not paid immediately
        if (in_array($saleType, [SaleMetadata::TYPE_CREDIT, SaleMetadata::TYPE_VENDOR_CARD])) {
            return 0;
        }

        // All other types are paid on sale
        return $total;
    }

    /**
     * Determine invoice status based on sale type.
     */
    private function determineInvoiceStatus(string $saleType): string
    {
        if (in_array($saleType, [SaleMetadata::TYPE_CREDIT, SaleMetadata::TYPE_VENDOR_CARD])) {
            return 'sent'; // Pending payment
        }

        return 'paid';
    }

    /**
     * Check if cash is in attendant transit.
     */
    private function isAttendantTransit(string $saleType): bool
    {
        // Retail and bulk cash sales are in attendant transit until handed over
        return in_array($saleType, [SaleMetadata::TYPE_RETAIL, SaleMetadata::TYPE_BULK]);
    }

    /**
     * Handle sale type specific business logic.
     */
    private function handleSaleTypeLogic(string $saleType, Invoice $invoice, array $data): void
    {
        switch ($saleType) {
            case SaleMetadata::TYPE_AMANAT:
                $this->handleAmanatSale($invoice, $data);
                break;

            case SaleMetadata::TYPE_INVESTOR:
                $this->handleInvestorSale($invoice, $data);
                break;

                // Other types don't need additional logic at sale time
        }
    }

    /**
     * Handle amanat (trust deposit) sale.
     * Deducts from customer's amanat balance.
     */
    private function handleAmanatSale(Invoice $invoice, array $data): void
    {
        if (! $invoice->customer_id) {
            throw new \InvalidArgumentException('Amanat sales require a customer.');
        }

        $line = $invoice->lineItems->first();

        $this->amanatService->applyToFuelPurchase(
            $invoice->customer,
            $invoice->total_amount,
            $data['item_id'], // Use item_id from request data since it's not on line item
            $line->quantity,
            $invoice->invoice_number
        );
    }

    /**
     * Handle investor sale.
     * Consumes units from investor's lots and records commission.
     */
    private function handleInvestorSale(Invoice $invoice, array $data): void
    {
        if (! isset($data['investor_id'])) {
            throw new \InvalidArgumentException('Investor sales require an investor_id.');
        }

        $investor = Investor::findOrFail($data['investor_id']);
        $line = $invoice->lineItems->first();
        $unitsToConsume = $line->quantity;

        // Consume units from active lots (FIFO)
        $lots = $investor->activeLots()
            ->orderBy('deposit_date')
            ->get();

        $totalCommission = 0;
        $remainingUnits = $unitsToConsume;

        foreach ($lots as $lot) {
            if ($remainingUnits <= 0) {
                break;
            }

            $unitsFromLot = min($remainingUnits, $lot->units_remaining);
            $commission = $lot->consumeUnits($unitsFromLot);
            $totalCommission += $commission;
            $remainingUnits -= $unitsFromLot;
        }

        if ($remainingUnits > 0) {
            throw new \InvalidArgumentException(
                "Investor does not have enough units. Requested: {$unitsToConsume}, Available: ".
                ($unitsToConsume - $remainingUnits)
            );
        }

        // Update investor totals
        $investor->recalculateTotals();
    }

    /**
     * Generate invoice number for fuel sales.
     */
    private function generateInvoiceNumber(string $companyId): string
    {
        $prefix = 'FS-';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(4));

        return "{$prefix}{$date}-{$random}";
    }
}
