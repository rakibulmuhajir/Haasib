<?php

namespace App\Support\Palette\Commands\Money;

use App\Support\Palette\Commands\Resolution;
use App\Support\Palette\Commands\ResolveContext;
use App\Support\Palette\Commands\Slot;
use App\Support\Palette\Commands\SlotInput;

/** Money in from a customer, into a cash or bank account. */
final class ReceiveCommand extends MoneyCommand
{
    public function name(): string
    {
        return 'receive';
    }

    public function aliases(): array
    {
        return ['received', 'rcv'];
    }

    public function label(): string
    {
        return 'Receive from a customer';
    }

    public function sentence(): string
    {
        return 'Receive {amount} from {customer} into {account?} on {date?}';
    }

    public function slots(): array
    {
        return [
            'amount' => Slot::amount(), 'customer' => Slot::search('customer'),
            'account' => Slot::account('cash_or_bank'), 'date' => Slot::date(),
        ];
    }

    public function target(): string
    {
        return 'payment.create';
    }

    public function opens(): ?string
    {
        return '/{company}/payments/{id}';
    }

    public function undo(): ?string
    {
        return 'payment.void';
    }

    public function resolve(SlotInput $in, ResolveContext $ctx): Resolution
    {
        $states = [];
        $error = null;

        $amount = $this->amount($in, $states);
        $customer = $this->party('customer', 'customer', $in, $ctx, $states);
        $into = $this->settlement('account', $in->text('account'), $in->value('account'), 'in', $ctx, $states, $error);
        $day = $this->day($in, $ctx, $states);
        if ($pending = $this->unsettled($states, $error)) {
            return $pending;
        }

        $cash = array_column($ctx->defaults['cash'], 'id');

        return Resolution::ready(
            'payment.create',
            [
                'customer_id' => $customer['id'], 'amount' => $amount,
                // As the payments page does: a cash account raises the drawer, a bank account never does.
                'method' => in_array($into['id'], $cash, true) ? 'cash' : 'bank_transfer',
                'date' => $day, 'deposit_account_id' => $into['id'],
            ],
            $this->card($ctx, $amount, $day, ['who' => $customer['label'], 'to' => $into['name']]),
            "Received {$this->money($ctx, $amount)} from {$customer['label']} into {$into['name']}",
        );
    }
}
