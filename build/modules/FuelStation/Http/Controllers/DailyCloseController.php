<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Partner;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Http\Requests\LockDailyCloseRequest;
use App\Modules\FuelStation\Http\Requests\LockMonthDailyCloseRequest;
use App\Modules\FuelStation\Http\Requests\UnlockDailyCloseRequest;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Modules\FuelStation\Models\Investor;
use App\Modules\FuelStation\Models\Nozzle;
use App\Modules\FuelStation\Models\NozzleReading;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\FuelStation\Services\DailyCloseLockService;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DailyCloseController extends Controller
{
    public function __construct(
        private readonly DailyCloseService $dailyCloseService,
        private readonly DailyCloseLockService $lockService,
    ) {}

    /**
     * Get employees selectable for daily close salary advances.
     *
     * Some payroll entry points can create employees without an explicit
     * is_active value. Treat employees as selectable unless they are
     * explicitly inactive/terminated or soft-deleted.
     */
    private function getEmployeesForAdvances(string $companyId)
    {
        $columns = ['id', 'first_name', 'last_name', 'position', 'base_salary'];

        DB::select("SELECT set_config('app.current_company_id', ?, false)", [$companyId]);

        return Employee::where('company_id', $companyId)
            ->where(function ($query) {
                $query->where('is_active', true)
                    ->orWhereNull('is_active');
            })
            ->where(function ($query) {
                $query->where('employment_status', '!=', 'terminated')
                    ->orWhereNull('employment_status');
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get($columns)
            ->map(fn ($employee) => $this->formatEmployeeForDailyClose($companyId, $employee));
    }

    private function formatEmployeeForDailyClose(string $companyId, object $employee): array
    {
        $outstandingAdvances = SalaryAdvance::where('company_id', $companyId)
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'partially_recovered'])
            ->sum('amount_outstanding');

        return [
            'id' => $employee->id,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'full_name' => trim($employee->first_name . ' ' . $employee->last_name),
            'position' => $employee->position,
            'base_salary' => (float) ($employee->base_salary ?? 0),
            'outstanding_advances' => (float) $outstandingAdvances,
        ];
    }

    private function getApprovedPayrollPayouts(string $companyId, string $date)
    {
        try {
            DB::select("SELECT set_config('app.current_company_id', ?, false)", [$companyId]);

            return Payslip::where('company_id', $companyId)
                // Wages belong to the close for the day they are paid - see Payslip::payableOn.
                ->payableOn($date)
                ->where('net_pay', '>', 0)
                ->with('employee:id,first_name,last_name,employee_number')
                ->orderBy('approved_at')
                ->get(['id', 'company_id', 'employee_id', 'payslip_number', 'net_pay', 'approved_at'])
                ->map(fn (Payslip $payslip) => [
                    'payslip_id' => $payslip->id,
                    'payslip_number' => $payslip->payslip_number,
                    'employee_id' => $payslip->employee_id,
                    'employee_name' => trim(($payslip->employee?->first_name ?? '') . ' ' . ($payslip->employee?->last_name ?? '')) ?: 'Employee',
                    'employee_number' => $payslip->employee?->employee_number,
                    'amount' => (float) $payslip->net_pay,
                    'approved_at' => $payslip->approved_at?->toISOString(),
                ]);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    private function getPendingBillPaymentsForDailyClose(string $companyId, string $date)
    {
        $cashAccountId = StationSettings::where('company_id', $companyId)->value('cash_account_id')
            ?? Account::where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->where('subtype', 'cash')
                ->orderBy('code')
                ->value('id');

        return BillPayment::where('company_id', $companyId)
            ->whereDate('payment_date', $date)
            ->whereNull('transaction_id')
            ->with([
                'vendor:id,name',
                'paymentAccount:id,code,name,subtype',
                'allocations.bill:id,bill_number',
            ])
            ->orderBy('payment_date')
            ->orderBy('payment_number')
            ->get(['id', 'company_id', 'vendor_id', 'payment_group_number', 'payment_number', 'payment_date', 'amount', 'currency', 'payment_method', 'payment_account_id', 'reference_number'])
            ->map(function (BillPayment $payment) use ($cashAccountId) {
                $accountLabel = trim(($payment->paymentAccount?->code ? $payment->paymentAccount->code . ' — ' : '') . ($payment->paymentAccount?->name ?? 'Payment account'));
                $billNumbers = $payment->allocations
                    ->map(fn ($allocation) => $allocation->bill?->bill_number)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'payment_group_number' => $payment->payment_group_number,
                    'vendor_id' => $payment->vendor_id,
                    'vendor_name' => $payment->vendor?->name ?? 'Supplier',
                    'payment_account_id' => $payment->payment_account_id,
                    'payment_account_name' => $accountLabel,
                    'payment_account_subtype' => $payment->paymentAccount?->subtype,
                    'payment_method' => $payment->payment_method,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'reference_number' => $payment->reference_number,
                    'bill_numbers' => $billNumbers,
                    'affects_cash_drawer' => $payment->payment_account_id === $cashAccountId,
                ];
            });
    }

    /**
     * Standalone credit fuel-sale invoices for this date not yet linked to a close. Every
     * litre already went through a nozzle the close reads, so these are pre-checked as a
     * channel of the close (see DailyCloseCreditSaleService::pendingFuelInvoiceDetails), never
     * additional sales.
     */
    /**
     * Plain Accounting -> Invoices invoices already posted for this date, on a fuel revenue
     * account, not flagged direct-delivery, not yet folded into a close -- see
     * DailyCloseCreditSaleService::pendingAccountingInvoiceDetails, which is the server's
     * authoritative recomputation of this same list on post.
     */
    private function getPendingAccountingInvoicesForDailyClose(string $companyId, string $date): array
    {
        $company = \App\Models\Company::whereKey($companyId)->firstOrFail();
        $fuelRevenueAccountIds = $this->dailyCloseService->fuelRevenueAccountIds($companyId);

        return app(\App\Modules\FuelStation\Services\DailyCloseCreditSaleService::class)
            ->pendingAccountingInvoiceDetails($company, $date, $fuelRevenueAccountIds);
    }

    private function getPendingFuelInvoicesForDailyClose(string $companyId, string $date)
    {
        return \App\Modules\Accounting\Models\Invoice::where('company_id', $companyId)
            ->whereDate('invoice_date', $date)
            ->whereNull('transaction_id')
            ->whereHas('saleMetadata', fn ($q) => $q->where('sale_type', \App\Modules\FuelStation\Models\SaleMetadata::TYPE_CREDIT))
            ->with(['customer:id,name', 'lineItems'])
            ->orderBy('invoice_number')
            ->get()
            ->map(function (\App\Modules\Accounting\Models\Invoice $invoice) {
                $line = $invoice->lineItems->first();
                return [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer_id' => $invoice->customer_id,
                    'customer_name' => $invoice->customer?->name ?? 'Buyer',
                    'litres' => (float) ($line->quantity ?? 0),
                    'amount' => (float) $invoice->total_amount,
                    'reference' => $invoice->invoice_number,
                ];
            })
            ->values();
    }

    /**
     * Buyers with an outstanding invoice, for the "Payments Received" section of the close:
     * a searchable list of invoices to settle, each showing its own outstanding balance.
     */
    private function getOpenInvoicesForDailyClose(string $companyId)
    {
        return \App\Modules\Accounting\Models\Invoice::where('company_id', $companyId)
            ->whereNotIn('status', ['draft', 'void', 'cancelled', 'paid'])
            ->where('balance', '>', 0)
            ->with('customer:id,name')
            ->orderBy('invoice_number')
            ->get()
            ->map(fn (\App\Modules\Accounting\Models\Invoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => $invoice->customer_id,
                'customer_name' => $invoice->customer?->name ?? 'Buyer',
                'balance' => (float) $invoice->balance,
                'currency' => $invoice->currency,
            ])
            ->values();
    }

    /** Suppliers selectable for an inline purchase entered inside the Daily Close. */
    private function getPurchaseSuppliersForDailyClose(string $companyId)
    {
        return \App\Modules\Accounting\Models\Vendor::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** Purchasable items (fuel + non-fuel) selectable for an inline purchase. */
    private function getPurchaseItemsForDailyClose(string $companyId)
    {
        return Item::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'fuel_category', 'unit_of_measure'])
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'is_fuel' => !is_null($item->fuel_category),
                'unit' => $item->unit_of_measure ?? 'unit',
            ]);
    }

    private function getPartnersForDailyClose(string $companyId)
    {
        return Partner::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'drawing_limit_period', 'drawing_limit_amount', 'current_period_withdrawn', 'total_invested', 'total_withdrawn'])
            ->map(fn (Partner $partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'drawing_limit_period' => $partner->drawing_limit_period,
                'drawing_limit_amount' => $partner->drawing_limit_amount !== null ? (float) $partner->drawing_limit_amount : null,
                'current_period_withdrawn' => (float) $partner->current_period_withdrawn,
                'remaining_drawing_limit' => $partner->remaining_drawing_limit,
                'total_invested' => (float) $partner->total_invested,
                'total_withdrawn' => (float) $partner->total_withdrawn,
                'net_capital' => $partner->net_capital,
            ]);
    }

    private function getAmanatHoldersForDailyClose(string $companyId)
    {
        return CustomerProfile::where('company_id', $companyId)
            ->where('is_amanat_holder', true)
            ->with('customer:id,name,phone')
            ->orderByDesc('amanat_balance')
            ->get(['id', 'company_id', 'customer_id', 'amanat_balance'])
            ->map(fn (CustomerProfile $profile) => [
                'id' => $profile->customer_id,
                'name' => $profile->customer?->name ?? 'Unknown customer',
                'phone' => $profile->customer?->phone,
                'amanat_balance' => (float) $profile->amanat_balance,
            ]);
    }

    private function getInvestorsForDailyClose(string $companyId)
    {
        return Investor::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'total_invested', 'total_commission_earned', 'total_commission_paid'])
            ->map(fn (Investor $investor) => [
                'id' => $investor->id,
                'name' => $investor->name,
                'total_invested' => (float) $investor->total_invested,
                'outstanding_commission' => $investor->outstanding_commission,
                'units_remaining' => $investor->total_units_remaining,
            ]);
    }

    private function getRateChangeSnapshotsForDailyClose(string $companyId, string $date)
    {
        return RateChange::where('company_id', $companyId)
            ->whereDate('effective_date', $date)
            ->where(function ($query) {
                $query->whereNotNull('snapshot_dip_liters')
                    ->orWhereNotNull('snapshot_nozzle_readings');
            })
            ->with('item:id,name,fuel_category')
            ->get()
            ->map(fn (RateChange $rateChange) => [
                'id' => $rateChange->id,
                'item_id' => $rateChange->item_id,
                'item_name' => $rateChange->item?->name,
                'effective_date' => $rateChange->effective_date?->toDateString(),
                'old_sale_rate' => (float) (RateChange::where('company_id', $companyId)
                    ->where('item_id', $rateChange->item_id)
                    ->whereDate('effective_date', '<', $rateChange->effective_date)
                    ->orderByDesc('effective_date')
                    ->value('sale_rate') ?? 0),
                'new_sale_rate' => (float) $rateChange->sale_rate,
                'snapshot_dip_liters' => $rateChange->snapshot_dip_liters !== null ? (float) $rateChange->snapshot_dip_liters : null,
                'snapshot_nozzle_count' => count($rateChange->snapshot_nozzle_readings ?? []),
                'snapshot_nozzle_readings' => $rateChange->snapshot_nozzle_readings ?? [],
            ]);
    }

    private function getTankBaselines(string $companyId, $tanks, string $date, string $previousDate, ?array $parkedFigures = null)
    {
        $baselines = collect();

        foreach ($tanks as $tank) {
            // The previous day is parked but not posted: its draft dip is the freshest evidence
            // we have, dated the parked business date, so it stands in for a real posted dip —
            // see DailyCloseReconciliationService::parkedClosingFigures.
            $parkedTank = $parkedFigures['tanks'][$tank->id] ?? null;
            if ($parkedTank && $parkedTank['liters'] !== null) {
                $baselines->put($tank->id, (object) [
                    'tank_id' => $tank->id,
                    'dip_measurement_liters' => $parkedTank['liters'],
                    'stick_reading' => $parkedTank['stick_reading'] ?? 0,
                    'reading_date' => \Carbon\Carbon::parse($parkedFigures['date']),
                    'source' => 'parked_close',
                    'source_label' => 'Parked close (not posted)',
                    'as_of' => $parkedFigures['date'],
                ]);
                continue;
            }

            $dipReading = $this->latestTankDipBeforeClose($companyId, $tank->id, $date);
            $stockBaseline = $this->latestStockBaselineBeforeClose($companyId, $tank->id, $tank->linked_item_id, $date);

            $baseline = $dipReading ?: $stockBaseline;

            if ($baseline) {
                $baselines->put($tank->id, $baseline);
            }
        }

        return $baselines;
    }

    private function latestTankDipBeforeClose(string $companyId, string $tankId, string $date): ?object
    {
        $reading = TankReading::where('company_id', $companyId)
            ->where('tank_id', $tankId)
            ->where('status', TankReading::STATUS_POSTED)
            ->whereDate('reading_date', '<', $date)
            ->orderByDesc('reading_date')
            ->orderByDesc('created_at')
            ->first(['tank_id', 'dip_measurement_liters', 'stick_reading', 'reading_date', 'created_at']);

        if (! $reading) {
            return null;
        }

        $reading->source = 'tank_dip';
        $reading->source_label = 'Tank dip';
        $reading->as_of = $reading->reading_date?->toDateString();

        return $reading;
    }

    private function latestStockBaselineBeforeClose(string $companyId, string $tankId, ?string $itemId, string $date): ?object
    {
        if (! $itemId) {
            return null;
        }

        $stockMovement = StockMovement::where('company_id', $companyId)
            ->where('warehouse_id', $tankId)
            ->where('item_id', $itemId)
            ->whereDate('movement_date', '<=', $date)
            ->orderByDesc('movement_date')
            ->orderByDesc('created_at')
            ->first(['warehouse_id', 'item_id', 'movement_date', 'movement_type', 'created_at']);

        if (! $stockMovement) {
            return null;
        }

        $movementDate = $stockMovement->movement_date?->toDateString();
        $stockQuantity = (float) StockMovement::where('company_id', $companyId)
            ->where('warehouse_id', $tankId)
            ->where('item_id', $itemId)
            ->whereDate('movement_date', '<=', $movementDate)
            ->sum('quantity');

        return (object) [
            'tank_id' => $tankId,
            'dip_measurement_liters' => $stockQuantity,
            'stick_reading' => 0,
            'reading_date' => $stockMovement->movement_date,
            'source' => 'stock_level',
            'source_label' => $this->stockMovementLabel($stockMovement->movement_type),
            'as_of' => $movementDate,
            'created_at' => $stockMovement->created_at,
        ];
    }

    private function stockMovementLabel(?string $movementType): string
    {
        return match ($movementType) {
            'adjustment_in' => 'Stock adjustment in',
            'adjustment_out' => 'Stock adjustment out',
            'purchase' => 'Stock receipt',
            'transfer_in' => 'Stock transfer in',
            'transfer_out' => 'Stock transfer out',
            'opening' => 'Opening stock',
            default => 'Inventory stock',
        };
    }

    private function decorateTanksWithStockSnapshot(string $companyId, $tanks, string $date, $baselines = null): void
    {
        foreach ($tanks as $tank) {
            $stockLevel = null;
            $latestMovement = null;
            $movementsSinceBaseline = 0.0;

            if ($tank->linked_item_id) {
                $stockLevel = StockLevel::where('company_id', $companyId)
                    ->where('warehouse_id', $tank->id)
                    ->where('item_id', $tank->linked_item_id)
                    ->first(['quantity', 'available_quantity']);

                $latestMovement = StockMovement::where('company_id', $companyId)
                    ->where('warehouse_id', $tank->id)
                    ->where('item_id', $tank->linked_item_id)
                    ->orderByDesc('movement_date')
                    ->orderByDesc('created_at')
                    ->first(['movement_date', 'movement_type', 'created_at']);

                $baseline = $baselines?->get($tank->id);
                $baselineDate = $baseline?->as_of ?? $baseline?->reading_date?->toDateString();

                if ($baselineDate) {
                    $movementsSinceBaseline = (float) StockMovement::where('company_id', $companyId)
                        ->where('warehouse_id', $tank->id)
                        ->where('item_id', $tank->linked_item_id)
                        ->whereDate('movement_date', '>', $baselineDate)
                        ->whereDate('movement_date', '<=', $date)
                        ->where(function ($query) {
                            $query->whereNull('reference_type')
                                ->orWhere('reference_type', '!=', 'fuel.daily_close');
                        })
                        ->sum('quantity');
                }
            }

            $movementDate = $latestMovement?->movement_date?->toDateString();

            $tank->setAttribute('current_stock_liters', $stockLevel ? (float) $stockLevel->quantity : null);
            $tank->setAttribute('current_stock_source_label', $this->stockMovementLabel($latestMovement?->movement_type));
            $tank->setAttribute('current_stock_as_of', $movementDate);
            $tank->setAttribute('current_stock_after_close_date', $movementDate !== null && $movementDate > $date);
            $tank->setAttribute('stock_movements_since_baseline_liters', $movementsSinceBaseline);

            // A fuel bill's litres are invisible to the dip until someone receives
            // them, so the preview must surface what the close will receive on
            // posting - otherwise a delivery entered from Accounting -> Bills but
            // not yet received reads here as a false gain. See
            // DailyCloseService::pendingDeliveries.
            $pendingDeliveries = [];
            $pendingDeliveryLiters = 0.0;
            if ($tank->linked_item_id) {
                $baseline = $baselines?->get($tank->id);
                $afterDate = $baseline?->as_of ?? $baseline?->reading_date?->toDateString();
                $pendingDeliveries = $this->dailyCloseService
                    ->pendingDeliveries($companyId, $tank->id, $tank->linked_item_id, $afterDate, $date)
                    ->map(fn ($row) => [
                        'bill_id' => $row['bill_id'],
                        'bill_number' => $row['bill_number'],
                        'bill_date' => $row['bill_date'],
                        'litres' => $row['remaining'],
                    ])
                    ->values()
                    ->all();
                $pendingDeliveryLiters = array_sum(array_column($pendingDeliveries, 'litres'));
            }
            $tank->setAttribute('pending_deliveries', $pendingDeliveries);
            $tank->setAttribute('pending_delivery_liters', $pendingDeliveryLiters);
        }
    }

    /**
     * Show the daily close form - tabbed wizard matching their manual register.
     */
    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasCompanyPermission(Permissions::DAILY_CLOSE_CREATE), 403);
        /** @var Company $company */
        $company = app(CurrentCompany::class)->get();
        $companyId = $company->id;

        // The close is a morning ritual: today's dip closes yesterday. Before noon, default to yesterday.
        $defaultDate = now()->hour < 12 ? now()->subDay()->toDateString() : now()->toDateString();
        $date = $request->get('date', $defaultDate);

        // Get fuel items with current rates
        $priceColumns = DB::table('information_schema.columns')
            ->where('table_schema', 'inv')
            ->where('table_name', 'items')
            ->whereIn('column_name', ['sale_price', 'selling_price'])
            ->pluck('column_name')
            ->all();
        $hasSalePrice = in_array('sale_price', $priceColumns, true);
        $hasSellingPrice = in_array('selling_price', $priceColumns, true);
        $fuelSelect = ['id', 'name', 'fuel_category', 'avg_cost'];
        if ($hasSalePrice) {
            $fuelSelect[] = 'sale_price';
        }
        if ($hasSellingPrice) {
            $fuelSelect[] = 'selling_price';
        }
        $fuelItems = Item::where('company_id', $companyId)
            ->whereNotNull('fuel_category')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('fuel_category')
            ->get($fuelSelect)
            ->each(function ($item) use ($hasSalePrice, $hasSellingPrice) {
                $salePrice = null;
                if ($hasSalePrice && isset($item->sale_price)) {
                    $salePrice = $item->sale_price;
                } elseif ($hasSellingPrice && isset($item->selling_price)) {
                    $salePrice = $item->selling_price;
                }
                $item->sale_price = $salePrice;
            });

        $rates = [];
        foreach ($fuelItems as $item) {
            $rate = RateChange::getRateForDate($companyId, $item->id, $date);
            $rates[$item->id] = [
                'purchase_rate' => (float) ($rate?->purchase_rate ?? $item->avg_cost ?? 0),
                'sale_rate' => (float) ($rate?->sale_rate ?? $item->sale_price ?? 0),
            ];
        }

        // Get tanks with their dip sticks
        $tanks = Warehouse::where('company_id', $companyId)
            ->where('warehouse_type', 'tank')
            ->where('is_active', true)
            ->with(['linkedItem:id,name,fuel_category', 'dipStick:id,code,name,unit'])
            ->get(['id', 'code', 'name', 'capacity', 'linked_item_id', 'dip_stick_id']);

        // Get previous day for lookups
        $previousDate = date('Y-m-d', strtotime($date . ' -1 day'));

        // Owner's rule A: even when the previous day was never posted, a parked draft's
        // figures still open this day - see DailyCloseReconciliationService::parkedClosingFigures.
        $parkedFigures = app(\App\Modules\FuelStation\Services\DailyCloseReconciliationService::class)
            ->parkedClosingFigures($companyId, $previousDate);
        $openingsFromParked = $parkedFigures ? $previousDate : null;

        $previousTankReadings = $this->getTankBaselines($companyId, $tanks, $date, $previousDate, $parkedFigures);
        $this->decorateTanksWithStockSnapshot($companyId, $tanks, $date, $previousTankReadings);

        // Get nozzles with pump info, item info, and previous day's closing reading
        $nozzles = Nozzle::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereHas('pump', fn ($query) => $query->where('is_active', true))
            ->with([
                'pump:id,name',
                'item:id,name,fuel_category',
                'tank:id,name,code',
            ])
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['id', 'company_id', 'pump_id', 'tank_id', 'item_id', 'code', 'label', 'current_meter_reading', 'last_closing_reading', 'last_manual_reading', 'has_electronic_meter'])
            ->map(function ($nozzle) use ($companyId, $previousDate, $rates, $parkedFigures) {
                // Get previous day's closing reading if exists
                $previousReading = NozzleReading::where('company_id', $companyId)
                    ->where('nozzle_id', $nozzle->id)
                    ->where('reading_date', $previousDate)
                    ->first();

                // A parked-but-unposted previous day's own closing readings take priority over
                // the posted fallbacks - see DailyCloseReconciliationService::parkedClosingFigures.
                $parkedNozzle = $parkedFigures['nozzles'][$nozzle->id] ?? null;

                $openingReading = ($parkedNozzle && ($parkedNozzle['closing_electronic'] ?? 0) > 0)
                    ? $parkedNozzle['closing_electronic']
                    : ($previousReading?->closing_electronic
                        ?? $nozzle->last_closing_reading
                        ?? $nozzle->current_meter_reading
                        ?? 0);
                $openingManual = ($parkedNozzle && ($parkedNozzle['closing_manual'] ?? 0) > 0)
                    ? $parkedNozzle['closing_manual']
                    : ($previousReading?->closing_manual
                        ?? $nozzle->last_manual_reading
                        ?? null);

                return [
                    'id' => $nozzle->id,
                    'code' => $nozzle->code,
                    'label' => $nozzle->label,
                    'pump_id' => $nozzle->pump_id,
                    'pump_name' => $nozzle->pump?->name,
                    'tank_id' => $nozzle->tank_id,
                    'tank_name' => $nozzle->tank?->name,
                    'item_id' => $nozzle->item_id,
                    'fuel_name' => $nozzle->item?->name,
                    'fuel_category' => $nozzle->item?->fuel_category,
                    'has_electronic_meter' => $nozzle->has_electronic_meter,
                    'opening_reading' => (float) $openingReading,
                    'opening_manual' => $openingManual !== null ? (float) $openingManual : null,
                    'sale_rate' => $rates[$nozzle->item_id]['sale_rate'] ?? 0,
                ];
            });

        // Get pumps grouped by tank (for display grouping)
        $pumps = Pump::where('company_id', $companyId)
            ->where('is_active', true)
            ->with('tank:id,name,linked_item_id')
            ->get(['id', 'name', 'tank_id', 'current_meter_reading'])
            ->map(fn ($pump) => [
                'id' => $pump->id,
                'name' => $pump->name,
                'tank_id' => $pump->tank_id,
                'current_meter_reading' => $pump->current_meter_reading !== null
                    ? (float) $pump->current_meter_reading
                    : 0,
                'nozzle_count' => $pump->nozzle_count ?? 2,
                'tank' => $pump->tank,
            ]);

        // Get previous day's closing balance (cash)
        $previousClose = $this->dailyCloseService->getPreviousDayClosing($companyId, $date);
        if ($parkedFigures && $parkedFigures['closing_cash'] !== null) {
            $previousClose = [
                'date' => $parkedFigures['date'],
                'closing_cash' => round($parkedFigures['closing_cash'], 2),
                'exists' => true,
                'source' => 'parked',
            ];
        }

        // Get live people/balance lookups for daily close
        $partners = $this->getPartnersForDailyClose($companyId);
        $amanatHolders = $this->getAmanatHoldersForDailyClose($companyId);
        $investors = $this->getInvestorsForDailyClose($companyId);

        // Get employees for advances
        $employees = $this->getEmployeesForAdvances($companyId);
        $approvedPayrollPayouts = $this->getApprovedPayrollPayouts($companyId, $date);
        $pendingBillPayments = $this->getPendingBillPaymentsForDailyClose($companyId, $date);
        $pendingFuelInvoices = $this->getPendingFuelInvoicesForDailyClose($companyId, $date);
        $pendingAccountingInvoices = $this->getPendingAccountingInvoicesForDailyClose($companyId, $date);
        $purchaseSuppliers = $this->getPurchaseSuppliersForDailyClose($companyId);
        $purchaseItems = $this->getPurchaseItemsForDailyClose($companyId);
        $canEnterPurchases = auth()->user()?->hasCompanyPermission(Permissions::BILL_CREATE) ?? false;

        // Get bank accounts
        $bankAccounts = Account::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where('subtype', 'bank')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
        $openInvoices = $this->getOpenInvoicesForDailyClose($companyId);
        // Which of paymentAccounts (below) are cash, so the Payments Received section can
        // tell the frontend which rows raise expected drawer cash without redeclaring the
        // payment-accounts query itself.
        $cashAccountIds = Account::where('company_id', $companyId)->where('is_active', true)
            ->whereNull('deleted_at')->where('subtype', 'cash')->pluck('id')->all();
        $paymentAccounts = Account::where('company_id', $companyId)
            ->where('is_active', true)->whereNull('deleted_at')->whereIn('subtype', ['cash', 'bank'])
            ->orderBy('code')->get(['id', 'code', 'name']);

        // Get expense accounts
        $expenseAccounts = Account::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where('type', 'expense')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $otherDepositAccounts = Account::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereIn('type', ['revenue', 'other_income', 'liability', 'equity'])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        // Get lubricant items for other sales
        $lubricantSelect = ['id', 'name', 'sku', 'brand', 'unit_of_measure', 'cost_price'];
        if ($hasSalePrice) {
            $lubricantSelect[] = 'sale_price';
        }
        if ($hasSellingPrice) {
            $lubricantSelect[] = 'selling_price';
        }
        $lubricantItems = Item::where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereNull('fuel_category')
                    ->orWhere('fuel_category', 'lubricant');
            })
            ->where('item_type', 'product')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get($lubricantSelect)
            ->map(function ($item) use ($hasSalePrice, $hasSellingPrice) {
                $salePrice = null;
                if ($hasSalePrice && isset($item->sale_price)) {
                    $salePrice = $item->sale_price;
                } elseif ($hasSellingPrice && isset($item->selling_price)) {
                    $salePrice = $item->selling_price;
                }
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'brand' => $item->brand,
                    'unit' => $item->unit_of_measure ?? 'unit',
                    'sale_price' => (float) ($salePrice ?? 0),
                ];
            });

        // Get today's tank readings if any exist
        $existingTankReadings = TankReading::where('company_id', $companyId)
            ->whereDate('reading_date', $date)
            ->get(['id', 'tank_id', 'stick_reading', 'dip_measurement_liters', 'status']);

        // Get station settings for dynamic payment channels
        $stationSettings = null;
        try {
            $stationSettings = StationSettings::where('company_id', $companyId)->first();
        } catch (\Throwable $e) {
            // Table might not exist
        }

        // Build payment channels from settings or use defaults
        $paymentChannels = $stationSettings?->enabled_payment_channels
            ?? StationSettings::DEFAULT_PAYMENT_CHANNELS;

        // Get feature flags
        $features = [
            'has_partners' => $stationSettings?->has_partners ?? true,
            'has_amanat' => $stationSettings?->has_amanat ?? true,
            'has_lubricant_sales' => $stationSettings?->has_lubricant_sales ?? true,
            'has_investors' => $stationSettings?->has_investors ?? false,
            'dual_meter_readings' => $stationSettings?->dual_meter_readings ?? false,
        ];

        return Inertia::render('FuelStation/DailyClose/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency ?? 'PKR',
            ],
            'parkedDraft' => app(\App\Modules\FuelStation\Services\DailyCloseReconciliationService::class)->draft($companyId, $date),
            'canonicalActivity' => array_values(app(\App\Modules\FuelStation\Services\DailyCloseReconciliationService::class)->sources($companyId, $date)),
            'date' => $date,
            'openingsFromParked' => $openingsFromParked,
            'fuelItems' => $fuelItems,
            'rates' => $rates,
            'rateChangeSnapshots' => $this->getRateChangeSnapshotsForDailyClose($companyId, $date),
            'tanks' => $tanks,
            'pumps' => $pumps,
            'nozzles' => $nozzles,
            'partners' => $partners,
            'employees' => $employees,
            'approvedPayrollPayouts' => $approvedPayrollPayouts,
            'pendingBillPayments' => $pendingBillPayments,
            'pendingFuelInvoices' => $pendingFuelInvoices,
            'pendingAccountingInvoices' => $pendingAccountingInvoices,
            'unpaidDirectDeliveries' => $this->unpaidDirectDeliveries($companyId, $date),
            'purchaseSuppliers' => $purchaseSuppliers,
            'purchaseItems' => $purchaseItems,
            'canEnterPurchases' => $canEnterPurchases,
            'amanatHolders' => $amanatHolders,
            'investors' => $investors,
            'bankAccounts' => $bankAccounts,
            'openInvoices' => $openInvoices,
            'cashAccountIds' => $cashAccountIds,
            'paymentAccounts' => $paymentAccounts,
            'expenseAccounts' => $expenseAccounts,
            'otherDepositAccounts' => $otherDepositAccounts,
            'lubricantItems' => $lubricantItems,
            'existingTankReadings' => $existingTankReadings,
            'previousTankReadings' => $previousTankReadings->map(fn($r) => [
                'tank_id' => $r->tank_id,
                'liters' => (float) $r->dip_measurement_liters,
                'stick_reading' => (float) $r->stick_reading,
                'source' => $r->source ?? 'tank_dip',
                'source_label' => $r->source_label ?? 'Tank dip',
                'as_of' => $r->as_of ?? null,
            ])->values(),
            'previousClose' => $previousClose,
            'paymentChannels' => $paymentChannels,
            'features' => $features,
            'fuelVendor' => $stationSettings?->fuel_vendor ?? 'parco',
            'fuelCardLabel' => $stationSettings?->fuel_card_label ?? 'Fuel Card',
            'canFillTestData' => ! app()->environment('production'),
        ]);
    }

    /**
     * Store the daily close entry.
     */
    public function store(\App\Modules\FuelStation\Http\Requests\StoreDailyCloseRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $validated = $request->validated();

        try {
            $result = app(\App\Services\CommandBus::class)->dispatch('fuel.daily_close.save', $validated, $request->user());
            if ($result['parked'] ?? false) {
                return back()->with('success', 'Daily Close parked. Resume it by selecting this business date.');
            }


            return redirect()
                ->route('fuel.daily-close.index', ['company' => $company->slug])
                ->with('success', 'Daily close processed successfully. Transaction: ' . $result['transaction_number']);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Show the daily close history/reports.
     */
    public function postCloseExpense(\App\Modules\FuelStation\Http\Requests\StorePostCloseExpenseRequest $request, string $company, string $transaction): RedirectResponse
    {
        $companyModel = app(CurrentCompany::class)->get();
        $close = Transaction::where('company_id', $companyModel->id)->where('transaction_type', 'fuel_daily_close')->findOrFail($transaction);
        try {
            app(\App\Services\CommandBus::class)->dispatch('fuel.daily_close.expense', $request->validated() + ['close_id' => $close->id], $request->user());
            return back()->with('success', 'Expense recorded. The posted snapshot is unchanged.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Direct-delivery invoices of this business day still owed. The close leaves a direct sale
     * out of its pump credit rows, so a cash payment is what brings its money into the drawer;
     * listing the unpaid ones lets the close record that payment instead of showing an overage.
     */
    private function unpaidDirectDeliveries(string $companyId, string $date): array
    {
        return \App\Modules\Accounting\Models\Invoice::where('company_id', $companyId)
            ->where('is_direct_delivery', true)
            ->whereDate('invoice_date', $date)
            ->whereNotIn('status', ['void', 'cancelled', 'draft', 'paid'])
            ->where('balance', '>', 0)
            ->with('customer:id,name')
            ->orderBy('invoice_number')
            ->get()
            ->map(fn ($invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_name' => $invoice->customer?->name,
                'balance' => round((float) $invoice->balance, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * "Received in cash" on a direct delivery: the ordinary customer payment, into the cash
     * drawer, dated the business day, applied to that invoice. The close then counts it as
     * money in like any other cash that came in that day.
     */
    public function receiveDirectDeliveryCash(\App\Modules\FuelStation\Http\Requests\ReceiveDirectDeliveryCashRequest $request, string $company, string $invoice): RedirectResponse
    {
        $companyModel = app(CurrentCompany::class)->get();
        $record = \App\Modules\Accounting\Models\Invoice::where('company_id', $companyModel->id)
            ->where('is_direct_delivery', true)
            ->findOrFail($invoice);
        $amount = round((float) $record->balance, 2);
        $cashAccountId = $this->dailyCloseService->cashAccountId($companyModel->id);

        if ($amount <= 0) {
            return back()->with('error', "{$record->invoice_number} is already paid.");
        }
        if (! $cashAccountId) {
            return back()->with('error', 'No cash account is set for this station.');
        }

        try {
            app(\App\Services\CommandBus::class)->dispatch('payment.create', [
                'customer_id' => $record->customer_id,
                'allocations' => [['invoice_id' => $record->id, 'amount' => $amount]],
                'amount' => $amount,
                'method' => 'cash',
                'date' => $request->validated()['date'],
                'deposit_account_id' => $cashAccountId,
                'reference' => $record->invoice_number,
            ], $request->user());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Cash received for {$record->invoice_number}. It is counted in today's money in.");
    }

    public function storeCorrection(\App\Modules\FuelStation\Http\Requests\StoreCloseReadingCorrectionRequest $request, string $company, string $transaction): RedirectResponse
    {
        $companyModel = app(CurrentCompany::class)->get();
        $close = Transaction::where('company_id', $companyModel->id)->where('transaction_type', 'fuel_daily_close')->findOrFail($transaction);
        try {
            app(\App\Services\CommandBus::class)->dispatch('fuel.daily_close.correct_reading', $request->validated() + ['close_id' => $close->id], $request->user());
            return back()->with('success', 'Correction recorded. The posted snapshot is unchanged.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasCompanyPermission(Permissions::DAILY_CLOSE_VIEW), 403);
        $company = app(CurrentCompany::class)->get();

        // The window is the user's to choose, and it has to be reachable. Thirty days was
        // hardcoded, so a back-dated close - or simply last quarter's - was unreachable from
        // this page, which then reported that no closes existed at all.
        $range = (string) $request->query('range', '30');
        if (! in_array($range, ['30', '90', '365', 'all'], true)) {
            $range = '30';
        }

        $closes = $this->dailyCloseService->getRecentCloses(
            $company->id,
            $range === 'all' ? null : (int) $range,
        );

        // Get user permissions for UI
        $user = $request->user();
        $canLock = $user->hasCompanyPermission(Permissions::DAILY_CLOSE_LOCK);
        $canUnlock = $user->hasCompanyPermission(Permissions::DAILY_CLOSE_UNLOCK);

        return Inertia::render('FuelStation/DailyClose/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'closes' => $closes,
            'range' => $range,
            // Counted without the window, so the page can say "none in this range" rather
            // than "none at all" when the two are not the same thing.
            'totalCloses' => $this->dailyCloseService->countCloses($company->id),
            'parkedCloses' => DB::table('fuel.daily_close_drafts')->where('company_id', $company->id)->orderByDesc('business_date')->get(['business_date', 'updated_at']),
            'permissions' => [
                'canLock' => $canLock,
                'canUnlock' => $canUnlock,
            ],
        ]);
    }

    /**
     * Show a specific daily close (read-only view).
     */
    public function show(Request $request, string $company, string $transaction): Response
    {
        $companyModel = app(CurrentCompany::class)->get();

        // Check view permission
        $user = $request->user();
        if (!$user->hasCompanyPermission(Permissions::DAILY_CLOSE_VIEW)) {
            abort(403, 'You do not have permission to view daily closes.');
        }

        $txn = Transaction::where('id', $transaction)
            ->where('company_id', $companyModel->id)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereNull('deleted_at')
            ->firstOrFail();

        // Get user permissions
        $canLock = $user->hasCompanyPermission(Permissions::DAILY_CLOSE_LOCK);
        $canUnlock = $user->hasCompanyPermission(Permissions::DAILY_CLOSE_UNLOCK);

        $metadata = $txn->metadata ?? [];
        if (!is_array($metadata)) {
            $metadata = [];
        }

        return Inertia::render('FuelStation/DailyClose/Show', [
            'company' => [
                'id' => $companyModel->id,
                'name' => $companyModel->name,
                'slug' => $companyModel->slug,
                'base_currency' => $companyModel->base_currency ?? 'PKR',
            ],
            'transaction' => [
                'id' => $txn->id,
                'transaction_number' => $txn->transaction_number,
                'transaction_date' => $txn->transaction_date->toDateString(),
                'created_at' => $txn->created_at->toDateTimeString(),
                'status' => $txn->display_status,
                'is_locked' => $txn->is_locked,
                'lock_reason' => $txn->lock_reason,
                'locked_at' => $txn->locked_at?->toDateTimeString(),
                'metadata' => $metadata,
            ],
            // Every reopening of this day, oldest first. Append-only, so this is the whole
            // story even where a day was reopened and relocked several times.
            'unlockHistory' => $this->lockService->unlockHistory($txn)->map(fn ($row) => [
                'id' => $row->id,
                'unlocked_at' => $row->unlocked_at?->toDateTimeString(),
                'unlocked_by' => $row->unlockedBy?->name,
                'reason' => $row->reason,
                'previously_locked_at' => $row->previously_locked_at?->toDateTimeString(),
            ])->values(),
            'expenseAccounts' => Account::where('company_id', $companyModel->id)->where('is_active', true)->where('type', 'expense')->get(['id', 'name']),
            'canAddActivity' => $user->hasCompanyPermission(Permissions::DAILY_CLOSE_CREATE),
            'canCorrectReadings' => $user->hasCompanyPermission(Permissions::DAILY_CLOSE_CORRECT) && !empty($metadata['posting_snapshot']),
            'correctableReadings' => !empty($metadata['posting_snapshot']) ? [
                'tank' => TankReading::where('company_id', $companyModel->id)
                    ->whereDate('reading_date', $txn->transaction_date->toDateString())
                    ->get(['id', 'tank_id', 'dip_measurement_liters'])
                    ->map(fn ($r) => ['id' => $r->id, 'label' => 'Tank '.$r->tank_id.' — '.$r->dip_measurement_liters.'L', 'current_value' => (float) $r->dip_measurement_liters]),
                'nozzle' => NozzleReading::where('company_id', $companyModel->id)
                    ->where('daily_close_transaction_id', $txn->id)
                    ->get(['id', 'nozzle_id', 'liters_dispensed'])
                    ->map(fn ($r) => ['id' => $r->id, 'label' => 'Nozzle '.$r->nozzle_id.' — '.$r->liters_dispensed.'L', 'current_value' => (float) $r->liters_dispensed]),
            ] : ['tank' => [], 'nozzle' => []],
            'reconciliation' => app(\App\Modules\FuelStation\Services\DailyCloseReconciliationService::class)->view($txn),
            'permissions' => [
                'canLock' => $canLock && $txn->isLockable(),
                'canUnlock' => $canUnlock && $txn->is_locked,
            ],
        ]);
    }


    /**
     * Lock a daily close transaction.
     */
    public function lock(LockDailyCloseRequest $request, string $company, string $transaction): RedirectResponse
    {
        $companyModel = app(CurrentCompany::class)->get();

        $txn = Transaction::where('id', $transaction)
            ->where('company_id', $companyModel->id)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereNull('deleted_at')
            ->firstOrFail();

        if (!$txn->isLockable()) {
            return redirect()->back()->with('error', 'This entry cannot be locked. It may already be locked or reversed.');
        }

        try {
            $this->lockService->lockTransaction($txn, $request->user(), 'manual');
            return redirect()->back()->with('success', 'Daily close locked successfully.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Unlock a daily close transaction (owner only).
     */
    public function unlock(UnlockDailyCloseRequest $request, string $company, string $transaction): RedirectResponse
    {
        $companyModel = app(CurrentCompany::class)->get();

        $txn = Transaction::where('id', $transaction)
            ->where('company_id', $companyModel->id)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereNull('deleted_at')
            ->firstOrFail();

        if (!$txn->is_locked) {
            return redirect()->back()->with('error', 'This entry is not locked.');
        }

        try {
            $this->lockService->unlockTransaction($txn, $request->user(), $request->validated()['reason']);
            return redirect()->back()->with('success', 'Daily close reopened. The reason has been recorded.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Lock all daily closes for a month.
     */
    public function lockMonth(LockMonthDailyCloseRequest $request): RedirectResponse
    {
        $companyModel = app(CurrentCompany::class)->get();

        $validated = $request->validated();

        $count = $this->lockService->lockMonth(
            $companyModel->id,
            $validated['year'],
            $validated['month'],
            $request->user()->id
        );

        $monthName = \Carbon\Carbon::create($validated['year'], $validated['month'], 1)->format('F Y');

        return redirect()->back()->with('success', "Locked {$count} daily closes for {$monthName}.");
    }

}
