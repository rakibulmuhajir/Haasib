<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Models\CompanyCurrency;
use App\Modules\Accounting\Http\Requests\StoreBankAccountRequest;
use App\Modules\Accounting\Http\Requests\UpdateBankAccountRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bank;
use App\Modules\Accounting\Models\BankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $query = BankAccount::where('company_id', $company->id)
            ->with(['bank:id,name,swift_code', 'glAccount:id,code,name'])
            ->withCount(['transactions as unreconciled_count' => function ($q) {
                $q->where('is_reconciled', false);
            }]);

        if ($request->has('search') && $request->search) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('account_name', 'ilike', "%{$term}%")
                    ->orWhere('account_number', 'ilike', "%{$term}%")
                    ->orWhere('iban', 'ilike', "%{$term}%");
            });
        }

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        $sortBy = $request->get('sort_by', 'account_name');
        $sortDir = $request->get('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        $bankAccounts = $query->paginate(25)->withQueryString();

        return Inertia::render('accounting/bank-accounts/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'bankAccounts' => $bankAccounts,
            'filters' => [
                'search' => $request->search ?? '',
                'include_inactive' => $request->boolean('include_inactive'),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    public function create(): Response
    {
        $company = CompanyContext::getCompany();

        $banks = Bank::active()->orderBy('name')->get(['id', 'name', 'swift_code', 'country_code']);

        $currencies = CompanyCurrency::where('company_id', $company->id)
            ->orderByDesc('is_base')
            ->orderBy('currency_code')
            ->get(['currency_code', 'is_base']);

        $glAccounts = Account::where('company_id', $company->id)
            ->whereIn('subtype', ['bank', 'cash'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'subtype']);

        return Inertia::render('accounting/bank-accounts/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'banks' => $banks,
            'currencies' => $currencies,
            'glAccounts' => $glAccounts,
            'accountTypes' => $this->getAccountTypes(),
        ]);
    }

    public function store(StoreBankAccountRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $bankAccount = BankAccount::create([
            'company_id' => $company->id,
            'created_by_user_id' => Auth::id(),
            ...$request->validated(),
        ]);

        return redirect()
            ->route('banking.accounts.show', ['company' => $company->slug, 'bankAccount' => $bankAccount->id])
            ->with('success', 'Bank account created successfully.');
    }

    public function show(Request $request, string $company, string $bankAccount): Response
    {
        $companyModel = CompanyContext::getCompany();

        $account = BankAccount::where('company_id', $companyModel->id)
            ->with(['bank:id,name,swift_code', 'glAccount:id,code,name'])
            ->withCount(['transactions as unreconciled_count' => function ($q) {
                $q->where('is_reconciled', false);
            }])
            ->findOrFail($bankAccount);

        $recentTransactions = $account->transactions()
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get([
                'id',
                'transaction_date',
                'description',
                'transaction_type',
                'amount',
                'is_reconciled',
                'payee_name',
                'category',
            ]);

        $lastReconciliation = $account->reconciliations()
            ->completed()
            ->orderByDesc('statement_date')
            ->first(['id', 'statement_date', 'statement_ending_balance', 'completed_at']);

        return Inertia::render('accounting/bank-accounts/Show', [
            'company' => [
                'id' => $companyModel->id,
                'name' => $companyModel->name,
                'slug' => $companyModel->slug,
                'base_currency' => $companyModel->base_currency,
            ],
            'bankAccount' => $account,
            'recentTransactions' => $recentTransactions,
            'lastReconciliation' => $lastReconciliation,
            'canEdit' => $request->user()->hasPermissionTo(Permissions::BANK_ACCOUNT_UPDATE),
            'canDelete' => $request->user()->hasPermissionTo(Permissions::BANK_ACCOUNT_DELETE),
        ]);
    }

    public function edit(Request $request, string $company, string $bankAccount): Response
    {
        $companyModel = CompanyContext::getCompany();

        $account = BankAccount::where('company_id', $companyModel->id)
            ->findOrFail($bankAccount);

        $banks = Bank::active()->orderBy('name')->get(['id', 'name', 'swift_code', 'country_code']);

        $currencies = CompanyCurrency::where('company_id', $companyModel->id)
            ->orderByDesc('is_base')
            ->orderBy('currency_code')
            ->get(['currency_code', 'is_base']);

        $glAccounts = Account::where('company_id', $companyModel->id)
            ->whereIn('subtype', ['bank', 'cash'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'subtype']);

        return Inertia::render('accounting/bank-accounts/Edit', [
            'company' => [
                'id' => $companyModel->id,
                'name' => $companyModel->name,
                'slug' => $companyModel->slug,
                'base_currency' => $companyModel->base_currency,
            ],
            'bankAccount' => $account,
            'banks' => $banks,
            'currencies' => $currencies,
            'glAccounts' => $glAccounts,
            'accountTypes' => $this->getAccountTypes(),
            'hasTransactions' => $account->hasTransactions(),
        ]);
    }

    public function update(UpdateBankAccountRequest $request, string $company, string $bankAccount): RedirectResponse
    {
        $companyModel = CompanyContext::getCompany();

        $account = BankAccount::where('company_id', $companyModel->id)
            ->findOrFail($bankAccount);

        $account->update([
            'updated_by_user_id' => Auth::id(),
            ...$request->validated(),
        ]);

        return redirect()
            ->route('banking.accounts.show', ['company' => $companyModel->slug, 'bankAccount' => $account->id])
            ->with('success', 'Bank account updated successfully.');
    }

    public function destroy(Request $request, string $company, string $bankAccount): RedirectResponse
    {
        $companyModel = CompanyContext::getCompany();

        $account = BankAccount::where('company_id', $companyModel->id)
            ->findOrFail($bankAccount);

        if ($account->hasUnreconciledTransactions()) {
            return redirect()
                ->back()
                ->with('error', 'Cannot delete bank account with unreconciled transactions.');
        }

        $account->delete();

        return redirect()
            ->route('banking.accounts.index', ['company' => $companyModel->slug])
            ->with('success', 'Bank account deleted successfully.');
    }

    /**
     * Search bank accounts (JSON API for selects)
     */
    public function search(Request $request): JsonResponse
    {
        $company = CompanyContext::getCompany();
        $query = $request->get('q', '');
        $limit = min((int) $request->get('limit', 10), 50);

        $accounts = BankAccount::where('company_id', $company->id)
            ->where('is_active', true)
            ->when($query, function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('account_name', 'ilike', "%{$query}%")
                        ->orWhere('account_number', 'ilike', "%{$query}%");
                });
            })
            ->orderBy('account_name')
            ->limit($limit)
            ->get(['id', 'account_name', 'account_number', 'account_type', 'current_balance', 'currency']);

        return response()->json(['results' => $accounts]);
    }

    private function getAccountTypes(): array
    {
        return [
            ['value' => 'checking', 'label' => 'Checking Account'],
            ['value' => 'savings', 'label' => 'Savings Account'],
            ['value' => 'credit_card', 'label' => 'Credit Card'],
            ['value' => 'cash', 'label' => 'Petty Cash'],
            ['value' => 'other', 'label' => 'Other'],
        ];
    }
}
