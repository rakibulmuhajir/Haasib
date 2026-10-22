<?php

namespace App\Support\Palette\Commands\Money;

use App\Support\Palette\Commands\Resolution;
use App\Support\Palette\Commands\ResolveContext;
use App\Support\Palette\Commands\Slot;
use App\Support\Palette\Commands\SlotInput;

/** Bank to bank. */
final class TransferCommand extends MoneyCommand
{
    public function name(): string
    {
        return 'transfer';
    }

    public function label(): string
    {
        return 'Transfer between banks';
    }

    public function sentence(): string
    {
        return 'Transfer {amount} from {from} to {to} on {date?}';
    }

    public function slots(): array
    {
        return [
            'amount' => Slot::amount(), 'from' => Slot::account('bank'),
            'to' => Slot::account('bank'), 'date' => Slot::date(),
        ];
    }

    public function target(): string
    {
        return 'bank_transaction.create';
    }

    public function opens(): ?string
    {
        return '/{company}/journals/{id}';
    }

    public function undo(): ?string
    {
        return 'bank_transaction.reverse';
    }

    public function resolve(SlotInput $in, ResolveContext $ctx): Resolution
    {
        $states = [];
        $error = null;

        $amount = $this->amount($in, $states);
        $from = $this->requiredBank($in, 'from', $ctx, $states, $error);
        $to = $this->requiredBank($in, 'to', $ctx, $states, $error);
        $day = $this->day($in, $ctx, $states);
        if ($from !== null && $to !== null && $from['id'] === $to['id']) {
            $error ??= 'Choose two different banks';
        }
        if ($pending = $this->unsettled($states, $error)) {
            return $pending;
        }

        return Resolution::ready(
            'bank_transaction.create',
            [
                'kind' => 'transfer', 'date' => $day, 'amount' => $amount,
                'from_bank_account_id' => $from['id'], 'to_bank_account_id' => $to['id'],
            ],
            $this->card($ctx, $amount, $day, ['from' => $from['name'], 'to' => $to['name']]),
            "Transfer {$this->money($ctx, $amount)}: {$from['name']} to {$to['name']}",
        );
    }

    /** A bank the line must name: there is no default to fall back on. */
    private function requiredBank(SlotInput $in, string $slot, ResolveContext $ctx, array &$states, ?string &$error): ?array
    {
        $text = $in->text($slot);
        $value = $in->value($slot);
        if ($text === null && $value === null) {
            $states[$slot] = ['state' => 'missing'];

            return null;
        }

        return $this->bank($slot, $text, $value, 'out', $ctx, $states, $error);
    }
}
