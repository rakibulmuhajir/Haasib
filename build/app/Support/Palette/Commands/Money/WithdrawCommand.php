<?php

namespace App\Support\Palette\Commands\Money;

/** Bank to cash. */
final class WithdrawCommand extends DepositCommand
{
    public function name(): string
    {
        return 'withdraw';
    }

    public function label(): string
    {
        return 'Withdraw cash from a bank';
    }

    public function sentence(): string
    {
        return 'Withdraw {amount} from {bank?} on {date?}';
    }

    protected function isDeposit(): bool
    {
        return false;
    }
}
