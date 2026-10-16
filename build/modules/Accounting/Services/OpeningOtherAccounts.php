<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Models\Partner;
use App\Modules\Accounting\Extensions\OpeningBalanceSections;
use App\Modules\Accounting\Models\Account;
use Illuminate\Support\Collection;

/**
 * The accounts the "Other balances" opening section may use: fixed assets, loans, prepaid
 * expenses, owner's capital -- any balance-sheet account that no other opening section posts to.
 *
 * Other balances are not stored anywhere of their own. They are read back from the opening
 * journal's lines, so an account another section also posts to would be ambiguous (its line
 * would show up twice). Excluded:
 *  - bank / cash / accounts_receivable / accounts_payable subtypes, and Opening Balance Equity (3080);
 *  - the control accounts the party sections resolve (cash, AR, AP, employee advances 1150);
 *  - the payroll-payable and partner-deposit accounts (2210, 2211, 2220, 2221 and the
 *    names PayrollPostingService looks a payable up by);
 *  - every partner's own capital and drawings account;
 *  - the account of each module section this company has (OpeningBalanceSection::accountId()).
 */
class OpeningOtherAccounts
{
    public const EXCLUDED_SUBTYPES = ['bank', 'cash', 'accounts_receivable', 'accounts_payable'];

    private const PAYROLL_CODES = ['2210', '2211', '2220', '2221'];

    private const PAYROLL_NAMES = ['accrued salaries & wages', 'salary payable', 'payroll payable', 'payroll salaries payable', 'payroll deductions payable', 'payroll taxes payable'];

    public function __construct(private readonly OpeningBalanceAccounts $accounts) {}

    /** @return Collection<int, Account> */
    public function eligible(Company $company): Collection
    {
        $reserved = array_filter($this->accounts->resolve($company->id, false));

        foreach (app(OpeningBalanceSections::class)->availableFor($company) as $section) {
            if ($id = $section->accountId($company->id)) {
                $reserved[] = $id;
            }
        }

        foreach (Partner::where('company_id', $company->id)->get(['capital_account_id', 'drawing_account_id']) as $partner) {
            array_push($reserved, ...array_filter([$partner->capital_account_id, $partner->drawing_account_id]));
        }

        return Account::where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->where(fn ($q) => $q->whereNull('subtype')->orWhereNotIn('subtype', self::EXCLUDED_SUBTYPES))
            ->whereNotIn('code', [OpeningBalanceAccounts::EQUITY_CODE, ...self::PAYROLL_CODES])
            ->whereRaw('lower(name) not in ('.implode(',', array_fill(0, count(self::PAYROLL_NAMES), '?')).')', self::PAYROLL_NAMES)
            ->when($reserved, fn ($q) => $q->whereNotIn('id', array_values($reserved)))
            ->orderBy('code')
            ->get();
    }
}
