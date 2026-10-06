<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\FuelStation\Http\Requests\UpdateStationSettingsRequest;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\FuelStation\Services\FuelProductAccountMapper;
use App\Modules\FuelStation\Services\StationAccountMapper;
use App\Modules\Inventory\Models\Item;
use App\Services\CommandBus;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StationSettingsController extends Controller
{
    /**
     * Show the station settings form.
     */
    public function edit(): Response
    {
        $company = app(CurrentCompany::class)->get();
        $companyId = $company->id;

        // Get or create settings with defaults
        $settings = StationSettings::forCompany($companyId);
        $settings = app(StationAccountMapper::class)->ensureMappings($settings, optional(request()->user())->id);

        $fuelProducts = Item::where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereNotNull('fuel_category')
            ->orderBy('fuel_category')
            ->orderBy('name')
            ->get();

        $mapper = app(FuelProductAccountMapper::class);
        $fuelProducts = $fuelProducts->map(fn (Item $item) => $mapper->ensureItemMappings($item));

        // Get accounts for dropdowns
        $accounts = Account::where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'subtype']);

        // Group accounts by type for easier selection
        $accountsByType = [
            'cash' => $accounts->where('subtype', 'cash')->values(),
            'bank' => $accounts->where('subtype', 'bank')->values(),
            'receivable' => $accounts->filter(fn ($account) => in_array($account->subtype, ['receivable', 'accounts_receivable', 'other_current_asset'], true))->values(),
            'clearing' => $accounts->filter(fn ($account) => in_array($account->subtype, ['other_current_asset', 'accounts_receivable', 'cash', 'bank'], true))->values(),
            'inventory' => $accounts->where('subtype', 'inventory')->values(),
            'revenue' => $accounts->where('type', 'revenue')->values(),
            'cogs' => $accounts->where('type', 'cogs')->values(),
            'expense' => $accounts->where('type', 'expense')->values(),
            'equity' => $accounts->where('type', 'equity')->values(),
        ];

        $vendors = Vendor::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('FuelStation/Settings/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'settings' => [
                'id' => $settings->id,
                'fuel_vendor' => $settings->fuel_vendor,
                'month_end_stock_valuation' => $settings->month_end_stock_valuation,
                'supplier_payment_allocation' => $settings->supplier_payment_allocation ?? 'oldest_first',
                'has_partners' => $settings->has_partners,
                'has_amanat' => $settings->has_amanat,
                'has_lubricant_sales' => $settings->has_lubricant_sales,
                'has_investors' => $settings->has_investors,
                'dual_meter_readings' => $settings->dual_meter_readings,
                'track_attendant_handovers' => $settings->track_attendant_handovers,
                'payment_channels' => $settings->payment_channels ?? StationSettings::DEFAULT_PAYMENT_CHANNELS,
                'cash_account_id' => $settings->cash_account_id,
                'fuel_sales_account_id' => $settings->fuel_sales_account_id,
                'fuel_cogs_account_id' => $settings->fuel_cogs_account_id,
                'fuel_inventory_account_id' => $settings->fuel_inventory_account_id,
                'cash_over_short_account_id' => $settings->cash_over_short_account_id,
                'partner_drawings_account_id' => $settings->partner_drawings_account_id,
                'employee_advances_account_id' => $settings->employee_advances_account_id,
                'operating_bank_account_id' => $settings->operating_bank_account_id,
                'fuel_card_clearing_account_id' => $settings->fuel_card_clearing_account_id,
                'card_pos_clearing_account_id' => $settings->card_pos_clearing_account_id,
            ],
            'vendors' => StationSettings::VENDORS,
            'defaultPaymentChannels' => StationSettings::DEFAULT_PAYMENT_CHANNELS,
            'accountsByType' => $accountsByType,
            'fuelProducts' => $fuelProducts,
            'companyVendors' => $vendors,
        ]);
    }

    /**
     * Update the station settings.
     */
    public function update(UpdateStationSettingsRequest $request): RedirectResponse
    {
        try {
            app(CommandBus::class)->dispatch('fuel.settings.update', $request->validated(), $request->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with('error', 'Station settings could not be saved. Please try again.');
        }

        return redirect()->back()->with('success', 'Station settings updated successfully.');
    }
}
