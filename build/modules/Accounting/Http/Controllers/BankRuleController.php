<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreBankRuleRequest;
use App\Modules\Accounting\Http\Requests\UpdateBankRuleRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\BankRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BankRuleController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $query = BankRule::where('company_id', $company->id)
            ->with(['bankAccount:id,account_name,account_number'])
            ->byPriority();

        if ($request->has('bank_account_id') && $request->bank_account_id) {
            $query->where(function ($q) use ($request) {
                $q->where('bank_account_id', $request->bank_account_id)
                    ->orWhereNull('bank_account_id');
            });
        }

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        $rules = $query->paginate(25)->withQueryString();

        $bankAccounts = BankAccount::where('company_id', $company->id)
            ->active()
            ->orderBy('account_name')
            ->get(['id', 'account_name', 'account_number']);

        return Inertia::render('accounting/bank-rules/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'rules' => $rules,
            'bankAccounts' => $bankAccounts,
            'filters' => [
                'bank_account_id' => $request->bank_account_id ?? '',
                'include_inactive' => $request->boolean('include_inactive'),
            ],
        ]);
    }

    public function create(): Response
    {
        $company = CompanyContext::getCompany();

        $bankAccounts = BankAccount::where('company_id', $company->id)
            ->active()
            ->orderBy('account_name')
            ->get(['id', 'account_name', 'account_number', 'currency']);

        $glAccounts = Account::where('company_id', $company->id)
            ->where('is_active', true)
            ->whereIn('type', ['expense', 'revenue', 'asset', 'liability'])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'subtype']);

        // Get max priority for this company
        $maxPriority = BankRule::where('company_id', $company->id)->max('priority') ?? 0;

        return Inertia::render('accounting/bank-rules/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'bankAccounts' => $bankAccounts,
            'glAccounts' => $glAccounts,
            'nextPriority' => $maxPriority + 10,
            'conditionFields' => $this->getConditionFields(),
            'conditionOperators' => $this->getConditionOperators(),
            'actionTypes' => $this->getActionTypes(),
        ]);
    }

    public function store(StoreBankRuleRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $rule = BankRule::create([
            'company_id' => $company->id,
            'created_by_user_id' => Auth::id(),
            ...$request->validated(),
        ]);

        return redirect()
            ->route('banking.rules.show', ['company' => $company->slug, 'rule' => $rule->id])
            ->with('success', 'Bank rule created successfully.');
    }

    public function show(Request $request, string $company, string $rule): Response
    {
        $companyModel = CompanyContext::getCompany();

        $bankRule = BankRule::where('company_id', $companyModel->id)
            ->with(['bankAccount:id,account_name,account_number', 'createdByUser:id,name'])
            ->findOrFail($rule);

        // Get GL account names for display
        $glAccountIds = [];
        foreach ($bankRule->actions as $action => $value) {
            if ($action === 'set_gl_account_id' && $value) {
                $glAccountIds[] = $value;
            }
        }

        $glAccounts = [];
        if (! empty($glAccountIds)) {
            $glAccounts = Account::whereIn('id', $glAccountIds)
                ->get(['id', 'code', 'name'])
                ->keyBy('id')
                ->toArray();
        }

        return Inertia::render('accounting/bank-rules/Show', [
            'company' => [
                'id' => $companyModel->id,
                'name' => $companyModel->name,
                'slug' => $companyModel->slug,
                'base_currency' => $companyModel->base_currency,
            ],
            'rule' => $bankRule,
            'glAccounts' => $glAccounts,
            'conditionFields' => $this->getConditionFields(),
            'conditionOperators' => $this->getConditionOperators(),
            'actionTypes' => $this->getActionTypes(),
            'canEdit' => $request->user()->hasPermissionTo(Permissions::BANK_RULE_UPDATE),
            'canDelete' => $request->user()->hasPermissionTo(Permissions::BANK_RULE_DELETE),
        ]);
    }

    public function edit(Request $request, string $company, string $rule): Response
    {
        $companyModel = CompanyContext::getCompany();

        $bankRule = BankRule::where('company_id', $companyModel->id)
            ->findOrFail($rule);

        $bankAccounts = BankAccount::where('company_id', $companyModel->id)
            ->active()
            ->orderBy('account_name')
            ->get(['id', 'account_name', 'account_number', 'currency']);

        $glAccounts = Account::where('company_id', $companyModel->id)
            ->where('is_active', true)
            ->whereIn('type', ['expense', 'revenue', 'asset', 'liability'])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'subtype']);

        return Inertia::render('accounting/bank-rules/Edit', [
            'company' => [
                'id' => $companyModel->id,
                'name' => $companyModel->name,
                'slug' => $companyModel->slug,
                'base_currency' => $companyModel->base_currency,
            ],
            'rule' => $bankRule,
            'bankAccounts' => $bankAccounts,
            'glAccounts' => $glAccounts,
            'conditionFields' => $this->getConditionFields(),
            'conditionOperators' => $this->getConditionOperators(),
            'actionTypes' => $this->getActionTypes(),
        ]);
    }

    public function update(UpdateBankRuleRequest $request, string $company, string $rule): RedirectResponse
    {
        $companyModel = CompanyContext::getCompany();

        $bankRule = BankRule::where('company_id', $companyModel->id)
            ->findOrFail($rule);

        $bankRule->update($request->validated());

        return redirect()
            ->route('banking.rules.show', ['company' => $companyModel->slug, 'rule' => $bankRule->id])
            ->with('success', 'Bank rule updated successfully.');
    }

    public function destroy(Request $request, string $company, string $rule): RedirectResponse
    {
        $companyModel = CompanyContext::getCompany();

        $bankRule = BankRule::where('company_id', $companyModel->id)
            ->findOrFail($rule);

        $bankRule->delete();

        return redirect()
            ->route('banking.rules.index', ['company' => $companyModel->slug])
            ->with('success', 'Bank rule deleted successfully.');
    }

    /**
     * Get available condition fields.
     */
    private function getConditionFields(): array
    {
        return [
            ['value' => 'description', 'label' => 'Description'],
            ['value' => 'payee_name', 'label' => 'Payee Name'],
            ['value' => 'amount', 'label' => 'Amount'],
            ['value' => 'reference_number', 'label' => 'Reference Number'],
            ['value' => 'transaction_type', 'label' => 'Transaction Type'],
        ];
    }

    /**
     * Get available condition operators.
     */
    private function getConditionOperators(): array
    {
        return [
            ['value' => 'contains', 'label' => 'Contains', 'types' => ['text']],
            ['value' => 'equals', 'label' => 'Equals', 'types' => ['text', 'number']],
            ['value' => 'starts_with', 'label' => 'Starts with', 'types' => ['text']],
            ['value' => 'ends_with', 'label' => 'Ends with', 'types' => ['text']],
            ['value' => 'gt', 'label' => 'Greater than', 'types' => ['number']],
            ['value' => 'lt', 'label' => 'Less than', 'types' => ['number']],
        ];
    }

    /**
     * Get available action types.
     */
    private function getActionTypes(): array
    {
        return [
            ['value' => 'set_category', 'label' => 'Set Category', 'inputType' => 'text'],
            ['value' => 'set_payee', 'label' => 'Set Payee Name', 'inputType' => 'text'],
            ['value' => 'set_gl_account_id', 'label' => 'Set GL Account', 'inputType' => 'select'],
            ['value' => 'set_transaction_type', 'label' => 'Set Transaction Type', 'inputType' => 'select'],
        ];
    }
}
