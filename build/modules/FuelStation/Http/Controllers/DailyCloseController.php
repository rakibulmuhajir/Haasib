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
use App\Modules\FuelStation\Http\Requests\StoreDailyCloseAmendmentRequest;
use App\Modules\FuelStation\Http\Requests\UnlockDailyCloseRequest;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Modules\FuelStation\Models\Investor;
use App\Modules\FuelStation\Models\Nozzle;
use App\Modules\FuelStation\Models\NozzleReading;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\FuelStation\Services\DailyCloseAmendmentService;
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
        private readonly DailyCloseAmendmentService $amendmentService,
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
                ->where('status', 'approved')
                ->whereNull('payment_gl_transaction_id')
                ->whereDate('approved_at', $date)
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

    private function getTankBaselines(string $companyId, $tanks, string $date, string $previousDate)
    {
        $baselines = collect();

        foreach ($tanks as $tank) {
            $dipReading = $this->latestTankDipBeforeClose($companyId, $tank->id, $date, $previousDate);
            $stockBaseline = $this->latestStockBaselineBeforeClose($companyId, $tank->id, $tank->linked_item_id, $date);

            $baseline = $this->stockBaselineIsNewer($stockBaseline, $dipReading)
                ? $stockBaseline
                : $dipReading;

            if ($baseline) {
                $baselines->put($tank->id, $baseline);
            }
        }

        return $baselines;
    }

    private function latestTankDipBeforeClose(string $companyId, string $tankId, string $date, string $previousDate): ?object
    {
        $reading = TankReading::where('company_id', $companyId)
            ->where('tank_id', $tankId)
            ->whereDate('reading_date', $previousDate)
            ->where('reading_type', 'closing')
            ->first(['tank_id', 'dip_measurement_liters', 'stick_reading', 'reading_date', 'created_at']);

        if (! $reading) {
            $reading = TankReading::where('company_id', $companyId)
                ->where('tank_id', $tankId)
                ->whereDate('reading_date', '<', $date)
                ->orderByDesc('reading_date')
                ->orderByDesc('created_at')
                ->first(['tank_id', 'dip_measurement_liters', 'stick_reading', 'reading_date', 'created_at']);
        }

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

        $stockLevel = StockLevel::where('company_id', $companyId)
            ->where('warehouse_id', $tankId)
            ->where('item_id', $itemId)
            ->first();

        if (! $stockLevel) {
            return null;
        }

        return (object) [
            'tank_id' => $tankId,
            'dip_measurement_liters' => (float) $stockLevel->quantity,
            'stick_reading' => 0,
            'reading_date' => $stockMovement->movement_date,
            'source' => 'stock_level',
            'source_label' => $this->stockMovementLabel($stockMovement->movement_type),
            'as_of' => $stockMovement->movement_date?->toDateString(),
            'created_at' => $stockMovement->created_at,
        ];
    }

    private function stockBaselineIsNewer(?object $stockBaseline, ?object $dipReading): bool
    {
        if (! $stockBaseline) {
            return false;
        }

        if (! $dipReading) {
            return true;
        }

        $stockDate = $stockBaseline->reading_date ? strtotime((string) $stockBaseline->reading_date) : 0;
        $dipDate = $dipReading->reading_date ? strtotime((string) $dipReading->reading_date) : 0;

        return $stockDate > $dipDate;
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

    private function decorateTanksWithStockSnapshot(string $companyId, $tanks, string $date): void
    {
        foreach ($tanks as $tank) {
            $stockLevel = null;
            $latestMovement = null;

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
            }

            $movementDate = $latestMovement?->movement_date?->toDateString();

            $tank->setAttribute('current_stock_liters', $stockLevel ? (float) $stockLevel->quantity : null);
            $tank->setAttribute('current_stock_source_label', $this->stockMovementLabel($latestMovement?->movement_type));
            $tank->setAttribute('current_stock_as_of', $movementDate);
            $tank->setAttribute('current_stock_after_close_date', $movementDate !== null && $movementDate > $date);
        }
    }

    /**
     * Show the daily close form - tabbed wizard matching their manual register.
     */
    public function create(Request $request): Response
    {
        /** @var Company $company */
        $company = app(CurrentCompany::class)->get();
        $companyId = $company->id;

        $date = $request->get('date', now()->toDateString());

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

        $previousTankReadings = $this->getTankBaselines($companyId, $tanks, $date, $previousDate);
        $this->decorateTanksWithStockSnapshot($companyId, $tanks, $date);

        // Get nozzles with pump info, item info, and previous day's closing reading
        $nozzles = Nozzle::where('company_id', $companyId)
            ->where('is_active', true)
            ->with([
                'pump:id,name',
                'item:id,name,fuel_category',
                'tank:id,name,code',
            ])
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['id', 'company_id', 'pump_id', 'tank_id', 'item_id', 'code', 'label', 'current_meter_reading', 'last_closing_reading', 'last_manual_reading', 'has_electronic_meter'])
            ->map(function ($nozzle) use ($companyId, $previousDate, $rates) {
                // Get previous day's closing reading if exists
                $previousReading = NozzleReading::where('company_id', $companyId)
                    ->where('nozzle_id', $nozzle->id)
                    ->where('reading_date', $previousDate)
                    ->first();

                $openingReading = $previousReading?->closing_electronic
                    ?? $nozzle->last_closing_reading
                    ?? $nozzle->current_meter_reading
                    ?? 0;
                $openingManual = $previousReading?->closing_manual
                    ?? $nozzle->last_manual_reading
                    ?? null;

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

        // Get live people/balance lookups for daily close
        $partners = $this->getPartnersForDailyClose($companyId);
        $amanatHolders = $this->getAmanatHoldersForDailyClose($companyId);
        $investors = $this->getInvestorsForDailyClose($companyId);

        // Get employees for advances
        $employees = $this->getEmployeesForAdvances($companyId);
        $approvedPayrollPayouts = $this->getApprovedPayrollPayouts($companyId, $date);
        $pendingBillPayments = $this->getPendingBillPaymentsForDailyClose($companyId, $date);

        // Get bank accounts
        $bankAccounts = Account::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where('subtype', 'bank')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

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
            'date' => $date,
            'fuelItems' => $fuelItems,
            'rates' => $rates,
            'tanks' => $tanks,
            'pumps' => $pumps,
            'nozzles' => $nozzles,
            'partners' => $partners,
            'employees' => $employees,
            'approvedPayrollPayouts' => $approvedPayrollPayouts,
            'pendingBillPayments' => $pendingBillPayments,
            'amanatHolders' => $amanatHolders,
            'investors' => $investors,
            'bankAccounts' => $bankAccounts,
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
        ]);
    }

    /**
     * Store the daily close entry.
     */
    public function store(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $validated = $request->validate([
            'date' => 'required|date',

            // Tab 1: Sales (nozzle readings - each nozzle has electronic + optional manual readings)
            'nozzle_readings' => 'required|array|min:1',
            'nozzle_readings.*.nozzle_id' => 'required|uuid',
            'nozzle_readings.*.item_id' => 'required|uuid',
            'nozzle_readings.*.opening_electronic' => 'required|numeric|min:0',
            'nozzle_readings.*.closing_electronic' => 'required|numeric|min:0',
            'nozzle_readings.*.opening_manual' => 'nullable|numeric|min:0',
            'nozzle_readings.*.closing_manual' => 'nullable|numeric|min:0',
            'nozzle_readings.*.liters_sold' => 'required|numeric|min:0',
            'nozzle_readings.*.sale_rate' => 'required|numeric|min:0',

            // Other sales (lubricants, etc.)
            'other_sales' => 'nullable|array',
            'other_sales.*.item_id' => 'required|uuid',
            'other_sales.*.item_name' => 'required|string|max:255',
            'other_sales.*.quantity' => 'required|numeric|min:0.001',
            'other_sales.*.unit_price' => 'required|numeric|min:0',
            'other_sales.*.amount' => 'required|numeric|min:0',

            // Tab 2: Tank readings
            'tank_readings' => 'nullable|array',
            'tank_readings.*.tank_id' => 'required|uuid',
            'tank_readings.*.stick_reading' => 'required|numeric|min:0',
            'tank_readings.*.liters' => 'required|numeric|min:0',

            // Tab 3: Money In
            'opening_cash' => 'required|numeric|min:0',
            'partner_deposits' => 'nullable|array',
            'partner_deposits.*.partner_id' => 'required|uuid',
            'partner_deposits.*.amount' => 'required|numeric|min:0',

            'amanat_deposits' => 'nullable|array',
            'amanat_deposits.*.customer_id' => 'required|uuid',
            'amanat_deposits.*.customer_name' => 'nullable|string|max:255',
            'amanat_deposits.*.amount' => 'required|numeric|min:0',
            'amanat_deposits.*.reference' => 'nullable|string|max:255',

            'other_deposits' => 'nullable|array',
            'other_deposits.*.deposit_type' => 'required|in:loss_compensation,fuel_disbursement,misc_income',
            'other_deposits.*.account_id' => 'nullable|uuid',
            'other_deposits.*.description' => 'nullable|string|max:255',
            'other_deposits.*.amount' => 'required|numeric|min:0',

            // Dynamic payment receipts (replaces hardcoded bank_transfers, card_swipes, parco_cards)
            'payment_receipts' => 'nullable|array',
            'payment_receipts.*.entries' => 'nullable|array',
            'payment_receipts.*.entries.*.reference' => 'nullable|string|max:255',
            'payment_receipts.*.entries.*.last_four' => 'nullable|string|max:4',
            'payment_receipts.*.entries.*.amount' => 'required|numeric|min:0',

            // Tab 4: Money Out
            'bank_deposits' => 'nullable|array',
            'bank_deposits.*.bank_account_id' => 'required|uuid',
            'bank_deposits.*.amount' => 'required|numeric|min:0',
            'bank_deposits.*.reference' => 'nullable|string|max:100',
            'bank_deposits.*.purpose' => 'nullable|string|max:255',

            'partner_withdrawals' => 'nullable|array',
            'partner_withdrawals.*.partner_id' => 'required|uuid',
            'partner_withdrawals.*.amount' => 'required|numeric|min:0',

            'employee_advances' => 'nullable|array',
            'employee_advances.*.employee_id' => 'required|uuid',
            'employee_advances.*.amount' => 'required|numeric|min:0',
            'employee_advances.*.reason' => 'nullable|string|max:255',

            'payroll_payouts' => 'nullable|array',
            'payroll_payouts.*.payslip_id' => 'required|uuid',
            'payroll_payouts.*.employee_id' => 'required|uuid',
            'payroll_payouts.*.amount' => 'required|numeric|min:0',

            'amanat_disbursements' => 'nullable|array',
            'amanat_disbursements.*.customer_id' => 'required|uuid',
            'amanat_disbursements.*.customer_name' => 'nullable|string|max:255',
            'amanat_disbursements.*.amount' => 'required|numeric|min:0',

            'expenses' => 'nullable|array',
            'expenses.*.account_id' => 'required|uuid',
            'expenses.*.description' => 'required|string|max:255',
            'expenses.*.amount' => 'required|numeric|min:0',

            // Tab 5: Summary
            'closing_cash' => 'required|numeric|min:0',
            'cash_variance' => 'nullable|numeric',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $result = $this->dailyCloseService->processDailyClose($company->id, $validated, $request->user());

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
    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();

        $closes = $this->dailyCloseService->getRecentCloses($company->id, 30);

        // Get user permissions for UI
        $user = $request->user();
        $canAmend = $user->hasCompanyPermission(Permissions::DAILY_CLOSE_AMEND);
        $canLock = $user->hasCompanyPermission(Permissions::DAILY_CLOSE_LOCK);
        $canUnlock = $user->hasCompanyPermission(Permissions::DAILY_CLOSE_UNLOCK);

        return Inertia::render('FuelStation/DailyClose/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'closes' => $closes,
            'permissions' => [
                'canAmend' => $canAmend,
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

        // Get amendment chain if this transaction was amended
        $chain = $this->amendmentService->getAmendmentChain($txn);

        // Get user permissions
        $user = $request->user();
        $canAmend = $user->hasCompanyPermission(Permissions::DAILY_CLOSE_AMEND);
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
                'is_amendable' => $txn->isAmendable(),
                'lock_reason' => $txn->lock_reason,
                'locked_at' => $txn->locked_at?->toDateTimeString(),
                'amendment_reason' => $txn->amendment_reason,
                'amended_at' => $txn->amended_at?->toDateTimeString(),
                'metadata' => $metadata,
            ],
            'amendmentChain' => $chain,
            'permissions' => [
                'canAmend' => $canAmend && $txn->isAmendable(),
                'canLock' => $canLock && $txn->isLockable(),
                'canUnlock' => $canUnlock && $txn->is_locked,
            ],
        ]);
    }

    /**
     * Show the amendment form (pre-filled with original data).
     */
    public function amend(Request $request, string $company, string $transaction): Response
    {
        $companyModel = app(CurrentCompany::class)->get();

        // Check permission
        $user = $request->user();
        if (!$user->hasCompanyPermission(Permissions::DAILY_CLOSE_AMEND)) {
            abort(403, 'You do not have permission to amend daily closes.');
        }

        $txn = Transaction::where('id', $transaction)
            ->where('company_id', $companyModel->id)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereNull('deleted_at')
            ->firstOrFail();

        if (!$txn->isAmendable()) {
            return redirect()->back()->with('error', 'This entry cannot be amended. It may be locked or already reversed.');
        }

        $date = $txn->transaction_date->toDateString();

        // Get all the same data as create(), but we'll pass the original values
        $createData = $this->getCreatePageData($companyModel, $date);

        // Add amendment-specific data
        $createData['isAmendment'] = true;

        $metadata = $txn->metadata ?? [];
        $formInput = $metadata['form_input'] ?? [];

        $createData['originalTransaction'] = [
            'id' => $txn->id,
            'transaction_number' => $txn->transaction_number,
            'metadata' => $metadata,
        ];

        // Pass original form data for pre-filling
        $createData['originalFormData'] = $formInput;

        return Inertia::render('FuelStation/DailyClose/Create', $createData);
    }

    /**
     * Store an amendment (reversal + correction).
     */
    public function storeAmendment(StoreDailyCloseAmendmentRequest $request, string $company, string $transaction): RedirectResponse
    {
        $companyModel = app(CurrentCompany::class)->get();

        $txn = Transaction::where('id', $transaction)
            ->where('company_id', $companyModel->id)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereNull('deleted_at')
            ->firstOrFail();

        if (!$txn->isAmendable()) {
            return redirect()->back()->with('error', 'This entry cannot be amended. It may be locked or already reversed.');
        }

        $validated = $request->validated();

        try {
            $result = $this->amendmentService->amendDailyClose(
                $txn,
                $validated,
                $request->user(),
                $validated['amendment_reason']
            );

            return redirect()
                ->route('fuel.daily-close.index', ['company' => $companyModel->slug])
                ->with('success', "Amendment posted. Reversal: {$result['reversal_number']}, Correction: {$result['correction_number']}");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
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
            $this->amendmentService->lockTransaction($txn, $request->user(), 'manual');
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
            $this->amendmentService->unlockTransaction($txn);
            return redirect()->back()->with('success', 'Daily close unlocked successfully.');
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

        $count = $this->amendmentService->lockMonth(
            $companyModel->id,
            $validated['year'],
            $validated['month'],
            $request->user()->id
        );

        $monthName = \Carbon\Carbon::create($validated['year'], $validated['month'], 1)->format('F Y');

        return redirect()->back()->with('success', "Locked {$count} daily closes for {$monthName}.");
    }

    /**
     * Get the amendment chain for a transaction (API endpoint).
     */
    public function amendmentChain(Request $request, string $company, string $transaction): \Illuminate\Http\JsonResponse
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

        $chain = $this->amendmentService->getAmendmentChain($txn);

        return response()->json(['chain' => $chain]);
    }

    /**
     * Extract create page data to a reusable method.
     */
    private function getCreatePageData(Company $company, string $date): array
    {
        $companyId = $company->id;

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

        $previousTankReadings = $this->getTankBaselines($companyId, $tanks, $date, $previousDate);

        // Get nozzles with pump info, item info, and previous day's closing reading
        $nozzles = Nozzle::where('company_id', $companyId)
            ->where('is_active', true)
            ->with([
                'pump:id,name',
                'item:id,name,fuel_category',
                'tank:id,name,code',
            ])
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['id', 'company_id', 'pump_id', 'tank_id', 'item_id', 'code', 'label', 'current_meter_reading', 'last_closing_reading', 'last_manual_reading', 'has_electronic_meter'])
            ->map(function ($nozzle) use ($companyId, $previousDate, $rates) {
                $previousReading = NozzleReading::where('company_id', $companyId)
                    ->where('nozzle_id', $nozzle->id)
                    ->where('reading_date', $previousDate)
                    ->first();

                $openingReading = $previousReading?->closing_electronic
                    ?? $nozzle->last_closing_reading
                    ?? $nozzle->current_meter_reading
                    ?? 0;
                $openingManual = $previousReading?->closing_manual
                    ?? $nozzle->last_manual_reading
                    ?? null;

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

        // Get pumps grouped by tank
        $pumps = Pump::where('company_id', $companyId)
            ->where('is_active', true)
            ->with('tank:id,name,linked_item_id')
            ->get(['id', 'name', 'tank_id', 'current_meter_reading'])
            ->map(fn($pump) => [
                'id' => $pump->id,
                'name' => $pump->name,
                'tank_id' => $pump->tank_id,
                'current_meter_reading' => $pump->current_meter_reading !== null
                    ? (float) $pump->current_meter_reading
                    : 0,
                'nozzle_count' => $pump->nozzle_count ?? 2,
                'tank' => $pump->tank,
            ]);

        // Get previous day's closing balance
        $previousClose = $this->dailyCloseService->getPreviousDayClosing($companyId, $date);

        // Get live people/balance lookups for daily close
        $partners = $this->getPartnersForDailyClose($companyId);
        $amanatHolders = $this->getAmanatHoldersForDailyClose($companyId);
        $investors = $this->getInvestorsForDailyClose($companyId);

        // Get employees
        $employees = $this->getEmployeesForAdvances($companyId);
        $approvedPayrollPayouts = $this->getApprovedPayrollPayouts($companyId, $date);
        $pendingBillPayments = $this->getPendingBillPaymentsForDailyClose($companyId, $date);

        // Get bank accounts
        $bankAccounts = Account::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where('subtype', 'bank')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

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

        // Get lubricant items
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

        // Get today's tank readings
        $existingTankReadings = TankReading::where('company_id', $companyId)
            ->whereDate('reading_date', $date)
            ->get(['id', 'tank_id', 'stick_reading', 'dip_measurement_liters', 'status']);

        // Get station settings
        $stationSettings = null;
        try {
            $stationSettings = StationSettings::where('company_id', $companyId)->first();
        } catch (\Throwable $e) {
            // Table might not exist
        }

        $paymentChannels = $stationSettings?->enabled_payment_channels
            ?? StationSettings::DEFAULT_PAYMENT_CHANNELS;

        $features = [
            'has_partners' => $stationSettings?->has_partners ?? true,
            'has_amanat' => $stationSettings?->has_amanat ?? true,
            'has_lubricant_sales' => $stationSettings?->has_lubricant_sales ?? true,
            'has_investors' => $stationSettings?->has_investors ?? false,
            'dual_meter_readings' => $stationSettings?->dual_meter_readings ?? false,
        ];

        return [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency ?? 'PKR',
            ],
            'date' => $date,
            'fuelItems' => $fuelItems,
            'rates' => $rates,
            'tanks' => $tanks,
            'pumps' => $pumps,
            'nozzles' => $nozzles,
            'partners' => $partners,
            'employees' => $employees,
            'approvedPayrollPayouts' => $approvedPayrollPayouts,
            'pendingBillPayments' => $pendingBillPayments,
            'amanatHolders' => $amanatHolders,
            'investors' => $investors,
            'bankAccounts' => $bankAccounts,
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
            'isAmendment' => false,
            'originalTransaction' => null,
        ];
    }
}
