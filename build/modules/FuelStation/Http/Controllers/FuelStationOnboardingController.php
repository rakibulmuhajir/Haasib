<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\IndustryCoaPack;
use App\Modules\FuelStation\Models\DipStick;
use App\Modules\FuelStation\Models\Nozzle;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\FuelStation\Services\FuelStationOnboardingService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Payroll\Models\Employee;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class FuelStationOnboardingController extends Controller
{
    public function __construct(
        private FuelStationOnboardingService $onboardingService
    ) {}

    /**
     * Show onboarding wizard.
     */
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();
        CompanyContext::setContext($company);
        try {
            DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
            if (!empty($company->base_currency)) {
                DB::select("SELECT set_config('app.company_base_currency', ?, false)", [$company->base_currency]);
            }
        } catch (\Throwable $e) {
            // If we can't set session config, queries may still work via explicit company filters.
        }

        $wizardData = $this->onboardingService->getWizardData($company->id);

        $industries = IndustryCoaPack::active()
            ->orderBy('sort_order')
            ->get(['code', 'name', 'description']);

        $timezones = [
            'Asia/Karachi' => 'Pakistan Standard Time (PKT)',
            'UTC' => 'Coordinated Universal Time (UTC)',
            'America/New_York' => 'Eastern Time (ET)',
            'Europe/London' => 'Greenwich Mean Time (GMT)',
            'Asia/Dubai' => 'Gulf Standard Time (GST)',
            'Asia/Singapore' => 'Singapore Time (SGT)',
        ];

        $months = [
            ['value' => 1, 'label' => 'January'],
            ['value' => 2, 'label' => 'February'],
            ['value' => 3, 'label' => 'March'],
            ['value' => 4, 'label' => 'April'],
            ['value' => 5, 'label' => 'May'],
            ['value' => 6, 'label' => 'June'],
            ['value' => 7, 'label' => 'July'],
            ['value' => 8, 'label' => 'August'],
            ['value' => 9, 'label' => 'September'],
            ['value' => 10, 'label' => 'October'],
            ['value' => 11, 'label' => 'November'],
            ['value' => 12, 'label' => 'December'],
        ];

        $currencies = DB::table('public.currencies')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name', 'symbol']);

        $arAccounts = Account::where('company_id', $company->id)
            ->where('subtype', 'accounts_receivable')
            ->where('is_active', true)
            ->get(['id', 'code', 'name']);

        $apAccounts = Account::where('company_id', $company->id)
            ->where('subtype', 'accounts_payable')
            ->where('is_active', true)
            ->get(['id', 'code', 'name']);

        $revenueAccounts = Account::where('company_id', $company->id)
            ->where('type', 'revenue')
            ->where('is_active', true)
            ->get(['id', 'code', 'name']);

        $expenseAccounts = Account::where('company_id', $company->id)
            ->where('type', 'expense')
            ->where('is_active', true)
            ->get(['id', 'code', 'name']);

        $bankAccounts = Account::where('company_id', $company->id)
            ->whereIn('subtype', ['bank', 'cash'])
            ->where('is_active', true)
            ->get(['id', 'code', 'name']);

        $existingBankAccounts = Account::where('company_id', $company->id)
            ->whereIn('subtype', ['bank', 'cash'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'name', 'currency', 'subtype']);

        $retainedEarningsAccounts = Account::where('company_id', $company->id)
            ->where('subtype', 'retained_earnings')
            ->where('is_active', true)
            ->get(['id', 'code', 'name']);

        $taxPayableAccounts = Account::where('company_id', $company->id)
            ->where('type', 'liability')
            ->where('name', 'like', '%tax%payable%')
            ->where('is_active', true)
            ->get(['id', 'code', 'name']);

        $taxReceivableAccounts = Account::where('company_id', $company->id)
            ->where('type', 'asset')
            ->where('name', 'like', '%tax%receivable%')
            ->where('is_active', true)
            ->get(['id', 'code', 'name']);

        $hasTransitLoss = Schema::connection('pgsql')->hasColumn('auth.companies', 'transit_loss_account_id');
        $hasTransitGain = Schema::connection('pgsql')->hasColumn('auth.companies', 'transit_gain_account_id');
        $transitColumnsReady = $hasTransitLoss && $hasTransitGain;

        $priceColumns = $this->resolveItemPriceColumns();
        $hasSalePrice = $priceColumns['sale_price'];
        $hasSellingPrice = $priceColumns['selling_price'];

        // Get partners for the company - use DB query to avoid model issues
        $partners = collect();
        try {
            $partners = DB::table('auth.partners')
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->select(['id', 'name', 'phone', 'profit_share_percentage', 'drawing_limit_period', 'drawing_limit_amount'])
                ->get();
        } catch (\Throwable $e) {
            // Table might not exist
        }

        // Get employees for the company - use DB query to avoid model issues
        $employees = collect();
        try {
            DB::connection('pay')->select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
            $employees = DB::connection('pay')
                ->table('employees')
                ->where('company_id', $company->id)
                ->whereNull('deleted_at')
                ->select(['id', 'first_name', 'last_name', 'phone', 'position', 'base_salary'])
                ->get();
        } catch (\Throwable $e) {
            try {
                DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
                $employees = DB::table('pay.employees')
                    ->where('company_id', $company->id)
                    ->whereNull('deleted_at')
                    ->select(['id', 'first_name', 'last_name', 'phone', 'position', 'base_salary'])
                    ->get();
            } catch (\Throwable $fallbackException) {
                // Table might not exist
            }
        }

        // Get dip sticks - use DB query
        $dipSticks = collect();
        try {
            $dipSticks = DipStick::where('company_id', $company->id)
                ->where('is_active', true)
                ->with('chartEntries')
                ->get();
        } catch (\Throwable $e) {
            // Table might not exist
        }

        // Get tanks (warehouses of type tank) with linked fuel category
        $tanks = collect();
        try {
            $tanks = Warehouse::where('company_id', $company->id)
                ->where('warehouse_type', 'tank')
                ->where('is_active', true)
                ->with(['linkedItem:id,name,sku,fuel_category', 'dipStick:id,code'])
                ->get()
                ->map(fn ($tank) => [
                    'id' => $tank->id,
                    'name' => $tank->name,
                    'code' => $tank->code,
                    'capacity' => $tank->capacity,
                    'fuel_category' => $this->normalizeFuelCategory($tank->linkedItem?->fuel_category),
                    'linked_item_id' => $tank->linked_item_id,
                    'linked_item_name' => $tank->linkedItem?->name,
                    'linked_item_sku' => $tank->linkedItem?->sku,
                    'dip_stick_code' => $tank->dipStick?->code,
                ]);
        } catch (\Throwable $e) {
            // Table might not exist
        }

        // Get pumps
        $pumps = collect();
        try {
            $pumps = Pump::where('company_id', $company->id)
                ->where('is_active', true)
                ->with(['nozzles' => function ($query) {
                    $query->orderBy('sort_order')->orderBy('code');
                }])
                ->get(['id', 'name', 'tank_id', 'current_meter_reading', 'current_manual_reading'])
                ->map(function ($pump) {
                    $frontNozzle = $pump->nozzles->firstWhere('sort_order', 0) ?? $pump->nozzles->get(0);
                    $backNozzle = $pump->nozzles->firstWhere('sort_order', 1) ?? $pump->nozzles->get(1);
                    $nozzleCount = $pump->nozzles->where('is_active', true)->count();

                    return [
                        'id' => $pump->id,
                        'name' => $pump->name,
                        'tank_id' => $pump->tank_id,
                        'nozzle_count' => $nozzleCount > 0 ? $nozzleCount : 2,
                        'front_electronic' => $frontNozzle?->last_closing_reading ?? $frontNozzle?->current_meter_reading ?? 0,
                        'front_manual' => $frontNozzle?->last_manual_reading,
                        'back_electronic' => $backNozzle?->last_closing_reading ?? $backNozzle?->current_meter_reading ?? 0,
                        'back_manual' => $backNozzle?->last_manual_reading,
                    ];
                });
        } catch (\Throwable $e) {
            // Table might not exist
        }

        // Get fuel items
        $fuelItems = collect();
        try {
            $fuelSelect = ['id', 'name', 'sku', 'fuel_category', 'avg_cost'];
            if ($hasSalePrice) {
                $fuelSelect[] = 'sale_price';
            }
            if ($hasSellingPrice) {
                $fuelSelect[] = 'selling_price';
            }
            $fuelItems = Item::where('company_id', $company->id)
                ->whereNotNull('fuel_category')
                ->where('is_active', true)
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
        } catch (\Throwable $e) {
            // Table might not exist
        }

        $lubricants = collect();
        try {
            $lubricantSelect = ['id', 'name', 'sku', 'brand', 'unit_of_measure', 'cost_price'];
            if ($hasSalePrice) {
                $lubricantSelect[] = 'sale_price';
            }
            if ($hasSellingPrice) {
                $lubricantSelect[] = 'selling_price';
            }
            $lubricants = Item::where('company_id', $company->id)
                ->whereNull('fuel_category')
                ->where(function ($query) {
                    $query->where('sku', 'like', 'OIL-%')
                        ->orWhere('sku', 'like', 'MOB-%')
                        ->orWhere('sku', 'like', 'LUB-%');
                })
                ->where('is_active', true)
                ->get($lubricantSelect)
                ->each(function ($item) use ($hasSalePrice, $hasSellingPrice) {
                    $salePrice = null;
                    if ($hasSalePrice && isset($item->sale_price)) {
                        $salePrice = $item->sale_price;
                    } elseif ($hasSellingPrice && isset($item->selling_price)) {
                        $salePrice = $item->selling_price;
                    }
                    $item->sale_price = $salePrice;
                });
        } catch (\Throwable $e) {
            // Table might not exist
        }

        $rateChanges = collect();
        try {
            $fuelItemIds = $fuelItems->pluck('id')->all();
            if (!empty($fuelItemIds)) {
                $rateChanges = RateChange::where('company_id', $company->id)
                    ->whereIn('item_id', $fuelItemIds)
                    ->orderByDesc('effective_date')
                    ->get(['id', 'item_id', 'effective_date', 'purchase_rate', 'sale_rate'])
                    ->groupBy('item_id')
                    ->map(fn ($group) => $group->first())
                    ->values();
            }
        } catch (\Throwable $e) {
            // Table might not exist
        }

        $openingReadings = collect();
        try {
            $openingReadings = TankReading::where('company_id', $company->id)
                ->where('reading_type', 'opening')
                ->orderByDesc('reading_date')
                ->get(['id', 'tank_id', 'reading_date', 'dip_measurement_liters', 'stick_reading'])
                ->groupBy('tank_id')
                ->map(function ($group) {
                    $reading = $group->first();
                    if ($reading) {
                        $reading->liters_measured = $reading->dip_measurement_liters;
                    }
                    return $reading;
                })
                ->values();
        } catch (\Throwable $e) {
            // Table might not exist
        }

        $settings = $company->settings ?? [];
        $openingBalances = $settings['opening_balances'] ?? [];

        // Get or create station settings with defaults
        $stationSettings = null;
        try {
            $stationSettings = StationSettings::where('company_id', $company->id)->first();
            if ($stationSettings) {
                $stationSettings = [
                    'id' => $stationSettings->id,
                    'fuel_vendor' => $stationSettings->fuel_vendor,
                    'vendor_name' => $stationSettings->vendor_name,
                    'fuel_card_label' => $stationSettings->fuel_card_label,
                    'has_partners' => $stationSettings->has_partners,
                    'has_amanat' => $stationSettings->has_amanat,
                    'has_lubricant_sales' => $stationSettings->has_lubricant_sales,
                    'has_investors' => $stationSettings->has_investors,
                    'dual_meter_readings' => $stationSettings->dual_meter_readings,
                    'track_attendant_handovers' => $stationSettings->track_attendant_handovers,
                    'payment_channels' => $stationSettings->payment_channels,
                    'cash_account_id' => $stationSettings->cash_account_id,
                    'fuel_sales_account_id' => $stationSettings->fuel_sales_account_id,
                    'fuel_cogs_account_id' => $stationSettings->fuel_cogs_account_id,
                    'fuel_inventory_account_id' => $stationSettings->fuel_inventory_account_id,
                    'operating_bank_account_id' => $stationSettings->operating_bank_account_id,
                    'fuel_card_clearing_account_id' => $stationSettings->fuel_card_clearing_account_id,
                    'card_pos_clearing_account_id' => $stationSettings->card_pos_clearing_account_id,
                ];
            }
        } catch (\Throwable $e) {
            // Table might not exist yet
        }

        // Fuel vendors list for dropdown
        $fuelVendors = StationSettings::VENDORS;

        return Inertia::render('FuelStation/Onboarding/Index', [
            'wizard' => $wizardData,
            'industries' => $industries,
            'timezones' => $timezones,
            'months' => $months,
            'partners' => $partners,
            'employees' => $employees,
            'dipSticks' => $dipSticks,
            'tanks' => $tanks,
            'pumps' => $pumps,
            'fuelItems' => $fuelItems,
            'lubricants' => $lubricants,
            'rateChanges' => $rateChanges,
            'openingReadings' => $openingReadings,
            'openingBalances' => $openingBalances,
            'currencies' => $currencies,
            'arAccounts' => $arAccounts,
            'apAccounts' => $apAccounts,
            'revenueAccounts' => $revenueAccounts,
            'expenseAccounts' => $expenseAccounts,
            'bankAccounts' => $bankAccounts,
            'existingBankAccounts' => $existingBankAccounts,
            'retainedEarningsAccounts' => $retainedEarningsAccounts,
            'taxPayableAccounts' => $taxPayableAccounts,
            'taxReceivableAccounts' => $taxReceivableAccounts,
            'transitColumnsReady' => $transitColumnsReady,
            'transitColumnsMessage' => $transitColumnsReady
                ? null
                : 'Transit Loss and Transit Gain will be added after the system update. You can continue setup for now.',
            'stationSettings' => $stationSettings,
            'fuelVendors' => $fuelVendors,
            'defaultPaymentChannels' => StationSettings::DEFAULT_PAYMENT_CHANNELS,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
                'industry_code' => $company->industry_code,
                'registration_number' => $company->registration_number,
                'trade_name' => $company->trade_name,
                'timezone' => $company->timezone,
                'fiscal_year_start_month' => $company->fiscal_year_start_month,
                'period_frequency' => $company->period_frequency,
                'tax_registered' => $company->tax_registered,
                'tax_rate' => $company->tax_rate,
                'tax_inclusive' => $company->tax_inclusive,
                'invoice_prefix' => $company->invoice_prefix,
                'invoice_start_number' => $company->invoice_start_number,
                'bill_prefix' => $company->bill_prefix,
                'bill_start_number' => $company->bill_start_number,
                'default_customer_payment_terms' => $company->default_customer_payment_terms,
                'default_vendor_payment_terms' => $company->default_vendor_payment_terms,
                'default_drawing_limit_period' => $company->default_drawing_limit_period ?? 'monthly',
                'default_drawing_limit_amount' => $company->default_drawing_limit_amount,
                'ar_account_id' => $company->ar_account_id,
                'ap_account_id' => $company->ap_account_id,
                'income_account_id' => $company->income_account_id,
                'expense_account_id' => $company->expense_account_id,
                'bank_account_id' => $company->bank_account_id,
                'retained_earnings_account_id' => $company->retained_earnings_account_id,
                'sales_tax_payable_account_id' => $company->sales_tax_payable_account_id,
                'purchase_tax_receivable_account_id' => $company->purchase_tax_receivable_account_id,
            ],
        ]);
    }

    /**
     * Get current onboarding status (for progress checks).
     */
    public function status(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $status = $this->onboardingService->getOnboardingStatus($company->id);

        return Inertia::render('FuelStation/Onboarding/Status', [
            'status' => $status,
        ]);
    }

    /**
     * Setup station settings (vendor, payment channels, features).
     */
    public function setupStationSettings(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $validated = $request->validate([
            'fuel_vendor' => 'required|string|in:parco,pso,shell,total,caltex,attock,hascol,byco,go,other',
            'has_partners' => 'boolean',
            'has_amanat' => 'boolean',
            'has_lubricant_sales' => 'boolean',
            'has_investors' => 'boolean',
            'dual_meter_readings' => 'boolean',
            'track_attendant_handovers' => 'boolean',
            'payment_channels' => 'required|array|min:1',
            'payment_channels.*.code' => 'required|string|max:50',
            'payment_channels.*.label' => 'required|string|max:100',
            'payment_channels.*.type' => 'required|string|in:cash,bank_transfer,card_pos,fuel_card,mobile_wallet',
            'payment_channels.*.enabled' => 'boolean',
            'payment_channels.*.bank_account_id' => 'nullable|uuid',
            'payment_channels.*.clearing_account_id' => 'nullable|uuid',
            'operating_bank_account_id' => 'nullable|uuid',
        ]);

        try {
            DB::transaction(function () use ($company, $validated, $request) {
                // Get or create station settings
                $settings = StationSettings::firstOrNew(['company_id' => $company->id]);

                $settings->fuel_vendor = $validated['fuel_vendor'];
                $settings->has_partners = $validated['has_partners'] ?? true;
                $settings->has_amanat = $validated['has_amanat'] ?? true;
                $settings->has_lubricant_sales = $validated['has_lubricant_sales'] ?? true;
                $settings->has_investors = $validated['has_investors'] ?? false;
                $settings->dual_meter_readings = $validated['dual_meter_readings'] ?? false;
                $settings->track_attendant_handovers = $validated['track_attendant_handovers'] ?? false;
                $settings->payment_channels = $validated['payment_channels'];
                $settings->operating_bank_account_id = $validated['operating_bank_account_id'] ?? null;

                // Auto-assign clearing accounts from payment channels
                foreach ($validated['payment_channels'] as $channel) {
                    if ($channel['type'] === 'fuel_card' && !empty($channel['clearing_account_id'])) {
                        $settings->fuel_card_clearing_account_id = $channel['clearing_account_id'];
                    }
                    if ($channel['type'] === 'card_pos' && !empty($channel['clearing_account_id'])) {
                        $settings->card_pos_clearing_account_id = $channel['clearing_account_id'];
                    }
                }

                // Auto-resolve default accounts if not set
                if (!$settings->cash_account_id) {
                    $cashAccount = Account::where('company_id', $company->id)
                        ->where('code', '1050')
                        ->where('is_active', true)
                        ->first();
                    $settings->cash_account_id = $cashAccount?->id;
                }

                if (!$settings->fuel_sales_account_id) {
                    $salesAccount = Account::where('company_id', $company->id)
                        ->where('code', '4100')
                        ->where('is_active', true)
                        ->first();
                    $settings->fuel_sales_account_id = $salesAccount?->id;
                }

                if (!$settings->fuel_cogs_account_id) {
                    $cogsAccount = Account::where('company_id', $company->id)
                        ->where('code', '5100')
                        ->where('is_active', true)
                        ->first();
                    $settings->fuel_cogs_account_id = $cogsAccount?->id;
                }

                if (!$settings->fuel_inventory_account_id) {
                    $invAccount = Account::where('company_id', $company->id)
                        ->where('code', '1200')
                        ->where('is_active', true)
                        ->first();
                    $settings->fuel_inventory_account_id = $invAccount?->id;
                }

                $settings->save();
            });

            $vendorName = StationSettings::VENDORS[$validated['fuel_vendor']] ?? $validated['fuel_vendor'];

            return redirect()->back()->with('success', "Station settings saved. Vendor: {$vendorName}");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Failed to save station settings: ' . $e->getMessage());
        }
    }

    /**
     * Create required accounts.
     */
    public function setupAccounts(): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $created = $this->onboardingService->ensureRequiredAccounts($company->id);
        app(\App\Modules\Accounting\Services\PostingTemplateInstaller::class)
            ->ensureDefaults($company->fresh());

        if (empty($created)) {
            return redirect()->back()->with('info', 'All required accounts already exist.');
        }

        return redirect()->back()->with('success', 'Created accounts: ' . implode(', ', $created));
    }

    /**
     * Create/update fuel items based on user selection.
     * Items not in the selection are marked as inactive.
     */
    public function setupFuelItems(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $validated = $request->validate([
            'fuel_items' => 'required|array|min:1',
            'fuel_items.*.name' => 'required|string|max:255',
            'fuel_items.*.fuel_category' => 'required|in:petrol,diesel,high_octane,hi_octane,lubricant',
        ]);

        $created = [];
        $updated = [];
        $baseCurrency = strtoupper((string) ($company->base_currency ?: 'PKR'));
        $skuMap = [
            'petrol' => 'FUEL-PET',
            'diesel' => 'FUEL-DSL',
            'high_octane' => 'FUEL-HOC',
            'lubricant' => 'FUEL-LUB',
        ];

        // Track which categories were selected
        $selectedCategories = [];

        foreach ($validated['fuel_items'] as $fuelData) {
            $normalizedCategory = $this->normalizeFuelCategory($fuelData['fuel_category']);
            $selectedCategories[] = $normalizedCategory;
            if ($normalizedCategory === 'high_octane') {
                $selectedCategories[] = 'hi_octane'; // Include alias
            }

            $existingQuery = Item::where('company_id', $company->id);
            if ($normalizedCategory === 'high_octane') {
                $existingQuery->whereIn('fuel_category', ['high_octane', 'hi_octane']);
            } else {
                $existingQuery->where('fuel_category', $normalizedCategory);
            }

            $existing = $existingQuery->first();
            $payload = [
                'name' => $fuelData['name'],
                'fuel_category' => $normalizedCategory,
                'is_active' => true,
                'currency' => $baseCurrency,
            ];

            if ($existing) {
                $existing->update($payload + [
                    'updated_by_user_id' => $request->user()->id,
                ]);
                $updated[] = $fuelData['name'];
                continue;
            }

            $sku = $skuMap[$normalizedCategory] ?? ('FUEL-' . strtoupper($normalizedCategory));
            Item::create($payload + [
                'company_id' => $company->id,
                'sku' => $sku,
                'item_type' => 'product',
                'track_inventory' => true,
                'unit_of_measure' => 'liters',
                'cost_price' => 0,
                'avg_cost' => 0,
                'created_by_user_id' => $request->user()->id,
            ]);
            $created[] = $fuelData['name'];
        }

        // Mark unselected fuel items as inactive
        $deactivated = Item::where('company_id', $company->id)
            ->whereNotNull('fuel_category')
            ->whereNotIn('fuel_category', $selectedCategories)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $messageParts = [];
        if (!empty($created)) {
            $messageParts[] = 'Added: ' . implode(', ', $created);
        }
        if (!empty($updated)) {
            $messageParts[] = 'Updated: ' . implode(', ', $updated);
        }
        if ($deactivated > 0) {
            $messageParts[] = "Deactivated {$deactivated} item(s)";
        }

        if (empty($messageParts)) {
            return redirect()->back()->with('info', 'No changes made.');
        }

        return redirect()->back()->with('success', 'Products saved. ' . implode(' | ', $messageParts));
    }

    /**
     * Setup partners.
     */
    public function setupPartners(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $partnerRows = collect($request->input('partners', []))
            ->filter(function ($row) {
                $name = trim((string) ($row['name'] ?? ''));
                $phone = trim((string) ($row['phone'] ?? ''));
                $profitShare = $row['profit_share_percentage'] ?? null;
                $hasShare = $profitShare !== null && $profitShare !== '';
                $hasLimit = ($row['drawing_limit_amount'] ?? null) !== null && $row['drawing_limit_amount'] !== '';
                return $name !== '' || $phone !== '' || $hasShare || $hasLimit;
            })
            ->values()
            ->all();

        if (empty($partnerRows)) {
            return redirect()->back()->with('info', 'No partners added. You can add them later.');
        }

        $request->merge(['partners' => $partnerRows]);

        $validated = $request->validate([
            'partners' => 'required|array|min:1',
            'partners.*.id' => 'nullable|uuid',
            'partners.*.name' => 'required|string|max:255',
            'partners.*.phone' => 'nullable|string|max:50',
            'partners.*.profit_share_percentage' => 'required|numeric|min:0|max:100',
            'partners.*.drawing_limit_period' => 'required|in:monthly,yearly,none',
            'partners.*.drawing_limit_amount' => 'nullable|numeric|min:0',
        ]);

        // Validate total profit share doesn't exceed 100%
        $totalShare = collect($validated['partners'])->sum('profit_share_percentage');
        if ($totalShare > 100) {
            return redirect()->back()->with('error', 'Total profit share cannot exceed 100%.');
        }

        $created = [];
        $updated = [];

        DB::transaction(function () use ($validated, $company, $request, &$created, &$updated) {
            CompanyContext::setContext($company);
            foreach ($validated['partners'] as $partnerData) {
                $partner = null;
                $partnerPhone = trim((string) ($partnerData['phone'] ?? ''));
                $partnerPhone = $partnerPhone === '' ? null : $partnerPhone;

                if (!empty($partnerData['id'])) {
                    $partner = Partner::where('company_id', $company->id)
                        ->where('id', $partnerData['id'])
                        ->first();
                }

                if (!$partner) {
                    $partner = Partner::where('company_id', $company->id)
                        ->where('name', $partnerData['name'])
                        ->first();
                }

                $payload = [
                    'name' => $partnerData['name'],
                    'phone' => $partnerPhone,
                    'profit_share_percentage' => $partnerData['profit_share_percentage'],
                    'drawing_limit_period' => $partnerData['drawing_limit_period'],
                    'drawing_limit_amount' => $partnerData['drawing_limit_amount'] ?? null,
                    'is_active' => true,
                ];

                if ($partner) {
                    $partner->update($payload);
                    $updated[] = $partner->name;
                } else {
                    Partner::create($payload + [
                        'company_id' => $company->id,
                        'created_by_user_id' => $request->user()->id,
                    ]);
                    $created[] = $partnerData['name'];
                }
            }
        });

        $messageParts = [];
        if (!empty($created)) {
            $messageParts[] = 'Added: ' . implode(', ', $created);
        }
        if (!empty($updated)) {
            $messageParts[] = 'Updated: ' . implode(', ', $updated);
        }
        $message = empty($messageParts) ? 'Partners saved.' : 'Partners saved. ' . implode(' | ', $messageParts);

        return redirect()->back()->with('success', $message);
    }

    /**
     * Setup employees.
     */
    public function setupEmployees(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        CompanyContext::setContext($company);

        $validated = $request->validate([
            'employees' => 'required|array|min:1',
            'employees.*.id' => 'nullable|uuid',
            'employees.*.first_name' => 'required|string|max:100',
            'employees.*.last_name' => 'required|string|max:100',
            'employees.*.phone' => 'nullable|string|max:50',
            'employees.*.position' => 'nullable|string|max:100',
            'employees.*.base_salary' => 'required|numeric|min:0',
        ]);

        $created = [];
        $updated = [];
        $baseCurrency = strtoupper((string) ($company->base_currency ?: 'PKR'));

        DB::transaction(function () use ($validated, $company, $request, $baseCurrency, &$created, &$updated) {
            CompanyContext::setContext($company);
            $employeeCount = Employee::where('company_id', $company->id)->count();

            foreach ($validated['employees'] as $index => $empData) {
                $employee = null;
                $employeePhone = trim((string) ($empData['phone'] ?? ''));
                $employeePhone = $employeePhone === '' ? null : $employeePhone;

                if (!empty($empData['id'])) {
                    $employee = Employee::where('company_id', $company->id)
                        ->where('id', $empData['id'])
                        ->first();
                }

                if (!$employee) {
                    $employee = Employee::where('company_id', $company->id)
                        ->where('first_name', $empData['first_name'])
                        ->where('last_name', $empData['last_name'])
                        ->first();
                }

                $payload = [
                    'first_name' => $empData['first_name'],
                    'last_name' => $empData['last_name'],
                    'phone' => $employeePhone,
                    'position' => $empData['position'] ?? 'Attendant',
                    'base_salary' => $empData['base_salary'],
                    'is_active' => true,
                ];

                if ($employee) {
                    $employee->update($payload + [
                        'updated_by_user_id' => $request->user()->id,
                    ]);
                    $updated[] = $empData['first_name'] . ' ' . $empData['last_name'];
                    continue;
                }

                $employeeNumber = 'EMP-' . str_pad($employeeCount + $index + 1, 4, '0', STR_PAD_LEFT);

                Employee::create($payload + [
                    'company_id' => $company->id,
                    'employee_number' => $employeeNumber,
                    'hire_date' => now()->toDateString(),
                    'employment_type' => 'full_time',
                    'employment_status' => 'active',
                    'pay_frequency' => 'monthly',
                    'currency' => $baseCurrency,
                    'created_by_user_id' => $request->user()->id,
                ]);
                $created[] = $empData['first_name'] . ' ' . $empData['last_name'];
            }
        });

        $messageParts = [];
        if (!empty($created)) {
            $messageParts[] = 'Added: ' . implode(', ', $created);
        }
        if (!empty($updated)) {
            $messageParts[] = 'Updated: ' . implode(', ', $updated);
        }
        $message = empty($messageParts) ? 'Employees saved.' : 'Employees saved. ' . implode(' | ', $messageParts);

        return redirect()->back()->with('success', $message);
    }

    /**
     * Setup tanks with dip sticks.
     */
    public function setupTanks(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        CompanyContext::setContext($company);

        $validated = $request->validate([
            'tanks' => 'required|array|min:1',
            'tanks.*.id' => 'nullable|uuid',
            'tanks.*.name' => 'required|string|max:255',
            'tanks.*.code' => 'required|string|max:50',
            'tanks.*.fuel_category' => 'nullable|in:petrol,diesel,high_octane,hi_octane,lubricant',
            'tanks.*.linked_item_id' => 'nullable|uuid',
            'tanks.*.linked_item_sku' => 'nullable|string|max:100',
            'tanks.*.linked_item_name' => 'nullable|string|max:255',
            'tanks.*.capacity' => 'required|numeric|min:1',
            'tanks.*.dip_stick_code' => 'nullable|string|max:50',
        ]);

        $created = [];
        $updated = [];
        $baseCurrency = strtoupper((string) ($company->base_currency ?: 'PKR'));
        $priceColumns = $this->resolveItemPriceColumns();
        $hasSalePrice = $priceColumns['sale_price'];
        $hasSellingPrice = $priceColumns['selling_price'];

        DB::transaction(function () use ($validated, $company, $request, $baseCurrency, $hasSalePrice, $hasSellingPrice, &$created, &$updated) {
            CompanyContext::setContext($company);
            foreach ($validated['tanks'] as $tankData) {
                if (empty($tankData['fuel_category']) && empty($tankData['linked_item_id']) && empty($tankData['linked_item_sku'])) {
                    throw new \RuntimeException('Each tank must have a fuel category or linked item.');
                }

                $fuelItem = null;
                if (!empty($tankData['linked_item_id'])) {
                    $fuelItem = Item::where('company_id', $company->id)
                        ->where('id', $tankData['linked_item_id'])
                        ->first();
                }

                if (!$fuelItem && !empty($tankData['linked_item_sku'])) {
                    $fuelItem = Item::where('company_id', $company->id)
                        ->where('sku', $tankData['linked_item_sku'])
                        ->first();

                    if (!$fuelItem) {
                        $fuelItem = Item::create([
                            'company_id' => $company->id,
                            'name' => $tankData['linked_item_name'] ?? $tankData['name'],
                            'sku' => $tankData['linked_item_sku'],
                            'item_type' => 'product',
                            'is_active' => true,
                            'track_inventory' => true,
                            'unit_of_measure' => 'liter',
                            'cost_price' => 0,
                            'avg_cost' => 0,
                            'currency' => $baseCurrency,
                            'created_by_user_id' => $request->user()->id,
                        ]);
                        $pricePayload = [];
                        if ($hasSalePrice) {
                            $pricePayload['sale_price'] = 0;
                        }
                        if ($hasSellingPrice) {
                            $pricePayload['selling_price'] = 0;
                        }
                        if (!empty($pricePayload)) {
                            DB::table('inv.items')
                                ->where('id', $fuelItem->id)
                                ->update($pricePayload + [
                                    'updated_by_user_id' => $request->user()->id,
                                    'updated_at' => now(),
                                ]);
                        }
                    }
                }

                if (!$fuelItem && !empty($tankData['fuel_category'])) {
                    $normalizedCategory = $this->normalizeFuelCategory($tankData['fuel_category']);
                    $fuelItemQuery = Item::where('company_id', $company->id);
                    if ($normalizedCategory === 'high_octane') {
                        $fuelItemQuery->whereIn('fuel_category', ['high_octane', 'hi_octane']);
                    } else {
                        $fuelItemQuery->where('fuel_category', $normalizedCategory);
                    }
                    $fuelItem = $fuelItemQuery->first();
                }

                if (!$fuelItem) {
                    $missingCategory = $tankData['fuel_category'] ?? 'linked item';
                    throw new \RuntimeException("No fuel item found for {$missingCategory}. Please create fuel items first.");
                }

                $dipStickId = null;
                if (!empty($tankData['dip_stick_code'])) {
                    $dipStick = DipStick::where('company_id', $company->id)
                        ->where('code', $tankData['dip_stick_code'])
                        ->first();

                    if ($dipStick) {
                        $dipStick->update([
                            'name' => $tankData['name'] . ' Dip Stick',
                            'unit' => 'cm',
                            'is_active' => true,
                        ]);
                    } else {
                        $dipStick = DipStick::create([
                            'company_id' => $company->id,
                            'code' => $tankData['dip_stick_code'],
                            'name' => $tankData['name'] . ' Dip Stick',
                            'unit' => 'cm',
                            'is_active' => true,
                            'created_by_user_id' => $request->user()->id,
                        ]);
                    }
                    $dipStickId = $dipStick->id;
                }

                $tank = null;
                if (!empty($tankData['id'])) {
                    $tank = Warehouse::where('company_id', $company->id)
                        ->where('id', $tankData['id'])
                        ->where('warehouse_type', 'tank')
                        ->first();
                }

                if (!$tank) {
                    $tank = Warehouse::where('company_id', $company->id)
                        ->where('warehouse_type', 'tank')
                        ->where('code', $tankData['code'])
                        ->first();
                }

                $payload = [
                    'code' => $tankData['code'],
                    'name' => $tankData['name'],
                    'warehouse_type' => 'tank',
                    'capacity' => $tankData['capacity'],
                    'linked_item_id' => $fuelItem->id,
                    'dip_stick_id' => $dipStickId ?? $tank?->dip_stick_id,
                    'is_active' => true,
                ];

                if ($tank) {
                    $tank->update($payload);
                    $updated[] = $tankData['name'];
                    continue;
                }

                Warehouse::create($payload + [
                    'company_id' => $company->id,
                    'created_by_user_id' => $request->user()->id,
                ]);

                $created[] = $tankData['name'];
            }
        });

        $messageParts = [];
        if (!empty($created)) {
            $messageParts[] = 'Added: ' . implode(', ', $created);
        }
        if (!empty($updated)) {
            $messageParts[] = 'Updated: ' . implode(', ', $updated);
        }

        return redirect()->back()->with('success', 'Tanks saved. ' . implode(' | ', $messageParts));
    }

    /**
     * Setup pumps with nozzles.
     */
    public function setupPumps(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $validated = $request->validate([
            'pumps' => 'required|array|min:1',
            'pumps.*.id' => 'nullable|uuid',
            'pumps.*.name' => 'required|string|max:255',
            'pumps.*.tank_id' => 'required|uuid|exists:inv.warehouses,id',
            'pumps.*.nozzle_count' => 'required|integer|min:1|max:2',
            'pumps.*.front_electronic' => 'nullable|numeric|min:0',
            'pumps.*.front_manual' => 'nullable|numeric|min:0',
            'pumps.*.back_electronic' => 'nullable|numeric|min:0',
            'pumps.*.back_manual' => 'nullable|numeric|min:0',
        ]);

        $created = [];
        $updated = [];
        $nozzlesCreated = 0;

        DB::transaction(function () use ($validated, $company, $request, &$created, &$updated, &$nozzlesCreated) {
            // Get pump number for naming nozzles
            $pumpIndex = 0;

            foreach ($validated['pumps'] as $pumpData) {
                $pumpIndex++;

                // Verify tank belongs to this company and get linked item
                $tank = Warehouse::where('id', $pumpData['tank_id'])
                    ->where('company_id', $company->id)
                    ->where('warehouse_type', 'tank')
                    ->first();

                if (!$tank) {
                    throw new \RuntimeException("Tank not found: {$pumpData['tank_id']}");
                }

                if (!$tank->linked_item_id) {
                    throw new \RuntimeException("Tank {$tank->name} has no linked fuel item. Please set up tanks first.");
                }

                $pump = null;
                $isNewPump = false;

                if (!empty($pumpData['id'])) {
                    $pump = Pump::where('company_id', $company->id)
                        ->where('id', $pumpData['id'])
                        ->first();
                }

                if (!$pump) {
                    $pump = Pump::where('company_id', $company->id)
                        ->where('name', $pumpData['name'])
                        ->first();
                }

                // Sum up front and back readings for pump totals
                $totalElectronic = ($pumpData['front_electronic'] ?? 0) + ($pumpData['back_electronic'] ?? 0);
                $totalManual = ($pumpData['front_manual'] ?? 0) + ($pumpData['back_manual'] ?? 0);

                $payload = [
                    'name' => $pumpData['name'],
                    'tank_id' => $pumpData['tank_id'],
                    'current_meter_reading' => $totalElectronic,
                    'current_manual_reading' => $totalManual,
                    'is_active' => true,
                ];

                if ($pump) {
                    $pump->update($payload);
                    $updated[] = $pumpData['name'];
                } else {
                    $pump = Pump::create($payload + [
                        'company_id' => $company->id,
                    ]);
                    $created[] = $pumpData['name'];
                    $isNewPump = true;
                }

                // Create or update nozzles for this pump
                $existingNozzles = Nozzle::where('pump_id', $pump->id)
                    ->orderBy('sort_order')
                    ->orderBy('code')
                    ->get();
                $desiredNozzleCount = (int) $pumpData['nozzle_count'];

                // Extract pump number from name (e.g., "Pump 1" -> 1, "Pump 2" -> 2)
                $pumpNumber = $pumpIndex;
                if (preg_match('/(\d+)/', $pumpData['name'], $matches)) {
                    $pumpNumber = (int) $matches[1];
                }

                // Nozzle labels: A, B for sides (Front/Back)
                $nozzleLabels = ['A', 'B'];
                $nozzleSideNames = ['Front', 'Back'];

                // Get readings per side
                $sideReadings = [
                    0 => [ // Front
                        'electronic' => (float) ($pumpData['front_electronic'] ?? 0),
                        'manual' => (float) ($pumpData['front_manual'] ?? 0),
                    ],
                    1 => [ // Back
                        'electronic' => (float) ($pumpData['back_electronic'] ?? 0),
                        'manual' => (float) ($pumpData['back_manual'] ?? 0),
                    ],
                ];

                for ($i = 0; $i < $desiredNozzleCount; $i++) {
                    $nozzleCode = $pumpNumber . $nozzleLabels[$i];
                    $nozzleLabel = $pumpData['name'] . ' - ' . $nozzleSideNames[$i];
                    $electronicReading = $sideReadings[$i]['electronic'];
                    $manualReading = $sideReadings[$i]['manual'];

                    $nozzlePayload = [
                        'pump_id' => $pump->id,
                        'tank_id' => $tank->id,
                        'item_id' => $tank->linked_item_id,
                        'code' => $nozzleCode,
                        'label' => $nozzleLabel,
                        'current_meter_reading' => $electronicReading,
                        'last_closing_reading' => $electronicReading,
                        'last_manual_reading' => $manualReading,
                        'has_electronic_meter' => true,
                        'is_active' => true,
                        'sort_order' => $i,
                    ];

                    $existingNozzle = $existingNozzles->get($i);
                    if ($existingNozzle) {
                        $existingNozzle->update($nozzlePayload);
                        continue;
                    }

                    Nozzle::create($nozzlePayload + [
                        'company_id' => $company->id,
                    ]);
                    $nozzlesCreated++;
                }

            }
        });

        $messageParts = [];
        if (!empty($created)) {
            $messageParts[] = 'Added pumps: ' . implode(', ', $created);
        }
        if (!empty($updated)) {
            $messageParts[] = 'Updated pumps: ' . implode(', ', $updated);
        }
        if ($nozzlesCreated > 0) {
            $messageParts[] = "Created {$nozzlesCreated} nozzles";
        }

        return redirect()->back()->with('success', 'Pumps saved. ' . implode(' | ', $messageParts));
    }

    private function normalizeFuelCategory(?string $category): ?string
    {
        if ($category === null) {
            return null;
        }

        return $category === 'hi_octane' ? 'high_octane' : $category;
    }

    private function resolveItemPriceColumns(): array
    {
        try {
            $columns = DB::table('information_schema.columns')
                ->where('table_schema', 'inv')
                ->where('table_name', 'items')
                ->whereIn('column_name', ['sale_price', 'selling_price'])
                ->pluck('column_name')
                ->all();
        } catch (\Throwable $e) {
            return [
                'sale_price' => false,
                'selling_price' => false,
            ];
        }

        return [
            'sale_price' => in_array('sale_price', $columns, true),
            'selling_price' => in_array('selling_price', $columns, true),
        ];
    }


    /**
     * Setup initial rates.
     */
    public function setupRates(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        CompanyContext::setContext($company);

        $validated = $request->validate([
            'effective_date' => 'required|date',
            'rates' => 'required|array|min:1',
            'rates.*.item_id' => 'required|uuid|exists:inv.items,id',
            'rates.*.purchase_rate' => 'required|numeric|min:0',
            'rates.*.sale_rate' => 'required|numeric|min:0',
        ]);

        $created = [];
        $updated = [];

        $priceColumns = $this->resolveItemPriceColumns();
        $hasSalePrice = $priceColumns['sale_price'];
        $hasSellingPrice = $priceColumns['selling_price'];

        DB::transaction(function () use ($validated, $company, $request, $hasSalePrice, $hasSellingPrice, &$created, &$updated) {
            CompanyContext::setContext($company);
            foreach ($validated['rates'] as $rateData) {
                // Verify item belongs to this company
                $item = Item::where('id', $rateData['item_id'])
                    ->where('company_id', $company->id)
                    ->whereNotNull('fuel_category')
                    ->first();

                if (!$item) {
                    continue;
                }

                $existingRate = RateChange::where('company_id', $company->id)
                    ->where('item_id', $rateData['item_id'])
                    ->where('effective_date', $validated['effective_date'])
                    ->first();

                $payload = [
                    'purchase_rate' => $rateData['purchase_rate'],
                    'sale_rate' => $rateData['sale_rate'],
                ];

                if ($existingRate) {
                    $existingRate->update($payload);
                    $itemUpdate = [
                        'avg_cost' => $rateData['purchase_rate'],
                        'updated_by_user_id' => $request->user()->id,
                        'updated_at' => now(),
                    ];
                    if ($hasSalePrice) {
                        $itemUpdate['sale_price'] = $rateData['sale_rate'];
                    }
                    if ($hasSellingPrice) {
                        $itemUpdate['selling_price'] = $rateData['sale_rate'];
                    }
                    DB::table('inv.items')
                        ->where('id', $item->id)
                        ->update($itemUpdate);
                    $updated[] = $item->name;
                    continue;
                }

                RateChange::create($payload + [
                    'company_id' => $company->id,
                    'item_id' => $rateData['item_id'],
                    'effective_date' => $validated['effective_date'],
                    'created_by_user_id' => $request->user()->id,
                ]);
                $itemUpdate = [
                    'avg_cost' => $rateData['purchase_rate'],
                    'updated_by_user_id' => $request->user()->id,
                    'updated_at' => now(),
                ];
                if ($hasSalePrice) {
                    $itemUpdate['sale_price'] = $rateData['sale_rate'];
                }
                if ($hasSellingPrice) {
                    $itemUpdate['selling_price'] = $rateData['sale_rate'];
                }
                DB::table('inv.items')
                    ->where('id', $item->id)
                    ->update($itemUpdate);

                $created[] = $item->name;
            }
        });

        $messageParts = [];
        if (!empty($created)) {
            $messageParts[] = 'Added: ' . implode(', ', $created);
        }
        if (!empty($updated)) {
            $messageParts[] = 'Updated: ' . implode(', ', $updated);
        }

        return redirect()->back()->with('success', 'Rates saved. ' . implode(' | ', $messageParts));
    }

    /**
     * Setup lubricants/motor oils.
     */
    public function setupLubricants(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        CompanyContext::setContext($company);

        $validated = $request->validate([
            'lubricants' => 'required|array|min:1',
            'lubricants.*.id' => 'nullable|uuid',
            'lubricants.*.name' => 'required|string|max:255',
            'lubricants.*.sku' => 'required|string|max:50',
            'lubricants.*.brand' => 'nullable|string|max:100',
            'lubricants.*.unit' => 'nullable|string|max:50',
            'lubricants.*.cost_price' => 'required|numeric|min:0',
            'lubricants.*.sale_price' => 'required|numeric|min:0',
            'lubricants.*.opening_quantity' => 'nullable|integer|min:0',
        ]);

        $created = [];
        $updated = [];
        $baseCurrency = strtoupper((string) ($company->base_currency ?: 'PKR'));
        $priceColumns = $this->resolveItemPriceColumns();
        $hasSalePrice = $priceColumns['sale_price'];
        $hasSellingPrice = $priceColumns['selling_price'];

        DB::transaction(function () use ($validated, $company, $request, $baseCurrency, $hasSalePrice, $hasSellingPrice, &$created, &$updated) {
            CompanyContext::setContext($company);
            // Get or create lubricants inventory account
            $lubricantsAccount = Account::where('company_id', $company->id)
                ->where('code', '1210')
                ->first();

            foreach ($validated['lubricants'] as $lubricantData) {
                $item = null;
                if (!empty($lubricantData['id'])) {
                    $item = Item::where('company_id', $company->id)
                        ->where('id', $lubricantData['id'])
                        ->first();
                }

                if (!$item) {
                    $item = Item::where('company_id', $company->id)
                        ->where('sku', $lubricantData['sku'])
                        ->first();
                }

                $payload = [
                    'name' => $lubricantData['name'],
                    'sku' => $lubricantData['sku'],
                    'item_type' => 'product',
                    'brand' => $lubricantData['brand'] ?? null,
                    'is_active' => true,
                    'track_inventory' => true,
                    'unit_of_measure' => $lubricantData['unit'] ?? 'bottle',
                    'cost_price' => $lubricantData['cost_price'],
                    'avg_cost' => $lubricantData['cost_price'],
                    'currency' => $baseCurrency,
                    'inventory_account_id' => $lubricantsAccount?->id,
                ];
                $pricePayload = [];
                if ($hasSalePrice) {
                    $pricePayload['sale_price'] = $lubricantData['sale_price'];
                }
                if ($hasSellingPrice) {
                    $pricePayload['selling_price'] = $lubricantData['sale_price'];
                }

                if ($item) {
                    $item->update($payload + [
                        'updated_by_user_id' => $request->user()->id,
                    ]);
                    if (!empty($pricePayload)) {
                        DB::table('inv.items')
                            ->where('id', $item->id)
                            ->update($pricePayload + [
                                'updated_by_user_id' => $request->user()->id,
                                'updated_at' => now(),
                            ]);
                    }
                    $updated[] = $lubricantData['name'];
                } else {
                    $item = Item::create($payload + [
                        'company_id' => $company->id,
                        'created_by_user_id' => $request->user()->id,
                    ]);
                    if (!empty($pricePayload)) {
                        DB::table('inv.items')
                            ->where('id', $item->id)
                            ->update($pricePayload + [
                                'updated_by_user_id' => $request->user()->id,
                                'updated_at' => now(),
                            ]);
                    }
                    $created[] = $lubricantData['name'];
                }

                // Record opening quantity if provided (for packed lubricants only)
                // Note: Open mobil oil should be added as a Tank in the Tanks step, not here
                $openingQty = (int) ($lubricantData['opening_quantity'] ?? 0);

                // Get default warehouse for packed lubricants (standard type)
                $warehouse = Warehouse::where('company_id', $company->id)
                    ->where('warehouse_type', 'standard')
                    ->first();

                if (!$warehouse && $openingQty === 0) {
                    continue;
                }

                if (!$warehouse) {
                    $warehouse = Warehouse::create([
                        'company_id' => $company->id,
                        'code' => 'WH-MAIN',
                        'name' => 'Main Warehouse',
                        'warehouse_type' => 'standard',
                        'is_active' => true,
                        'created_by_user_id' => $request->user()->id,
                    ]);
                }

                $movement = StockMovement::where('company_id', $company->id)
                    ->where('item_id', $item->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('movement_type', 'opening')
                    ->where('notes', 'Opening balance from onboarding')
                    ->first();

                if ($openingQty === 0 && !$movement) {
                    continue;
                }

                $movementPayload = [
                    'quantity' => $openingQty,
                    'unit_cost' => $lubricantData['cost_price'],
                    'total_cost' => $openingQty * $lubricantData['cost_price'],
                    'movement_date' => now()->toDateString(),
                ];

                if ($movement) {
                    $movement->update($movementPayload);
                } else {
                    StockMovement::create($movementPayload + [
                        'company_id' => $company->id,
                        'item_id' => $item->id,
                        'warehouse_id' => $warehouse->id,
                        'movement_type' => 'opening',
                        'notes' => 'Opening balance from onboarding',
                        'created_by_user_id' => $request->user()->id,
                    ]);
                }
            }
        });

        if (empty($created) && empty($updated)) {
            return redirect()->back()->with('info', 'All lubricants already exist.');
        }

        $messageParts = [];
        if (!empty($created)) {
            $messageParts[] = 'Added: ' . implode(', ', $created);
        }
        if (!empty($updated)) {
            $messageParts[] = 'Updated: ' . implode(', ', $updated);
        }

        return redirect()->back()->with('success', 'Lubricants saved. ' . implode(' | ', $messageParts));
    }

    /**
     * Setup initial tank stock (opening balances).
     */
    public function setupInitialStock(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        CompanyContext::setContext($company);

        $validated = $request->validate([
            'stock_date' => 'required|date',
            'tank_readings' => 'required|array|min:1',
            'tank_readings.*.id' => 'nullable|uuid',
            'tank_readings.*.tank_id' => 'required|uuid|exists:inv.warehouses,id',
            'tank_readings.*.stick_reading' => 'nullable|numeric|min:0',
            'tank_readings.*.liters' => 'required|numeric|min:0',
            'tank_readings.*.value' => 'nullable|numeric|min:0',
        ]);

        $recordedTanks = [];

        DB::transaction(function () use ($validated, $company, $request, &$recordedTanks) {
            CompanyContext::setContext($company);
            foreach ($validated['tank_readings'] as $reading) {
                // Verify tank belongs to this company
                $tank = Warehouse::where('id', $reading['tank_id'])
                    ->where('company_id', $company->id)
                    ->where('warehouse_type', 'tank')
                    ->with('linkedItem')
                    ->first();

                if (!$tank || !$tank->linkedItem) {
                    continue;
                }

                // Get current rate for the fuel item
                $rate = RateChange::getRateForDate($company->id, $tank->linked_item_id, $validated['stock_date']);
                $unitCost = (float) ($rate?->purchase_rate ?? $tank->linkedItem->avg_cost ?? 0);
                $liters = (float) $reading['liters'];
                $totalValue = $reading['value'] ?? ($liters * $unitCost);

                // Create opening tank reading
                $existingReading = null;
                if (!empty($reading['id'])) {
                    $existingReading = TankReading::where('company_id', $company->id)
                        ->where('id', $reading['id'])
                        ->first();
                }

                if (!$existingReading) {
                    $existingReading = TankReading::where('company_id', $company->id)
                        ->where('tank_id', $tank->id)
                        ->where('reading_type', 'opening')
                        ->where('reading_date', $validated['stock_date'])
                        ->first();
                }

                $readingPayload = [
                    'tank_id' => $tank->id,
                    'item_id' => $tank->linked_item_id,
                    'reading_date' => $validated['stock_date'],
                    'reading_type' => 'opening',
                    'stick_reading' => $reading['stick_reading'] ?? null,
                    'dip_measurement_liters' => $liters,
                    'system_calculated_liters' => $liters,
                    'status' => 'posted',
                    'notes' => 'Opening balance from onboarding',
                    'recorded_by_user_id' => $request->user()->id,
                ];

                if ($existingReading) {
                    $existingReading->update($readingPayload + [
                        'updated_by_user_id' => $request->user()->id,
                    ]);
                } else {
                    TankReading::create($readingPayload + [
                        'company_id' => $company->id,
                        'created_by_user_id' => $request->user()->id,
                    ]);
                }

                $movement = StockMovement::where('company_id', $company->id)
                    ->where('item_id', $tank->linked_item_id)
                    ->where('warehouse_id', $tank->id)
                    ->where('movement_type', 'opening')
                    ->where('movement_date', $validated['stock_date'])
                    ->where('notes', 'Opening fuel balance from onboarding')
                    ->first();

                $movementPayload = [
                    'quantity' => $liters,
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalValue,
                    'movement_date' => $validated['stock_date'],
                ];

                if ($movement) {
                    $movement->update($movementPayload);
                } else {
                    StockMovement::create($movementPayload + [
                        'company_id' => $company->id,
                        'item_id' => $tank->linked_item_id,
                        'warehouse_id' => $tank->id,
                        'movement_type' => 'opening',
                        'notes' => 'Opening fuel balance from onboarding',
                        'created_by_user_id' => $request->user()->id,
                    ]);
                }

                $recordedTanks[] = $tank->name;
            }
        });

        if (empty($recordedTanks)) {
            return redirect()->back()->with('error', 'No valid tanks found to record stock.');
        }

        return redirect()->back()->with('success', 'Recorded opening stock for: ' . implode(', ', $recordedTanks));
    }

    /**
     * Setup opening cash balance.
     */
    public function setupOpeningCash(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $validated = $request->validate([
            'as_of_date' => 'required|date',
            'cash_on_hand' => 'required|numeric|min:0',
            'bank_balance' => 'nullable|numeric|min:0',
            'bank_balances' => 'nullable|array',
            'bank_balances.*.account_id' => 'required|uuid',
            'bank_balances.*.balance' => 'required|numeric|min:0',
        ]);

        $baseCurrency = strtoupper((string) ($company->base_currency ?: 'PKR'));
        $recorded = [];

        DB::transaction(function () use ($validated, $company, $request, $baseCurrency, &$recorded) {
            // Record cash on hand opening balance
            $cashOnHand = (float) $validated['cash_on_hand'];
            $cashAccount = Account::where('company_id', $company->id)
                ->where('code', '1050')
                ->first();

            if ($cashAccount) {
                // Store opening balance in company settings (simpler than JE for onboarding)
                $settings = $company->settings ?? [];
                $settings['opening_balances'] = $settings['opening_balances'] ?? [];
                $settings['opening_balances']['cash_on_hand'] = [
                    'account_id' => $cashAccount->id,
                    'amount' => $cashOnHand,
                    'as_of_date' => $validated['as_of_date'],
                    'currency' => $baseCurrency,
                ];
                $company->settings = $settings;
                $company->save();

                if ($cashOnHand > 0) {
                    $recorded[] = "Cash on Hand: {$baseCurrency} " . number_format($cashOnHand, 2);
                }
            }

            // Record simple bank balance (for main operating account)
            $simpleBankBalance = (float) ($validated['bank_balance'] ?? 0);
            $bankAccount = Account::where('company_id', $company->id)
                ->where('code', '1000')
                ->first();

            if ($bankAccount) {
                $settings = $company->settings ?? [];
                $settings['opening_balances'] = $settings['opening_balances'] ?? [];
                $settings['opening_balances']['banks'] = $settings['opening_balances']['banks'] ?? [];
                $settings['opening_balances']['banks'][$bankAccount->id] = [
                    'account_id' => $bankAccount->id,
                    'account_name' => $bankAccount->name,
                    'amount' => $simpleBankBalance,
                    'as_of_date' => $validated['as_of_date'],
                    'currency' => $baseCurrency,
                ];
                $company->settings = $settings;
                $company->save();

                if ($simpleBankBalance > 0) {
                    $recorded[] = "{$bankAccount->name}: {$baseCurrency} " . number_format($simpleBankBalance, 2);
                }
            }

            // Record detailed bank balances (if provided)
            if (!empty($validated['bank_balances'])) {
                foreach ($validated['bank_balances'] as $bankBalance) {
                    $account = Account::where('company_id', $company->id)
                        ->where('id', $bankBalance['account_id'])
                        ->where('subtype', 'bank')
                        ->first();

                    if ($account) {
                        $settings = $company->settings ?? [];
                        $settings['opening_balances'] = $settings['opening_balances'] ?? [];
                        $settings['opening_balances']['banks'] = $settings['opening_balances']['banks'] ?? [];
                        $settings['opening_balances']['banks'][$account->id] = [
                            'account_id' => $account->id,
                            'account_name' => $account->name,
                            'amount' => (float) $bankBalance['balance'],
                            'as_of_date' => $validated['as_of_date'],
                            'currency' => $baseCurrency,
                        ];
                        $company->settings = $settings;
                        $company->save();

                        if ($bankBalance['balance'] > 0) {
                            $recorded[] = "{$account->name}: {$baseCurrency} " . number_format($bankBalance['balance'], 2);
                        }
                    }
                }
            }
        });

        if (empty($recorded)) {
            return redirect()->back()->with('info', 'No opening balances to record.');
        }

        return redirect()->back()->with('success', 'Recorded opening balances: ' . implode(', ', $recorded));
    }

    /**
     * Complete onboarding and redirect to dashboard.
     */
    public function complete(): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        // Mark fuel station module as onboarded
        $settings = $company->settings ?? [];
        $settings['fuel_station_onboarded'] = true;
        $settings['fuel_station_onboarded_at'] = now()->toIso8601String();
        $company->settings = $settings;

        // Also mark the general company onboarding as complete
        $company->onboarding_completed = true;
        $company->onboarding_completed_at = now();

        $company->save();

        return redirect()->back()
            ->with('success', 'Fuel station setup marked complete. You can continue editing setup anytime.');
    }
}
