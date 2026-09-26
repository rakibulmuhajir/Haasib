<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\FuelStation\Models\Nozzle;
use App\Modules\FuelStation\Models\NozzleReading;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\FuelStation\Models\AmanatTransaction;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Modules\Payroll\Services\PayrollPostingService;
use App\Services\CommandBus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DailyCloseService
{
    public function __construct(
        private readonly GlPostingService $postingService,
        private readonly PayrollPostingService $payrollPostingService,
    ) {}

    public function openingBaselineForTank(string $companyId, string $tankId, string $itemId, string $date): array
    {
        $previousReading = TankReading::where('company_id', $companyId)
            ->where('tank_id', $tankId)
            ->where('reading_date', '<', $date)
            ->orderByDesc('reading_date')
            ->orderByDesc('created_at')
            ->first();

        if ($previousReading) {
            return [
                'liters' => (float) $previousReading->dip_measurement_liters,
                'date' => $previousReading->reading_date?->toDateString(),
                'created_at' => $previousReading->created_at,
                'has_baseline' => true,
            ];
        }

        $stockMovement = StockMovement::where('company_id', $companyId)
            ->where('warehouse_id', $tankId)
            ->where('item_id', $itemId)
            ->whereDate('movement_date', '<=', $date)
            ->orderByDesc('movement_date')
            ->orderByDesc('created_at')
            ->first(['movement_date', 'created_at']);

        if (! $stockMovement) {
            return [
                'liters' => 0.0,
                'date' => null,
                'created_at' => null,
                'has_baseline' => false,
            ];
        }

        $movementDate = $stockMovement->movement_date?->toDateString();

        return [
            'liters' => (float) StockMovement::where('company_id', $companyId)
                ->where('warehouse_id', $tankId)
                ->where('item_id', $itemId)
                ->whereDate('movement_date', '<=', $movementDate)
                ->sum('quantity'),
            'date' => $movementDate,
            'created_at' => $stockMovement->created_at,
            'has_baseline' => true,
        ];
    }

    /**
     * The tank a bill line with no warehouse belongs to, for an item with several
     * tanks. Mirrors Bill\UpdateAction::preferredWarehouseId - tank warehouses only,
     * primary first, then name - so a null-warehouse delivery line is never counted
     * against every tank that shares the item.
     */
    public function primaryTankIdForItem(string $companyId, string $itemId): ?string
    {
        return Warehouse::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('linked_item_id', $itemId)
            ->orderByRaw("case when warehouse_type = 'tank' then 0 else 1 end")
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->value('id');
    }

    /**
     * Fuel deliveries that landed on a bill for this tank's item but have not yet
     * been received into stock. A delivery only becomes real litres in the tank
     * once someone (or the close itself, on posting) receives it - until then the
     * dip sees nothing, so these rows are what the close's preview must add back
     * to explain a dip that looks like a gain but is really an unreceived bill.
     *
     * afterDate/upToDate are exclusive/inclusive on bill_date, matching how the
     * tank's opening baseline and "today's receipts" window are read elsewhere
     * in this service.
     */
    public function pendingDeliveries(string $companyId, string $tankId, string $itemId, ?string $afterDate, string $upToDate): Collection
    {
        $primaryTankId = $this->primaryTankIdForItem($companyId, $itemId);

        return BillLineItem::query()
            ->join('acct.bills', 'acct.bills.id', '=', 'acct.bill_line_items.bill_id')
            ->where('acct.bill_line_items.company_id', $companyId)
            ->where('acct.bill_line_items.item_id', $itemId)
            ->whereNotIn('acct.bills.status', ['void', 'cancelled', 'draft'])
            ->when($afterDate, fn ($q) => $q->whereDate('acct.bills.bill_date', '>', $afterDate))
            ->whereDate('acct.bills.bill_date', '<=', $upToDate)
            // Litres sold directly never go into a tank, so only the rest is still to arrive.
            ->whereRaw('acct.bill_line_items.quantity > acct.bill_line_items.direct_quantity + acct.bill_line_items.quantity_received')
            ->where(function ($q) use ($tankId, $primaryTankId) {
                $q->where('acct.bill_line_items.warehouse_id', $tankId);
                if ($primaryTankId !== null && $primaryTankId === $tankId) {
                    $q->orWhereNull('acct.bill_line_items.warehouse_id');
                }
            })
            ->orderBy('acct.bills.bill_date')
            ->get([
                'acct.bill_line_items.id as line_id',
                'acct.bill_line_items.bill_id',
                'acct.bills.bill_number',
                'acct.bills.bill_date',
                'acct.bill_line_items.quantity',
                'acct.bill_line_items.quantity_received',
                'acct.bill_line_items.direct_quantity',
            ])
            ->map(fn ($row) => [
                'bill_id' => $row->bill_id,
                'bill_number' => $row->bill_number,
                'bill_date' => $row->bill_date instanceof \Carbon\Carbon ? $row->bill_date->toDateString() : (string) $row->bill_date,
                'line_id' => $row->line_id,
                'remaining' => round((float) $row->quantity - (float) $row->direct_quantity - (float) $row->quantity_received, 3),
            ])
            ->filter(fn ($row) => $row['remaining'] > 0)
            ->values();
    }

    /**
     * Get the previous day's closing cash balance.
     */
    public function getPreviousDayClosing(string $companyId, string $date): array
    {
        $previousDate = date('Y-m-d', strtotime($date . ' -1 day'));

        // Look for the most recent daily close transaction before this date
        $previousClose = Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->where('transaction_date', '<', $date)
            ->whereNull('deleted_at')
            ->orderByDesc('transaction_date')
            ->first();

        if (!$previousClose) {
            // No prior daily close exists (typically the very first close for this
            // station). Opening cash for today is not zero in that case — it is
            // whatever cash the ledger already carries as of the day before, most
            // commonly posted via Accounting opening balances. Falling back to 0
            // here silently drops that cash from the first day's reconciliation.
            $cashAccountId = $this->cashAccountId($companyId);
            $balance = 0.0;

            if ($cashAccountId) {
                $row = DB::table('acct.journal_entries as je')
                    ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
                    ->where('t.company_id', $companyId)
                    ->where('je.account_id', $cashAccountId)
                    ->where('t.transaction_date', '<', $date)
                    ->whereIn('t.status', ['posted', 'locked'])
                    ->selectRaw('COALESCE(SUM(je.debit_amount), 0) - COALESCE(SUM(je.credit_amount), 0) as balance')
                    ->first();
                $balance = (float) ($row->balance ?? 0);
            }

            return [
                'date' => null,
                'closing_cash' => round($balance, 2),
                'exists' => false,
                'source' => 'ledger',
            ];
        }

        // Get the closing cash from metadata or calculate from entries
        $metadata = $previousClose->metadata ?? [];
        if (!is_array($metadata)) {
            $metadata = [];
        }

        return [
            'date' => $previousClose->transaction_date->toDateString(),
            'closing_cash' => (float) ($metadata['closing_cash'] ?? 0),
            'exists' => true,
            'source' => 'daily_close',
        ];
    }

    /**
     * Resolve the station's cash-on-hand account the same way resolveAccounts() does:
     * station settings first, then account code 1050, then any active cash-subtype account.
     */
    public function cashAccountId(string $companyId): ?string
    {
        $stationSettings = StationSettings::where('company_id', $companyId)->first();

        return $stationSettings?->cash_account_id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('code', '1050')->value('id')
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('subtype', 'cash')->orderBy('code')->value('id');
    }

    /**
     * Allocates an amount being paid to a vendor across that vendor's open bills, oldest
     * first, up to what is actually owed; anything left over is not applied to any bill and
     * is reported back as an advance (VendorAdvanceService::autoApply picks it up the moment
     * the vendor's next bill posts). Shared by every close-initiated vendor payment —
     * the card-channel supplier settlement above and DailyClosePaySupplierService — so the
     * oldest-first rule lives in exactly one place.
     *
     * @return array{allocations: array<int, array{bill_id: string, amount_allocated: float}>, applied_amount: float, advance_amount: float}
     */
    public function allocateOldestFirst(string $companyId, string $vendorId, float $amount): array
    {
        $openBills = Bill::where('company_id', $companyId)
            ->where('vendor_id', $vendorId)
            ->where('balance', '>', 0.000001)
            ->orderBy('bill_date')
            ->orderBy('created_at')
            ->get(['id', 'bill_number', 'balance']);
        $openBalance = round((float) $openBills->sum('balance'), 2);
        $appliedAmount = round(min($amount, $openBalance), 2);
        $advanceAmount = round($amount - $appliedAmount, 2);

        $allocations = [];
        $remaining = $appliedAmount;
        foreach ($openBills as $bill) {
            if ($remaining <= 0.000001) {
                break;
            }
            $take = round(min((float) $bill->balance, $remaining), 2);
            if ($take <= 0) {
                continue;
            }
            $allocations[] = ['bill_id' => $bill->id, 'amount_allocated' => $take];
            $remaining = round($remaining - $take, 2);
        }

        return ['allocations' => $allocations, 'applied_amount' => $appliedAmount, 'advance_amount' => $advanceAmount];
    }

    /**
     * The account(s) a fuel-station company's meter revenue can land on: every active fuel
     * item's own income_account_id, plus the station's fallback fuel_sales account. A plain
     * Accounting invoice needs at least one line on one of these to be recognised by the
     * close as fuel that went through a meter (see DailyCloseCreditSaleService::
     * pendingAccountingInvoiceDetails); used by the Create page's preview, before any nozzle
     * reading exists for the date being closed.
     */
    public function fuelRevenueAccountIds(string $companyId): array
    {
        $accounts = $this->resolveAccounts($companyId);
        $itemAccountIds = Item::where('company_id', $companyId)
            ->whereNotNull('fuel_category')
            ->whereNull('deleted_at')
            ->whereNotNull('income_account_id')
            ->pluck('income_account_id')
            ->all();

        return array_values(array_unique(array_filter(array_merge($itemAccountIds, [$accounts['fuel_sales']]))));
    }

    /**
     * Process the complete daily close.
     *
     * @param string $companyId
     * @param array $data
     * @param User $user
     * @param bool $isCorrection Whether this is a correction entry (allows multiple per date)
     */
    public function processDailyClose(string $companyId, array $data, User $user, bool $isCorrection = false): array
    {
        return \App\Services\AccountingWriteTransaction::run(function () use ($companyId, $data, $user, $isCorrection) {
            $date = $data['date'];

            // Owner's rule A means openings can silently come from a parked-but-unposted
            // previous day; posting this day on top of that would freeze figures nobody has
            // confirmed. Parking stays allowed (see DailyCloseReconciliationService::park) —
            // only posting is blocked here.
            $previousDate = date('Y-m-d', strtotime($date . ' -1 day'));
            if (app(DailyCloseReconciliationService::class)->parkedClosingFigures($companyId, $previousDate)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'date' => "Post {$previousDate} first — this day's openings come from it.",
                ]);
            }

            // Litres are worked out here, from the meters. See litresFromReadings().
            $data['nozzle_readings'] = $this->litresFromReadings($data['nozzle_readings'] ?? []);
            // Reserve the date before reading sources. Row triggers use nonblocking shared
            // locks and signal a whole-transaction retry instead of waiting while holding rows.
            DB::selectOne('select pg_advisory_xact_lock(hashtext(?), hashtext(?))', [$companyId, $date]);
            \App\Models\Company::whereKey($companyId)->firstOrFail();
            $declaredExpenses = $data['expenses'] ?? [];
            // Reserve/check the close before creating any canonical entries.
            $transactionNumber = $this->generateTransactionNumber($companyId, $date, $isCorrection);
            // Recorded so DailyCloseReopenService can find and delete exactly the transaction
            // each inline expense created -- see metadata['expense_transaction_ids'] below.
            $expenseTransactionIds = [];
            foreach ($declaredExpenses as $expense) {
                if ((float) ($expense['amount'] ?? 0) > 0) {
                    $expenseTransactionIds[] = app(DailyCloseEntryService::class)->expense($companyId, $date, $expense)->id;
                }
            }
            $data['expenses'] = [];

            // Supplier bills / fuel purchases entered inline become ordinary canonical bills
            // (and, when paid now, an ordinary bill payment) before sources() reads the date,
            // so they are picked up exactly like any other canonical activity below.
            $declaredPurchases = $data['purchases'] ?? [];
            $purchaseTransactionIds = [];
            // Recorded so DailyCloseReopenService can find and undo exactly what an inline
            // purchase created (the bill, its stock receipt, and any immediate payment) — see
            // metadata['purchase_details'] below. Never read by anything else.
            $purchaseDetails = [];
            foreach ($declaredPurchases as $purchase) {
                if (empty($purchase['supplier_id']) || empty($purchase['item_id']) || (float) ($purchase['quantity'] ?? 0) <= 0) {
                    continue;
                }
                $purchaseResult = app(DailyCloseEntryService::class)->purchase($companyId, $date, $purchase, $user);
                $purchaseTransactionIds[] = $purchaseResult['bill_transaction_id'];
                if ($purchaseResult['payment_transaction_id']) {
                    $purchaseTransactionIds[] = $purchaseResult['payment_transaction_id'];
                }
                $purchaseDetails[] = [
                    'bill_id' => $purchaseResult['bill_id'],
                    'bill_transaction_id' => $purchaseResult['bill_transaction_id'],
                    'payment_transaction_id' => $purchaseResult['payment_transaction_id'],
                ];
            }
            $data['purchases'] = [];

            $createdAmanat = []; $createdPartners = []; $createdAdvances = [];
            $reconciliation = app(DailyCloseReconciliationService::class);
            $canonicalSources = $reconciliation->sources($companyId, $date);
            foreach (array_filter($purchaseTransactionIds) as $purchaseTransactionId) {
                if (isset($canonicalSources['journal:'.$purchaseTransactionId])) {
                    $canonicalSources['journal:'.$purchaseTransactionId]['source'] = 'close_purchase';
                }
            }
            $externalCashIn = array_sum(array_column($canonicalSources, 'money_in'));
            $externalCashOut = array_sum(array_column($canonicalSources, 'money_out'));

            // Resolve all required accounts
            $accounts = $this->resolveAccounts($companyId);

            $currency = \App\Models\Company::whereKey($companyId)->value('base_currency') ?: 'PKR';

            $entries = [];
            $amanatDepositByAccount = [];
            $amanatWithdrawalByAccount = [];
            $amanatDepositsCashTotal = 0.0;
            $amanatWithdrawalsCashTotal = 0.0;
            $metadata = [
                'date' => $date,
                'opening_cash' => $data['opening_cash'],
                'closing_cash' => $data['closing_cash'],
                'readings_taken_at' => self::readingsTakenAt($companyId, $date),
            ];

            // ─────────────────────────────────────────────────────────────────
            // 1. Process Fuel Sales (from nozzle readings)
            // ─────────────────────────────────────────────────────────────────
            $totalRevenue = 0;
            $totalCogs = 0;
            $revenuePostings = [];
            $cogsPostings = [];
            $inventoryPostings = [];
            $salesByFuel = [];
            $nozzleReadingsData = [];
            $rateChangeSnapshots = $this->getRateChangeSnapshotsForDate($companyId, $date);
            $rateChangeSegments = [];
            $pumpTests = [];

            foreach ($data['nozzle_readings'] as $reading) {
                // Net of any returned-to-tank calibration litres - see litresFromReadings().
                $liters = (float) $reading['liters_sold'];
                $meterLiters = (float) ($reading['meter_liters'] ?? $liters);
                $returnedLiters = (float) ($reading['returned_liters'] ?? 0);

                // Get the item for cost calculation
                $item = Item::where('id', $reading['item_id'])
                    ->where('company_id', $companyId)
                    ->first();

                $saleRate = (float) $reading['sale_rate'];
                $avgCost = (float) ($item?->avg_cost ?? 0);

                $rateSplit = $this->calculateRateChangeSplit($reading, $rateChangeSnapshots[$reading['item_id']] ?? null, $saleRate);
                $revenue = $rateSplit['revenue'];
                $cogs = round($liters * $avgCost, 2);

                if ($liters > 0) {
                    $totalRevenue += $revenue;
                    $totalCogs += $cogs;

                    $fuelCategory = $item?->fuel_category ?? 'unknown';
                    $fuelLabel = $item?->name ?? str_replace('_', ' ', ucfirst($fuelCategory));
                    if (!isset($salesByFuel[$fuelCategory])) {
                        $salesByFuel[$fuelCategory] = ['liters' => 0, 'revenue' => 0, 'cogs' => 0];
                    }
                    $salesByFuel[$fuelCategory]['liters'] += $liters;
                    $salesByFuel[$fuelCategory]['revenue'] += $revenue;
                    $salesByFuel[$fuelCategory]['cogs'] += $cogs;

                    $this->addGroupedPosting(
                        $revenuePostings,
                        $item?->income_account_id ?: $accounts['fuel_sales'],
                        $revenue,
                        $fuelLabel
                    );

                    if ($cogs > 0) {
                        $this->addGroupedPosting(
                            $cogsPostings,
                            $item?->expense_account_id ?: $accounts['fuel_cogs'],
                            $cogs,
                            $fuelLabel
                        );
                        $this->addGroupedPosting(
                            $inventoryPostings,
                            $item?->asset_account_id ?: $accounts['fuel_inventory'],
                            $cogs,
                            $fuelLabel
                        );
                    }

                    if ($rateSplit['used_snapshot']) {
                        $rateChangeSegments[] = [
                            'item_id' => $reading['item_id'],
                            'item_name' => $item?->name,
                            'nozzle_id' => $reading['nozzle_id'],
                            'rate_change_id' => $rateSplit['rate_change_id'],
                            'snapshot_electronic_reading' => $rateSplit['snapshot_electronic_reading'],
                            'old_rate' => $rateSplit['old_rate'],
                            'new_rate' => $rateSplit['new_rate'],
                            'old_rate_liters' => $rateSplit['old_rate_liters'],
                            'new_rate_liters' => $rateSplit['new_rate_liters'],
                            'fallback_liters' => $rateSplit['fallback_liters'],
                            'revenue' => $rateSplit['revenue'],
                        ];
                    }
                }

                if ($returnedLiters > 0) {
                    $pumpTests[] = [
                        'nozzle_id' => $reading['nozzle_id'],
                        'fuel' => $item?->name ?? ($item?->fuel_category ?? 'Fuel'),
                        'liters' => $returnedLiters,
                    ];
                }

                // Store nozzle reading data for later save
                $nozzleReadingsData[] = [
                    'nozzle_id' => $reading['nozzle_id'],
                    'tank_id' => Nozzle::where('company_id', $companyId)->where('id', $reading['nozzle_id'])->value('tank_id'),
                    'unit_cost' => $avgCost,
                    'income_account_id' => $item?->income_account_id ?: $accounts['fuel_sales'],
                    'cogs_account_id' => $item?->expense_account_id ?: $accounts['fuel_cogs'],
                    'inventory_account_id' => $item?->asset_account_id ?: $accounts['fuel_inventory'],
                    'pricing' => $rateSplit,
                    'item_id' => $reading['item_id'],
                    'opening_electronic' => (float) $reading['opening_electronic'],
                    'closing_electronic' => (float) $reading['closing_electronic'],
                    'opening_manual' => isset($reading['opening_manual']) ? (float) $reading['opening_manual'] : null,
                    'closing_manual' => isset($reading['closing_manual']) ? (float) $reading['closing_manual'] : null,
                    'liters_dispensed' => $liters,
                    'meter_liters' => $meterLiters,
                    'returned_liters' => $returnedLiters,
                    'revenue' => $revenue,
                    'sale_rate' => $saleRate,
                    'rate_segments' => $rateSplit['segments'],
                ];
            }

            $metadata['fuel_sales'] = $salesByFuel;
            $metadata['rate_change_segments'] = $rateChangeSegments;
            $metadata['pump_tests'] = $pumpTests;
            $metadata['total_revenue'] = $totalRevenue;
            $metadata['total_cogs'] = $totalCogs;

            // ─────────────────────────────────────────────────────────────────
            // 2. Process Other Sales (Lubricants, etc.)
            // ─────────────────────────────────────────────────────────────────
            $otherSalesTotal = 0;
            $otherSalesDetails = [];
            if (!empty($data['other_sales'])) {
                foreach ($data['other_sales'] as $sale) {
                    // Derived here, never taken from the request.
                    //
                    // This used to post (float) $sale['amount'] - a figure computed in the
                    // browser - straight into a revenue posting, with quantity and unit_price
                    // validated, stored, and then ignored. Two things followed.
                    //
                    // A UI bug became a ledger bug. The lubricant rows stopped recalculating
                    // when quantity changed, so day one of the manual E2E would have posted
                    // 3,500 of lubricant revenue instead of 12,900 - and it would have
                    // balanced, because the close reconciles against the same wrong number.
                    // Wrong and self-consistent is the worst way for money to be wrong.
                    //
                    // And the three fields were independent as far as this method was
                    // concerned, so a request could carry a quantity and a price that did not
                    // multiply to the amount. other_sales_details then fed exactly that
                    // contradiction to ProductProfitabilityReportService.
                    //
                    // The client may still send an amount; it is simply not what gets posted.
                    $quantity = (float) ($sale['quantity'] ?? 0);
                    $unitPrice = (float) ($sale['unit_price'] ?? 0);
                    $amount = round($quantity * $unitPrice, 2);

                    $otherSalesTotal += $amount;
                    $item = Item::where('id', $sale['item_id'])
                        ->where('company_id', $companyId)
                        ->first();
                    $label = $item?->name ?? ($sale['item_name'] ?? 'Other sales');

                    if ($amount > 0) {
                        $this->addGroupedPosting(
                            $revenuePostings,
                            $item?->income_account_id ?: $accounts['fuel_sales'],
                            $amount,
                            $label
                        );
                    }

                    // Stored from the same three numbers that were posted, so the line can
                    // always be re-derived from what it records.
                    $otherSalesDetails[] = [
                        'item_id' => $sale['item_id'],
                        'item_name' => $sale['item_name'],
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'amount' => $amount,
                    ];
                }
            }
            $metadata['other_sales'] = $otherSalesTotal;
            $metadata['other_sales_details'] = $otherSalesDetails;
            $totalRevenue += $otherSalesTotal;

            // ─────────────────────────────────────────────────────────────────
            // 2b. Receive pending fuel deliveries before computing tank variance.
            // A fuel bill's litres are invisible to the dip until someone receives
            // them (delivery_mode 'requires_receiving' - see Product\SetupAction),
            // and a bill entered from Accounting -> Bills only gets received when
            // someone presses "Receive stock" on it. The close is the backstop:
            // on posting, it receives whatever belongs to the date being dipped so
            // a real delivery is never counted as a physical gain in the tank.
            // The stock movement is dated on the bill's own bill_date (not $date),
            // exactly like DailyCloseEntryService::purchase's inline receipt, so the
            // "today's receipts" query below (and the reconciliation service) picks
            // it up the same way it would any other receipt.
            // ─────────────────────────────────────────────────────────────────
            $deliveriesReceived = [];
            if (!empty($data['tank_readings'])) {
                foreach ($data['tank_readings'] as $tankData) {
                    $tank = Warehouse::where('company_id', $companyId)->find($tankData['tank_id']);
                    $itemId = $tank?->linked_item_id;
                    if (!$itemId) {
                        continue;
                    }

                    $baseline = $this->openingBaselineForTank($companyId, $tankData['tank_id'], $itemId, $date);
                    $afterDate = $baseline['has_baseline'] ? $baseline['date'] : null;
                    $pending = $this->pendingDeliveries($companyId, $tankData['tank_id'], $itemId, $afterDate, $date);

                    foreach ($pending as $delivery) {
                        app(CommandBus::class)->dispatch('bill.receive_goods', [
                            'id' => $delivery['bill_id'],
                            'receipt_date' => $delivery['bill_date'],
                            'lines' => [[
                                'line_id' => $delivery['line_id'],
                                'quantity' => $delivery['remaining'],
                                'warehouse_id' => $tankData['tank_id'],
                            ]],
                        // Part of posting the close, which already checked its own permission:
                        // a cashier who may close the day need not also be allowed to edit bills.
                        ], $user, true);

                        $deliveriesReceived[] = [
                            'bill_id' => $delivery['bill_id'],
                            'bill_number' => $delivery['bill_number'],
                            'bill_date' => $delivery['bill_date'],
                            'line_id' => $delivery['line_id'],
                            'tank' => $tank->name,
                            'tank_id' => $tankData['tank_id'],
                            'litres' => $delivery['remaining'],
                        ];
                    }
                }
            }
            $metadata['deliveries_received'] = $deliveriesReceived;

            // ─────────────────────────────────────────────────────────────────
            // 3. Process Tank Readings (calculate variance and save)
            // ─────────────────────────────────────────────────────────────────
            $tankVariances = [];
            $tankSnapshot = [];
            $stockReconciliations = [];
            $totalShrinkage = 0;
            $totalGain = 0;
            $shrinkageInventoryPostings = [];
            $gainInventoryPostings = [];

            if (!empty($data['tank_readings'])) {
                foreach ($data['tank_readings'] as $tankData) {
                    // Get the tank to find linked item
                    $tank = \App\Modules\Inventory\Models\Warehouse::where('company_id', $companyId)
                        ->find($tankData['tank_id']);
                    $itemId = $tank?->linked_item_id;

                    if (!$itemId) {
                        continue;
                    }

                    // Calculate system expected liters:
                    // Opening (previous closing dip, or stock baseline for first close) + Receipts - Sales = Expected
                    $openingBaseline = $this->openingBaselineForTank($companyId, $tankData['tank_id'], $itemId, $date);

                    if (! $openingBaseline['has_baseline']) {
                        $tankName = $tank?->name ?? 'this tank';
                        throw new \RuntimeException("No opening stock for {$tankName}. Record opening stock in Fuel setup (or post the previous day's close) before closing {$date}.");
                    }

                    $openingLiters = $openingBaseline['liters'];

                    // Get today's sales for this tank's item from nozzle readings and open/bulk product sales.
                    $todaysSales = 0;
                    foreach ($nozzleReadingsData as $nozzleData) {
                        if ($nozzleData['item_id'] === $itemId && $nozzleData['tank_id'] === $tankData['tank_id']) {
                            $todaysSales += $nozzleData['liters_dispensed'];
                        }
                    }
                    foreach ($otherSalesDetails as $sale) {
                        if (($sale['item_id'] ?? null) === $itemId) {
                            $todaysSales += (float) ($sale['quantity'] ?? 0);
                        }
                    }
                    $todaysReceipts = 0.0;
                    if ($openingBaseline['date']) {
                        $todaysReceipts = (float) StockMovement::where('company_id', $companyId)
                            ->where('warehouse_id', $tankData['tank_id'])
                            ->where('item_id', $itemId)
                            ->whereDate('movement_date', '>', $openingBaseline['date'])
                            ->whereDate('movement_date', '<=', $date)
                            ->where(function ($query) {
                                $query->whereNull('reference_type')
                                    ->orWhere('reference_type', '!=', 'fuel.daily_close');
                            })
                            ->sum('quantity');
                    }

                    $systemCalculatedLiters = round($openingLiters + $todaysReceipts - $todaysSales, 2);
                    $dipMeasurement = (float) $tankData['liters'];
                    $varianceLiters = round($dipMeasurement - $systemCalculatedLiters, 2);

                    // Determine variance type
                    $varianceType = 'none';
                    if ($varianceLiters < -0.5) {
                        $varianceType = 'loss';
                    } elseif ($varianceLiters > 0.5) {
                        $varianceType = 'gain';
                    }

                    // Get item for cost calculation
                    $item = Item::find($itemId);
                    $avgCost = (float) ($item?->avg_cost ?? 0);
                    $varianceAmount = round(abs($varianceLiters) * $avgCost, 2);

                    // Track variances for GL posting. The inventory side is resolved
                    // per item, the same way sales and COGS are: a diesel shrinkage
                    // must not credit the petrol inventory account. Previously every
                    // tank's variance collapsed onto the single fallback inventory
                    // account, which left per-product stock values misstated even
                    // though the journal still balanced.
                    $varianceInventoryAccount = $item?->asset_account_id ?: null;

                    if ($varianceType === 'loss' && $varianceAmount > 0) {
                        $totalShrinkage += $varianceAmount;
                        $shrinkageInventoryPostings[] = [
                            'account_id' => $varianceInventoryAccount,
                            'amount' => $varianceAmount,
                            'label' => $item?->name ?? 'Fuel',
                        ];
                        $tankVariances[] = [
                            'tank_name' => $tank->name,
                            'item_name' => $item?->name ?? 'Unknown',
                            'type' => 'loss',
                            'liters' => abs($varianceLiters),
                            'amount' => $varianceAmount,
                        ];
                    } elseif ($varianceType === 'gain' && $varianceAmount > 0) {
                        $totalGain += $varianceAmount;
                        $gainInventoryPostings[] = [
                            'account_id' => $varianceInventoryAccount,
                            'amount' => $varianceAmount,
                            'label' => $item?->name ?? 'Fuel',
                        ];
                        $tankVariances[] = [
                            'tank_name' => $tank->name,
                            'item_name' => $item?->name ?? 'Unknown',
                            'type' => 'gain',
                            'liters' => $varianceLiters,
                            'amount' => $varianceAmount,
                        ];
                    }

                    $ledgerLiters = (float) (StockLevel::where('company_id', $companyId)
                        ->where('warehouse_id', $tankData['tank_id'])
                        ->where('item_id', $itemId)
                        ->value('quantity') ?? $systemCalculatedLiters);
                    $ledgerDeltaToDip = round($dipMeasurement - $ledgerLiters, 3);

                    if (abs($ledgerDeltaToDip) >= 0.001) {
                        $totalCost = round(abs($ledgerDeltaToDip) * $avgCost, 2);
                        $stockReconciliations[] = [
                            'warehouse_id' => $tankData['tank_id'],
                            'item_id' => $itemId,
                            'quantity' => $ledgerDeltaToDip,
                            'unit_cost' => $avgCost,
                            'total_cost' => $ledgerDeltaToDip > 0 ? $totalCost : -$totalCost,
                            'movement_type' => $ledgerDeltaToDip > 0 ? 'adjustment_in' : 'adjustment_out',
                            'reason' => 'Daily close physical dip',
                            'notes' => "Daily close {$date}: stock ledger reconciled to physical tank dip",
                        ];
                    }

                    $tankSnapshot[] = ['tank_id' => $tankData['tank_id'], 'tank_name' => $tank->name,
                        'item_id' => $itemId, 'physical_liters' => $dipMeasurement,
                        'expected_liters' => $systemCalculatedLiters, 'variance_liters' => $varianceLiters,
                        'stick_reading' => $tankData['stick_reading'] ?? null,
                        'unit_cost' => $avgCost,
                        'inventory_account_id' => $item?->asset_account_id ?: $accounts['fuel_inventory']];
                    // Save tank reading with calculated values
                    TankReading::updateOrCreate(
                        [
                            'company_id' => $companyId,
                            'tank_id' => $tankData['tank_id'],
                            'reading_date' => $date,
                        ],
                        [
                            'item_id' => $itemId,
                            'reading_type' => 'closing',
                            'stick_reading' => $tankData['stick_reading'] ?? null,
                            'dip_measurement_liters' => $dipMeasurement,
                            'system_calculated_liters' => $systemCalculatedLiters,
                            'variance_liters' => $varianceLiters,
                            'variance_type' => $varianceType,
                            'status' => 'posted', // Marked as posted since it's part of daily close
                            'recorded_by_user_id' => $user->id,
                        ]
                    );
                }
            }

            $metadata['tank_variances'] = $tankVariances;
            $metadata['total_shrinkage'] = $totalShrinkage;
            $metadata['total_gain'] = $totalGain;
            $metadata['stock_reconciliations'] = $stockReconciliations;

            // ─────────────────────────────────────────────────────────────────
            // 4. Calculate Money In totals
            // ─────────────────────────────────────────────────────────────────
            $openingCash = (float) $data['opening_cash'];

            // Partner deposits (add to cash)
            $partnerDepositsTotal = 0;
            if (!empty($data['partner_deposits'])) {
                foreach ($data['partner_deposits'] as $deposit) {
                    $partnerDepositsTotal += (float) $deposit['amount'];

                    // Record partner investment
                    $createdPartners[] = PartnerTransaction::create([
                        'company_id' => $companyId,
                        'partner_id' => $deposit['partner_id'],
                        'transaction_date' => $date,
                        'transaction_type' => 'investment',
                        'amount' => $deposit['amount'],
                        'description' => 'Daily deposit',
                        'payment_method' => 'cash',
                        'recorded_by_user_id' => $user->id,
                    ]);
                }
            }
            $metadata['partner_deposits'] = $partnerDepositsTotal;

            // Amanat deposits/top-ups received in cash or a selected bank account.
            $amanatDepositsTotal = 0;
            $amanatDepositDetails = [];
            if (!empty($data['amanat_deposits'])) {
                foreach ($data['amanat_deposits'] as $deposit) {
                    $amount = (float) ($deposit['amount'] ?? 0);
                    if ($amount <= 0) {
                        continue;
                    }

                    $customerId = $deposit['customer_id'] ?? null;
                    if (!$customerId) {
                        throw new \RuntimeException('Select an Amanat depositor before posting a deposit.');
                    }

                    $profile = CustomerProfile::where('company_id', $companyId)
                        ->where('customer_id', $customerId)
                        ->where('is_amanat_holder', true)
                        ->with('customer:id,name')
                        ->lockForUpdate()
                        ->first();

                    if (!$profile) {
                        throw new \RuntimeException('Selected Amanat depositor was not found.');
                    }

                    $paymentAccount = $this->resolveAmanatPaymentAccount($companyId, $deposit['payment_account_id'] ?? null, $accounts['cash_on_hand']);
                    $amanatDepositByAccount[$paymentAccount->id] = ($amanatDepositByAccount[$paymentAccount->id] ?? 0) + $amount;
                    if ($paymentAccount->id === $accounts['cash_on_hand']) {
                        $amanatDepositsCashTotal += $amount;
                    }

                    $createdAmanat[] = AmanatTransaction::create([
                        'company_id' => $companyId,
                        'customer_id' => $customerId,
                        'transaction_type' => AmanatTransaction::TYPE_DEPOSIT,
                        'amount' => $amount,
                        'payment_account_id' => $paymentAccount->id,
                        'reference' => $deposit['reference'] ?? 'Daily close ' . $date,
                        'notes' => $deposit['notes'] ?? 'Daily close Amanat deposit',
                        'recorded_by_user_id' => $user->id,
                    ]);

                    $profile->adjustAmanatBalance($amount);

                    $amanatDepositsTotal += $amount;
                    $amanatDepositDetails[] = [
                        'customer_id' => $customerId,
                        'customer_name' => $profile->customer?->name ?? ($deposit['customer_name'] ?? null),
                        'amount' => $amount,
                        'payment_account_id' => $paymentAccount->id,
                        'payment_account_name' => $paymentAccount->name,
                        'reference' => $deposit['reference'] ?? null,
                    ];
                }
            }
            $metadata['amanat_deposits'] = $amanatDepositsTotal;
            $metadata['amanat_deposit_details'] = $amanatDepositDetails;

            // Controlled other cash-in deposits.
            $otherDepositsTotal = 0;
            $otherDepositPostings = [];
            $otherDepositDetails = [];
            if (!empty($data['other_deposits'])) {
                foreach ($data['other_deposits'] as $deposit) {
                    $amount = (float) ($deposit['amount'] ?? 0);
                    if ($amount <= 0) {
                        continue;
                    }

                    $type = $deposit['deposit_type'] ?? null;
                    $accountId = $deposit['account_id'] ?? null;
                    $label = match ($type) {
                        'loss_compensation' => 'Loss compensation',
                        'fuel_disbursement' => 'Fuel disbursement recovery',
                        'misc_income' => 'Other cash income',
                        default => throw new \RuntimeException('Invalid cash-in deposit type.'),
                    };

                    if ($type === 'loss_compensation') {
                        $accountId = $accounts['cash_over_short'];
                    } elseif ($type === 'fuel_disbursement') {
                        $accountId = $accountId ?: $accounts['fuel_sales'];
                    }

                    if (!$accountId) {
                        throw new \RuntimeException("Select an account for {$label}.");
                    }

                    $accountAllowed = Account::where('company_id', $companyId)
                        ->where('id', $accountId)
                        ->where('is_active', true)
                        ->whereNull('deleted_at')
                        ->whereIn('type', ['revenue', 'other_income', 'liability', 'equity'])
                        ->exists();

                    if (!$accountAllowed && $type !== 'loss_compensation') {
                        throw new \RuntimeException("Selected account is not valid for {$label}.");
                    }

                    $otherDepositsTotal += $amount;
                    if (!isset($otherDepositPostings[$accountId])) {
                        $otherDepositPostings[$accountId] = [
                            'amount' => 0,
                            'labels' => [],
                        ];
                    }
                    $otherDepositPostings[$accountId]['amount'] += $amount;
                    $otherDepositPostings[$accountId]['labels'][] = $label;
                    $otherDepositDetails[] = [
                        'deposit_type' => $type,
                        'account_id' => $accountId,
                        'description' => $deposit['description'] ?? $label,
                        'amount' => $amount,
                    ];
                }
            }
            $metadata['other_deposits'] = $otherDepositsTotal;
            $metadata['other_deposit_details'] = $otherDepositDetails;

            // ─────────────────────────────────────────────────────────────────
            // 4b. Process Dynamic Payment Channels (non-cash receipts)
            // ─────────────────────────────────────────────────────────────────
            $paymentReceiptsTotals = [];
            $paymentReceiptPostings = [];
            $totalNonCashReceipts = 0;
            $bankTransfersTotal = 0;
            $cardSwipesTotal = 0;
            $fuelCardsTotal = 0;

            if (!empty($data['payment_receipts'])) {
                // Get station settings to understand channel types
                $stationSettings = StationSettings::where('company_id', $companyId)->first();
                $paymentChannels = $stationSettings?->payment_channels ?? [];
                $channelMap = [];
                foreach ($paymentChannels as $ch) {
                    $channelMap[$ch['code']] = $ch;
                }

                foreach ($data['payment_receipts'] as $channelCode => $channelData) {
                    $receiptEntries = $channelData['entries'] ?? [];
                    $channelTotal = 0;

                    foreach ($receiptEntries as $entry) {
                        $channelTotal += (float) ($entry['amount'] ?? 0);
                    }

                    $paymentReceiptsTotals[$channelCode] = $channelTotal;
                    if ($channelTotal <= 0) {
                        continue;
                    }

                    // Categorize by type for GL posting
                    $channel = $channelMap[$channelCode] ?? [
                        'code' => $channelCode,
                        'label' => $channelCode,
                        'type' => 'bank_transfer',
                    ];
                    $channelType = $channel['type'] ?? 'bank_transfer';
                    $destinationAccountId = $this->resolvePaymentChannelAccount($channel, $accounts);

                    if ($channelType !== 'cash') {
                        $totalNonCashReceipts += $channelTotal;
                        $paymentReceiptPostings[] = [
                            'channel_code' => $channelCode,
                            'channel_label' => $channel['label'] ?? $channelCode,
                            'channel_type' => $channelType,
                            'account_id' => $destinationAccountId,
                            'amount' => $channelTotal,
                        ];
                    }

                    if ($channelType === 'bank_transfer') {
                        $bankTransfersTotal += $channelTotal;
                    } elseif ($channelType === 'card_pos') {
                        $cardSwipesTotal += $channelTotal;
                    } elseif ($channelType === 'fuel_card') {
                        $fuelCardsTotal += $channelTotal;
                    } elseif ($channelType === 'mobile_wallet') {
                        // Mobile wallets typically go to bank
                        $bankTransfersTotal += $channelTotal;
                    }
                }
            }

            $metadata['payment_receipts'] = $paymentReceiptsTotals;
            $metadata['payment_receipt_postings'] = $paymentReceiptPostings;
            $metadata['bank_transfers_received'] = $bankTransfersTotal;
            $metadata['card_swipes'] = $cardSwipesTotal;
            $metadata['fuel_cards'] = $fuelCardsTotal;

            // ─────────────────────────────────────────────────────────────────
            // 4c. Channels that settle straight to a supplier (e.g. a fuel-card vendor
            // like Parco): the sale already landed in the channel's clearing account
            // above, unchanged. Here the close additionally pays that vendor out of
            // clearing, oldest bill first, for whatever the vendor is actually owed —
            // never more than the card sales, never more than the open balance. This
            // is an ordinary supplier payment (Dr AP / Cr clearing), posted through the
            // same bill_payment.create command the Bills module uses, so it shows on
            // the vendor's statement exactly like any other payment. It never touches
            // the cash drawer, so the close's cash reconciliation is unaffected.
            // ─────────────────────────────────────────────────────────────────
            $channelSupplierSettlements = [];
            foreach ($paymentReceiptPostings as $posting) {
                $channel = $channelMap[$posting['channel_code']] ?? null;
                if (!$channel || ($channel['settles_to'] ?? 'clearing') !== 'supplier') {
                    continue;
                }

                $vendorId = $channel['settles_to_vendor_id'] ?? null;
                $clearingAccountId = $posting['account_id'];
                $channelTotal = round((float) $posting['amount'], 2);
                if (!$vendorId || !$clearingAccountId || $channelTotal <= 0) {
                    continue;
                }

                $vendor = Vendor::where('company_id', $companyId)->find($vendorId);
                if (!$vendor) {
                    continue;
                }
                $clearingAccount = Account::where('company_id', $companyId)->find($clearingAccountId);

                // The FULL card total always leaves clearing for this vendor now, whether or
                // not it covers every open bill: whatever exceeds the open balance is not an
                // error, it's an advance -- money the vendor is holding on account, applied
                // automatically (VendorAdvanceService::autoApply) the moment its next bill
                // posts. Clearing therefore always nets to zero for a supplier-settled
                // channel, never leaves an "excess parked in clearing" balance behind.
                ['allocations' => $allocations, 'applied_amount' => $appliedAmount, 'advance_amount' => $advanceAmount]
                    = $this->allocateOldestFirst($companyId, $vendorId, $channelTotal);

                $notes = "{$posting['channel_label']} settlement — Daily Close {$transactionNumber}";
                if ($advanceAmount > 0.004) {
                    $notes .= '. ' . number_format($advanceAmount, 2) . " of {$posting['channel_label']} sales are held as an advance with {$vendor->name}.";
                }

                $paymentResult = app(CommandBus::class)->dispatch('bill_payment.create', [
                    'vendor_id' => $vendorId,
                    'payment_date' => $date,
                    'amount' => $channelTotal,
                    'currency' => $currency,
                    'base_currency' => $currency,
                    'payment_method' => 'other',
                    'payment_account_id' => $clearingAccountId,
                    'ap_account_id' => $vendor->ap_account_id,
                    'allocations' => $allocations,
                    'notes' => $notes,
                    'allow_clearing_account' => true,
                // Part of posting the close, which already checked its own permission —
                // same reasoning as the bill.receive_goods dispatch above.
                ], $user, true);

                $channelSupplierSettlements[] = [
                    'channel_code' => $posting['channel_code'],
                    'channel_label' => $posting['channel_label'],
                    'vendor_id' => $vendorId,
                    'vendor_name' => $vendor->name,
                    'clearing_account_id' => $clearingAccountId,
                    'clearing_account_name' => $clearingAccount?->name ?? 'clearing',
                    'card_sales' => $channelTotal,
                    'amount_paid' => $channelTotal,
                    'applied_to_bills' => $appliedAmount,
                    'advance_amount' => $advanceAmount,
                    'bill_payment_id' => $paymentResult['data']['id'] ?? null,
                ];
            }
            $metadata['channel_supplier_settlements'] = $channelSupplierSettlements;

            // Re-read canonical sources now that this close may have posted its own
            // supplier settlement (like the close_purchase tagging above, done once
            // sources() has something to tag) -- otherwise a same-day reconciliation
            // view would flag the close's own settlement payment as unexplained
            // "Added to this business date after posting" activity.
            if (!empty($channelSupplierSettlements)) {
                $refreshedSources = $reconciliation->sources($companyId, $date);
                foreach ($channelSupplierSettlements as $settlement) {
                    if (empty($settlement['bill_payment_id'])) {
                        continue;
                    }
                    $paidTransactionId = BillPayment::where('company_id', $companyId)
                        ->whereKey($settlement['bill_payment_id'])->value('transaction_id');
                    if ($paidTransactionId && isset($refreshedSources['journal:'.$paidTransactionId])) {
                        $canonicalSources['journal:'.$paidTransactionId] = $refreshedSources['journal:'.$paidTransactionId];
                        $canonicalSources['journal:'.$paidTransactionId]['source'] = 'close_supplier_settlement';
                    }
                }
            }

            // ─────────────────────────────────────────────────────────────────
            // 4d. "Pay supplier" rows entered directly in this Cash Out section: a supplier,
            // an amount, and the account it is paid from (defaulting to the station cash
            // drawer). Each becomes an ordinary bill_payment.create, allocated oldest-first
            // to that vendor's open bills — the remainder is left as an advance.
            // ─────────────────────────────────────────────────────────────────
            $paySupplierDetails = app(DailyClosePaySupplierService::class)->prepare(
                $companyId, $date, $data['pay_suppliers'] ?? [], $user, $this
            );
            $paySuppliersTotal = round(array_sum(array_column($paySupplierDetails, 'amount')), 2);
            $cashPaySuppliersTotal = round(array_sum(array_map(
                fn ($detail) => $detail['affects_cash_drawer'] ? $detail['amount'] : 0,
                $paySupplierDetails
            )), 2);
            $metadata['pay_suppliers_total'] = $paySuppliersTotal;
            $metadata['cash_pay_suppliers'] = $cashPaySuppliersTotal;
            $metadata['pay_supplier_details'] = $paySupplierDetails;

            // Unlike the "Supplier Bill Payments recorded elsewhere" sweep below (which posts
            // straight into this close's own journal via $entries, since those payments have no
            // transaction of their own yet), a Pay Supplier row posts through its own, separately
            // balanced bill_payment.create transaction -- same as the card-channel supplier
            // settlement above. A cash-drawer row's cash effect is therefore external to this
            // journal's own $entries and must be folded into $externalCashOut so the "Cash on
            // Hand net change" line below and expectedClosing/variance both see it; otherwise the
            // drawer would appear to have lost cash this journal's own entries cannot explain.
            $externalCashOut += $cashPaySuppliersTotal;

            // Same reasoning as the card-channel supplier settlement above: tag these
            // close-initiated payments' own postings as this close's own source, so a later
            // reconciliation view never lists them as "recorded on other screens".
            if (!empty($paySupplierDetails)) {
                $refreshedSources = $reconciliation->sources($companyId, $date);
                foreach ($paySupplierDetails as $detail) {
                    if (empty($detail['payment_id'])) {
                        continue;
                    }
                    $paidTransactionId = BillPayment::where('company_id', $companyId)
                        ->whereKey($detail['payment_id'])->value('transaction_id');
                    if ($paidTransactionId && isset($refreshedSources['journal:'.$paidTransactionId])) {
                        $canonicalSources['journal:'.$paidTransactionId] = $refreshedSources['journal:'.$paidTransactionId];
                        $canonicalSources['journal:'.$paidTransactionId]['source'] = 'close_pay_supplier';
                    }
                }
            }

            // ─────────────────────────────────────────────────────────────────
            // 5. Calculate Money Out totals
            // ─────────────────────────────────────────────────────────────────

            // Bank-to-drawer transfers are Money In, never sales revenue.
            $bankWithdrawalsTotal = 0.0;
            $bankWithdrawalsByAccount = [];
            foreach ($data['bank_withdrawals'] ?? [] as $index => $withdrawal) {
                $amount = round((float) ($withdrawal['amount'] ?? 0), 2);
                $bank = Account::where('company_id', $companyId)->where('is_active', true)
                    ->where('type', 'asset')->where('subtype', 'bank')
                    ->where('currency', $currency)
                    ->find($withdrawal['bank_account_id'] ?? null);
                if (!$bank || $amount <= 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "bank_withdrawals.{$index}.bank_account_id" => 'Choose an active bank account in the company currency and enter a positive amount.',
                    ]);
                }
                $bankWithdrawalsTotal += $amount;
                $bankWithdrawalsByAccount[$bank->id] = ($bankWithdrawalsByAccount[$bank->id] ?? 0) + $amount;
            }
            $metadata['bank_withdrawals'] = $bankWithdrawalsTotal;
            $metadata['bank_withdrawals_by_account'] = $bankWithdrawalsByAccount;

            // Bank deposits
            $bankDepositsTotal = 0;
            $bankDepositsByAccount = [];
            if (!empty($data['bank_deposits'])) {
                foreach ($data['bank_deposits'] as $deposit) {
                    $amount = (float) $deposit['amount'];
                    if ($amount <= 0) {
                        continue;
                    }

                    $bankDepositsTotal += $amount;
                    $accountId = $deposit['bank_account_id'] ?? $accounts['operating_bank'];
                    if (!isset($bankDepositsByAccount[$accountId])) {
                        $bankDepositsByAccount[$accountId] = 0;
                    }
                    $bankDepositsByAccount[$accountId] += $amount;
                }
            }
            $metadata['bank_deposits'] = $bankDepositsTotal;
            $metadata['bank_deposits_by_account'] = $bankDepositsByAccount;

            // Partner withdrawals
            $partnerWithdrawalsTotal = 0;
            if (!empty($data['partner_withdrawals'])) {
                foreach ($data['partner_withdrawals'] as $withdrawal) {
                    $amount = (float) $withdrawal['amount'];
                    $partnerWithdrawalsTotal += $amount;

                    // Record partner withdrawal
                    $partner = Partner::find($withdrawal['partner_id']);
                    if ($partner) {
                        // Note: Drawing limit is informational only, not a blocker
                        // The limit is shown in UI for awareness but doesn't prevent withdrawal

                        $createdPartners[] = PartnerTransaction::create([
                            'company_id' => $companyId,
                            'partner_id' => $withdrawal['partner_id'],
                            'transaction_date' => $date,
                            'transaction_type' => 'withdrawal',
                            'amount' => $amount,
                            'description' => 'Daily withdrawal',
                            'payment_method' => 'cash',
                            'recorded_by_user_id' => $user->id,
                        ]);

                        // Update current period withdrawn
                        $partner->increment('current_period_withdrawn', $amount);
                    }
                }
            }
            $metadata['partner_withdrawals'] = $partnerWithdrawalsTotal;

            // Employee advances
            $employeeAdvancesTotal = 0;
            if (!empty($data['employee_advances'])) {
                // Set RLS context for pay schema (salary_advances table has RLS enabled)
                DB::select("SELECT set_config('app.current_company_id', ?, false)", [$companyId]);
                DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);

                foreach ($data['employee_advances'] as $advance) {
                    $amount = (float) $advance['amount'];
                    $employeeAdvancesTotal += $amount;

                    // Create salary advance record
                    $createdAdvances[] = SalaryAdvance::create([
                        'company_id' => $companyId,
                        'employee_id' => $advance['employee_id'],
                        'advance_date' => $date,
                        'amount' => $amount,
                        'amount_outstanding' => $amount,
                        'reason' => $advance['reason'] ?? 'Daily advance',
                        'status' => 'pending',
                        'payment_method' => 'cash',
                        'recorded_by_user_id' => $user->id,
                    ]);
                }
            }
            $metadata['employee_advances'] = $employeeAdvancesTotal;

            // Approved salary payouts paid from station cash.
            $payrollPayoutsTotal = 0;
            $payrollPayoutDetails = [];
            $payrollPayoutIds = [];
            if (!empty($data['payroll_payouts'])) {
                DB::select("SELECT set_config('app.current_company_id', ?, false)", [$companyId]);
                DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);

                foreach ($data['payroll_payouts'] as $payout) {
                    $amount = round((float) ($payout['amount'] ?? 0), 2);
                    if ($amount <= 0) {
                        continue;
                    }

                    $payslip = Payslip::where('company_id', $companyId)
                        // By the period's pay day, not the day of approval - see Payslip::payableOn.
                        ->payableOn($date)
                        ->with('employee:id,first_name,last_name,employee_number')
                        ->lockForUpdate()
                        ->find($payout['payslip_id'] ?? null);

                    if (!$payslip) {
                        throw new \RuntimeException('One approved salary payout is no longer available for this daily close.');
                    }

                    $netPay = round((float) $payslip->net_pay, 2);
                    if (abs($amount - $netPay) > 0.01) {
                        throw new \RuntimeException("Salary payout for {$payslip->payslip_number} must equal remaining net salary.");
                    }

                    $payrollPayoutsTotal = round($payrollPayoutsTotal + $netPay, 2);
                    $payrollPayoutIds[] = $payslip->id;
                    $payrollPayoutDetails[] = [
                        'payslip_id' => $payslip->id,
                        'payslip_number' => $payslip->payslip_number,
                        'employee_id' => $payslip->employee_id,
                        'employee_name' => trim(($payslip->employee?->first_name ?? '') . ' ' . ($payslip->employee?->last_name ?? '')) ?: 'Employee',
                        'employee_number' => $payslip->employee?->employee_number,
                        'amount' => $netPay,
                    ];
                }
            }
            $metadata['payroll_payouts'] = $payrollPayoutsTotal;
            $metadata['payroll_payout_details'] = $payrollPayoutDetails;

            // Amanat disbursements paid from cash or a selected bank account.
            $amanatTotal = 0;
            $amanatDetails = [];
            if (!empty($data['amanat_disbursements'])) {
                foreach ($data['amanat_disbursements'] as $amanat) {
                    $amount = (float) $amanat['amount'];
                    if ($amount <= 0) {
                        continue;
                    }

                    $customerId = $amanat['customer_id'] ?? null;
                    if (!$customerId) {
                        throw new \RuntimeException('Select an Amanat depositor before posting a disbursement.');
                    }

                    $profile = CustomerProfile::where('company_id', $companyId)
                        ->where('customer_id', $customerId)
                        ->where('is_amanat_holder', true)
                        ->with('customer:id,name')
                        ->lockForUpdate()
                        ->first();

                    if (!$profile) {
                        throw new \RuntimeException('Selected Amanat depositor was not found.');
                    }

                    $paymentAccount = $this->resolveAmanatPaymentAccount($companyId, $amanat['payment_account_id'] ?? null, $accounts['cash_on_hand']);
                    $amanatWithdrawalByAccount[$paymentAccount->id] = ($amanatWithdrawalByAccount[$paymentAccount->id] ?? 0) + $amount;
                    if ($paymentAccount->id === $accounts['cash_on_hand']) {
                        $amanatWithdrawalsCashTotal += $amount;
                    }

                    if ($amount > (float) $profile->amanat_balance) {
                        $name = $profile->customer?->name ?? 'Selected Amanat depositor';
                        throw new \RuntimeException("{$name} has only {$profile->amanat_balance} available in Amanat.");
                    }

                    $createdAmanat[] = AmanatTransaction::create([
                        'company_id' => $companyId,
                        'customer_id' => $customerId,
                        'transaction_type' => AmanatTransaction::TYPE_WITHDRAWAL,
                        'amount' => $amount,
                        'payment_account_id' => $paymentAccount->id,
                        'reference' => 'Daily close ' . $date,
                        'notes' => $amanat['notes'] ?? 'Daily close cash disbursement',
                        'recorded_by_user_id' => $user->id,
                    ]);

                    $profile->adjustAmanatBalance(-$amount);

                    $amanatTotal += $amount;
                    $amanatDetails[] = [
                        'customer_id' => $customerId,
                        'customer_name' => $profile->customer?->name ?? ($amanat['customer_name'] ?? null),
                        'amount' => $amount,
                        'payment_account_id' => $paymentAccount->id,
                        'payment_account_name' => $paymentAccount->name,
                    ];
                }
            }
            $metadata['amanat_disbursements'] = $amanatTotal;
            $metadata['amanat_disbursement_details'] = $amanatDetails;

            // Expenses
            $expensesTotal = 0;
            $expensesByAccount = [];
            if (!empty($data['expenses'])) {
                foreach ($data['expenses'] as $index => $expense) {
                    if (!isset($expense['account_id']) || empty($expense['account_id'])) {
                        \Log::warning("DailyClose: Skipping expense at index {$index} - missing account_id", $expense);
                        continue;
                    }
                    $amount = (float) ($expense['amount'] ?? 0);
                    if ($amount <= 0) {
                        continue;
                    }
                    $expensesTotal += $amount;

                    $accountId = $expense['account_id'];
                    if (!isset($expensesByAccount[$accountId])) {
                        $expensesByAccount[$accountId] = 0;
                    }
                    $expensesByAccount[$accountId] += $amount;
                }
            }
            $metadata['expenses'] = array_sum(array_map(fn ($source) => ($source['type'] ?? null) === 'expense' ? ($source['money_out'] ?? 0) : 0, $canonicalSources));

            // Supplier bill payments recorded elsewhere are still posted through Daily Close.
            $billPaymentIds = [];
            $billPaymentDetails = [];
            $billPaymentPostings = [];
            $billPaymentsTotal = 0;
            $cashBillPaymentsTotal = 0;
            $nonCashBillPaymentsTotal = 0;

            $billPayments = $this->getPendingBillPaymentsForDate($companyId, $date);
            foreach ($billPayments as $payment) {
                $amount = round((float) $payment->amount, 2);
                if ($amount <= 0) {
                    continue;
                }

                if (!$payment->payment_account_id) {
                    throw new \RuntimeException("Payment {$payment->payment_number} is missing a payment account.");
                }

                $apAccountId = $payment->vendor?->ap_account_id ?: $accounts['accounts_payable'];
                if (!$apAccountId) {
                    throw new \RuntimeException("Payment {$payment->payment_number} needs an Accounts Payable account.");
                }

                $affectsCashDrawer = $payment->payment_account_id === $accounts['cash_on_hand'];
                $billNumbers = $payment->allocations
                    ->map(fn ($allocation) => $allocation->bill?->bill_number)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $billPaymentIds[] = $payment->id;
                $billPaymentsTotal = round($billPaymentsTotal + $amount, 2);
                if ($affectsCashDrawer) {
                    $cashBillPaymentsTotal = round($cashBillPaymentsTotal + $amount, 2);
                } else {
                    $nonCashBillPaymentsTotal = round($nonCashBillPaymentsTotal + $amount, 2);
                }

                $billPaymentPostings[] = [
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'ap_account_id' => $apAccountId,
                    'payment_account_id' => $payment->payment_account_id,
                    'affects_cash_drawer' => $affectsCashDrawer,
                    'amount' => $amount,
                ];

                $billPaymentDetails[] = [
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'payment_group_number' => $payment->payment_group_number,
                    'vendor_id' => $payment->vendor_id,
                    'vendor_name' => $payment->vendor?->name ?? 'Supplier',
                    'payment_account_id' => $payment->payment_account_id,
                    'payment_account_name' => trim(($payment->paymentAccount?->code ? $payment->paymentAccount->code . ' — ' : '') . ($payment->paymentAccount?->name ?? 'Payment account')),
                    'payment_method' => $payment->payment_method,
                    'amount' => $amount,
                    'bill_numbers' => $billNumbers,
                    'affects_cash_drawer' => $affectsCashDrawer,
                ];
            }

            $metadata['bill_payments'] = $billPaymentsTotal;
            $metadata['cash_bill_payments'] = $cashBillPaymentsTotal;
            $metadata['non_cash_bill_payments'] = $nonCashBillPaymentsTotal;
            $metadata['bill_payment_details'] = $billPaymentDetails;

            // ─────────────────────────────────────────────────────────────────
            // 6. Build Journal Entries
            // ─────────────────────────────────────────────────────────────────

            // Cash from sales (total revenue goes to cash initially)
            // Fuel revenue accounts: whichever account(s) this close itself just credited for
            // meter fuel revenue above (per item income_account_id, falling back to the
            // station's fuel_sales account) -- the same accounts a plain Accounting invoice
            // must land on to be recognised as fuel that went through a meter today.
            $fuelRevenueAccountIds = array_values(array_unique(array_filter(array_merge(
                array_column($nozzleReadingsData, 'income_account_id'),
                [$accounts['fuel_sales']]
            ))));
            $creditDetails = app(DailyCloseCreditSaleService::class)->prepare(
                $companyId, $date, $data['credit_sales'] ?? [], $totalRevenue - $otherSalesTotal,
                $totalRevenue - $totalNonCashReceipts, $user, $fuelRevenueAccountIds
            );
            $creditTotal = round(array_sum(array_column($creditDetails, 'amount')), 2);
            $metadata['credit_sales_total'] = $creditTotal;
            $metadata['credit_sale_details'] = $creditDetails;
            $cashFromSales = $totalRevenue - $totalNonCashReceipts - $creditTotal;

            // Payments received: a buyer settling a credit invoice, entered inline instead
            // of at /payments. Each row is posted through the existing Payment\CreateAction
            // (see DailyClosePaymentsReceivedService); a cash-account row raises expected
            // drawer cash exactly like a standalone payment would, a bank-account row does
            // not.
            $paymentsReceivedDetails = app(DailyClosePaymentsReceivedService::class)->prepare(
                $companyId, $date, $data['payments_received'] ?? [], $user
            );
            $paymentsReceivedCashTotal = round(array_sum(array_map(
                fn ($detail) => $detail['affects_cash_drawer'] ? $detail['amount'] : 0,
                $paymentsReceivedDetails
            )), 2);
            $metadata['payments_received_total'] = round(array_sum(array_column($paymentsReceivedDetails, 'amount')), 2);
            $metadata['payments_received_details'] = $paymentsReceivedDetails;

            $totalCashIn = $openingCash + $bankWithdrawalsTotal + $partnerDepositsTotal + $amanatDepositsCashTotal + $otherDepositsTotal + $cashFromSales;
            $totalCashOut = $bankDepositsTotal + $partnerWithdrawalsTotal + $employeeAdvancesTotal + $payrollPayoutsTotal + $amanatWithdrawalsCashTotal + $expensesTotal + $cashBillPaymentsTotal;
            $totalCashIn += $paymentsReceivedCashTotal;

            // Debit: Cash on Hand (opening + deposits + cash sales - withdrawals)
            $closingCash = (float) $data['closing_cash'];
            $expectedClosing = $totalCashIn - $totalCashOut + $externalCashIn - $externalCashOut;
            $variance = round($closingCash - $expectedClosing, 2);

            $metadata['expected_closing'] = $expectedClosing;
            $metadata['variance'] = $variance;

            // Store original form input for amendment pre-filling
            $metadata['form_input'] = [
                'nozzle_readings' => $data['nozzle_readings'] ?? [],
                'other_sales' => $data['other_sales'] ?? [],
                'tank_readings' => $data['tank_readings'] ?? [],
                'opening_cash' => $data['opening_cash'],
                'partner_deposits' => $data['partner_deposits'] ?? [],
                'amanat_deposits' => $data['amanat_deposits'] ?? [],
                'other_deposits' => $data['other_deposits'] ?? [],
                'payment_receipts' => $data['payment_receipts'] ?? [],
                'credit_sales' => $data['credit_sales'] ?? [],
                'payments_received' => $data['payments_received'] ?? [],
                'bank_deposits' => $data['bank_deposits'] ?? [],
                'bank_withdrawals' => $data['bank_withdrawals'] ?? [],
                'partner_withdrawals' => $data['partner_withdrawals'] ?? [],
                'employee_advances' => $data['employee_advances'] ?? [],
                'payroll_payouts' => $payrollPayoutDetails,
                'bill_payments' => $billPaymentDetails,
                'pay_suppliers' => $data['pay_suppliers'] ?? [],
                'amanat_disbursements' => $data['amanat_disbursements'] ?? [],
                'expenses' => $declaredExpenses,
                'purchases' => $declaredPurchases,
                'closing_cash' => $data['closing_cash'],
                'notes' => $data['notes'] ?? null,
            ];
            // What each inline purchase/expense row actually created -- DailyCloseReopenService's
            // own lookup, never read by anything that renders the close.
            $metadata['purchase_details'] = $purchaseDetails;
            $metadata['expense_transaction_ids'] = $expenseTransactionIds;

            // Revenue entries. Prefer product-level mappings; station settings are fallback defaults.
            foreach ($revenuePostings as $posting) {
                $entries[] = [
                    'account_id' => $posting['account_id'],
                    'type' => 'credit',
                    'amount' => round($posting['amount'], 2),
                    'description' => 'Daily sales - ' . implode(', ', $posting['labels']),
                ];
            }

            // COGS entries. Prefer product-level mappings; station settings are fallback defaults.
            foreach ($cogsPostings as $posting) {
                $entries[] = [
                    'account_id' => $posting['account_id'],
                    'type' => 'debit',
                    'amount' => round($posting['amount'], 2),
                    'description' => 'Cost of goods sold - ' . implode(', ', $posting['labels']),
                ];
            }

            foreach ($inventoryPostings as $posting) {
                $entries[] = [
                    'account_id' => $posting['account_id'],
                    'type' => 'credit',
                    'amount' => round($posting['amount'], 2),
                    'description' => 'Inventory reduction - ' . implode(', ', $posting['labels']),
                ];
            }

            // Cash on hand (net change)
            $accountingInvoicesIncluded = [];
            foreach ($creditDetails as $credit) {
                if (($credit['source'] ?? null) === 'accounting_invoice') {
                    // Already booked its own AR when the invoice posted -- debiting AR again
                    // here would double it. Instead, take the revenue back out of the invoice's
                    // own income account(s), so this close's meter revenue stands alone.
                    foreach (($credit['income_lines'] ?? []) as $incomeAccountId => $amount) {
                        if ($amount <= 0) {
                            continue;
                        }
                        $entries[] = ['account_id' => $incomeAccountId, 'type' => 'debit',
                            'amount' => round((float) $amount, 2), 'description' => 'Invoiced in Accounting — '.$credit['invoice_number'].' — '.$credit['customer_name']];
                    }
                    $accountingInvoicesIncluded[] = [
                        'invoice_id' => $credit['invoice_id'],
                        'invoice_number' => $credit['invoice_number'],
                        'customer' => $credit['customer_name'],
                        'amount' => $credit['amount'],
                    ];
                    continue;
                }
                // A discounted manual row invoices at net; the discount itself is booked
                // below as its own contra-revenue debit, using the same account
                // FuelSaleService::postDiscount posts to, so Dr AR (net) + Dr Sales
                // Discounts (discount) together equal the gross this row removes from
                // expected drawer cash.
                $netAmount = $credit['net_amount'] ?? $credit['amount'];
                $entries[] = ['account_id' => $credit['ar_account_id'], 'type' => 'debit',
                    'amount' => $netAmount, 'description' => 'Credit sale '.$credit['invoice_number'].' — '.$credit['customer_name']];
            }
            $creditDiscountTotal = round(array_sum(array_column($creditDetails, 'discount_amount')), 2);
            if ($creditDiscountTotal > 0) {
                $salesDiscountAccountId = app(StationAccountMapper::class)
                    ->resolveMappedAccountId($companyId, 'sales_discount_account_id', $user->id);
                if (!$salesDiscountAccountId) {
                    throw new \RuntimeException('Set up a sales discount account before closing a date with discounted credit sales.');
                }
                $entries[] = ['account_id' => $salesDiscountAccountId, 'type' => 'debit',
                    'amount' => $creditDiscountTotal, 'description' => 'Customer discount on credit sales — '.$date];
            }
            $metadata['accounting_invoices_included'] = $accountingInvoicesIncluded;
            // Payments received created inline just above already posted their own Dr Cash /
            // Cr AR transaction; that cash is real and already in the drawer, but must not be
            // debited again here or the same dollar is posted twice across two transactions.
            $cashChange = $closingCash - $openingCash - $externalCashIn + $externalCashOut - $paymentsReceivedCashTotal;
            if ($cashChange != 0) {
                $entries[] = [
                    'account_id' => $accounts['cash_on_hand'],
                    'type' => $cashChange > 0 ? 'debit' : 'credit',
                    'amount' => abs(round($cashChange, 2)),
                    'description' => 'Net cash change',
                ];
            }

            foreach ($bankWithdrawalsByAccount as $bankAccountId => $amount) {
                $entries[] = [
                    'account_id' => $bankAccountId, 'type' => 'credit',
                    'amount' => round($amount, 2), 'description' => 'Cash withdrawn from bank',
                ];
            }

            // Bank deposits (cash goes out, selected bank goes up)
            foreach ($bankDepositsByAccount as $bankAccountId => $amount) {
                $entries[] = [
                    'account_id' => $bankAccountId,
                    'type' => 'debit',
                    'amount' => round($amount, 2),
                    'description' => 'Cash deposited to bank',
                ];
            }

            // Amanat deposits paid into a bank increase that bank without changing drawer cash.
            foreach ($amanatDepositByAccount as $accountId => $amount) {
                if ($accountId === $accounts['cash_on_hand']) {
                    continue;
                }
                $entries[] = [
                    'account_id' => $accountId,
                    'type' => 'debit',
                    'amount' => round($amount, 2),
                    'description' => 'Amanat deposits received',
                ];
            }

            // Non-cash payment channels land in their configured clearing/bank accounts.
            foreach ($paymentReceiptPostings as $posting) {
                $entries[] = [
                    'account_id' => $posting['account_id'],
                    'type' => 'debit',
                    'amount' => round($posting['amount'], 2),
                    'description' => ($posting['channel_label'] ?? 'Payment channel') . ' receipts',
                ];
            }

            // Partner deposits (capital contributions)
            if ($partnerDepositsTotal > 0) {
                if (!$accounts['partner_deposits']) {
                    throw new \RuntimeException('Partner deposits account missing. Set up account 2210 (Investor Deposits) or update station settings.');
                }
                $entries[] = [
                    'account_id' => $accounts['partner_deposits'],
                    'type' => 'credit',
                    'amount' => round($partnerDepositsTotal, 2),
                    'description' => 'Partner deposits',
                ];
            }

            // Amanat deposits (increase customer liability)
            if ($amanatDepositsTotal > 0) {
                if (!$accounts['amanat_deposits']) {
                    throw new \RuntimeException('Amanat deposits account missing. Set up account 2200 (Amanat Deposits) or update station settings.');
                }
                $entries[] = [
                    'account_id' => $accounts['amanat_deposits'],
                    'type' => 'credit',
                    'amount' => round($amanatDepositsTotal, 2),
                    'description' => 'Amanat deposits received',
                ];
            }

            foreach ($otherDepositPostings as $accountId => $posting) {
                $entries[] = [
                    'account_id' => $accountId,
                    'type' => 'credit',
                    'amount' => round($posting['amount'], 2),
                    'description' => 'Cash in - ' . implode(', ', array_unique($posting['labels'])),
                ];
            }

            // Partner withdrawals (drawings)
            if ($partnerWithdrawalsTotal > 0 && $accounts['partner_drawings']) {
                $entries[] = [
                    'account_id' => $accounts['partner_drawings'],
                    'type' => 'debit',
                    'amount' => round($partnerWithdrawalsTotal, 2),
                    'description' => 'Partner withdrawals',
                ];
            }

            // Employee advances
            if ($employeeAdvancesTotal > 0 && $accounts['employee_advances']) {
                $entries[] = [
                    'account_id' => $accounts['employee_advances'],
                    'type' => 'debit',
                    'amount' => round($employeeAdvancesTotal, 2),
                    'description' => 'Employee salary advances',
                ];
            }

            if ($payrollPayoutsTotal > 0) {
                $payrollAccounts = $this->payrollPostingService->ensureDefaultPayrollAccounts($companyId);
                $entries[] = [
                    'account_id' => $payrollAccounts['payroll_payable']['id'],
                    'type' => 'debit',
                    'amount' => round($payrollPayoutsTotal, 2),
                    'description' => 'Approved salary payouts',
                ];
            }

            foreach ($billPaymentPostings as $posting) {
                $entries[] = [
                    'account_id' => $posting['ap_account_id'],
                    'type' => 'debit',
                    'amount' => round($posting['amount'], 2),
                    'description' => 'Supplier payment ' . $posting['payment_number'],
                ];

                if (!$posting['affects_cash_drawer']) {
                    $entries[] = [
                        'account_id' => $posting['payment_account_id'],
                        'type' => 'credit',
                        'amount' => round($posting['amount'], 2),
                        'description' => 'Supplier payment ' . $posting['payment_number'],
                    ];
                }
            }

            // Amanat disbursements (reduce liability)
            if ($amanatTotal > 0) {
                if (!$accounts['amanat_deposits']) {
                    throw new \RuntimeException('Amanat deposits account missing. Set up account 2200 (Amanat Deposits) or update station settings.');
                }
                $entries[] = [
                    'account_id' => $accounts['amanat_deposits'],
                    'type' => 'debit',
                    'amount' => round($amanatTotal, 2),
                    'description' => 'Amanat disbursements',
                ];
            }

            // Amanat withdrawals paid from a bank reduce that bank without changing drawer cash.
            foreach ($amanatWithdrawalByAccount as $accountId => $amount) {
                if ($accountId === $accounts['cash_on_hand']) {
                    continue;
                }
                $entries[] = [
                    'account_id' => $accountId,
                    'type' => 'credit',
                    'amount' => round($amount, 2),
                    'description' => 'Amanat disbursements',
                ];
            }

            // Expenses by account
            foreach ($expensesByAccount as $accountId => $amount) {
                if (!$accountId) {
                    continue;
                }
                $entries[] = [
                    'account_id' => $accountId,
                    'type' => 'debit',
                    'amount' => round($amount, 2),
                    'description' => 'Daily expenses',
                ];
            }

            // Cash variance (over/short)
            if (abs($variance) > 0.01 && $accounts['cash_over_short']) {
                $entries[] = [
                    'account_id' => $accounts['cash_over_short'],
                    'type' => $variance > 0 ? 'credit' : 'debit',
                    'amount' => abs(round($variance, 2)),
                    'description' => $variance > 0 ? 'Cash over' : 'Cash short',
                ];
            }

            // Fuel shrinkage (loss): Dr Shrinkage Expense, Cr Inventory per product.
            // The expense stays on one account — items carry no shrinkage-account
            // mapping — but the credit follows each tank's own inventory account.
            if ($totalShrinkage > 0 && $accounts['fuel_shrinkage']) {
                $entries[] = [
                    'account_id' => $accounts['fuel_shrinkage'],
                    'type' => 'debit',
                    'amount' => round($totalShrinkage, 2),
                    'description' => 'Fuel shrinkage loss',
                ];

                $grouped = [];
                foreach ($shrinkageInventoryPostings as $posting) {
                    $this->addGroupedPosting(
                        $grouped,
                        $posting['account_id'] ?: $accounts['fuel_inventory'],
                        $posting['amount'],
                        $posting['label']
                    );
                }

                foreach ($grouped as $posting) {
                    $entries[] = [
                        'account_id' => $posting['account_id'],
                        'type' => 'credit',
                        'amount' => round($posting['amount'], 2),
                        'description' => 'Inventory reduction (shrinkage) - ' . implode(', ', $posting['labels']),
                    ];
                }
            }

            // Fuel variance gain: Dr Inventory per product, Cr Variance Gain
            if ($totalGain > 0 && $accounts['fuel_variance_gain']) {
                $grouped = [];
                foreach ($gainInventoryPostings as $posting) {
                    $this->addGroupedPosting(
                        $grouped,
                        $posting['account_id'] ?: $accounts['fuel_inventory'],
                        $posting['amount'],
                        $posting['label']
                    );
                }

                foreach ($grouped as $posting) {
                    $entries[] = [
                        'account_id' => $posting['account_id'],
                        'type' => 'debit',
                        'amount' => round($posting['amount'], 2),
                        'description' => 'Inventory increase (gain) - ' . implode(', ', $posting['labels']),
                    ];
                }

                $entries[] = [
                    'account_id' => $accounts['fuel_variance_gain'],
                    'type' => 'credit',
                    'amount' => round($totalGain, 2),
                    'description' => 'Fuel variance gain',
                ];
            }

            $accountEffects = [];
            foreach ($canonicalSources as $source) {
                foreach ($source['account_effects'] ?? [] as $accountId => $amount) {
                    $accountEffects[$accountId] = ($accountEffects[$accountId] ?? 0) + $amount;
                }
            }
            foreach ($entries as $entry) {
                $accountEffects[$entry['account_id']] = ($accountEffects[$entry['account_id']] ?? 0)
                    + ($entry['type'] === 'debit' ? $entry['amount'] : -$entry['amount']);
            }
            $channelAccounts = Account::where('company_id', $companyId)
                ->where(function ($query) use ($accounts) {
                    $query->whereIn('subtype', ['cash', 'bank'])->orWhereIn('id', array_filter([
                        $accounts['cash_on_hand'], $accounts['operating_bank'], $accounts['card_clearing'], $accounts['fuel_card_clearing'],
                    ]));
                })->pluck('name', 'id')->all();
            $metadata['posting_snapshot'] = [
                'channel_accounts' => $channelAccounts,
                'account_effects' => $accountEffects, 'cash_account_id' => $accounts['cash_on_hand'],
                'version' => 2, 'business_date' => $date, 'posted_at' => now()->toISOString(),
                'posted_by' => $user->id, 'sources' => $canonicalSources,
                'zero_sales_confirmed' => (array_sum(array_column($data['nozzle_readings'], 'liters_sold')) <= 0 && $otherSalesTotal <= 0) ? filter_var($data['zero_sales_confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN) : false,
                'zero_sales_reason' => (array_sum(array_column($data['nozzle_readings'], 'liters_sold')) <= 0 && $otherSalesTotal <= 0) ? ($data['zero_sales_reason'] ?? null) : null,
                'totals' => [
                    'total_revenue' => $totalRevenue + array_sum(array_column($canonicalSources, 'sales')), 'money_in' => $totalCashIn - $openingCash + $externalCashIn + $totalNonCashReceipts + $creditTotal,
                    'money_out' => $totalCashOut + $externalCashOut + $totalNonCashReceipts + $creditTotal,
                    'opening_cash' => $openingCash, 'closing_cash' => $closingCash,
                    'expected_closing' => $expectedClosing, 'variance' => $variance,
                ],
                'channels' => $paymentReceiptPostings, 'tanks' => $tankSnapshot,
                'credit_sales' => $creditDetails,
                'payments_received' => $paymentsReceivedDetails,
                'nozzles' => $nozzleReadingsData, 'correction_accounts' => $accounts,
                'physical_observations' => $data['tank_readings'] ?? [],
            ];
            // Post the transaction
            $transaction = $this->postingService->postBalancedTransaction([
                'company_id' => $companyId,
                'transaction_number' => $transactionNumber,
                'transaction_type' => 'fuel_daily_close',
                'date' => $date,
                'currency' => $currency,
                'base_currency' => $currency,
                'description' => "Daily close - {$date}",
                'reference_type' => 'fuel.daily_close',
                'metadata' => array_diff_key($metadata, ['posting_snapshot' => true]),
            ], $entries);
            // Existing subledger records reference this one posting, including its business date.
            app(DailyCloseCreditSaleService::class)->attach($companyId, $transaction->id, $creditDetails);
            $amanatEntryId = $transaction->journalEntries()->where('account_id', $accounts['amanat_deposits'])->value('id');
            foreach ($createdAmanat as $record) { $record->update(['journal_entry_id' => $amanatEntryId]); }
            foreach ($createdAdvances as $record) {
                $record->update(['journal_entry_id' => $transaction->journalEntries()->where('account_id', $accounts['employee_advances'])->value('id')]);
            }
            foreach ($createdPartners as $record) {
                $accountId = $record->transaction_type === 'investment' ? $accounts['partner_deposits'] : $accounts['partner_drawings'];
                $record->update(['journal_entry_id' => $transaction->journalEntries()->where('account_id', $accountId)->value('id')]);
            }

            foreach ($stockReconciliations as $reconciliation) {
                StockMovement::create([
                    'company_id' => $companyId,
                    'warehouse_id' => $reconciliation['warehouse_id'],
                    'item_id' => $reconciliation['item_id'],
                    'movement_date' => $date,
                    'movement_type' => $reconciliation['movement_type'],
                    'quantity' => $reconciliation['quantity'],
                    'unit_cost' => $reconciliation['unit_cost'],
                    'total_cost' => $reconciliation['total_cost'],
                    'gl_transaction_id' => $transaction->id,
                    'reference_type' => 'fuel.daily_close',
                    'reference_id' => $transaction->id,
                    'reason' => $reconciliation['reason'],
                    'notes' => $reconciliation['notes'],
                    'created_by_user_id' => $user->id,
                ]);
            }

            // ─────────────────────────────────────────────────────────────────
            // 7. Save Nozzle Readings and update nozzle last_closing_reading
            // ─────────────────────────────────────────────────────────────────
            foreach ($nozzleReadingsData as $nozzleData) {
                NozzleReading::updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'nozzle_id' => $nozzleData['nozzle_id'],
                        'reading_date' => $date,
                    ],
                    [
                        'item_id' => $nozzleData['item_id'],
                        'opening_electronic' => $nozzleData['opening_electronic'],
                        'closing_electronic' => $nozzleData['closing_electronic'],
                        'opening_manual' => $nozzleData['opening_manual'],
                        'closing_manual' => $nozzleData['closing_manual'],
                        'liters_dispensed' => $nozzleData['liters_dispensed'],
                        'recorded_by_user_id' => $user->id,
                        'daily_close_transaction_id' => $transaction->id,
                    ]
                );

                // Update nozzle's last_closing_reading for next day's opening
                $nozzleUpdate = [
                    'last_closing_reading' => $nozzleData['closing_electronic'],
                ];
                if ($nozzleData['closing_manual'] !== null) {
                    $nozzleUpdate['last_manual_reading'] = $nozzleData['closing_manual'];
                }

                Nozzle::where('id', $nozzleData['nozzle_id'])
                    ->update($nozzleUpdate);
            }

            if (!empty($payrollPayoutIds)) {
                Payslip::where('company_id', $companyId)
                    ->whereIn('id', $payrollPayoutIds)
                    ->update([
                        'status' => 'paid',
                        // The business date the drawer paid them, not the moment of posting.
                        'paid_at' => \Illuminate\Support\Carbon::parse($date),
                        'payment_method' => 'cash',
                        'payment_reference' => $transactionNumber,
                        'payment_gl_transaction_id' => $transaction->id,
                    ]);
            }

            if (!empty($billPaymentIds)) {
                BillPayment::where('company_id', $companyId)
                    ->whereIn('id', $billPaymentIds)
                    ->whereNull('transaction_id')
                    ->update([
                        'transaction_id' => $transaction->id,
                        'updated_by_user_id' => $user->id,
                        'updated_at' => now(),
                    ]);
            }

            // Freeze only after all journal, stock and subledger records have been constructed.
            $transaction->update(['metadata' => $metadata, 'posted_at' => $metadata['posting_snapshot']['posted_at'],
                'posted_by_user_id' => $user->id, 'created_by_user_id' => $user->id]);

            DB::table('fuel.daily_close_drafts')->where('company_id', $companyId)->where('business_date', $date)->delete();

            return [
                'transaction_number' => $transactionNumber,
                'transaction_id' => $transaction->id,
                'metadata' => $metadata,
            ];
        });
    }

    /**
     * Get recent daily closes for history view.
     */
    /**
     * Whether a new rate took effect on this close's business date, whether the day was split
     * between two rates at a meter reading, and how many litres had to be priced without
     * knowing which side of a change they fell on.
     *
     * The first version only reported split days. A station that closes its register at
     * midnight on a rate-change night never splits one - each register day is already all one
     * rate - so the marker never appeared. It now reports any day a rate took effect.
     *
     * fallback_liters is the honest part of a split. When the reading at the moment of the
     * change was never taken, the day is priced at one rate and the litres valued that way are
     * counted; that approximation was being kept and shown nowhere.
     *
     * @return array{changed: bool, split: bool, fallback_liters: float}|null
     */
    private function rateChangeSummary(array $metadata, bool $rateTookEffect): ?array
    {
        $segments = $metadata['rate_change_segments'] ?? [];

        if (! $rateTookEffect && empty($segments)) {
            return null;
        }

        return [
            'changed' => true,
            'split' => ! empty($segments),
            'fallback_liters' => round(array_sum(array_map(
                fn ($s) => (float) ($s['fallback_liters'] ?? 0),
                $segments
            )), 2),
        ];
    }

    /**
     * Every close on record, ignoring any date window.
     *
     * The history page needs this to tell "this company has never closed a day" apart from
     * "it has, just not inside the window you are looking at". Those rendered identically -
     * as "No daily close records found", under a button offering to create the first one -
     * while five closes sat in the table, back-dated beyond the default thirty days.
     */
    public function countCloses(string $companyId): int
    {
        return Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * @param  int|null  $days  Days back from today, or null for every close on record.
     */
    public function getRecentCloses(string $companyId, ?int $days = 30): array
    {
        $closes = Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereNull('deleted_at')
            ->when($days !== null, fn ($q) => $q->where('transaction_date', '>=', now()->subDays($days)->toDateString()))
            ->orderByDesc('transaction_date')
            ->get(['id', 'company_id', 'transaction_number', 'transaction_date', 'metadata', 'is_locked', 'reversed_by_id', 'reversal_of_id', 'corrects_transaction_id']);

        // One aggregated query instead of a full DailyCloseReconciliationService::view()
        // (~6-7 queries) per close: fuel.daily_close_activity is the append-only audit
        // trail every post-close write lands in, so its mere presence for a close id
        // is a cheap, sufficient proxy for "has post-close activity" on a history list.
        $activeCloseIds = DB::table('fuel.daily_close_activity')
            ->where('company_id', $companyId)
            ->whereIn('close_transaction_id', $closes->pluck('id'))
            ->distinct()
            ->pluck('close_transaction_id')
            ->merge(
                DB::table('fuel.daily_close_reading_corrections')
                    ->where('company_id', $companyId)
                    ->whereIn('close_transaction_id', $closes->pluck('id'))
                    ->distinct()
                    ->pluck('close_transaction_id')
            )
            ->flip();

        // Days on which a new rate took effect, for all listed closes in one query. The marker
        // keys off this rather than off a split: a station that ends its day at midnight on a
        // rate-change night never splits a day, so a split-only marker never showed at all.
        // Only a rate that replaced an earlier one counts: an item's first rate is setup, not
        // a change.
        $rateChangeDates = DB::table('fuel.rate_changes as rc')
            ->where('rc.company_id', $companyId)
            ->whereIn('rc.effective_date', $closes->map(fn ($t) => $t->transaction_date->toDateString())->unique()->values())
            ->whereExists(fn ($q) => $q->from('fuel.rate_changes as prior')
                ->whereColumn('prior.company_id', 'rc.company_id')
                ->whereColumn('prior.item_id', 'rc.item_id')
                ->whereColumn('prior.effective_date', '<', 'rc.effective_date'))
            ->pluck('rc.effective_date')
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->flip();

        return $closes->map(function ($t) use ($activeCloseIds, $rateChangeDates) {
            $metadata = $t->metadata ?? [];
            if (!is_array($metadata)) {
                $metadata = [];
            }

            return [
                'id' => $t->id,
                'transaction_number' => $t->transaction_number,
                'date' => $t->transaction_date->toDateString(),
                'opening_cash' => $metadata['opening_cash'] ?? 0,
                'closing_cash' => $metadata['closing_cash'] ?? 0,
                'total_revenue' => $metadata['total_revenue'] ?? 0,
                'variance' => $metadata['variance'] ?? 0,
                'status' => $t->display_status,
                'is_locked' => $t->is_locked ?? false,
                'is_amendable' => $t->isAmendable(),
                'has_amendments' => $t->reversed_by_id !== null,
                'has_post_close_activity' => $activeCloseIds->has($t->id),
                'has_pump_test' => ! empty($metadata['pump_tests']),
                'readings_taken_at' => $metadata['readings_taken_at'] ?? null,

                // A rate change is the most common reason a day's revenue or margin looks
                // unlike its neighbours, and it is the first thing someone reading the
                // history wants to know. The detail is already in the close; this only
                // surfaces that it happened.
                'rate_change' => $this->rateChangeSummary($metadata, $rateChangeDates->has($t->transaction_date->toDateString())),
            ];
        })->pipe(function ($rows) {
            // Hours from the previous day's readings to this close's. Only between consecutive
            // business dates, and only when both closes recorded a time; closes posted before
            // times were recorded show nothing rather than a guess.
            $takenAt = $rows->pluck('readings_taken_at', 'date');

            return $rows->map(function ($row) use ($takenAt) {
                $previousDate = \Illuminate\Support\Carbon::parse($row['date'])->subDay()->toDateString();
                $previous = $takenAt[$previousDate] ?? null;

                $row['hours_covered'] = ($row['readings_taken_at'] && $previous)
                    ? round(\Illuminate\Support\Carbon::parse($previous)->diffInMinutes(\Illuminate\Support\Carbon::parse($row['readings_taken_at'])) / 60, 1)
                    : null;

                return $row;
            });
        })->toArray();
    }

    private function generateTransactionNumber(string $companyId, string $date, bool $isCorrection = false): string
    {
        $base = 'FDC-' . str_replace('-', '', $date);

        // For corrections, we need to find the next available suffix
        if ($isCorrection) {
            // Find existing corrections for this date
            $existing = Transaction::where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->where('transaction_number', 'like', $base . '-C%')
                ->pluck('transaction_number')
                ->all();

            if (empty($existing)) {
                return $base . '-C1';
            }

            // Extract the highest correction number
            $maxNum = 0;
            foreach ($existing as $txnNum) {
                if (preg_match('/-C(\d+)$/', $txnNum, $matches)) {
                    $num = (int) $matches[1];
                    if ($num > $maxNum) {
                        $maxNum = $num;
                    }
                }
            }

            return $base . '-C' . ($maxNum + 1);
        }

        // For original entries, check if ANY entry exists for this date (including reversed)
        // This ensures users must always use the amendment flow for dates that have any history
        $exists = Transaction::where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('transaction_type', 'fuel_daily_close')
            ->whereDate('transaction_date', $date)
            ->exists();

        if ($exists) {
            throw new \RuntimeException("A daily close entry already exists for {$date}. Record a separate dated adjustment to preserve the original close.");
        }

        return $base;
    }

    private function resolveAmanatPaymentAccount(string $companyId, ?string $accountId, string $cashAccountId): Account
    {
        $query = Account::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('subtype', ['cash', 'bank']);

        if ($accountId) {
            return $query->whereKey($accountId)->firstOrFail();
        }

        return Account::whereKey($cashAccountId)->where('company_id', $companyId)->firstOrFail();
    }

    private function resolvePaymentChannelAccount(array $channel, array $accounts): string
    {
        $type = $channel['type'] ?? 'bank_transfer';
        $label = $channel['label'] ?? $channel['code'] ?? 'payment channel';
        $settlesTo = $channel['settles_to'] ?? 'clearing';

        // A card/fuel-card/mobile-wallet channel set to settle straight to the bank debits
        // that bank account directly at the close instead of parking the sale in clearing —
        // nothing else about the close changes. 'supplier' still lands in clearing here; the
        // extra supplier-settlement step happens separately, after this account is resolved.
        if ($settlesTo === 'bank' && in_array($type, ['card_pos', 'fuel_card', 'mobile_wallet'], true)) {
            $accountId = $channel['bank_account_id'] ?? null;
            if (!$accountId) {
                throw new \RuntimeException("No bank account configured for payment channel: {$label}.");
            }

            return $accountId;
        }

        $accountId = match ($type) {
            'cash' => $accounts['cash_on_hand'] ?? null,
            'bank_transfer' => $channel['bank_account_id'] ?? $accounts['operating_bank'] ?? null,
            'card_pos' => $channel['clearing_account_id'] ?? $accounts['card_clearing'] ?? null,
            'fuel_card' => $channel['clearing_account_id'] ?? $accounts['fuel_card_clearing'] ?? null,
            'mobile_wallet' => $channel['clearing_account_id'] ?? $channel['bank_account_id'] ?? $accounts['operating_bank'] ?? null,
            default => $channel['bank_account_id'] ?? $channel['clearing_account_id'] ?? $accounts['operating_bank'] ?? null,
        };

        if (!$accountId) {
            throw new \RuntimeException("No GL account configured for payment channel: {$label}.");
        }

        return $accountId;
    }

    private function getPendingBillPaymentsForDate(string $companyId, string $date)
    {
        return BillPayment::where('company_id', $companyId)
            ->whereDate('payment_date', $date)
            ->whereNull('transaction_id')
            ->with([
                'vendor:id,name,ap_account_id',
                'paymentAccount:id,code,name,subtype',
                'allocations.bill:id,bill_number',
            ])
            ->lockForUpdate()
            ->orderBy('payment_date')
            ->orderBy('payment_number')
            ->get();
    }

    private function resolveAccounts(string $companyId): array
    {
        // First try to get accounts from station settings
        $stationSettings = StationSettings::where('company_id', $companyId)->first();

        $byCode = Account::where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->get(['id', 'code', 'subtype', 'type'])
            ->keyBy('code');

        // Use settings first, then fallback to code-based lookup
        $cashOnHand = $stationSettings?->cash_account_id
            ?? $byCode->get('1050')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('subtype', 'cash')->orderBy('code')->value('id');

        $operatingBank = $stationSettings?->operating_bank_account_id
            ?? $byCode->get('1000')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('subtype', 'bank')->orderBy('code')->value('id');

        // Clearing accounts from settings
        $cardClearing = $stationSettings?->card_pos_clearing_account_id
            ?? $byCode->get('1040')?->id
            ?? $cashOnHand;

        $fuelCardClearing = $stationSettings?->fuel_card_clearing_account_id
            ?? $byCode->get('1030')?->id
            ?? $cashOnHand;

        $fuelInventory = $stationSettings?->fuel_inventory_account_id
            ?? $byCode->get('1200')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('subtype', 'inventory')->orderBy('code')->value('id');

        $fuelSales = $stationSettings?->fuel_sales_account_id
            ?? $byCode->get('4100')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('type', 'revenue')->orderBy('code')->value('id');

        $fuelCogs = $stationSettings?->fuel_cogs_account_id
            ?? $byCode->get('5100')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('type', 'cogs')->orderBy('code')->value('id');

        $cashOverShort = $stationSettings?->cash_over_short_account_id
            ?? $byCode->get('6180')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('type', 'expense')->orderBy('code')->value('id');

        $amanatDeposits = $byCode->get('2200')?->id;

        $partnerDeposits = $byCode->get('2210')?->id;

        // Partner drawings - from settings or fallback
        $partnerDrawings = $stationSettings?->partner_drawings_account_id
            ?? $byCode->get('3200')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('type', 'equity')->orderByDesc('code')->value('id');

        // Employee advances - from settings or fallback
        $employeeAdvances = $stationSettings?->employee_advances_account_id
            ?? $byCode->get('1150')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('subtype', 'receivable')->orderByDesc('code')->value('id')
            ?? $cashOnHand;

        $accountsPayable = $byCode->get('2100')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('subtype', 'accounts_payable')->orderBy('code')->value('id')
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('type', 'liability')->where('name', 'like', '%Payable%')->orderBy('code')->value('id');

        // Fuel shrinkage account - prefer 5900 (onboarding), then 6300 with Shrinkage in name
        $fuelShrinkage = $stationSettings?->fuel_shrinkage_account_id
            ?? $byCode->get('5900')?->id
            ?? Account::where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->where('code', '6300')
                ->where('name', 'like', '%Shrinkage%')
                ->value('id')
            ?? Account::where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->where('name', 'like', '%Shrinkage%')
                ->value('id');

        // Fuel variance gain account
        $fuelVarianceGain = $stationSettings?->fuel_variance_gain_account_id
            ?? $byCode->get('4900')?->id
            ?? Account::where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->where('name', 'like', '%Variance Gain%')
                ->value('id');

        $required = [
            'cash_on_hand' => $cashOnHand,
            'operating_bank' => $operatingBank,
            'card_clearing' => $cardClearing,
            'fuel_card_clearing' => $fuelCardClearing,
            'fuel_sales' => $fuelSales,
            'fuel_cogs' => $fuelCogs,
            'fuel_inventory' => $fuelInventory,
            'cash_over_short' => $cashOverShort,
            'amanat_deposits' => $amanatDeposits,
            'partner_deposits' => $partnerDeposits,
            'partner_drawings' => $partnerDrawings,
            'employee_advances' => $employeeAdvances,
            'accounts_payable' => $accountsPayable,
            'fuel_shrinkage' => $fuelShrinkage,
            'fuel_variance_gain' => $fuelVarianceGain,
        ];

        foreach (['cash_on_hand', 'fuel_sales', 'fuel_cogs', 'fuel_inventory'] as $key) {
            if (!$required[$key]) {
                throw new \RuntimeException("Required account missing: {$key}. Ensure fuel station COA is set up.");
            }
        }

        return $required;
    }

    private function addGroupedPosting(array &$postings, string $accountId, float $amount, string $label): void
    {
        if ($amount <= 0) {
            return;
        }

        $label = trim($label) !== '' ? trim($label) : 'Fuel';

        if (!isset($postings[$accountId])) {
            $postings[$accountId] = [
                'account_id' => $accountId,
                'amount' => 0,
                'labels' => [],
            ];
        }

        $postings[$accountId]['amount'] += $amount;
        if (!in_array($label, $postings[$accountId]['labels'], true)) {
            $postings[$accountId]['labels'][] = $label;
        }
    }

    /**
     * Public so DailyCloseReconciliationService can recompute a corrected nozzle
     * reading's revenue using the exact same rate-change-split logic (review
     * finding #6): never reimplement pricing outside this service.
     */
    public function getRateChangeSnapshotsForDate(string $companyId, string $date): array
    {
        return RateChange::where('company_id', $companyId)
            ->whereDate('effective_date', $date)
            ->whereNotNull('snapshot_nozzle_readings')
            ->get()
            ->mapWithKeys(function (RateChange $rateChange) use ($companyId) {
                $previousRate = RateChange::where('company_id', $companyId)
                    ->where('item_id', $rateChange->item_id)
                    ->whereDate('effective_date', '<', $rateChange->effective_date)
                    ->orderByDesc('effective_date')
                    ->first();

                return [
                    $rateChange->item_id => [
                        'rate_change_id' => $rateChange->id,
                        'old_rate' => $previousRate ? (float) $previousRate->sale_rate : null,
                        'new_rate' => (float) $rateChange->sale_rate,
                        'nozzles' => collect($rateChange->snapshot_nozzle_readings ?? [])
                            ->keyBy('nozzle_id')
                            ->all(),
                    ],
                ];
            })
            ->all();
    }

    /**
     * When a close's readings were taken. Nobody types it: a station reads at 08:00 the
     * morning after, except on the night before a rate change, when it reads at the first
     * minute of the new rate - 00:00 on the change date. Only a rate that replaced an earlier
     * one counts; an item's first rate is setup, not a change.
     */
    public static function readingsTakenAt(string $companyId, string $businessDate): string
    {
        $next = \Illuminate\Support\Carbon::parse($businessDate)->addDay();

        $rateChangesAtMidnight = DB::table('fuel.rate_changes as rc')
            ->where('rc.company_id', $companyId)
            ->whereDate('rc.effective_date', $next->toDateString())
            ->whereExists(fn ($q) => $q->from('fuel.rate_changes as prior')
                ->whereColumn('prior.company_id', 'rc.company_id')
                ->whereColumn('prior.item_id', 'rc.item_id')
                ->whereColumn('prior.effective_date', '<', 'rc.effective_date'))
            ->exists();

        return $next->setTime($rateChangesAtMidnight ? 0 : 8, 0)->format('Y-m-d H:i');
    }

    /**
     * Litres sold at each nozzle, derived from its meter readings - never taken from the request.
     *
     * The close used to post fuel revenue from (float) $reading['liters_sold'], a figure the
     * browser computed and the server never checked against the meters. Fuel is the largest
     * number in any close, and a request could name any litres it liked beside readings that
     * said otherwise. The same fault was fixed for lubricants on 22 September; this closes it for
     * fuel.
     *
     * This is also where the meter rule is enforced for every caller - the web form, the
     * CommandBus action, anything else - rather than only in one form request.
     *
     * A nozzle's optional returned_liters is fuel run through the meter for a calibration test
     * and poured straight back into the tank: the meter genuinely advanced (so it is kept, as
     * meter_liters, exactly as read), but nothing was sold. liters_sold - what revenue, COGS and
     * the tank's expected stock are worked out from everywhere downstream - is meter litres minus
     * whatever came back. It can never exceed the meter litres themselves.
     *
     * @throws \Illuminate\Validation\ValidationException when a pair of readings is impossible,
     *         or more litres are declared returned than the meter moved
     */
    private function litresFromReadings(array $readings): array
    {
        $errors = [];

        foreach ($readings as $i => $reading) {
            $opening = (float) ($reading['opening_electronic'] ?? 0);
            $closing = (float) ($reading['closing_electronic'] ?? 0);
            $rolledOver = filter_var($reading['meter_rolled_over'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $problem = self::meterReadingProblem($opening, $closing, $rolledOver);
            if ($problem !== null) {
                $errors["nozzle_readings.{$i}.closing_electronic"] = $problem;

                continue;
            }

            $meterLiters = self::litresFromMeters($opening, $closing, $rolledOver);
            $returned = round((float) ($reading['returned_liters'] ?? 0), 3);

            if ($returned > $meterLiters) {
                $errors["nozzle_readings.{$i}.returned_liters"] = 'Returned litres ('.$returned.' L) cannot exceed the '
                    .'litres the meter moved ('.$meterLiters.' L).';

                continue;
            }

            $readings[$i]['meter_liters'] = $meterLiters;
            $readings[$i]['returned_liters'] = $returned;
            $readings[$i]['liters_sold'] = round($meterLiters - $returned, 3);
        }

        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        return $readings;
    }

    /**
     * What a pair of readings sold. A totaliser that passes its last digit restarts from zero, so
     * on that day the litres are the distance to the rollover point plus the new reading.
     *
     * The rollover point is the next power of ten above the opening reading: a meter can only
     * roll over when it is at the top of its range, and at the top of its range the opening
     * reading has as many digits as the meter does. meterReadingProblem() refuses a rollover
     * whose opening is not near that top, so this is only ever reached when that holds.
     */
    public static function litresFromMeters(float $opening, float $closing, bool $rolledOver): float
    {
        if ($rolledOver) {
            return round((self::rolloverPoint($opening) - $opening) + $closing, 3);
        }

        return round(max(0, $closing - $opening), 3);
    }

    /**
     * Why a pair of readings is impossible, or null when it is fine.
     *
     * A closing reading below the opening one is almost always a mistake - a skipped nozzle, a
     * transposed digit, a reading in the wrong row. Day 13 of the scenario run lost 375 litres of
     * diesel that way, booked as zero with only a cash surplus to show for it. The one honest
     * exception is a totaliser rolling past its last digit, which must be declared, never
     * guessed: guessing would bring the silent typo straight back.
     */
    public static function meterReadingProblem(float $opening, float $closing, bool $rolledOver): ?string
    {
        $fmt = fn (float $v) => rtrim(rtrim(number_format($v, 2), '0'), '.');

        if (! $rolledOver) {
            return $closing < $opening
                ? "Closing reading ({$fmt($closing)}) is below the opening reading ({$fmt($opening)}). "
                    .'A pump meter cannot go backwards. If this meter passed its last digit and started '
                    .'again from zero, tick "Meter rolled over".'
                : null;
        }

        if ($closing >= $opening) {
            return 'Marked as rolled over, but the closing reading is not below the opening one. '
                .'Untick "Meter rolled over" unless the meter really restarted from zero.';
        }

        $top = self::rolloverPoint($opening);
        if ($opening < 0.9 * $top) {
            $digits = strlen((string) (int) $top) - 1;

            return "Marked as rolled over, but the opening reading ({$fmt($opening)}) is nowhere near "
                ."the top of a {$digits}-digit meter ({$fmt($top - 1)}). Check the readings.";
        }

        return null;
    }

    private static function rolloverPoint(float $opening): float
    {
        return 10 ** strlen((string) (int) floor(max($opening, 1)));
    }

    public function calculateRateChangeSplit(array $reading, ?array $snapshot, float $fallbackRate): array
    {
        // Already net of any returned-to-tank test litres (see litresFromReadings) - this is
        // what gets priced and posted, at whichever rate(s) it falls under below.
        $liters = (float) $reading['liters_sold'];
        $opening = (float) $reading['opening_electronic'];
        $closing = (float) $reading['closing_electronic'];
        $snapshotReading = $snapshot['nozzles'][$reading['nozzle_id']]['electronic_reading'] ?? null;
        $oldRate = $snapshot['old_rate'] ?? null;
        $newRate = $snapshot['new_rate'] ?? $fallbackRate;

        if ($snapshotReading === null || $oldRate === null || $snapshotReading <= $opening || $snapshotReading >= $closing) {
            return [
                'used_snapshot' => false,
                'rate_change_id' => $snapshot['rate_change_id'] ?? null,
                'snapshot_electronic_reading' => $snapshotReading,
                'old_rate' => $oldRate,
                'new_rate' => $newRate,
                'old_rate_liters' => 0,
                'new_rate_liters' => 0,
                'fallback_liters' => $liters,
                'revenue' => round($liters * $fallbackRate, 2),
                'segments' => [
                    ['liters' => $liters, 'rate' => $fallbackRate, 'amount' => round($liters * $fallbackRate, 2)],
                ],
            ];
        }

        // The meter positions place these litres on either side of the change - gross of any
        // return, since the return is not a meter position but a separate declared amount. A
        // returned litre is dispensed last, off the pump, so it comes off the new-rate portion
        // (the one after the snapshot) first; only once that portion is exhausted does it eat
        // into the old-rate portion. That keeps a return on a day with no rate change (the
        // ordinary case) doing the equivalent thing: coming off what would otherwise be sold.
        $returned = round((float) ($reading['returned_liters'] ?? 0), 3);
        $oldLitersGross = max(0, round($snapshotReading - $opening, 3));
        $newLitersGross = max(0, round($closing - $snapshotReading, 3));

        $returnFromNew = min($returned, $newLitersGross);
        $newLiters = round($newLitersGross - $returnFromNew, 3);
        $remainingReturn = round($returned - $returnFromNew, 3);
        $oldLiters = max(0, round($oldLitersGross - $remainingReturn, 3));

        $segmentedLiters = $oldLiters + $newLiters;
        $fallbackLiters = max(0, round($liters - $segmentedLiters, 3));
        $revenue = round(($oldLiters * $oldRate) + ($newLiters * $newRate) + ($fallbackLiters * $fallbackRate), 2);

        return [
            'used_snapshot' => true,
            'rate_change_id' => $snapshot['rate_change_id'] ?? null,
            'snapshot_electronic_reading' => (float) $snapshotReading,
            'old_rate' => $oldRate,
            'new_rate' => $newRate,
            'old_rate_liters' => $oldLiters,
            'new_rate_liters' => $newLiters,
            'fallback_liters' => $fallbackLiters,
            'revenue' => $revenue,
            'segments' => [
                ['liters' => $oldLiters, 'rate' => $oldRate, 'amount' => round($oldLiters * $oldRate, 2), 'period' => 'before_rate_change'],
                ['liters' => $newLiters, 'rate' => $newRate, 'amount' => round($newLiters * $newRate, 2), 'period' => 'after_rate_change'],
            ],
        ];
    }
}
