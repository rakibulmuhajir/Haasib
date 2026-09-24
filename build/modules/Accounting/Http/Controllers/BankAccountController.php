<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreBankAccountRequest;
use App\Modules\Accounting\Http\Requests\UpdateBankAccountRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bank;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Services\CompanyBankAccountSyncService;
use App\Services\CommandBus;
use App\Services\CompanyCurrencyOptions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        app(CompanyBankAccountSyncService::class)->archiveSeededBankAccountsIfUserCreatedExist($company->id);

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

        $banks = Bank::active()->forCountry($company->country)->orderBy('name')->get(['id', 'name', 'swift_code', 'country_code']);

        $currencies = app(CompanyCurrencyOptions::class)->forCompany($company);

        $glAccounts = Account::where('company_id', $company->id)
            ->whereIn('subtype', ['bank', 'cash', 'credit_card'])
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
            'openingBalances' => $this->openingBalanceState($company),
        ]);
    }

    public function store(StoreBankAccountRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $validated = $request->validated();
        $validated['gl_account_id'] = $validated['gl_account_id']
            ?? $this->createLedgerAccountFor($company->id, $validated)->id;

        // The opening balance is not this form's to write. It is a ledger position, and the
        // only thing that may post one is opening_balance.save; writing the column here is
        // what used to leave the figure showing on screen with nothing behind it in the
        // ledger. Pulled out of the attributes, then set through the command below.
        $opening = $this->takeOpening($validated);

        $bankAccount = BankAccount::create([
            'company_id' => $company->id,
            'created_by_user_id' => Auth::id(),
            ...$validated,
        ]);

        $error = $this->applyOpening($bankAccount, $opening, $request->user());

        if ($error !== null) {
            return redirect()
                ->route('banking.accounts.show', ['company' => $company->slug, 'bankAccount' => $bankAccount->id])
                ->with('error', 'Bank account created, but the opening balance was not set: '.$error);
        }

        return redirect()
            ->route('banking.accounts.show', ['company' => $company->slug, 'bankAccount' => $bankAccount->id])
            ->with('success', 'Bank account created successfully.');
    }

    /**
     * Whether the opening position has been locked, so the form can render the field
     * read-only rather than letting someone type a figure the save is going to refuse.
     *
     * This is a courtesy, not the enforcement. SaveAction::guardNotLocked and the
     * acct.protect_locked_opening trigger are what actually hold the line, and they still
     * run whatever the page does.
     *
     * @return array{locked: bool, locked_at: ?string}
     */
    private function openingBalanceState($company): array
    {
        $opening = ($company->settings ?? [])['opening_balances'] ?? [];

        return [
            'locked' => ! empty($opening['locked_at']),
            'locked_at' => $opening['locked_at'] ?? null,
        ];
    }

    /**
     * Lift the opening balance out of the attributes so it cannot reach the column, and
     * return what the command needs. Null when the form did not offer one.
     *
     * @return array{amount: float, as_of_date: ?string}|null
     */
    private function takeOpening(array &$validated): ?array
    {
        $amount = $validated['opening_balance'] ?? null;
        $asOf = $validated['opening_balance_date'] ?? null;

        unset($validated['opening_balance'], $validated['opening_balance_date']);

        return $amount === null ? null : [
            'amount' => (float) $amount,
            'as_of_date' => $asOf ?: null,
        ];
    }

    /**
     * Set the opening balance through opening_balance.set_account, which is the single write
     * path for one: it merges into the current opening set and delegates to SaveAction, so
     * the lock, the date bound and the ledger posting all apply exactly as they do on the
     * Opening Balances page.
     *
     * Returns null on success, or the reason it did not happen. The account itself has
     * already been saved by the time this runs, so a refusal here — locked balances, a date
     * after the first posted transaction — must not read as though the whole save failed.
     */
    private function applyOpening(BankAccount $account, ?array $opening, $user): ?string
    {
        if ($opening === null || $account->gl_account_id === null) {
            return null;
        }

        // Nothing to post and nothing to clear: an account created at zero should not
        // reverse and repost the company's whole opening generation for no change.
        if ($opening['amount'] === 0.0 && (float) $account->opening_balance === 0.0) {
            return null;
        }

        try {
            app(CommandBus::class)->dispatch('opening_balance.set_account', [
                'gl_account_id' => $account->gl_account_id,
                'amount' => $opening['amount'],
                'as_of_date' => $opening['as_of_date'],
            ], $user);
        } catch (ValidationException $e) {
            return collect($e->errors())->flatten()->first() ?? 'validation failed.';
        } catch (AuthorizationException $e) {
            return 'you do not have permission to set opening balances.';
        }

        return null;
    }

    /**
     * Every bank record owns its own ledger account, because every posting hits the ledger
     * account and not the record: two records sharing one cannot be told apart, and a
     * transfer between them would debit and credit the same account. Codes come from the
     * 1000-1049 band the chart of accounts reserves for money accounts, matching
     * CompanyOnboardingService::setupBankAccounts.
     */
    private function createLedgerAccountFor(string $companyId, array $validated): Account
    {
        $subtype = match ($validated['account_type']) {
            'cash' => 'cash',
            'credit_card' => 'credit_card',
            default => 'bank',
        };

        $taken = Account::where('company_id', $companyId)->pluck('code')->all();
        $code = null;
        foreach (range(1000, 1049) as $candidate) {
            if (! in_array((string) $candidate, $taken, true)) {
                $code = (string) $candidate;
                break;
            }
        }

        if ($code === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'gl_account_id' => 'No free ledger account code between 1000 and 1049. Choose an existing account or free a code before saving.',
            ]);
        }

        return Account::create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => $validated['account_name'],
            'type' => $subtype === 'credit_card' ? 'liability' : 'asset',
            'subtype' => $subtype,
            'normal_balance' => $subtype === 'credit_card' ? 'credit' : 'debit',
            'currency' => strtoupper($validated['currency']),
            'is_active' => true,
            'is_system' => false,
            'created_by_user_id' => Auth::id(),
        ]);
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

        $banks = Bank::active()->forCountry($companyModel->country, $account->bank_id)->orderBy('name')->get(['id', 'name', 'swift_code', 'country_code']);

        $currencies = app(CompanyCurrencyOptions::class)->forCompany($companyModel);

        $glAccounts = Account::where('company_id', $companyModel->id)
            ->whereIn('subtype', ['bank', 'cash', 'credit_card'])
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
            'openingBalances' => $this->openingBalanceState($companyModel),
        ]);
    }

    public function update(UpdateBankAccountRequest $request, string $company, string $bankAccount): RedirectResponse
    {
        $companyModel = CompanyContext::getCompany();

        $account = BankAccount::where('company_id', $companyModel->id)
            ->findOrFail($bankAccount);

        $opening = null;

        DB::transaction(function () use ($account, $request, $companyModel, &$opening) {
            $validated = $request->validated();
            // See store(): the opening balance goes through the command, never the column.
            $opening = $this->takeOpening($validated);
            // An explicit automatic selection creates a separate ledger; old cash
            // postings remain on their original account.
            if (array_key_exists('gl_account_id', $validated) && $validated['gl_account_id'] === null) {
                $validated['gl_account_id'] = $this->createLedgerAccountFor($companyModel->id, $validated)->id;
            }
            $account->update(['updated_by_user_id' => Auth::id(), ...$validated]);
        });

        // Outside the transaction above on purpose: SaveAction opens its own, takes an
        // exclusive advisory lock as its first statement and retries on deadlock. Nesting it
        // inside another write transaction would hold that lock for the outer transaction's
        // lifetime and defeat the retry.
        $error = $this->applyOpening($account->refresh(), $opening, $request->user());

        if ($error !== null) {
            return redirect()
                ->route('banking.accounts.show', ['company' => $companyModel->slug, 'bankAccount' => $account->id])
                ->with('error', 'Bank account updated, but the opening balance was not set: '.$error);
        }

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
