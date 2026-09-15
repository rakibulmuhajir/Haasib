<?php

namespace App\Modules\Accounting\Actions\OpeningBalance;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Accounting\Services\OpeningBalanceAccounts;
use App\Modules\FuelStation\Models\AmanatTransaction;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Services\CommandBus;
use Illuminate\Support\Facades\Auth;
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
            $pending = [];   // closures run after the journal exists, receiving its per-line entry ids
            $this->addAmanatLines($company->id, $params, $accounts, $lines, $pending);
            $this->addEmployeeAdvanceLines($company->id, $params, $accounts, $asOf, $lines, $pending);
            $this->addPartnerLines($company->id, $params, $accounts, $asOf, $lines, $pending);

            [$journalId, $entryIdsByLine] = $this->postJournal($company->id, $currency, $asOf, $accounts['equity'], $lines);
            foreach ($pending as $create) {
                $create($entryIdsByLine);
            }

            $invoiceIds = $this->createOpeningInvoices($company, $params, $accounts, $asOf, $currency);
            $billIds = $this->createOpeningBills($company, $params, $accounts, $asOf, $currency);

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
                'data' => ['journal_id' => $journalId, 'invoice_ids' => $invoiceIds, 'bill_ids' => $billIds],
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

    /**
     * Posts the lines plus a single 3080 line that makes them balance.
     * Returns [journal id, entry ids ordered to match $lines (0-based)] or [null, []] if there are no lines.
     *
     * @return array{0: ?string, 1: array<int, string>}
     */
    private function postJournal(string $companyId, string $currency, string $asOf, string $equityId, array $lines): array
    {
        if (empty($lines)) {
            return [null, []];
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

        $entryIdsByLine = $transaction->journalEntries()->orderBy('line_number')->pluck('id')->values()->all();

        return [$transaction->id, $entryIdsByLine];
    }

    private function addAmanatLines(string $companyId, array $params, array $accounts, array &$lines, array &$pending): void
    {
        $rows = array_filter($params['amanat'] ?? [], fn ($r) => (float) $r['amount'] > 0);
        if (empty($rows)) {
            return;
        }
        if (! $accounts['amanat']) {
            throw ValidationException::withMessages(['amanat' => 'Set up account 2200 (Customer Amanat Deposits) first.']);
        }
        foreach ($rows as $row) {
            $customer = Customer::where('company_id', $companyId)->findOrFail($row['customer_id']);
            $amount = round((float) $row['amount'], 2);
            $lineIndex = count($lines);
            $lines[] = ['account_id' => $accounts['amanat'], 'type' => 'credit', 'amount' => $amount, 'description' => "Opening amanat — {$customer->name}"];
            $pending[] = function (array $entryIdsByLine) use ($companyId, $customer, $amount, $lineIndex) {
                $profile = CustomerProfile::getOrCreateForCustomer($companyId, $customer->id);
                if (! $profile->is_amanat_holder) {
                    $profile->update(['is_amanat_holder' => true]);
                }
                AmanatTransaction::create([
                    'company_id' => $companyId,
                    'customer_id' => $customer->id,
                    'transaction_type' => AmanatTransaction::TYPE_DEPOSIT,
                    'amount' => $amount,
                    'reference' => self::MARK,
                    'notes' => 'Opening balance',
                    'recorded_by_user_id' => Auth::id(),
                    'journal_entry_id' => $entryIdsByLine[$lineIndex],
                ]);
                $profile->adjustAmanatBalance($amount);
            };
        }
    }

    private function addEmployeeAdvanceLines(string $companyId, array $params, array $accounts, string $asOf, array &$lines, array &$pending): void
    {
        $rows = array_filter($params['employees'] ?? [], fn ($r) => (float) $r['amount'] > 0);
        if (empty($rows)) {
            return;
        }
        if (! $accounts['employee_advances']) {
            throw ValidationException::withMessages(['employees' => 'Set up account 1150 (Employee Advances) first.']);
        }
        foreach ($rows as $row) {
            $amount = round((float) $row['amount'], 2);
            $employeeId = $row['employee_id'];
            $lineIndex = count($lines);
            $lines[] = ['account_id' => $accounts['employee_advances'], 'type' => 'debit', 'amount' => $amount, 'description' => 'Opening employee advance'];
            $pending[] = function (array $entryIdsByLine) use ($companyId, $employeeId, $amount, $asOf, $accounts, $lineIndex) {
                SalaryAdvance::create([
                    'company_id' => $companyId,
                    'employee_id' => $employeeId,
                    'advance_date' => $asOf,
                    'amount' => $amount,
                    'amount_recovered' => 0,
                    'amount_outstanding' => $amount,
                    'reason' => 'Opening balance',
                    'status' => 'pending',
                    'payment_method' => 'cash',
                    'reference' => self::MARK,
                    'journal_entry_id' => $entryIdsByLine[$lineIndex],
                    'advance_account_id' => $accounts['employee_advances'],
                    'recorded_by_user_id' => Auth::id(),
                ]);
            };
        }
    }

    private function addPartnerLines(string $companyId, array $params, array $accounts, string $asOf, array &$lines, array &$pending): void
    {
        $rows = array_filter($params['partners'] ?? [], fn ($r) => (float) $r['amount'] > 0);
        if (empty($rows)) {
            return;
        }
        if (! $accounts['partner_deposits']) {
            throw ValidationException::withMessages(['partners' => 'Set up account 2210 (Investor Deposits) first.']);
        }
        foreach ($rows as $row) {
            $amount = round((float) $row['amount'], 2);
            $partnerId = $row['partner_id'];
            $lineIndex = count($lines);
            $lines[] = ['account_id' => $accounts['partner_deposits'], 'type' => 'credit', 'amount' => $amount, 'description' => 'Opening partner capital'];
            $pending[] = function (array $entryIdsByLine) use ($companyId, $partnerId, $amount, $asOf, $lineIndex) {
                PartnerTransaction::create([
                    'company_id' => $companyId,
                    'partner_id' => $partnerId,
                    'transaction_date' => $asOf,
                    'transaction_type' => 'investment',
                    'amount' => $amount,
                    'description' => 'Opening balance',
                    'reference' => self::MARK,
                    'payment_method' => 'cash',
                    'journal_entry_id' => $entryIdsByLine[$lineIndex],
                    'recorded_by_user_id' => Auth::id(),
                ]);
            };
        }
    }

    private function createOpeningInvoices($company, array $params, array $accounts, string $asOf, string $currency): array
    {
        $rows = array_filter($params['credit_customers'] ?? [], fn ($r) => (float) $r['amount'] > 0);
        if (empty($rows)) {
            return [];
        }
        if (! $accounts['ar']) {
            throw ValidationException::withMessages(['credit_customers' => 'Set up account 1100 (Accounts Receivable) first.']);
        }
        $bus = app(CommandBus::class);
        $ids = [];
        foreach ($rows as $row) {
            $customer = Customer::where('company_id', $company->id)->findOrFail($row['customer_id']);
            $result = $bus->dispatch('invoice.create', [
                'customer' => $customer->id,
                'currency' => $currency,
                'date' => $asOf,
                'due' => $asOf,
                'send_immediately' => true,
                'internal_notes' => self::MARK,
                'description' => 'Opening balance as of '.$asOf,
                'line_items' => [[
                    'description' => 'Opening balance as of '.$asOf,
                    'quantity' => 1,
                    'unit_price' => round((float) $row['amount'], 2),
                    'tax_rate' => 0,
                    'income_account_id' => $accounts['equity'],
                ]],
            ], Auth::user(), true);
            $ids[] = $result['data']['id'];
        }

        return $ids;
    }

    private function createOpeningBills($company, array $params, array $accounts, string $asOf, string $currency): array
    {
        $rows = array_filter($params['suppliers'] ?? [], fn ($r) => (float) $r['amount'] > 0);
        if (empty($rows)) {
            return [];
        }
        if (! $accounts['ap']) {
            throw ValidationException::withMessages(['suppliers' => 'Set up account 2100 (Accounts Payable) first.']);
        }
        $bus = app(CommandBus::class);
        $ids = [];
        foreach ($rows as $row) {
            Vendor::where('company_id', $company->id)->findOrFail($row['vendor_id']);
            $result = $bus->dispatch('bill.create', [
                'vendor_id' => $row['vendor_id'],
                'bill_date' => $asOf,
                'due_date' => $asOf,
                'status' => 'received',
                'currency' => $currency,
                'base_currency' => $currency,
                'internal_notes' => self::MARK,
                'line_items' => [[
                    'description' => 'Opening balance as of '.$asOf,
                    'quantity' => 1,
                    'unit_price' => round((float) $row['amount'], 2),
                    'tax_rate' => 0,
                    'expense_account_id' => $accounts['equity'],
                ]],
            ], Auth::user(), true);
            $ids[] = $result['data']['id'];
        }

        return $ids;
    }
}
