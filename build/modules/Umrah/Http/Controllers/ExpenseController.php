<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Umrah\Http\Requests\ReverseExpenseRequest;
use App\Modules\Umrah\Http\Requests\StoreExpenseRequest;
use App\Modules\Umrah\Models\Expense;
use App\Modules\Umrah\Services\TravelExpenseService;
use App\Services\CompanyCurrencyOptions;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ExpenseController extends Controller
{
    public function __construct(private readonly TravelExpenseService $service) {}

    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        $this->authorizeCompanyStaff($request, Permissions::UMRAH_EXPENSE_VIEW, $company->id);

        $query = Expense::query()
            ->where('company_id', $company->id)
            ->with(['expenseAccount:id,code,name', 'paymentAccount:id,code,name', 'transaction:id,transaction_number'])
            ->when($request->filled('status'), fn ($expenseQuery) => $expenseQuery->where('status', $request->string('status')))
            ->when($request->filled('from'), fn ($expenseQuery) => $expenseQuery->whereDate('expense_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($expenseQuery) => $expenseQuery->whereDate('expense_date', '<=', $request->date('to')))
            ->when($request->filled('search'), function ($expenseQuery) use ($request) {
                $term = $request->string('search')->toString();
                $expenseQuery->where(function ($searchQuery) use ($term) {
                    $searchQuery->where('expense_number', 'ilike', "%{$term}%")
                        ->orWhere('payee', 'ilike', "%{$term}%")
                        ->orWhere('description', 'ilike', "%{$term}%")
                        ->orWhere('reference', 'ilike', "%{$term}%")
                        ->orWhereHas('expenseAccount', fn ($accountQuery) => $accountQuery->where('name', 'ilike', "%{$term}%"));
                });
            });

        return Inertia::render('Umrah/Expenses/Index', [
            'company' => ['name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'expenses' => (clone $query)->orderByDesc('expense_date')->orderByDesc('created_at')->paginate(25)->withQueryString(),
            'summary' => [
                'total' => (float) (clone $query)->where('status', Expense::STATUS_POSTED)->sum('base_amount'),
                'count' => (clone $query)->where('status', Expense::STATUS_POSTED)->count(),
            ],
            'filters' => $request->only(['search', 'status', 'from', 'to']),
            'canCreate' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_EXPENSE_CREATE),
            'canReverse' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_EXPENSE_REVERSE),
        ]);
    }

    public function create(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        $this->authorizeCompanyStaff($request, Permissions::UMRAH_EXPENSE_CREATE, $company->id);

        return Inertia::render('Umrah/Expenses/Create', [
            'company' => ['name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'expenseAccounts' => Account::query()
                ->where('company_id', $company->id)
                ->whereIn('type', ['expense', 'other_expense', 'cogs'])
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'paymentAccounts' => Account::query()
                ->where('company_id', $company->id)
                ->whereIn('subtype', ['bank', 'cash', 'credit_card'])
                ->where('is_active', true)
                ->orderByRaw("CASE WHEN subtype = 'cash' THEN 0 WHEN subtype = 'bank' THEN 1 ELSE 2 END")
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'currency']),
            'currencies' => app(CompanyCurrencyOptions::class)->forCompany($company),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        try {
            $this->service->record($company, $request->validated(), $request->user()?->id);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', 'Expense could not be recorded. Check the accounts and accounting period, then try again.');
        }

        return redirect()->route('umrah.expenses.index', ['company' => $company->slug])->with('success', 'Expense recorded successfully.');
    }

    public function reverse(ReverseExpenseRequest $request, string $companySlug, string $expense): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = Expense::query()->where('company_id', $company->id)->findOrFail($expense);

        try {
            $this->service->reverse($record, $request->validated('reason'), $request->user()?->id);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Expense could not be reversed. Check the accounting period and try again.');
        }

        return back()->with('success', 'Expense reversed successfully.');
    }

    private function authorizeCompanyStaff(Request $request, string $permission, string $companyId): void
    {
        abort_unless($request->user()?->hasCompanyPermission($permission), 403);
        abort_if(DB::table('auth.company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $request->user()?->id)
            ->where('is_active', true)
            ->value('role') === 'agent', 403, 'Agent logins cannot access company expenses.');
    }
}
