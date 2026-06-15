<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreBillPaymentRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\DefaultAccountProvisioner;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Stringable;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BillPaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();

        $query = \App\Modules\Accounting\Models\BillPayment::query()
            ->with('vendor:id,name')
            ->where('company_id', $company->id)
            ->orderByDesc('payment_date');

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->string('vendor_id'));
        }
        if ($request->filled('from_date')) {
            $query->where('payment_date', '>=', $request->string('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->where('payment_date', '<=', $request->string('to_date'));
        }

        $rawPayments = $query->get();
        $groupedPayments = $rawPayments
            ->groupBy(fn ($payment) => $payment->payment_group_id ?: $payment->id)
            ->map(function ($rows) {
                $first = $rows->sortBy('payment_number')->first();
                $amount = round((float) $rows->sum('amount'), 6);

                return [
                    'id' => $first->id,
                    'payment_group_id' => $first->payment_group_id ?: $first->id,
                    'payment_group_number' => $first->payment_group_number ?: $first->payment_number,
                    'payment_number' => $first->payment_group_number ?: $first->payment_number,
                    'vendor' => $first->vendor,
                    'payment_date' => $first->payment_date,
                    'amount' => $amount,
                    'currency' => $first->currency,
                    'payment_method' => $rows->count() > 1 ? 'split' : $first->payment_method,
                    'reference_number' => $first->reference_number,
                    'source_count' => $rows->count(),
                ];
            })
            ->sortByDesc('payment_date')
            ->values();

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = 25;
        $payments = new \Illuminate\Pagination\LengthAwarePaginator(
            $groupedPayments->forPage($page, $perPage)->values(),
            $groupedPayments->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
        $vendors = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('accounting/bill-payments/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'payments' => $payments,
            'vendors' => $vendors,
            'filters' => $request->only(['vendor_id', 'from_date', 'to_date']),
        ]);
    }

    public function create(Request $request): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        app(DefaultAccountProvisioner::class)->ensureCoreDefaults($company);
        $vendors = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $bankAccounts = Account::where('company_id', $company->id)
            ->whereIn('subtype', ['bank', 'cash', 'credit_card'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'subtype', 'normal_balance']);

        $accountBalances = $this->ledgerBalances($company->id, $bankAccounts->pluck('id')->all());
        $bankAccounts = $bankAccounts->map(function (Account $account) use ($accountBalances) {
            $debit = (float) ($accountBalances[$account->id]->debit ?? 0);
            $credit = (float) ($accountBalances[$account->id]->credit ?? 0);
            $balance = $account->normal_balance === 'credit'
                ? $credit - $debit
                : $debit - $credit;

            return [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'subtype' => $account->subtype,
                'normal_balance' => $account->normal_balance,
                'estimated_balance' => round($balance, 2),
            ];
        })->values();

        $apAccounts = Account::where('company_id', $company->id)
            ->where('subtype', 'accounts_payable')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        // Check if coming from a specific bill
        $billId = $request->string('bill_id');
        $selectedBill = null;
        $selectedVendorId = null;

        if ($billId instanceof Stringable && $billId->isNotEmpty()) {
            $selectedBill = \App\Modules\Accounting\Models\Bill::where('company_id', $company->id)
                ->with('vendor:id,name')
                ->findOrFail($billId->toString());
            $selectedVendorId = $selectedBill->vendor_id;
        } else {
            $vendorId = $request->string('vendor_id');
            $selectedVendorId = $vendorId instanceof Stringable && $vendorId->isNotEmpty()
                ? $vendorId->toString()
                : null;
        }

        $unpaidBills = collect();
        if ($selectedVendorId !== null) {
            $unpaidBills = \App\Modules\Accounting\Models\Bill::where('company_id', $company->id)
                ->where('vendor_id', $selectedVendorId)
                ->whereNotIn('status', ['paid', 'void', 'cancelled'])
                ->orderByDesc('bill_date')
                ->get(['id', 'bill_number', 'balance', 'currency', 'due_date']);
        }

        return Inertia::render('accounting/bill-payments/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'defaults' => [
                'ap_account_id' => $company->ap_account_id,
            ],
            'vendors' => $vendors,
            'bankAccounts' => $bankAccounts,
            'apAccounts' => $apAccounts,
            'unpaidBills' => $unpaidBills,
            'selectedBill' => $selectedBill,
            'filters' => [
                'vendor_id' => $selectedVendorId,
                'bill_id' => $billId instanceof Stringable && $billId->isNotEmpty() ? $billId->toString() : null,
            ],
        ]);
    }

    public function store(StoreBillPaymentRequest $request): RedirectResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();

        try {
            $result = app(CommandBus::class)->dispatch('bill_payment.create', [
                ...$request->validated(),
                'company_id' => $company->id,
            ], $request->user());

            return redirect()->route('bill-payments.index', ['company' => $company->slug])
                ->with('success', 'Bill payment recorded');
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    private function ledgerBalances(string $companyId, array $accountIds): \Illuminate\Support\Collection
    {
        if (empty($accountIds)) {
            return collect();
        }

        return DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->where('je.company_id', $companyId)
            ->where('t.company_id', $companyId)
            ->where('t.status', 'posted')
            ->whereIn('je.account_id', $accountIds)
            ->groupBy('je.account_id')
            ->selectRaw('je.account_id, COALESCE(SUM(je.debit_amount), 0) as debit, COALESCE(SUM(je.credit_amount), 0) as credit')
            ->get()
            ->keyBy('account_id');
    }

    public function show(string $company, string $payment): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $record = \App\Modules\Accounting\Models\BillPayment::with(['vendor', 'allocations.bill', 'paymentAccount:id,code,name'])
            ->where('company_id', $company->id)
            ->findOrFail($payment);

        $groupId = $record->payment_group_id ?: $record->id;
        $groupPayments = \App\Modules\Accounting\Models\BillPayment::with(['paymentAccount:id,code,name', 'allocations.bill'])
            ->where('company_id', $company->id)
            ->where(function ($query) use ($groupId, $record) {
                $query->where('payment_group_id', $groupId)
                    ->orWhere('id', $record->id);
            })
            ->orderBy('payment_number')
            ->get();

        $journalTransactionId = Transaction::where('company_id', $company->id)
            ->where('reference_type', 'acct.bill_payments')
            ->where('reference_id', $record->id)
            ->orderByDesc('posting_date')
            ->value('id');

        return Inertia::render('accounting/bill-payments/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'payment' => $record,
            'groupPayments' => $groupPayments,
            'journalTransactionId' => $journalTransactionId,
        ]);
    }

    public function destroy(Request $request, string $company, string $payment): RedirectResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();
        try {
            app(CommandBus::class)->dispatch('bill_payment.void', [
                'id' => $payment,
                'company_id' => $company->id,
            ], $request->user());

            return back()->with('success', 'Bill payment voided');
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }
}
