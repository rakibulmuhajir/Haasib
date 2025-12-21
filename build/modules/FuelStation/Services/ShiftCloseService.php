<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\PumpReading;
use App\Modules\Inventory\Models\Item;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;

class ShiftCloseService
{
    public function __construct(
        private readonly GlPostingService $postingService,
    ) {}

    /**
     * Post a daily/shift close entry:
     * - Sales revenue at regulated sale rate (from RateChange)
     * - COGS at weighted-average cost (Item.avg_cost)
     * - Cash/clearing movements by channel
     *
     * @param array{
     *   date:string,
     *   shift:'day'|'night',
     *   lines:array<int, array{item_id:string, liters_sold:float, sale_rate:float}>,
     *   cash_amount?:float,
     *   easypaisa_amount?:float,
     *   jazzcash_amount?:float,
     *   bank_transfer_amount?:float,
     *   card_swipe_amount?:float,
     *   parco_card_amount?:float,
     *   notes?:string|null,
     * } $params
     */
    public function post(array $params): Transaction
    {
        $company = app(CurrentCompany::class)->get();

        $date = $params['date'];
        $shift = $params['shift'];

        $lines = $params['lines'];
        $hasAnyLiters = collect($lines)->sum(fn ($l) => (float) ($l['liters_sold'] ?? 0)) > 0;
        if (! $hasAnyLiters) {
            throw new \InvalidArgumentException('Enter liters sold for at least one fuel item.');
        }

        $transactionNumber = $this->generateTransactionNumber($company->id, $date, $shift);

        $accounts = $this->resolveAccounts($company->id);

        $currency = strtoupper((string) ($company->base_currency ?: 'PKR'));

        return DB::transaction(function () use ($company, $date, $shift, $transactionNumber, $accounts, $currency, $params, $lines) {
            $items = Item::where('company_id', $company->id)
                ->whereIn('id', array_values(array_unique(array_map(fn ($l) => (string) $l['item_id'], $lines))))
                ->get(['id', 'name', 'avg_cost', 'fuel_category']);

            $itemsById = $items->keyBy('id');

            $totalRevenue = 0.0;
            $totalCogs = 0.0;

            foreach ($lines as $line) {
                $itemId = (string) $line['item_id'];
                $liters = (float) $line['liters_sold'];
                if ($liters <= 0) {
                    continue;
                }

                $item = $itemsById->get($itemId);
                if (! $item) {
                    throw new \RuntimeException('Fuel item not found for this company.');
                }
                if (! $item->fuel_category) {
                    throw new \RuntimeException('Only fuel items can be posted in shift close.');
                }

                $saleRate = (float) $line['sale_rate'];
                if ($saleRate <= 0) {
                    $rate = RateChange::getRateForDate($company->id, $itemId, $date);
                    $saleRate = (float) ($rate?->sale_rate ?? 0);
                }
                if ($saleRate <= 0) {
                    throw new \RuntimeException("No sale rate available for {$item->name} on {$date}.");
                }

                $avgCost = (float) ($item->avg_cost ?? 0);
                if ($avgCost <= 0) {
                    throw new \RuntimeException("Average cost is missing for {$item->name}. Enter purchases before posting shift close.");
                }

                $totalRevenue += round($liters * $saleRate, 2);
                $totalCogs += round($liters * $avgCost, 2);
            }

            $cash = (float) ($params['cash_amount'] ?? 0);
            $walletsAndTransfers = (float) ($params['easypaisa_amount'] ?? 0)
                + (float) ($params['jazzcash_amount'] ?? 0)
                + (float) ($params['bank_transfer_amount'] ?? 0);
            $card = (float) ($params['card_swipe_amount'] ?? 0);
            $parco = (float) ($params['parco_card_amount'] ?? 0);

            $entries = [];

            if ($cash > 0) {
                $entries[] = [
                    'account_id' => $accounts['cash_on_hand'],
                    'type' => 'debit',
                    'amount' => round($cash, 2),
                    'description' => 'Cash collected',
                ];
            }
            if ($walletsAndTransfers > 0) {
                $entries[] = [
                    'account_id' => $accounts['undeposited_funds'],
                    'type' => 'debit',
                    'amount' => round($walletsAndTransfers, 2),
                    'description' => 'Wallets / bank transfers (pending)',
                ];
            }
            if ($card > 0) {
                $entries[] = [
                    'account_id' => $accounts['card_clearing'],
                    'type' => 'debit',
                    'amount' => round($card, 2),
                    'description' => 'Card swipes (pending settlement)',
                ];
            }
            if ($parco > 0) {
                $entries[] = [
                    'account_id' => $accounts['parco_clearing'],
                    'type' => 'debit',
                    'amount' => round($parco, 2),
                    'description' => 'Parco card sales (pending settlement)',
                ];
            }

            if ($totalCogs > 0) {
                $entries[] = [
                    'account_id' => $accounts['fuel_cogs'],
                    'type' => 'debit',
                    'amount' => round($totalCogs, 2),
                    'description' => 'Fuel COGS (WAC)',
                ];
                $entries[] = [
                    'account_id' => $accounts['fuel_inventory'],
                    'type' => 'credit',
                    'amount' => round($totalCogs, 2),
                    'description' => 'Fuel inventory reduction (WAC)',
                ];
            }

            if ($totalRevenue > 0) {
                $entries[] = [
                    'account_id' => $accounts['fuel_sales'],
                    'type' => 'credit',
                    'amount' => round($totalRevenue, 2),
                    'description' => "Fuel sales ({$shift} shift)",
                ];
            }

            $collections = round($cash + $walletsAndTransfers + $card + $parco, 2);
            $variance = round($collections - $totalRevenue, 2);
            if (abs($variance) > 0.0) {
                $entries[] = [
                    'account_id' => $accounts['cash_over_short'],
                    'type' => $variance >= 0 ? 'credit' : 'debit',
                    'amount' => abs($variance),
                    'description' => 'Cash over/short vs expected sales',
                ];
            }

            $notes = $params['notes'] ?? null;
            $description = trim("Fuel shift close ({$shift}) - {$date}" . ($notes ? " — {$notes}" : ''));

            return $this->postingService->postBalancedTransaction([
                'company_id' => $company->id,
                'transaction_number' => $transactionNumber,
                'transaction_type' => 'fuel_shift_close',
                'date' => $date,
                'currency' => $currency,
                'base_currency' => $currency,
                'description' => $description,
                'reference_type' => 'fuel.shift_close',
                'reference_id' => null,
            ], $entries);
        });
    }

