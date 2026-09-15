<?php

namespace App\Modules\Accounting\Actions\OpeningBalance;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\OpeningBalanceAccounts;
use App\Modules\FuelStation\Models\AmanatTransaction;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\SalaryAdvance;

class ViewAction implements PaletteAction
{
    public function __construct(private readonly OpeningBalanceAccounts $accounts) {}

    public function rules(): array
    {
        return [];
    }

    public function permission(): ?string
    {
        return Permissions::OPENING_BALANCE_VIEW;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $companyId = $company->id;
        $opening = ($company->settings ?? [])['opening_balances'] ?? [];
        $accounts = $this->accounts->resolve($companyId);

        // No 'reversals' relation exists on Transaction; the current (un-superseded)
        // opening journal is the one nothing has reversed yet.
        $journal = Transaction::where('company_id', $companyId)
            ->where('transaction_type', SaveAction::JOURNAL_TYPE)
            ->whereNull('reversed_by_id')
            ->whereNull('reversal_of_id') // exclude reversal transactions — they carry the same transaction_type
            ->with('journalEntries.account')
            ->latest('created_at')
            ->first();

        $cash = 0.0;
        $banks = [];
        if ($journal) {
            foreach ($journal->journalEntries as $entry) {
                if ($entry->account_id === $accounts['cash']) {
                    $cash += (float) $entry->debit_amount;
                } elseif ($entry->account?->subtype === 'bank') {
                    $banks[] = ['account_id' => $entry->account_id, 'account_name' => $entry->account->name, 'amount' => (float) $entry->debit_amount];
                }
            }
        }

        $entryIds = $journal ? $journal->journalEntries->pluck('id')->all() : [];
        $amanat = $journal ? AmanatTransaction::whereIn('journal_entry_id', $entryIds)->with('customer:id,name')->get()
            ->map(fn ($t) => ['customer_id' => $t->customer_id, 'customer_name' => $t->customer?->name, 'amount' => (float) $t->amount])->values()->all() : [];
        $employees = $journal ? SalaryAdvance::whereIn('journal_entry_id', $entryIds)->with('employee:id,first_name,last_name')->get()
            ->map(fn ($a) => ['employee_id' => $a->employee_id, 'employee_name' => trim(($a->employee?->first_name ?? '').' '.($a->employee?->last_name ?? '')), 'amount' => (float) $a->amount, 'recovered' => (float) $a->amount_recovered])->values()->all() : [];
        $partners = $journal ? PartnerTransaction::whereIn('journal_entry_id', $entryIds)->with('partner:id,name')->get()
            ->map(fn ($p) => ['partner_id' => $p->partner_id, 'partner_name' => $p->partner?->name, 'amount' => (float) $p->amount])->values()->all() : [];

        $creditCustomers = Invoice::where('company_id', $companyId)->where('internal_notes', SaveAction::MARK)->where('status', '!=', 'void')
            ->with('customer:id,name')->get()
            ->map(fn ($i) => ['customer_id' => $i->customer_id, 'customer_name' => $i->customer?->name, 'amount' => (float) $i->total_amount, 'invoice_id' => $i->id, 'paid_amount' => (float) $i->paid_amount])->values()->all();
        $suppliers = Bill::where('company_id', $companyId)->where('internal_notes', SaveAction::MARK)->where('status', '!=', 'void')
            ->with('vendor:id,name')->get()
            ->map(fn ($b) => ['vendor_id' => $b->vendor_id, 'vendor_name' => $b->vendor?->name, 'amount' => (float) $b->total_amount, 'bill_id' => $b->id, 'paid_amount' => (float) $b->paid_amount])->values()->all();

        $assets = $cash + array_sum(array_column($banks, 'amount')) + array_sum(array_column($creditCustomers, 'amount')) + array_sum(array_column($employees, 'amount'));
        $liabilities = array_sum(array_column($amanat, 'amount')) + array_sum(array_column($suppliers, 'amount')) + array_sum(array_column($partners, 'amount'));

        // Same exclusion as SaveAction::guardDate — opening invoices/bills post real
        // 'invoice'/'bill' transactions and must not count as "the first real transaction".
        $openingReferenceIds = Invoice::where('company_id', $companyId)->where('internal_notes', SaveAction::MARK)->pluck('id')
            ->merge(Bill::where('company_id', $companyId)->where('internal_notes', SaveAction::MARK)->pluck('id'))
            ->all();

        $earliest = Transaction::where('company_id', $companyId)
            ->whereNotIn('transaction_type', [SaveAction::JOURNAL_TYPE, SaveAction::REVERSAL_TYPE])
            ->where(fn ($q) => $q->whereNull('reference_type')->orWhere('reference_type', '!=', SaveAction::REFERENCE_TYPE))
            ->where(fn ($q) => $q->whereNull('reference_id')->orWhereNotIn('reference_id', $openingReferenceIds))
            ->whereIn('status', ['posted', 'locked'])
            ->min('transaction_date');

        $lockedBy = ! empty($opening['locked_by_user_id']) ? User::find($opening['locked_by_user_id'])?->name : null;

        return [
            'as_of_date' => $opening['as_of_date'] ?? null,
            'locked_at' => $opening['locked_at'] ?? null,
            'locked_by' => $lockedBy,
            'earliest_transaction_date' => $earliest ? substr((string) $earliest, 0, 10) : null,
            'rows' => [
                'cash' => ['amount' => round($cash, 2)],
                'banks' => $banks,
                'credit_customers' => $creditCustomers,
                'employees' => $employees,
                'amanat' => $amanat,
                'suppliers' => $suppliers,
                'partners' => $partners,
            ],
            'totals' => [
                'assets' => round($assets, 2),
                'liabilities' => round($liabilities, 2),
                'equity' => round($assets - $liabilities, 2),
            ],
            'options' => [
                'bank_accounts' => Account::where('company_id', $companyId)->where('is_active', true)->where('subtype', 'bank')->orderBy('code')->get(['id', 'code', 'name'])->toArray(),
                'customers' => Customer::where('company_id', $companyId)->orderBy('name')->get(['id', 'name'])->toArray(),
                'vendors' => Vendor::where('company_id', $companyId)->orderBy('name')->get(['id', 'name'])->toArray(),
                'employees' => Employee::where('company_id', $companyId)->orderBy('first_name')->get(['id', 'first_name', 'last_name'])
                    ->map(fn ($e) => ['id' => $e->id, 'name' => trim($e->first_name.' '.$e->last_name)])->values()->all(),
                'partners' => Partner::where('company_id', $companyId)->orderBy('name')->get(['id', 'name'])->toArray(),
            ],
        ];
    }
}
