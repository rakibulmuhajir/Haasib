<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\Inventory\Models\Item;
use Illuminate\Support\Facades\DB;

class StationSettingsService
{
    public function update(string $companyId, array $validated, ?string $userId): void
    {
        DB::transaction(function () use ($companyId, $validated, $userId) {
            $settings = StationSettings::forCompany($companyId);
            $validated = app(StationAccountMapper::class)->applyAutomaticPayloadMappings(
                $settings,
                $validated,
                $userId
            );

            $this->validatePaymentChannelMappings($validated['payment_channels'] ?? [], $companyId);

            $fuelProducts = $validated['fuel_products'] ?? [];
            unset($validated['fuel_products']);

            $valuationChanged = array_key_exists('month_end_stock_valuation', $validated)
                && $validated['month_end_stock_valuation'] !== $settings->month_end_stock_valuation;

            $settings->update($validated);
            app(StationAccountMapper::class)->ensureMappings($settings->fresh(), $userId);
            $this->updateFuelProductMappings($companyId, $fuelProducts);

            if ($valuationChanged) {
                $this->revalueClosedMonths($companyId);
            }

        });
    }

    /**
     * The valuation method changed: re-value every closed month, oldest first. Locked months keep
     * the value they were locked with.
     */
    private function revalueClosedMonths(string $companyId): void
    {
        $locked = app(DailyCloseLockService::class)->lockedMonths($companyId);
        $service = app(MonthEndStockValuationService::class);

        $months = DB::table('acct.transactions')
            ->where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereIn('status', ['posted', 'locked'])
            ->whereNull('deleted_at')->whereNull('reversed_by_id')
            ->pluck('transaction_date')
            ->map(fn ($date) => \Carbon\Carbon::parse($date))
            ->filter(fn ($day) => $day->toDateString() === $day->copy()->endOfMonth()->toDateString())
            ->map(fn ($day) => $day->format('Y-m'))
            ->unique()->sort()->values();

        foreach ($months as $month) {
            if (! in_array($month, $locked, true)) {
                $service->syncWithin($companyId, $month);
            }
        }
    }

    private function updateFuelProductMappings(string $companyId, array $fuelProducts): void
    {
        $mapper = app(FuelProductAccountMapper::class);

        foreach ($fuelProducts as $product) {
            $item = Item::where('company_id', $companyId)
                ->where('id', $product['id'])
                ->whereNotNull('fuel_category')
                ->first();

            if (! $item) {
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

    private function validatePaymentChannelMappings(array $channels, string $companyId): void
    {
        foreach ($channels as $index => $channel) {
            if (! ($channel['enabled'] ?? false)) {
                continue;
            }

            $label = $channel['label'] ?? 'Payment channel #'.($index + 1);
            $type = $channel['type'] ?? null;
            $bankAccountId = $channel['bank_account_id'] ?? null;
            $clearingAccountId = $channel['clearing_account_id'] ?? null;
            $settlesTo = $channel['settles_to'] ?? 'clearing';

            if (in_array($type, ['bank_transfer'], true) && ! $bankAccountId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "payment_channels.{$index}.bank_account_id" => "{$label} requires a destination bank account.",
                ]);
            }

            if (in_array($type, ['card_pos', 'fuel_card'], true) && ! $clearingAccountId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "payment_channels.{$index}.clearing_account_id" => "{$label} requires a clearing account.",
                ]);
            }

            if ($type === 'mobile_wallet' && ! $clearingAccountId && ! $bankAccountId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "payment_channels.{$index}.clearing_account_id" => "{$label} requires either a clearing account or a bank account.",
                ]);
            }

            if (! in_array($type, ['card_pos', 'fuel_card', 'mobile_wallet'], true) || $settlesTo === 'clearing') {
                continue;
            }

            if ($settlesTo === 'bank') {
                if (! $bankAccountId) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "payment_channels.{$index}.bank_account_id" => "{$label} settles straight to the bank, so it needs a bank account.",
                    ]);
                }
                $bankAccount = Account::where('company_id', $companyId)->whereKey($bankAccountId)->first();
                if (! $bankAccount || ! in_array($bankAccount->subtype, ['cash', 'bank'], true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "payment_channels.{$index}.bank_account_id" => "{$label}'s settlement account must be a cash or bank account.",
                    ]);
                }

                continue;
            }

            if ($settlesTo === 'supplier') {
                $vendorId = $channel['settles_to_vendor_id'] ?? null;
                if (! $vendorId || ! Vendor::where('company_id', $companyId)->whereKey($vendorId)->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "payment_channels.{$index}.settles_to_vendor_id" => "{$label} settles straight to a supplier, so it needs a vendor from this company.",
                    ]);
                }
                if (! $clearingAccountId) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "payment_channels.{$index}.clearing_account_id" => "{$label} still needs a clearing account — the supplier payment is made from it.",
                    ]);
                }
            }
        }
    }
}
