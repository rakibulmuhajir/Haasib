<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StatementReportRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\AccountStatementService;
use App\Modules\Accounting\Services\CustomerStatementService;
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
    public function index(StatementReportRequest $request): Response
    {
        $company = CompanyContext::getCompany();
        $kind = $request->validated('kind');
        $id = $request->validated('id');
        $from = $request->validated('from');
        $to = $request->validated('to');

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
            ->get(['id', 'name', 'customer_number']);

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

        $vendors = Vendor::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'vendor_number']);

        // A customer, supplier, holder or employee statement opens on everyone at once ('all'):
        // each of their transactions by date, named, with that person's own running balance.
        if ($kind !== 'bank' && ($id === null || $id === 'all')) {
            [$statement, $columns, $resolvedId] = match ($kind) {
                'customer' => $this->allParties($customers, fn ($pid) => $this->customerStatement($customers, $pid, $from, $to), $from, $to),
                'supplier' => $this->allParties($vendors, fn ($pid) => $this->supplierStatement($vendors, $pid, $from, $to), $from, $to),
                'amanat' => $this->allParties($holders, fn ($pid) => $this->amanatStatement($holders, $pid, $from, $to), $from, $to),
                'employee' => $this->allParties($employees, fn ($pid) => $this->employeeStatement($employees, $pid, $from, $to), $from, $to),
            };
        } else {
            [$statement, $columns, $resolvedId] = match ($kind) {
                'customer' => $this->customerStatement($customers, $id, $from, $to),
                'supplier' => $this->supplierStatement($vendors, $id, $from, $to),
                'amanat' => $this->amanatStatement($holders, $id, $from, $to),
                'employee' => $this->employeeStatement($employees, $id, $from, $to),
                default => $this->bankStatement($bankAccounts, $company->id, $id, $from, $to),
            };
        }

        return Inertia::render('accounting/reports/Statement', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'filters' => ['kind' => $kind, 'id' => $resolvedId, 'from' => $from, 'to' => $to],
            'options' => [
                'bank' => $bankAccounts,
                'customer' => $customers,
                'supplier' => $vendors,
                'amanat' => $holders,
                'employee' => $employees->values(),
            ],
            'columns' => $columns,
            'statement' => $statement,
        ]);
    }

    /**
     * Everyone's statement in one list: each person's rows (their own running balance kept),
     * named and sorted by date, between one opening and one closing row that add up everyone.
     * People with nothing in the range and nothing owing either way are left out.
     */
    private function allParties($parties, callable $one, string $from, string $to): array
    {
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

        return [
            [
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

        return [
            app(AccountStatementService::class)->statement($account, $from, $to),
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

        $statement = app(CustomerStatementService::class)->statement($customer, $from, $to);
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

        return [app(\App\Modules\Payroll\Services\EmployeeStatementService::class)->statement($employee, $from, $to), $columns, $employee->id];
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

        return [app(AmanatStatementService::class)->statement($holder, $from, $to), $columns, $holder->id];
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

        $statement = app(VendorStatementService::class)->statement($vendor, $from, $to);
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
