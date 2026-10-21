<?php

namespace App\Support\Palette\Commands\Money;

use App\Support\Palette\Commands\Resolution;
use App\Support\Palette\Commands\ResolveContext;
use App\Support\Palette\Commands\Slot;
use App\Support\Palette\Commands\SlotInput;

/** A fee the bank took, booked to the Bank Charges account. */
final class BankChargeCommand extends MoneyCommand
{
    public function name(): string
    {
        return 'bank charge';
    }

    public function aliases(): array
    {
        return ['bank charges'];
    }

    public function label(): string
    {
        return 'Bank charge';
    }

    public function sentence(): string
    {
        return 'Bank charge {amount} on {bank?} for {note?} on {date?}';
    }

    public function slots(): array
    {
        return [
            'amount' => Slot::amount(), 'bank' => Slot::account('bank'),
            'note' => Slot::text(), 'date' => Slot::date(),
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
        $d = $ctx->defaults;
        $states = [];
        $error = null;

        $amount = $this->amount($in, $states);
        $day = $this->day($in, $ctx, $states);

        $bankText = $in->text('bank');
        $bankValue = $in->value('bank');
        $note = $in->text('note');
        $bank = null;
        $named = true;
        // `bank charge 500 meezan october fee` can arrive as one blank: the bank first, the rest is the note.
        if ($bankValue === null && $bankText !== null && $note === null) {
            $split = Accounts::splitChargeTail($bankText, $d['banks']);
            if ($split === null) {
                $states['bank'] = ['state' => 'not_found', 'message' => "No bank matches \"{$bankText}\""];
                $named = false;
            } else {
                $bankText = $split['bank'];
                $note = $split['note'];
            }
        }
        if ($named) {
            $bank = $this->bank('bank', $bankText, $bankValue, 'out', $ctx, $states, $error);
        }
        if (empty($d['bank_charges_account_id'])) {
            $error ??= 'Add a Bank Charges account first';
        }
        if ($pending = $this->unsettled($states, $error)) {
            return $pending;
        }

        $charges = null;
        foreach ($d['expense'] as $a) {
            if ($a['id'] === $d['bank_charges_account_id']) {
                $charges = $a;
            }
        }

        $params = [
            'kind' => 'charge', 'date' => $day, 'amount' => $amount, 'bank_account_id' => $bank['id'],
            'expense_account_id' => $d['bank_charges_account_id'],
        ];
        if ($note !== null) {
            $params['notes'] = $note;
        }

        return Resolution::ready(
            'bank_transaction.create',
            $params,
            $this->card($ctx, $amount, $day, ['what' => 'Bank charge', 'from' => $bank['name'], 'note' => $note]),
            "Bank charge {$this->money($ctx, $amount)} on {$bank['name']}".($charges ? " ({$charges['name']})" : ''),
        );
    }
}
