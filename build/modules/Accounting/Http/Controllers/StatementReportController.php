<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Modules\Accounting\Http\Requests\StatementReportRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\CustomerCategory;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\AccountStatementService;
use App\Modules\Accounting\Services\CustomerStatementService;
use App\Modules\Accounting\Services\PartnerStatementService;
use App\Modules\Accounting\Services\VendorStatementService;
use App\Modules\FuelStation\Services\AmanatStatementService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The one statement report: bank & cash accounts, customers and suppliers,
 * all through the same page and the same table, backed by whichever of the
 * three statement services matches `kind`. See AccountStatementService for
 * why a bank/cash statement's closing balance always matches the Balance
 * Sheet's figure for that account.
 */
class StatementReportController extends Controller
{
    /** Entries undone by a reversal (and the reversal) are hidden unless ?reversed=1. */
    private bool $showReversed = false;

    private bool $includeTrail = false;

    public function index(StatementReportRequest $request): Response
    {
        $company = CompanyContext::getCompany();
        $kind = $request->validated('kind');
        $id = $request->validated('id');
        $from = $request->validated('from');
        $to = $request->validated('to');
        $ids = array_values(array_filter(explode(',', (string) $request->validated('ids'))));
        $this->showReversed = (bool) $request->validated('reversed');
        $evidence = app(\App\Modules\Accounting\Services\StatementValueTrail::class);
        $available = in_array($kind, ['bank', 'customer', 'supplier', 'partner', 'expense', 'employee', 'amanat'], true) && $evidence->available($request->user());
        $this->includeTrail = $available && $request->header('X-Inertia-Partial-Component') === 'accounting/reports/Statement' && in_array('valueTrail', explode(',', $request->header('X-Inertia-Partial-Data', '')), true);
        $categoryId = $kind === 'customer' ? $request->validated('category_id') : null;

        $bankAccounts = Account::where('company_id', $company->id)
            ->whereIn('subtype', ['bank', 'cash'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        // Amanat holders get their own list: their movements are deposits held for them, not
        // invoices and payments. A holder who also buys on credit stays under Customer too.
        $holderIds = DB::table('fuel.customer_profiles')
            ->where('company_id', $company->id)
            ->where('is_amanat_holder', true)
            ->pluck('is_credit_customer', 'customer_id');

        $allCustomers = Customer::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'customer_number', 'category_id']);

        $customers = $allCustomers
            ->reject(fn ($c) => $holderIds->has($c->id) && ! $holderIds[$c->id])
            ->values();
        $holders = $allCustomers->filter(fn ($c) => $holderIds->has($c->id))->values();

        // Employees, when payroll is on: salary earned against advances and salary paid.
        $employees = $company->isModuleEnabled('payroll')
            ? DB::table('pay.employees')->where('company_id', $company->id)->where('is_active', true)
                ->orderBy('first_name')->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'employee_number'])
                ->map(fn ($e) => ['id' => $e->id, 'name' => trim($e->first_name.' '.$e->last_name), 'customer_number' => $e->employee_number])
            : collect();

        // Expense accounts: everything Daily Close > Money out > Expenses can be booked to (fixed
        // assets included), one statement each, or any of them together.
        $expenseAccounts = Account::where('company_id', $company->id)
            ->moneyOutTarget()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        // Partners: each one's own Capital and Drawings accounts, statement by statement.
        $partners = Partner::where('company_id', $company->id)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name]);

        $vendors = Vendor::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'vendor_number']);

        // A customer, supplier, holder or employee statement opens on everyone at once ('all'):
        // each of their transactions by date, named, with that person's own running balance.
        // With ids, only those people: a group, or any the user picked.
        $categorySummary = null;
        if ($categoryId) {
            // A customer category: everyone in it, in one combined statement, plus each one's totals.
            $members = $customers->filter(fn ($c) => $c->category_id === $categoryId)->values();
            [$statement, $columns] = $this->allParties($members, fn ($pid) => $this->customerStatement($customers, $pid, $from, $to), $from, $to);
            $resolvedId = 'category';
            $categorySummary = $this->categorySummary($members, $from, $to);
            $statement['category_summary'] = $categorySummary;
        } elseif ($kind !== 'bank' && ($id === null || $id === 'all' || $ids)) {
            $pick = fn ($list) => $ids ? collect($list)->filter(fn ($p) => in_array(is_array($p) ? $p['id'] : $p->id, $ids, true))->values() : $list;
            [$statement, $columns, $resolvedId] = match ($kind) {
                'customer' => $this->allParties($pick($customers), fn ($pid) => $this->customerStatement($customers, $pid, $from, $to), $from, $to),
                'supplier' => $this->allParties($pick($vendors), fn ($pid) => $this->supplierStatement($vendors, $pid, $from, $to), $from, $to),
                'amanat' => $this->allParties($pick($holders), fn ($pid) => $this->amanatStatement($holders, $pid, $from, $to), $from, $to),
                'employee' => $this->allParties($pick($employees), fn ($pid) => $this->employeeStatement($employees, $pid, $from, $to), $from, $to),
                'expense' => $this->allParties($pick($expenseAccounts), fn ($pid) => $this->expenseStatement($expenseAccounts, $pid, $from, $to), $from, $to),
                'partner' => $this->allParties($pick($partners), fn ($pid) => $this->partnerStatement($partners, $pid, $from, $to), $from, $to),
            };
            if ($ids) {
                $resolvedId = 'some';
            }
        } else {
            [$statement, $columns, $resolvedId] = match ($kind) {
                'customer' => $this->customerStatement($customers, $id, $from, $to),
                'supplier' => $this->supplierStatement($vendors, $id, $from, $to),
                'amanat' => $this->amanatStatement($holders, $id, $from, $to),
                'employee' => $this->employeeStatement($employees, $id, $from, $to),
                'expense' => $this->expenseStatement($expenseAccounts, $id, $from, $to),
                'partner' => $this->partnerStatement($partners, $id, $from, $to),
                default => $this->bankStatement($bankAccounts, $company->id, $id, $from, $to),
            };
        }

        $graph = $statement['valueTrail'] ?? null;
        unset($statement['valueTrail']);

        return Inertia::render('accounting/reports/Statement', [
            'valueTrailsAvailable' => $available && $resolvedId !== null,
            'valueTrail' => Inertia::optional(fn () => $evidence->present(fn () => $graph, $request->user(), $company->slug)),
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'filters' => ['kind' => $kind, 'id' => $resolvedId, 'ids' => $ids, 'category_id' => $categoryId, 'from' => $from, 'to' => $to, 'reversed' => $this->showReversed],
            'options' => [
                'bank' => $bankAccounts,
                'customer' => $customers,
                'supplier' => $vendors,
                'amanat' => $holders,
                'employee' => $employees->values(),
                'expense' => $expenseAccounts,
                'partner' => $partners->values(),
                'categories' => CustomerCategory::where('company_id', $company->id)->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
                // Customer groups: the group and its members, to pick in one go.
                'groups' => Customer::where('company_id', $company->id)->whereNotNull('parent_customer_id')
                    ->where('is_active', true)->get(['id', 'parent_customer_id'])
                    ->groupBy('parent_customer_id')
                    ->map(fn ($members, $parentId) => [
                        'id' => $parentId,
                        'name' => $allCustomers->firstWhere('id', $parentId)?->name ?? 'Group',
                        'member_ids' => [$parentId, ...$members->pluck('id')->all()],
                    ])->values(),
            ],
            'columns' => $columns,
            'statement' => $statement,
            // Customer statements only; a statement is a document the company issues, the rest are internal.
            'stamp' => $kind === 'customer' ? app(\App\Services\CompanyLetterhead::class)->stampFor($company, 'statement', true, $to ?: now()->toDateString()) : null,
        ]);
    }

    /**
     * Per customer for a category: opening, bought (invoiced), paid (received) and owes (closing),
     * straight from each customer's own statement so the figures agree with it.
     */
    private function categorySummary($members, string $from, string $to): array
    {
        $rows = [];
        foreach ($members as $member) {
            [$s] = $this->customerStatement($members, $member->id, $from, $to);
            $moves = array_filter($s['rows'], fn ($r) => ! in_array($r['type'], ['opening_balance', 'closing_balance'], true));
            $bought = round((float) array_sum(array_column($moves, 'money_in')), 2);
            $paid = round((float) array_sum(array_column($moves, 'money_out')), 2);
            $opening = round((float) $s['opening_balance'], 2);
            $closing = round((float) $s['closing_balance'], 2);
            if (! $moves && abs($opening) < 0.005 && abs($closing) < 0.005) {
                continue;
            }
            $rows[] = ['customer_id' => $member->id, 'name' => $member->name, 'opening' => $opening, 'bought' => $bought, 'paid' => $paid, 'owes' => $closing];
        }

        return [
            'rows' => $rows,
            'totals' => [
                'opening' => round(array_sum(array_column($rows, 'opening')), 2),
                'bought' => round(array_sum(array_column($rows, 'bought')), 2),
                'paid' => round(array_sum(array_column($rows, 'paid')), 2),
                'owes' => round(array_sum(array_column($rows, 'owes')), 2),
            ],
        ];
    }

    /**
     * Everyone's statement in one list: each person's rows (their own running balance kept),
     * named and sorted by date, between one opening and one closing row that add up everyone.
     * People with nothing in the range and nothing owing either way are left out.
     */
    private function allParties($parties, callable $one, string $from, string $to): array
    {
        $graph = ['nodes' => [], 'roots' => [], 'context' => ['start_date' => $from, 'end_date' => $to]];
        $openingRoots = $closingRoots = [];
        $rows = [];
        $opening = 0.0;
        $closing = 0.0;
        $columns = null;
        foreach ($parties as $party) {
            $partyId = is_array($party) ? $party['id'] : $party->id;
            $name = is_array($party) ? $party['name'] : $party->name;
            [$statement, $columns] = $one($partyId);
            $moves = array_values(array_filter($statement['rows'], fn ($r) => ! in_array($r['type'], ['opening_balance', 'closing_balance'], true)));
            if (! $moves && abs((float) $statement['opening_balance']) < 0.005 && abs((float) $statement['closing_balance']) < 0.005) {
                continue;
            }
            if (isset($statement['valueTrail'])) {
                $evidence = $statement['valueTrail'];
                $prefix = 'party:'.$partyId.':';
                foreach ($evidence['nodes'] as $nodeId => $node) {
                    $node['id'] = $prefix.$nodeId;
                    $node['children'] = array_map(fn ($child) => $prefix.$child, $node['children']);
                    if (in_array($nodeId, [$evidence['roots']['statement:opening'], $evidence['roots']['statement:closing']], true)) {
                        $node['label'] = $name.' · '.$node['label'];
                    }
                    $graph['nodes'][$node['id']] = $node;
                }
                $openingRoots[] = $prefix.$evidence['roots']['statement:opening'];
                $closingRoots[] = $prefix.$evidence['roots']['statement:closing'];
            }
            $opening += (float) $statement['opening_balance'];
            $closing += (float) $statement['closing_balance'];
            foreach ($moves as $i => $row) {
                $rows[] = [...$row, 'party' => $name, '_order' => [$row['date'] ?? '', $row['created_at'] ?? '', $name, $i]];
            }
        }
        usort($rows, fn ($a, $b) => $a['_order'] <=> $b['_order']);
        $rows = array_map(function ($row) {
            unset($row['_order']);

            return $row;
        }, $rows);

        $edge = fn (string $date, string $type, string $label, float $balance) => [
            'date' => $date, 'type' => $type, 'reference' => null, 'description' => $label, 'party' => 'All',
            'money_in' => 0.0, 'money_out' => 0.0, 'balance' => round($balance, 2), 'link' => null,
        ];

        if ($this->includeTrail) {
            $builder = app(\App\Modules\Accounting\Services\StatementValueTrail::class);
            $builder->node($graph, 'statement:opening', 'Opening balance', round($opening, 2), $openingRoots, 'Sum of selected parties');
            $builder->node($graph, 'statement:closing', 'Closing balance', round($closing, 2), $closingRoots, 'Sum of selected parties');
            foreach (['statement:opening', 'statement:closing'] as $root) {
                $graph['nodes'][$root]['estimated'] = collect($graph['nodes'][$root]['children'])
                    ->contains(fn ($id) => $graph['nodes'][$id]['estimated']);
            }
        }

        return [
            [
                ...($this->includeTrail ? ['valueTrail' => $graph] : []),
                'rows' => [$edge($from, 'opening_balance', 'Opening balance', $opening), ...$rows, $edge($to, 'closing_balance', 'Closing balance', $closing)],
                'opening_balance' => round($opening, 2),
                'closing_balance' => round($closing, 2),
                'from' => $from,
                'to' => $to,
                'party' => null,
                'combined' => true,
            ],
            $columns ?? ['money_in' => 'In', 'money_out' => 'Out', 'balance' => 'Balance'],
            'all',
        ];
    }

    /** One partner: opening, each movement on their Capital and Drawings accounts, closing. */
    private function partnerStatement($partners, ?string $id, string $from, string $to): array
    {
        $columns = ['money_in' => 'Put in / profit', 'money_out' => 'Taken', 'balance' => 'Balance'];
        $pick = $id ? $partners->firstWhere('id', $id) : null;
        $pick ??= $partners->first();
        $partner = $pick ? Partner::find($pick['id']) : null;

        if (! $partner) {
            return [['rows' => [], 'opening_balance' => 0.0, 'closing_balance' => 0.0, 'from' => $from, 'to' => $to, 'party' => null], $columns, null];
        }

        return [app(PartnerStatementService::class)->statement($partner, $from, $to, $this->showReversed, $this->includeTrail), $columns, $partner->id];
    }

    /** One expense account's ledger: each entry to it by date, with the running total. */
    private function expenseStatement($expenseAccounts, ?string $id, string $from, string $to): array
    {
        $columns = ['money_in' => 'Spent', 'money_out' => 'Reduced', 'balance' => 'Total'];
        $pick = $id ? $expenseAccounts->firstWhere('id', $id) : $expenseAccounts->first();
        if (! $pick) {
            return [
                ['rows' => [], 'opening_balance' => 0.0, 'closing_balance' => 0.0, 'from' => $from, 'to' => $to, 'account' => null],
                $columns,
                null,
            ];
        }

        return [
            app(AccountStatementService::class)->statement(Account::find($pick->id), $from, $to, $this->showReversed, $this->includeTrail),
            $columns,
            $pick->id,
        ];
    }

    private function bankStatement($bankAccounts, string $companyId, ?string $id, string $from, string $to): array
    {
        $account = $id
            ? Account::where('company_id', $companyId)->whereIn('subtype', ['bank', 'cash'])->find($id)
            : null;
        $account ??= $bankAccounts->isNotEmpty()
            ? Account::find($bankAccounts->first()->id)
            : null;

        if (! $account) {
            return [
                ['rows' => [], 'opening_balance' => 0.0, 'closing_balance' => 0.0, 'from' => $from, 'to' => $to, 'account' => null],
                ['money_in' => 'Money in', 'money_out' => 'Money out', 'balance' => 'Balance'],
                null,
            ];
        }

        try {
            $statement = app(AccountStatementService::class)->statement($account, $from, $to, $this->showReversed, $this->includeTrail);
        } catch (\Throwable $e) {
            if (! $this->includeTrail) {
                throw $e;
            }
            report($e);
            $statement = app(AccountStatementService::class)->statement($account, $from, $to, $this->showReversed);
            $statement['valueTrail'] = ['error' => 'Statement evidence could not be loaded. Please try again.'];
        }

        return [
            $statement,
            ['money_in' => 'Money in', 'money_out' => 'Money out', 'balance' => 'Balance'],
            $account->id,
        ];
    }

    private function customerStatement($customers, ?string $id, string $from, string $to): array
    {
        $customer = $id ? $customers->firstWhere('id', $id) : null;
        $customer ??= $customers->first();
        $customer = $customer ? Customer::find($customer->id) : null;

        if (! $customer) {
            return [
                ['rows' => [], 'opening_balance' => 0.0, 'closing_balance' => 0.0, 'from' => $from, 'to' => $to, 'party' => null],
                ['money_in' => 'Invoiced', 'money_out' => 'Received', 'balance' => 'Owes'],
                null,
            ];
        }

        $statement = app(CustomerStatementService::class)->statement($customer, $from, $to, $this->includeTrail);
        // The statement's own rows carry debit/credit; the report table wants a
        // direction-neutral money_in/money_out pair like the other two kinds.
        // For a buyer, a debit (invoice) is what they were invoiced and a
        // credit (payment/credit note) is what they paid.
        $statement['rows'] = collect($statement['rows'])->map(fn ($row) => [
            ...$row,
            'money_in' => $row['debit'] ?? 0.0,
            'money_out' => $row['credit'] ?? 0.0,
        ])->all();

        return [
            $statement,
            ['money_in' => 'Invoiced', 'money_out' => 'Received', 'balance' => 'Owes'],
            $customer->id,
        ];
    }

    private function employeeStatement($employees, ?string $id, string $from, string $to): array
    {
        $pick = $id ? $employees->firstWhere('id', $id) : null;
        $pick ??= $employees->first();
        $employee = $pick ? \App\Modules\Payroll\Models\Employee::find($pick['id']) : null;
        $columns = ['money_in' => 'Earned', 'money_out' => 'Taken / paid', 'balance' => 'We owe'];

        if (! $employee) {
            return [['rows' => [], 'opening_balance' => 0.0, 'closing_balance' => 0.0, 'from' => $from, 'to' => $to, 'party' => null], $columns, null];
        }

        return [app(\App\Modules\Payroll\Services\EmployeeStatementService::class)->statement($employee, $from, $to, $this->includeTrail), $columns, $employee->id];
    }

    private function amanatStatement($holders, ?string $id, string $from, string $to): array
    {
        $holder = $id ? $holders->firstWhere('id', $id) : null;
        $holder ??= $holders->first();
        $holder = $holder ? Customer::find($holder->id) : null;
        $columns = ['money_in' => 'Deposited', 'money_out' => 'Paid out', 'balance' => 'We hold'];

        if (! $holder) {
            return [
                ['rows' => [], 'opening_balance' => 0.0, 'closing_balance' => 0.0, 'from' => $from, 'to' => $to, 'party' => null],
                $columns,
                null,
            ];
        }

        return [app(AmanatStatementService::class)->statement($holder, $from, $to, $this->includeTrail), $columns, $holder->id];
    }

    private function supplierStatement($vendors, ?string $id, string $from, string $to): array
    {
        $vendor = $id ? $vendors->firstWhere('id', $id) : null;
        $vendor ??= $vendors->first();
        $vendor = $vendor ? Vendor::find($vendor->id) : null;

        if (! $vendor) {
            return [
                ['rows' => [], 'opening_balance' => 0.0, 'closing_balance' => 0.0, 'from' => $from, 'to' => $to, 'party' => null],
                ['money_in' => 'Billed', 'money_out' => 'Paid', 'balance' => 'We owe'],
                null,
            ];
        }

        $statement = app(VendorStatementService::class)->statement($vendor, $from, $to, $this->includeTrail);
        // For a supplier: a credit (bill) is what they billed, a debit
        // (payment/vendor credit) is what was paid.
        $statement['rows'] = collect($statement['rows'])->map(fn ($row) => [
            ...$row,
            'money_in' => $row['credit'] ?? 0.0,
            'money_out' => $row['debit'] ?? 0.0,
        ])->all();

        return [
            $statement,
            ['money_in' => 'Billed', 'money_out' => 'Paid', 'balance' => 'We owe'],
            $vendor->id,
        ];
    }
}
