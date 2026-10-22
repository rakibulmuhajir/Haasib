<?php

namespace App\Support\Palette\Commands\Money;

use App\Support\Palette\Commands\Resolution;
use App\Support\Palette\Commands\ResolveContext;
use App\Support\Palette\Commands\Slot;
use App\Support\Palette\Commands\SlotInput;

/** An everyday expense, paid from cash or a bank. */
final class SpendCommand extends MoneyCommand
{
    public function name(): string
    {
        return 'spend';
    }

    public function aliases(): array
    {
        return ['expense', 'exp'];
    }

    public function label(): string
    {
        return 'Spend on an expense';
    }

    public function sentence(): string
    {
        return 'Spend {amount} on {what} from {account?} on {date?}';
    }

    public function slots(): array
    {
        return [
            'amount' => Slot::amount(), 'what' => Slot::account('expense'),
            'account' => Slot::account('cash_or_bank'), 'date' => Slot::date(),
        ];
    }

    public function target(): string
    {
        return 'expense.create';
    }

    public function opens(): ?string
    {
        return '/{company}/journals/{id}';
    }

    public function undo(): ?string
    {
        return 'expense.reverse';
    }

    public function resolve(SlotInput $in, ResolveContext $ctx): Resolution
    {
        $states = [];
        $error = null;

        $amount = $this->amount($in, $states);
        $what = $in->text('what');
        $account = null;
        if ($what === null && $in->value('what') === null) {
            $states['what'] = ['state' => 'missing'];
        } else {
            // A description that names no account is not guessed at: every expense account is offered.
            [$states['what'], $account] = Accounts::pickAccount('expense account', $ctx->defaults['expense'], $what, $in->value('what'), true);
        }
        $from = $this->settlement('account', $in->text('account'), $in->value('account'), 'out', $ctx, $states, $error);
        $day = $this->day($in, $ctx, $states);
        if ($pending = $this->unsettled($states, $error)) {
            return $pending;
        }

        return Resolution::ready(
            'expense.create',
            [
                'date' => $day, 'account_id' => $account['id'], 'amount' => $amount,
                'paid_from_account_id' => $from['id'], 'description' => $what ?? $account['name'],
            ],
            $this->card($ctx, $amount, $day, ['what' => $account['name'], 'from' => $from['name']]),
            "Expense {$this->money($ctx, $amount)}: {$account['name']}, paid from {$from['name']}",
        );
    }
}
