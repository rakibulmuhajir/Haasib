<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Partner;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Http\Requests\LockDailyCloseRequest;
use App\Modules\FuelStation\Http\Requests\LockMonthDailyCloseRequest;
use App\Modules\FuelStation\Http\Requests\StoreDailyCloseAmendmentRequest;
use App\Modules\FuelStation\Http\Requests\UnlockDailyCloseRequest;
use App\Modules\FuelStation\Models\Nozzle;
use App\Modules\FuelStation\Models\NozzleReading;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\FuelStation\Services\DailyCloseAmendmentService;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Payroll\Models\Employee;
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

        // Get previous day's tank readings for variance calculation
        // First try closing readings from previous day, then fall back to most recent reading (including opening)
        $previousTankReadings = collect();
        foreach ($tanks as $tank) {
            // Try previous day closing first
            $reading = TankReading::where('company_id', $companyId)
                ->where('tank_id', $tank->id)
                ->whereDate('reading_date', $previousDate)
                ->where('reading_type', 'closing')
                ->first(['tank_id', 'dip_measurement_liters', 'stick_reading', 'reading_date']);

            // If no closing, get most recent reading before today (could be opening or older closing)
            if (!$reading) {
                $reading = TankReading::where('company_id', $companyId)
                    ->where('tank_id', $tank->id)
                    ->whereDate('reading_date', '<', $date)
                    ->orderByDesc('reading_date')
                    ->first(['tank_id', 'dip_measurement_liters', 'stick_reading', 'reading_date']);
            }

            if ($reading) {
                $previousTankReadings->put($tank->id, $reading);
            }
        }

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

        // Get partners for withdrawals
        $partners = Partner::where('company_id', $companyId)
            ->where('is_active', true)
            ->get(['id', 'name', 'drawing_limit_period', 'drawing_limit_amount', 'current_period_withdrawn']);

        // Get employees for advances
        $employees = collect();
        try {
            DB::connection('pay')->select("SELECT set_config('app.current_company_id', ?, false)", [$companyId]);
            $employees = DB::connection('pay')
                ->table('employees')
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->get(['id', 'first_name', 'last_name', 'position', 'base_salary']);
        } catch (\Throwable $e) {
            try {
                $employees = Employee::where('company_id', $companyId)
                    ->where('is_active', true)
                    ->get(['id', 'first_name', 'last_name', 'position', 'base_salary']);
            } catch (\Throwable $fallbackException) {
                $employees = collect();
            }
        }

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

        // Get lubricant items for other sales
        $lubricantSelect = ['id', 'name', 'sku', 'brand', 'unit_of_measure', 'cost_price'];
        if ($hasSalePrice) {
            $lubricantSelect[] = 'sale_price';
        }
        if ($hasSellingPrice) {
            $lubricantSelect[] = 'selling_price';
        }
        $lubricantItems = Item::where('company_id', $companyId)
            ->whereNull('fuel_category')
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
            'bankAccounts' => $bankAccounts,
            'expenseAccounts' => $expenseAccounts,
            'lubricantItems' => $lubricantItems,
            'existingTankReadings' => $existingTankReadings,
            'previousTankReadings' => $previousTankReadings->map(fn($r) => [
                'tank_id' => $r->tank_id,
                'liters' => (float) $r->dip_measurement_liters,
                'stick_reading' => (float) $r->stick_reading,
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
            'other_sales.*.quantity' => 'required|integer|min:1',
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

            'amanat_disbursements' => 'nullable|array',
            'amanat_disbursements.*.customer_name' => 'required|string|max:255',
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

            return redirect()->back()->with('success', 'Daily close processed successfully. Transaction: ' . $result['transaction_number']);
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

        // Get previous day's tank readings for variance calculation
        // First try closing readings from previous day, then fall back to most recent reading (including opening)
        $previousTankReadings = collect();
        foreach ($tanks as $tank) {
            // Try previous day closing first
            $reading = TankReading::where('company_id', $companyId)
                ->where('tank_id', $tank->id)
                ->whereDate('reading_date', $previousDate)
                ->where('reading_type', 'closing')
                ->first(['tank_id', 'dip_measurement_liters', 'stick_reading', 'reading_date']);

            // If no closing, get most recent reading before today (could be opening or older closing)
            if (!$reading) {
                $reading = TankReading::where('company_id', $companyId)
                    ->where('tank_id', $tank->id)
                    ->whereDate('reading_date', '<', $date)
                    ->orderByDesc('reading_date')
                    ->first(['tank_id', 'dip_measurement_liters', 'stick_reading', 'reading_date']);
            }

            if ($reading) {
                $previousTankReadings->put($tank->id, $reading);
            }
        }

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

        // Get partners
        $partners = Partner::where('company_id', $companyId)
            ->where('is_active', true)
            ->get(['id', 'name', 'drawing_limit_period', 'drawing_limit_amount', 'current_period_withdrawn']);

        // Get employees
        $employees = Employee::where('company_id', $companyId)
            ->where('is_active', true)
            ->get(['id', 'first_name', 'last_name', 'position', 'base_salary']);

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

        // Get lubricant items
        $lubricantSelect = ['id', 'name', 'sku', 'brand', 'unit_of_measure', 'cost_price'];
        if ($hasSalePrice) {
            $lubricantSelect[] = 'sale_price';
        }
        if ($hasSellingPrice) {
            $lubricantSelect[] = 'selling_price';
        }
        $lubricantItems = Item::where('company_id', $companyId)
            ->whereNull('fuel_category')
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
            'bankAccounts' => $bankAccounts,
            'expenseAccounts' => $expenseAccounts,
            'lubricantItems' => $lubricantItems,
            'existingTankReadings' => $existingTankReadings,
            'previousTankReadings' => $previousTankReadings->map(fn($r) => [
                'tank_id' => $r->tank_id,
                'liters' => (float) $r->dip_measurement_liters,
                'stick_reading' => (float) $r->stick_reading,
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
