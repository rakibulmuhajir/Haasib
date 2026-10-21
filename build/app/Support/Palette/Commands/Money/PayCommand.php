<?php

namespace App\Support\Palette\Commands\Money;

use App\Http\Controllers\Api\PaletteDefaultsController;
use App\Support\Palette\Commands\Resolution;
use App\Support\Palette\Commands\ResolveContext;
use App\Support\Palette\Commands\Slot;
use App\Support\Palette\Commands\SlotInput;

/** Money out to a vendor, settling its open bills oldest first. */
final class PayCommand extends MoneyCommand
{
    public function name(): string
    {
        return 'pay';
    }

    public function aliases(): array
    {
        return ['paid'];
    }

    public function label(): string
    {
        return 'Pay a vendor';
    }

    public function sentence(): string
    {
        return 'Pay {vendor} {amount} from {account?} on {date?}';
    }

    public function slots(): array
    {
        return [
            'vendor' => Slot::search('vendor'), 'amount' => Slot::amount(),
            'account' => Slot::account('cash_or_bank'), 'date' => Slot::date(),
        ];
    }

    public function target(): string
    {
        return 'bill_payment.create';
    }

    public function opens(): ?string
    {
        return '/{company}/bill-payments/{id}';
    }

    public function undo(): ?string
    {
        return 'bill_payment.void';
    }

    public function resolve(SlotInput $in, ResolveContext $ctx): Resolution
    {
        $d = $ctx->defaults;
        $states = [];
        $error = null;

        $vendor = $this->party('vendor', 'vendor', $in, $ctx, $states);
        $amount = $this->amount($in, $states);
        $from = $this->settlement('account', $in->text('account'), $in->value('account'), 'out', $ctx, $states, $error);
        $day = $this->day($in, $ctx, $states);
        if ($pending = $this->unsettled($states, $error)) {
            return $pending;
        }

        // Settle the vendor's open bills oldest first; whatever is left stays as an advance.
        $remaining = $amount;
        $allocations = [];
        $settled = [];
        foreach (PaletteDefaultsController::openBillsFor($ctx->company, $vendor['id']) as $bill) {
            if ($remaining <= 0.004) {
                break;
            }
            $take = round(min($remaining, $bill['balance']) * 100) / 100;
            if ($take <= 0) {
                continue;
            }
            $allocations[] = ['bill_id' => $bill['id'], 'amount_allocated' => $take];
            $settled[] = $bill['bill_number'] ?? 'bill';
            $remaining = round(($remaining - $take) * 100) / 100;
        }
        $notes = [];
        if ($settled !== []) {
            $notes[] = 'Settles '.implode(', ', $settled);
        }
        if ($remaining > 0.004) {
            $notes[] = $settled !== []
                ? "{$d['currency']} {$this->fmt($remaining)} stays as an advance"
                : 'No open bills: held as an advance';
        }

        $cash = array_column($d['cash'], 'id');

        return Resolution::ready(
            'bill_payment.create',
            [
                'vendor_id' => $vendor['id'], 'payment_date' => $day, 'amount' => $amount,
                'currency' => $d['currency'], 'base_currency' => $d['currency'],
                'payment_method' => in_array($from['id'], $cash, true) ? 'cash' : 'bank_transfer',
                'payment_account_id' => $from['id'],
                'allocations' => $allocations,
            ],
            $this->card($ctx, $amount, $day, ['who' => $vendor['label'], 'from' => $from['name']]),
            "Paid {$this->money($ctx, $amount)} to {$vendor['label']} from {$from['name']}",
            $notes,
        );
    }
}
