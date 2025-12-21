<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreBillPaymentRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Stringable;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BillPaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();

        $query = \App\Modules\Accounting\Models\BillPayment::with('vendor')
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

        $payments = $query->paginate(25)->withQueryString();
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
        $vendors = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $bankAccounts = Account::where('company_id', $company->id)
            ->whereIn('subtype', ['bank', 'cash', 'credit_card'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'subtype']);

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

    public function show(string $payment): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $record = \App\Modules\Accounting\Models\BillPayment::with(['vendor', 'allocations.bill'])
            ->where('company_id', $company->id)
            ->findOrFail($payment);

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
            'journalTransactionId' => $journalTransactionId,
        ]);
    }

    public function destroy(Request $request, string $payment): RedirectResponse
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
