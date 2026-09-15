<?php

namespace App\Modules\Accounting\Actions\OpeningBalance;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Models\Partner;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Accounting\Services\OpeningBalanceAccounts;
use App\Modules\Payroll\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveAction implements PaletteAction
{
    public const JOURNAL_TYPE = 'opening_balance';
    public const REVERSAL_TYPE = 'opening_balance_reversal';
    public const REFERENCE_TYPE = 'acct.opening_balances';
    public const MARK = 'OPENING';

    public function __construct(
        private readonly GlPostingService $posting,
        private readonly OpeningBalanceAccounts $accounts,
    ) {}

    public function rules(): array
    {
        return [
            'as_of_date' => 'required|date',
            'cash' => 'nullable|array',
            'cash.amount' => 'nullable|numeric|min:0',
            'banks' => 'nullable|array',
            'banks.*.account_id' => ['required', 'uuid', Rule::exists(Account::class, 'id')],
            'banks.*.amount' => 'required|numeric|min:0',
            'credit_customers' => 'nullable|array',
            'credit_customers.*.customer_id' => ['required', 'uuid', Rule::exists(Customer::class, 'id')],
            'credit_customers.*.amount' => 'required|numeric|gt:0',
            'employees' => 'nullable|array',
            'employees.*.employee_id' => ['required', 'uuid', Rule::exists(Employee::class, 'id')],
            'employees.*.amount' => 'required|numeric|gt:0',
            'amanat' => 'nullable|array',
            'amanat.*.customer_id' => ['required', 'uuid', Rule::exists(Customer::class, 'id')],
            'amanat.*.amount' => 'required|numeric|gt:0',
            'suppliers' => 'nullable|array',
            'suppliers.*.vendor_id' => ['required', 'uuid', Rule::exists(Vendor::class, 'id')],
            'suppliers.*.amount' => 'required|numeric|gt:0',
            'partners' => 'nullable|array',
            'partners.*.partner_id' => ['required', 'uuid', Rule::exists(Partner::class, 'id')],
            'partners.*.amount' => 'required|numeric|gt:0',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::OPENING_BALANCE_MANAGE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $asOf = $params['as_of_date'];

        $this->guardNotLocked($company);
        $this->guardDate($company->id, $asOf);

        return DB::transaction(function () use ($company, $params, $asOf) {
            $accounts = $this->accounts->resolve($company->id);
            $currency = strtoupper((string) ($company->base_currency ?: 'PKR'));

            $lines = []; // journal entries: ['account_id','type','amount','description']

            $this->addCashAndBankLines($company->id, $params, $accounts, $lines);

            $journalId = $this->postJournal($company->id, $currency, $asOf, $accounts['equity'], $lines);

            $settings = $company->settings ?? [];
            $settings['opening_balances'] = [
                'as_of_date' => $asOf,
                'locked_at' => null,
                'locked_by_user_id' => null,
            ];
            $company->settings = $settings;
            $company->save();

            return [
                'message' => 'Opening balances saved as of '.$asOf,
                'data' => ['journal_id' => $journalId, 'invoice_ids' => [], 'bill_ids' => []],
            ];
        });
    }

    private function guardNotLocked($company): void
    {
        if (! empty(($company->settings['opening_balances'] ?? [])['locked_at'])) {
            throw ValidationException::withMessages(['as_of_date' => 'Opening balances are locked.']);
        }
    }

    private function guardDate(string $companyId, string $asOf): void
    {
        $earliest = Transaction::where('company_id', $companyId)
            ->whereNotIn('transaction_type', [self::JOURNAL_TYPE, self::REVERSAL_TYPE])
            ->where(function ($q) {
                $q->whereNull('reference_type')->orWhere('reference_type', '!=', self::REFERENCE_TYPE);
            })
            ->whereIn('status', ['posted', 'locked'])
            ->min('transaction_date');

        if ($earliest && $asOf >= substr((string) $earliest, 0, 10)) {
            throw ValidationException::withMessages([
                'as_of_date' => "Opening balances must be dated before the first posted transaction ({$earliest}).",
            ]);
        }
    }

    private function addCashAndBankLines(string $companyId, array $params, array $accounts, array &$lines): void
    {
        $cash = (float) ($params['cash']['amount'] ?? 0);
        if ($cash > 0) {
            if (! $accounts['cash']) {
                throw ValidationException::withMessages(['cash.amount' => 'Set up account 1050 (Cash on Hand) first.']);
            }
            $lines[] = ['account_id' => $accounts['cash'], 'type' => 'debit', 'amount' => $cash, 'description' => 'Opening cash on hand'];
        }

        foreach ($params['banks'] ?? [] as $i => $bank) {
            $amount = (float) $bank['amount'];
            if ($amount <= 0) {
                continue;
            }
            $account = Account::where('company_id', $companyId)->where('id', $bank['account_id'])->where('subtype', 'bank')->first();
            if (! $account) {
                throw ValidationException::withMessages(["banks.{$i}.account_id" => 'Not a bank account of this company.']);
            }
            $lines[] = ['account_id' => $account->id, 'type' => 'debit', 'amount' => $amount, 'description' => "Opening balance — {$account->name}"];
        }
    }

    /** Posts the lines plus a single 3080 line that makes them balance. Returns null if there are no lines. */
    private function postJournal(string $companyId, string $currency, string $asOf, string $equityId, array $lines): ?string
    {
        if (empty($lines)) {
            return null;
        }

        $debits = 0.0;
        $credits = 0.0;
        foreach ($lines as $line) {
            $line['type'] === 'debit' ? $debits += $line['amount'] : $credits += $line['amount'];
        }
        $net = round($debits - $credits, 2);
        if ($net > 0) {
            $lines[] = ['account_id' => $equityId, 'type' => 'credit', 'amount' => $net, 'description' => 'Opening balance equity'];
        } elseif ($net < 0) {
            $lines[] = ['account_id' => $equityId, 'type' => 'debit', 'amount' => abs($net), 'description' => 'Opening balance equity'];
        }

        $transaction = $this->posting->postBalancedTransaction([
            'company_id' => $companyId,
            'transaction_type' => self::JOURNAL_TYPE,
            'date' => $asOf,
            'currency' => $currency,
            'base_currency' => $currency,
            'description' => 'Opening balances as of '.$asOf,
            'reference_type' => self::REFERENCE_TYPE,
            'reference_id' => null,
        ], $lines);

        return $transaction->id;
    }
}