    private function generateTransactionNumber(string $companyId, string $date, string $shift): string
    {
        $base = 'FSC-' . str_replace('-', '', $date) . '-' . strtoupper($shift);

        $exists = Transaction::where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('transaction_number', $base)
            ->exists();

        if ($exists) {
            throw new \RuntimeException("Shift close already posted for {$date} ({$shift}). Void the existing entry to repost.");
        }

        return $base;
    }

    /**
     * @return array{
     *   cash_on_hand:string,
     *   undeposited_funds:string,
     *   card_clearing:string,
     *   parco_clearing:string,
     *   fuel_sales:string,
     *   fuel_cogs:string,
     *   fuel_inventory:string,
     *   cash_over_short:string,
     * }
     */
    private function resolveAccounts(string $companyId): array
    {
        $byCode = Account::where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereIn('code', ['1030', '1040', '1050', '1070', '1200', '4100', '5100', '6180'])
            ->get(['id', 'code'])
            ->keyBy('code');

        $cashOnHand = $byCode->get('1050')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('subtype', 'cash')->orderBy('code')->value('id');

        $undeposited = $byCode->get('1070')?->id ?? $cashOnHand;
        $cardClearing = $byCode->get('1040')?->id ?? $undeposited;
        $parcoClearing = $byCode->get('1030')?->id ?? $undeposited;

        $fuelInventory = $byCode->get('1200')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('subtype', 'inventory')->orderBy('code')->value('id');

        $fuelSales = $byCode->get('4100')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('type', 'revenue')->orderBy('code')->value('id');

        $fuelCogs = $byCode->get('5100')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('type', 'cogs')->orderBy('code')->value('id');

        $cashOverShort = $byCode->get('6180')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('type', 'expense')->orderBy('code')->value('id');

        $required = [
            'cash_on_hand' => $cashOnHand,
            'undeposited_funds' => $undeposited,
            'card_clearing' => $cardClearing,
            'parco_clearing' => $parcoClearing,
            'fuel_sales' => $fuelSales,
            'fuel_cogs' => $fuelCogs,
            'fuel_inventory' => $fuelInventory,
            'cash_over_short' => $cashOverShort,
        ];

        foreach ($required as $key => $id) {
            if (! $id) {
                throw new \RuntimeException("Required account missing: {$key}. Ensure fuel station COA is set up.");
            }
        }

        return $required;
    }
}

