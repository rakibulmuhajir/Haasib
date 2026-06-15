<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\FuelStation\Services\FuelProductAccountMapper;
use App\Modules\FuelStation\Services\FuelVendorSyncService;
use App\Modules\FuelStation\Services\StationAccountMapper;
use App\Modules\Inventory\Models\Item;
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
            'fuelProducts' => $fuelProducts,
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
            'payment_channels.*.bank_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'payment_channels.*.clearing_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'cash_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'fuel_sales_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'fuel_cogs_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'fuel_inventory_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'cash_over_short_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'partner_drawings_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'employee_advances_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'operating_bank_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'fuel_card_clearing_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'card_pos_clearing_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'fuel_products' => 'nullable|array',
            'fuel_products.*.id' => 'required|uuid|exists:inv.items,id',
            'fuel_products.*.income_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'fuel_products.*.expense_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'fuel_products.*.asset_account_id' => 'nullable|uuid|exists:acct.accounts,id',
        ]);

        $settings = StationSettings::forCompany($company->id);
        $validated = app(StationAccountMapper::class)->applyAutomaticPayloadMappings(
            $settings,
            $validated,
            optional($request->user())->id
        );

        $this->validatePaymentChannelMappings($validated['payment_channels'] ?? []);

        $fuelProducts = $validated['fuel_products'] ?? [];
        unset($validated['fuel_products']);

        $settings->update($validated);
        app(FuelVendorSyncService::class)->ensureVendorForStationSetting($company, $settings->fuel_vendor);
        app(StationAccountMapper::class)->ensureMappings($settings->fresh(), optional($request->user())->id);
        $this->updateFuelProductMappings($company->id, $fuelProducts);

        return redirect()->back()->with('success', 'Station settings updated successfully.');
    }

    private function updateFuelProductMappings(string $companyId, array $fuelProducts): void
    {
        $mapper = app(FuelProductAccountMapper::class);

        foreach ($fuelProducts as $product) {
            $item = Item::where('company_id', $companyId)
                ->where('id', $product['id'])
                ->whereNotNull('fuel_category')
                ->first();

            if (!$item) {
                continue;
            }

            $item = $mapper->ensureItemMappings($item);

            $item->update([
                'income_account_id' => $product['income_account_id'] ?? $item->income_account_id,
                'expense_account_id' => $product['expense_account_id'] ?? $item->expense_account_id,
                'asset_account_id' => $product['asset_account_id'] ?? $item->asset_account_id,
            ]);
        }
    }

    private function validatePaymentChannelMappings(array $channels): void
    {
        foreach ($channels as $index => $channel) {
            if (!($channel['enabled'] ?? false)) {
                continue;
            }

            $label = $channel['label'] ?? "Payment channel #" . ($index + 1);
            $type = $channel['type'] ?? null;
            $bankAccountId = $channel['bank_account_id'] ?? null;
            $clearingAccountId = $channel['clearing_account_id'] ?? null;

            if (in_array($type, ['bank_transfer'], true) && !$bankAccountId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "payment_channels.{$index}.bank_account_id" => "{$label} requires a destination bank account.",
                ]);
            }

            if (in_array($type, ['card_pos', 'fuel_card'], true) && !$clearingAccountId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "payment_channels.{$index}.clearing_account_id" => "{$label} requires a clearing account.",
                ]);
            }

            if ($type === 'mobile_wallet' && !$clearingAccountId && !$bankAccountId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "payment_channels.{$index}.clearing_account_id" => "{$label} requires either a clearing account or a bank account.",
                ]);
            }
        }
    }
}
