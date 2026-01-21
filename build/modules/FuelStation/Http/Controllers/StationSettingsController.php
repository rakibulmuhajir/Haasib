<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\FuelStation\Models\StationSettings;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'receivable' => $accounts->where('subtype', 'receivable')->values(),
            'inventory' => $accounts->where('subtype', 'inventory')->values(),
            'revenue' => $accounts->where('type', 'revenue')->values(),
            'cogs' => $accounts->where('type', 'cogs')->values(),
            'expense' => $accounts->where('type', 'expense')->values(),
            'equity' => $accounts->where('type', 'equity')->values(),
        ];

        return Inertia::render('FuelStation/Settings/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'settings' => [
                'id' => $settings->id,
                'fuel_vendor' => $settings->fuel_vendor,
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
        ]);
    }

    /**
     * Update the station settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $validated = $request->validate([
            'fuel_vendor' => 'required|string|in:' . implode(',', array_keys(StationSettings::VENDORS)),
            'has_partners' => 'boolean',
            'has_amanat' => 'boolean',
            'has_lubricant_sales' => 'boolean',
            'has_investors' => 'boolean',
            'dual_meter_readings' => 'boolean',
            'track_attendant_handovers' => 'boolean',
            'payment_channels' => 'nullable|array',
            'payment_channels.*.code' => 'required|string',
            'payment_channels.*.label' => 'required|string',
            'payment_channels.*.type' => 'required|string|in:cash,bank_transfer,card_pos,fuel_card,mobile_wallet',
            'payment_channels.*.enabled' => 'boolean',
            'payment_channels.*.bank_account_id' => 'nullable|uuid',
            'payment_channels.*.clearing_account_id' => 'nullable|uuid',
            'cash_account_id' => 'nullable|uuid',
            'fuel_sales_account_id' => 'nullable|uuid',
            'fuel_cogs_account_id' => 'nullable|uuid',
            'fuel_inventory_account_id' => 'nullable|uuid',
            'cash_over_short_account_id' => 'nullable|uuid',
            'partner_drawings_account_id' => 'nullable|uuid',
            'employee_advances_account_id' => 'nullable|uuid',
            'operating_bank_account_id' => 'nullable|uuid',
            'fuel_card_clearing_account_id' => 'nullable|uuid',
            'card_pos_clearing_account_id' => 'nullable|uuid',
        ]);

        $settings = StationSettings::forCompany($company->id);
        $settings->update($validated);

        return redirect()->back()->with('success', 'Station settings updated successfully.');
    }
}
