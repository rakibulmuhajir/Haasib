<?php

namespace App\Modules\Accounting\Actions\OpeningBalance;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Models\User;
use App\Modules\Accounting\Extensions\OpeningBalanceSections;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\OpeningBalanceAccounts;
use App\Modules\Accounting\Services\OpeningOtherAccounts;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\SalaryAdvance;

class ViewAction implements PaletteAction, \App\Contracts\DescribesPalette
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
        $accounts = $this->accounts->resolve($companyId, false);

        // The current generation's journal is looked up by the id tracked in settings — not by
        // scanning for transaction_type = 'opening_balance', which would also match a retired
        // generation's (reversed) journal. Fall back to the scan only when no id is on record
        // (settings written before this key existed).
        $journal = null;
        if (! empty($opening['journal_id'])) {
            $journal = Transaction::where('company_id', $companyId)
                ->where('id', $opening['journal_id'])
                ->whereNull('reversed_by_id')
                ->with('journalEntries.account')
                ->first();
        } else {
            $journal = Transaction::where('company_id', $companyId)
                ->where('transaction_type', SaveAction::JOURNAL_TYPE)
                ->whereNull('reversed_by_id')
                ->whereNull('reversal_of_id') // exclude reversal transactions — they carry the same transaction_type
                ->with('journalEntries.account')
                ->latest('created_at')
                ->first();
        }

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

        // People added one at a time after trading began each have their own small journal
        // (SaveAction::appendOnly); their lines belong to the opening position too.
        $journalIds = array_values(array_filter([$journal?->id, ...($opening['extra_journal_ids'] ?? [])]));
        $entryIds = $journalIds ? \Illuminate\Support\Facades\DB::table('acct.journal_entries')->whereIn('transaction_id', $journalIds)->pluck('id')->all() : [];
        // Module sections (amanat, ...): rows keyed by the section's key.
        $sections = app(OpeningBalanceSections::class)->availableFor($company);
        $sectionRows = [];
        foreach ($sections as $key => $section) {
            $sectionRows[$key] = $journalIds ? $section->rows($companyId, $entryIds) : [];
        }
        // Other balances: no table of their own, read back from the journal lines on the accounts
        // no other section posts to (OpeningOtherAccounts), net on the account's normal side.
        $otherAccounts = app(OpeningOtherAccounts::class)->eligible($company)->keyBy('id');
        $otherNet = [];
        if ($entryIds && $otherAccounts->isNotEmpty()) {
            foreach (\Illuminate\Support\Facades\DB::table('acct.journal_entries')->whereIn('id', $entryIds)->whereIn('account_id', $otherAccounts->keys()->all())->get(['account_id', 'debit_amount', 'credit_amount']) as $line) {
                $otherNet[$line->account_id] = ($otherNet[$line->account_id] ?? 0.0) + (float) $line->debit_amount - (float) $line->credit_amount;
            }
        }
        $otherRows = [];
        foreach ($otherNet as $accountId => $net) {
            $account = $otherAccounts[$accountId];
            $amount = round($account->normal_balance === 'credit' ? -$net : $net, 2);
            if ($amount > 0) {
                $otherRows[] = ['account_id' => $accountId, 'code' => $account->code, 'name' => $account->name, 'amount' => $amount, 'normal_balance' => $account->normal_balance];
            }
        }
        usort($otherRows, fn ($a, $b) => strcmp((string) $a['code'], (string) $b['code']));
        $otherAssets = array_sum(array_column(array_filter($otherRows, fn ($r) => $r['normal_balance'] !== 'credit'), 'amount'));
        $otherLiabilities = array_sum(array_column(array_filter($otherRows, fn ($r) => $r['normal_balance'] === 'credit'), 'amount'));

        $employees = $journalIds ? SalaryAdvance::whereIn('journal_entry_id', $entryIds)->with('employee:id,first_name,last_name')->get()
            ->map(fn ($a) => ['employee_id' => $a->employee_id, 'employee_name' => trim(($a->employee?->first_name ?? '').' '.($a->employee?->last_name ?? '')), 'amount' => (float) $a->amount, 'recovered' => (float) $a->amount_recovered])->values()->all() : [];
        $partners = $journalIds ? PartnerTransaction::whereIn('journal_entry_id', $entryIds)->with('partner:id,name')->get()
            ->map(fn ($p) => ['partner_id' => $p->partner_id, 'partner_name' => $p->partner?->name, 'amount' => (float) $p->amount])->values()->all() : [];
        $salariesOwed = $journalIds ? Payslip::where('company_id', $companyId)
            ->whereIn('gl_transaction_id', $journalIds)
            ->where('notes', SaveAction::MARK)
            ->with('employee:id,first_name,last_name')
            ->get()
            ->map(fn ($p) => [
                'employee_id' => $p->employee_id,
                'employee_name' => trim(($p->employee?->first_name ?? '').' '.($p->employee?->last_name ?? '')),
                'amount' => (float) $p->net_pay,
                'payslip_id' => $p->id,
                'paid' => $p->status === 'paid',
            ])->values()->all() : [];

        // Opening invoices/bills are identified by the ids stored in settings — never by the
        // internal_notes marker, which a void action can overwrite (Bill\VoidAction appends
        // the void reason into internal_notes) and which a user's own unrelated invoice could
        // coincidentally match.
        $invoiceIds = $opening['invoice_ids'] ?? [];
        $billIds = $opening['bill_ids'] ?? [];

        $creditCustomers = empty($invoiceIds) ? [] : Invoice::where('company_id', $companyId)->whereIn('id', $invoiceIds)->where('status', '!=', 'void')
            ->with('customer:id,name')->get()
            ->map(fn ($i) => ['customer_id' => $i->customer_id, 'customer_name' => $i->customer?->name, 'amount' => (float) $i->total_amount, 'invoice_id' => $i->id, 'paid_amount' => (float) $i->paid_amount])->values()->all();
        $suppliers = empty($billIds) ? [] : Bill::where('company_id', $companyId)->whereIn('id', $billIds)->where('status', '!=', 'void')
            ->with('vendor:id,name')->get()
            ->map(fn ($b) => ['vendor_id' => $b->vendor_id, 'vendor_name' => $b->vendor?->name, 'amount' => (float) $b->total_amount, 'bill_id' => $b->id, 'paid_amount' => (float) $b->paid_amount])->values()->all();

        $assets = $cash + array_sum(array_column($banks, 'amount')) + array_sum(array_column($creditCustomers, 'amount')) + array_sum(array_column($employees, 'amount'));
        $sectionTotal = function (string $side) use ($sections, $sectionRows): float {
            $total = 0.0;
            foreach ($sections as $key => $section) {
                if ($section->side() === $side) {
                    $total += array_sum(array_column($sectionRows[$key], 'amount'));
                }
            }

            return $total;
        };
        $assets += $sectionTotal('asset') + $otherAssets;
        $liabilities = $otherLiabilities + $sectionTotal('liability') +array_sum(array_column($suppliers, 'amount')) + array_sum(array_column($partners, 'amount')) + array_sum(array_column($salariesOwed, 'amount'));

        $earliest = SaveAction::nonOpeningTransactions($companyId, $opening)
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
                'salaries_owed' => $salariesOwed,
                'suppliers' => $suppliers,
                'partners' => $partners,
                'accounts' => $otherRows,
            ] + $sectionRows,
            'sections' => collect($sections)->map(fn ($section) => [
                'key' => $section->key(),
                'label' => $section->label(),
                'description' => $section->description(),
                'party' => $section->party(),
                'party_label' => $section->partyLabel(),
                'amount_label' => $section->amountLabel(),
                'side' => $section->side(),
            ])->values()->all(),
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
                'accounts' => $otherAccounts->map(fn ($a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'normal_balance' => $a->normal_balance])->values()->all(),
            ],
        ];
    }

    public function palette(): array
    {
        return ['hidden' => true];
    }
}
