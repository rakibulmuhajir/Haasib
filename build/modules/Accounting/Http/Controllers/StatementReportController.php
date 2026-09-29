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
use App\Modules\Accounting\Models\Invoice;
use App\Services\CompanyLetterhead;
use App\Constants\Permissions;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            // For "Print invoice" on a customer statement: the period's invoices, one row per line.
            'invoiceRows' => $kind === 'customer' && $resolvedId ? $this->invoiceRows($company->id, $resolvedId, $from, $to) : [],
            'billTo' => $kind === 'customer' && $resolvedId ? $this->billTo($resolvedId) : null,
            'letterhead' => $kind === 'customer' ? app(CompanyLetterhead::class)->forCompany($company) : null,
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

    /**
     * The "Print invoice" document as a PDF file. The lines, references and custom columns come
     * from the form; the rows themselves are re-read here, so only this customer's invoices print.
     */
    public function invoicePdf(Request $request)
    {
        abort_unless($request->user()?->hasCompanyPermission(Permissions::REPORT_VIEW), 403);
        $company = CompanyContext::getCompany();
        $data = json_decode((string) $request->input('payload'), true) ?: [];
        $customer = Customer::where('company_id', $company->id)->findOrFail($data['customer_id'] ?? null);
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($data['from'] ?? '')) ? $data['from'] : now()->startOfMonth()->toDateString();
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($data['to'] ?? '')) ? $data['to'] : now()->toDateString();

        $keys = array_flip(array_map('strval', (array) ($data['keys'] ?? [])));
        $references = (array) ($data['references'] ?? []);
        $rows = array_values(array_filter($this->invoiceRows($company->id, $customer->id, $from, $to), fn ($r) => isset($keys[$r['key']])));
        foreach ($rows as &$row) {
            $row['reference'] = trim((string) ($references[$row['key']] ?? $row['reference'] ?? ''));
        }
        unset($row);

        $columns = collect((array) ($data['columns'] ?? []))
            ->map(fn ($c) => ['label' => mb_substr(trim((string) ($c['label'] ?? '')), 0, 60), 'values' => array_map(fn ($v) => mb_substr((string) $v, 0, 200), (array) ($c['values'] ?? []))])
            ->filter(fn ($c) => $c['label'] !== '' || collect($rows)->contains(fn ($r) => trim($c['values'][$r['key']] ?? '') !== ''))
            ->values()->all();
        $title = mb_substr(trim((string) ($data['title'] ?? '')), 0, 60) ?: 'Invoice';

        $html = view()->file(base_path('modules/Accounting/Resources/views/statement-invoice.blade.php'), [
            'title' => $title,
            'issuer' => app(CompanyLetterhead::class)->forCompany($company),
            'billTo' => $this->billTo($customer->id),
            'from' => $from,
            'to' => $to,
            'today' => now()->toDateString(),
            'rows' => $rows,
            'columns' => $columns,
            'showReference' => collect($rows)->contains(fn ($r) => $r['reference'] !== ''),
            'showQuantity' => collect($rows)->contains(fn ($r) => $r['quantity'] !== null),
            'total' => round(array_sum(array_column($rows, 'amount')), 2),
            'currency' => $company->base_currency ?: 'PKR',
        ])->render();

        $name = Str::slug("{$title} {$customer->name} {$from} {$to}").'.pdf';

        // Only the characters used are embedded: the whole font made a one-page file ~900 KB.
        return Pdf::loadHTML($html)->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->download($name);
    }

    /**
     * The customer's invoices dated in the range, one row per line, for the pick-and-print
     * invoice on the statement page. Line totals are already net of any discount.
     *
     * @return array<int,array<string,mixed>>
     */
    private function invoiceRows(string $companyId, string $customerId, string $from, string $to): array
    {
        $rows = [];
        $invoices = Invoice::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereNotIn('status', ['draft', 'void', 'cancelled'])
            ->whereBetween('invoice_date', [$from, $to])
            ->with('lineItems')
            ->orderBy('invoice_date')
            ->orderBy('invoice_number')
            ->get();

        foreach ($invoices as $invoice) {
            $date = $invoice->invoice_date?->toDateString();
            // A daily close's credit sale keeps the slip number at the end of its notes.
            $reference = preg_match('/^Credit portion of meter sales for [0-9-]+\.\s*(.+)$/', (string) $invoice->notes, $m) ? trim($m[1]) : null;
            $base = [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'date' => $date,
                'reference' => $reference,
                'paid' => (float) $invoice->balance <= 0.005,
                'balance' => round((float) $invoice->balance, 2),
            ];
            foreach ($invoice->lineItems->sortBy('line_number') as $line) {
                $rows[] = $base + [
                    'key' => $line->id,
                    'description' => (string) $line->description,
                    'quantity' => $line->quantity !== null ? round((float) $line->quantity, 2) : null,
                    'rate' => $line->unit_price !== null ? round((float) $line->unit_price, 2) : null,
                    'amount' => round((float) $line->total, 2),
                ];
            }
        }

        return $rows;
    }

    private function billTo(string $customerId): ?array
    {
        $customer = Customer::find($customerId);
        if (! $customer) {
            return null;
        }
        $address = is_array($customer->billing_address) ? $customer->billing_address : [];

        return [
            'name' => $customer->name,
            'lines' => array_values(array_filter([
                $address['line1'] ?? $address['street'] ?? null,
                $address['line2'] ?? null,
                trim(($address['city'] ?? '').' '.($address['postal_code'] ?? '')) ?: null,
            ])),
            'phone' => $customer->phone,
            'email' => $customer->email,
        ];
    }
}
