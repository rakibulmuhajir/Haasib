<?php

namespace App\Support\Palette\Commands\Core;

use App\Support\Palette\Commands\Resolution;
use App\Support\Palette\Commands\ResolveContext;
use App\Support\Palette\Commands\Slot;
use App\Support\Palette\Commands\SlotInput;
use App\Support\Palette\PalettePreview;
use Illuminate\Support\Facades\DB;

/** What an account holds now. Shows a card and never executes. */
final class BalanceCommand extends CoreCommand
{
    public function name(): string
    {
        return 'balance';
    }

    public function label(): string
    {
        return 'Account balance';
    }

    public function sentence(): string
    {
        return 'Balance {account}';
    }

    public function slots(): array
    {
        return ['account' => Slot::search('account')];
    }

    public function target(): string
    {
        return 'account.view';
    }

    public function query(): bool
    {
        return true;
    }

    public function resolve(SlotInput $in, ResolveContext $ctx): Resolution
    {
        $states = [];
        $picked = $this->record('account', 'account', $in, $ctx, $states);
        if ($picked === null) {
            return Resolution::pending($states);
        }

        $account = DB::table('acct.accounts')
            ->where('company_id', $ctx->company->id)->whereNull('deleted_at')->where('id', $picked['id'])
            ->first(['id', 'name', 'normal_balance', 'currency']);
        if ($account === null) {
            return Resolution::pending(['account' => ['state' => 'not_found', 'message' => 'Not found']]);
        }

        $balance = PalettePreview::balance($ctx->company->id, $account->id, $account->normal_balance);

        return Resolution::ready(
            'account.view',
            ['id' => $account->id],
            ['what' => $account->name, 'amount' => $balance, 'currency' => $account->currency ?: $ctx->defaults['currency']],
            $account->name,
        );
    }
}
