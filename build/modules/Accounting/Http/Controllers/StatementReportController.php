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
use App\Modules\Accounting\Services\ConsolidatedInvoiceService;
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

        $vendors = Vendor::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'vendor_number']);

        [$statement, $columns, $resolvedId] = match ($kind) {
            'customer' => $this->customerStatement($customers, $id, $from, $to),
            'supplier' => $this->supplierStatement($vendors, $id, $from, $to),
            'amanat' => $this->amanatStatement($holders, $id, $from, $to),
            default => $this->bankStatement($bankAccounts, $company->id, $id, $from, $to),
        };

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
            ],
            'columns' => $columns,
            'statement' => $statement,
            // For "Consolidated invoice" on a customer statement: the period's invoice lines, and
            // who it goes to and from (editable before saving).
            ...($kind === 'customer' && $resolvedId ? $this->consolidatedInvoiceProps($company, $resolvedId, $from, $to) : []),
        ]);
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
    private function consolidatedInvoiceProps($company, string $customerId, string $from, string $to): array
    {
        $service = app(ConsolidatedInvoiceService::class);
        $customer = Customer::where('company_id', $company->id)->find($customerId);
        if (! $customer) {
            return [];
        }

        return [
            'invoiceRows' => $service->rowsFor($company->id, $customerId, $from, $to),
            'billTo' => $service->billTo($customer),
            'billedBy' => $service->billedBy($company),
            'invoiceLabels' => ConsolidatedInvoiceService::labels($company),
        ];
    }
}
