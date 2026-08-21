<?php

namespace App\Modules\Umrah\Dashboard\Widgets;

use App\Constants\Permissions;
use App\Dashboard\DashboardWidget;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "What's actually yours" — the dashboard's hero figure.
 *
 * Cash and bank, less what is being held for agents (agent advances) and less
 * what is owed to vendors (accounts payable). All three lines are read from
 * the SAME source — posted general-ledger entries — deliberately: mixing a
 * ledger figure with a denormalised balance column would put two accounting
 * bases in one column of arithmetic.
 *
 * The conclusion can legitimately be negative. That is the point of the
 * widget and must never be clamped, hidden or absolute-valued.
 */
class CashPositionWidget implements DashboardWidget
{
    public function key(): string
    {
        return 'umrah.cash_position';
    }

    public function title(): string
    {
        return "What's actually yours";
    }

    public function description(): string
    {
        return 'Cash and bank, less what you are holding for agents and owe to vendors.';
    }

    public function permission(): ?string
    {
        return Permissions::UMRAH_GROUP_ACCOUNTING_VIEW;
    }

    public function defaultSpan(): int
    {
        return 12;
    }

    public function minSpan(): int
    {
        return 12;
    }

    public function resolve(Company $company, User $user, array $options): array
    {
        $rows = DB::connection('pgsql')->select(
            "select a.subtype, a.code, sum(j.debit_amount - j.credit_amount) as bal
             from acct.journal_entries j
             join acct.transactions t on t.id = j.transaction_id and t.status = 'posted'
             join acct.accounts a on a.id = j.account_id
             where j.company_id = ? and a.is_active = true and a.deleted_at is null
             group by a.subtype, a.code",
            [$company->id],
        );

        $cashAndBank = 0.0;
        $heldForAgents = 0.0;
        $owedToVendors = 0.0;

        foreach ($rows as $row) {
            $bal = (float) $row->bal;

            if (in_array($row->subtype, ['cash', 'bank'], true)) {
                $cashAndBank += $bal;
            }

            if (in_array($row->code, ['2200', '2270'], true)) {
                $heldForAgents += -$bal;
            }

            if ($row->subtype === 'accounts_payable') {
                $owedToVendors += -$bal;
            }
        }

        $total = $cashAndBank - $heldForAgents - $owedToVendors;

        return [
            'lines' => [
                ['label' => 'Cash and bank', 'amount' => $cashAndBank, 'sign' => null],
                ['label' => 'Held for agents', 'amount' => $heldForAgents, 'sign' => '−'],
                ['label' => 'Owed to vendors', 'amount' => $owedToVendors, 'sign' => '−'],
            ],
            'total_label' => "What's actually yours",
            'total' => $total,
            'currency' => $company->base_currency,
        ];
    }
}
