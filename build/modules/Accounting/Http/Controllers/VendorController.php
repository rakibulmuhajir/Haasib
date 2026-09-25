<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreVendorRequest;
use App\Modules\Accounting\Http\Requests\UpdateVendorRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Vendor;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyCurrencyOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();

        $query = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->orderBy('name');

        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(function ($q) use ($term) {
                $q->where('vendor_number', 'ilike', "%{$term}%")
                    ->orWhere('name', 'ilike', "%{$term}%")
                    ->orWhere('email', 'ilike', "%{$term}%");
            });
        }

        if (! ($request->boolean('include_inactive') ?? false)) {
            $query->where('is_active', true);
        }

        $vendors = $query->paginate(25)->withQueryString();

        return Inertia::render('accounting/vendors/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'vendors' => $vendors,
            'vendorTypes' => Vendor::TYPES,
            'filters' => $request->only(['search', 'include_inactive']),
        ]);
    }

    public function create(): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $apAccounts = Account::where('company_id', $company->id)
            ->where('subtype', 'accounts_payable')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('accounting/vendors/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'apAccounts' => $apAccounts,
            'vendorTypes' => Vendor::TYPES,
        ]);
    }

    public function store(StoreVendorRequest $request): RedirectResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $result = app(CommandBus::class)->dispatch('vendor.create', [
            ...$request->validated(),
            'company_id' => $company->id,
        ], $request->user());

        $vendorId = $result['data']['id'] ?? null;
        if ($vendorId) {
            return redirect("/{$company->slug}/vendors/{$vendorId}")->with('success', 'Vendor created');
        }

        return redirect("/{$company->slug}/vendors")->with('success', 'Vendor created');
    }

    public function update(UpdateVendorRequest $request, string $vendor): RedirectResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();
        app(CommandBus::class)->dispatch('vendor.update', [
            ...$request->validated(),
            'id' => $vendor,
            'company_id' => $company->id,
        ], $request->user());

        return back()->with('success', 'Vendor updated');
    }

    public function show(Request $request): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $vendorId = $request->route('vendor');
        $record = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->withCount('bills')
            ->findOrFail($vendorId);

        $unpaid = \App\Modules\Accounting\Models\Bill::where('company_id', $company->id)
            ->where('vendor_id', $record->id)
            ->whereNotIn('status', ['paid', 'void', 'cancelled'])
            ->sum('balance');

        $overdue = \App\Modules\Accounting\Models\Bill::where('company_id', $company->id)
            ->where('vendor_id', $record->id)
            ->whereNotIn('status', ['paid', 'void', 'cancelled'])
            ->where('due_date', '<', now()->toDateString())
            ->sum('balance');

        $paidYtd = \App\Modules\Accounting\Models\BillPayment::where('company_id', $company->id)
            ->where('vendor_id', $record->id)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');

        $advanceOnAccount = app(\App\Modules\Accounting\Services\VendorAdvanceService::class)
            ->totalUnapplied($company->id, $record->id);

        $bills = \App\Modules\Accounting\Models\Bill::where('company_id', $company->id)
            ->where('vendor_id', $record->id)
            ->orderByDesc('bill_date')
            ->take(25)
            ->get(['id', 'bill_number', 'bill_date', 'due_date', 'total_amount', 'balance', 'currency', 'status']);

        $payments = \App\Modules\Accounting\Models\BillPayment::where('company_id', $company->id)
            ->where('vendor_id', $record->id)
            ->orderByDesc('payment_date')
            ->take(25)
            ->get(['id', 'payment_number', 'payment_date', 'amount', 'currency', 'payment_method', 'reference_number']);

        // The running ledger, the payables mirror of the buyer statement. Built from the
        // canonical AP records rather than the two lists above, which show recent activity
        // but never a balance.
        $statement = app(\App\Modules\Accounting\Services\VendorStatementService::class)->statement($record);

        $currencies = app(CompanyCurrencyOptions::class)->forCompany($company);

        return Inertia::render('accounting/vendors/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'vendor' => $record,
            'summary' => [
                'open_balance' => $unpaid,
                'overdue_balance' => $overdue,
                'bill_count' => $record->bills_count,
                'paid_ytd' => $paidYtd,
                'advance_on_account' => round($advanceOnAccount, 2),
            ],
            'bills' => $bills,
            'payments' => $payments,
            'statement' => $statement['rows'],
            'statementClosingBalance' => $statement['closing_balance'],
            'currencies' => $currencies,
            'canEdit' => true,
        ]);
    }

    public function edit(Request $request): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $vendorId = $request->route('vendor');
        $record = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)->findOrFail($vendorId);

        $apAccounts = Account::where('company_id', $company->id)
            ->where('subtype', 'accounts_payable')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('accounting/vendors/Edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'vendor' => $record,
            'apAccounts' => $apAccounts,
            'vendorTypes' => Vendor::TYPES,
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $vendorId = $request->route('vendor');
        app(CommandBus::class)->dispatch('vendor.delete', [
            'id' => $vendorId,
            'company_id' => $company->id,
        ], $request->user());

        return back()->with('success', 'Vendor deleted');
    }

    /**
     * Search vendors (JSON API for EntitySearch component)
     */
    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $query = $request->get('q', '');
        $limit = min((int) $request->get('limit', 10), 50);

        if (strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $vendors = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'ilike', "%{$query}%")
                    ->orWhere('email', 'ilike', "%{$query}%")
                    ->orWhere('vendor_number', 'ilike', "%{$query}%")
                    ->orWhere('phone', 'ilike', "%{$query}%");
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email', 'phone', 'vendor_number', 'vendor_type']);

        return response()->json(['results' => $vendors]);
    }

    /**
     * Get recent vendors (JSON API for EntitySearch component)
     */
    public function recent(Request $request): \Illuminate\Http\JsonResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $limit = min((int) $request->get('limit', 5), 20);

        // Get vendors from recent bills
        $recentVendorIds = \App\Modules\Accounting\Models\Bill::where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->pluck('vendor_id')
            ->unique()
            ->take($limit);

        $vendors = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->whereIn('id', $recentVendorIds)
            ->where('is_active', true)
            ->get(['id', 'name', 'email', 'phone', 'vendor_number', 'vendor_type']);

        // Sort by the order they appear in recent bills
        $sorted = $recentVendorIds->map(fn ($id) => $vendors->firstWhere('id', $id))
            ->filter()
            ->values();

        return response()->json(['results' => $sorted]);
    }

    /**
     * Quick store vendor with minimal data (for QuickAddModal)
     */
    public function quickStore(Request $request): RedirectResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'vendor_type' => 'nullable|in:'.implode(',', array_keys(Vendor::TYPES)),
            'opening_owed' => 'nullable|numeric|min:0',
            'opening_date' => 'nullable|date',
        ]);

        $owed = (float) ($validated['opening_owed'] ?? 0);
        $openingDate = $validated['opening_date'] ?? null;

        if ($owed > 0 && ! ($user?->hasCompanyPermission(Permissions::OPENING_BALANCE_MANAGE) ?? false)) {
            throw ValidationException::withMessages([
                'opening_owed' => 'You are not allowed to set opening balances.',
            ]);
        }

        $commandBus = app(CommandBus::class);

        $vendor = DB::transaction(function () use ($commandBus, $validated, $company, $user, $owed, $openingDate) {
            $result = $commandBus->dispatch('vendor.create', [
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'vendor_type' => $validated['vendor_type'] ?? Vendor::TYPE_GENERAL,
                'company_id' => $company->id,
                'base_currency' => $company->base_currency,
                'is_active' => true,
            ], $user);

            $vendor = Vendor::find($result['data']['id']);

            if ($owed > 0) {
                try {
                    $commandBus->dispatch('opening_balance.set_party', [
                        'section' => 'suppliers',
                        'party_id' => $vendor->id,
                        'amount' => $owed,
                        'as_of_date' => $openingDate,
                    ], $user);
                } catch (ValidationException $e) {
                    throw $this->remapOpeningError($e, 'opening_owed');
                }
            }

            return $vendor;
        });

        $message = $owed > 0 ? 'Vendor created with opening balance' : 'Vendor created';

        return back()->with([
            'success' => $message,
            'entity' => [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'email' => $vendor->email,
                'vendor_type' => $vendor->vendor_type,
            ],
        ]);
    }

    /**
     * opening_balance.set_party's errors are keyed for its own params (amount, as_of_date,
     * party_id). Quick Add's form has none of those fields — it has opening_owed and
     * opening_date — so a validation failure has to be re-keyed onto the field the user
     * actually sees, or the message lands nowhere on screen.
     */
    private function remapOpeningError(ValidationException $e, string $amountField): ValidationException
    {
        $remapped = [];
        foreach ($e->errors() as $key => $messages) {
            $target = match ($key) {
                'amount' => $amountField,
                'as_of_date' => 'opening_date',
                'amanat' => 'opening_advance',
                default => 'opening_owed',
            };
            $remapped[$target] = array_merge($remapped[$target] ?? [], $messages);
        }

        return ValidationException::withMessages($remapped);
    }

    /**
     * Get vendor's default tax code (JSON API for TaxToggle)
     */
    public function taxDefault(Request $request): \Illuminate\Http\JsonResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $vendorId = $request->route('vendor');

        $vendor = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->findOrFail($vendorId);

        // TODO: Implement vendor-specific tax code lookup
        // For now, return company default tax rate
        $defaultTaxRate = \App\Modules\Accounting\Models\TaxRate::where('company_id', $company->id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first(['id', 'name', 'code', 'rate']);

        return response()->json([
            'tax_code' => $defaultTaxRate ? [
                'id' => $defaultTaxRate->id,
                'name' => $defaultTaxRate->name,
                'code' => $defaultTaxRate->code,
                'rate' => (float) $defaultTaxRate->rate,
            ] : null,
        ]);
    }
}
