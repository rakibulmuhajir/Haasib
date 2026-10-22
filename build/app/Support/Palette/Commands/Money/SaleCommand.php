<?php

namespace App\Support\Palette\Commands\Money;

use App\Support\Palette\Commands\Resolution;
use App\Support\Palette\Commands\ResolveContext;
use App\Support\Palette\Commands\Slot;
use App\Support\Palette\Commands\SlotInput;

/** A cash sale, into the money-received account or the cash drawer. */
final class SaleCommand extends MoneyCommand
{
    public function name(): string
    {
        return 'sale';
    }

    public function label(): string
    {
        return 'Record a sale';
    }

    public function sentence(): string
    {
        return 'Sale {amount} for {note?} on {date?}';
    }

    public function slots(): array
    {
        return ['amount' => Slot::amount(), 'note' => Slot::text(), 'date' => Slot::date()];
    }

    public function target(): string
    {
        return 'sale.create';
    }

    public function opens(): ?string
    {
        return 'redirect';
    }

    public function undo(): ?string
    {
        return 'sale.void';
    }

    public function resolve(SlotInput $in, ResolveContext $ctx): Resolution
    {
        $states = [];

        $amount = $this->amount($in, $states);
        $day = $this->day($in, $ctx, $states);
        if ($pending = $this->unsettled($states, null)) {
            return $pending;
        }

        $note = $in->text('note');
        $into = Accounts::settlementFallback($ctx->defaults, 'in');

        $params = ['date' => $day, 'amount' => $amount];
        if ($note !== null) {
            $params['description'] = $note;
        }
        if ($into !== null) {
            $params['deposit_account_id'] = $into['id'];
        }

        return Resolution::ready(
            'sale.create',
            $params,
            $this->card($ctx, $amount, $day, ['what' => 'Sale', 'to' => $into['name'] ?? null, 'note' => $note]),
            "Sale {$this->money($ctx, $amount)}".($note !== null ? ": {$note}" : '').($into !== null ? ", into {$into['name']}" : ''),
        );
    }
}
