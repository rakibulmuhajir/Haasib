<?php

namespace App\Support\Palette\Commands\Money;

use App\Support\Palette\Commands\Resolution;
use App\Support\Palette\Commands\ResolveContext;
use App\Support\Palette\Commands\Slot;
use App\Support\Palette\Commands\SlotInput;

/** Cash to bank. WithdrawCommand is the same entry the other way round. */
class DepositCommand extends MoneyCommand
{
    public function name(): string
    {
        return 'deposit';
    }

    public function label(): string
    {
        return 'Deposit cash into a bank';
    }

    public function sentence(): string
    {
        return 'Deposit {amount} into {bank?} on {date?}';
    }

    public function slots(): array
    {
        return ['amount' => Slot::amount(), 'bank' => Slot::account('bank'), 'date' => Slot::date()];
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
        $deposit = $this->isDeposit();
        $states = [];
        $error = null;

        $amount = $this->amount($in, $states);
        $day = $this->day($in, $ctx, $states);
        $bank = $this->bank('bank', $in->text('bank'), $in->value('bank'), $deposit ? 'in' : 'out', $ctx, $states, $error);
        $cash = $this->cashDrawer($ctx, $error);
        if ($pending = $this->unsettled($states, $error)) {
            return $pending;
        }

        $money = $this->money($ctx, $amount);

        return Resolution::ready(
            'bank_transaction.create',
            [
                'kind' => $deposit ? 'deposit' : 'withdrawal', 'date' => $day, 'amount' => $amount,
                'bank_account_id' => $bank['id'], 'cash_account_id' => $cash['id'],
            ],
            $this->card($ctx, $amount, $day, $deposit
                ? ['from' => $cash['name'], 'to' => $bank['name']]
                : ['from' => $bank['name'], 'to' => $cash['name']]),
            $deposit
                ? "Deposit {$money}: {$cash['name']} to {$bank['name']}"
                : "Withdraw {$money}: {$bank['name']} to {$cash['name']}",
        );
    }

    protected function isDeposit(): bool
    {
        return true;
    }
}
