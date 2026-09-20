<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreExpenseRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\TransactionAttachment;
use App\Modules\Accounting\Services\TransactionAttachmentService;
use App\Services\CommandBus;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A standalone expense entry point. Today expenses could only be entered inside the Daily
 * Close or as a raw journal entry; this posts the same ordinary journal (transaction_type
 * 'expense') so the close still picks it up automatically for its date.
 */
class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();

        $accountId = $request->query('account_id') ?: null;
        $dateFrom = $request->query('date_from') ?: null;
        $dateTo = $request->query('date_to') ?: null;
        $search = $request->query('search') ?: null;

        $query = Transaction::where('company_id', $company->id)
            ->where('transaction_type', 'expense')
            ->whereIn('status', ['posted', 'locked'])
            ->with(['journalEntries.account', 'attachments']);

        if ($accountId) {
            // Either side of the expense journal (the expense account debited, or the
            // cash/bank account it was paid from) counts as "this account" for filtering.
            $query->whereHas('journalEntries', fn ($q) => $q->where('account_id', $accountId));
        }
        if ($dateFrom) {
            $query->where('transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('transaction_date', '<=', $dateTo);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'ilike', "%{$search}%")
                    ->orWhere('transaction_number', 'ilike', "%{$search}%");
            });
        }

        $expenses = $query->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'date' => $transaction->transaction_date->toDateString(),
                'transaction_number' => $transaction->transaction_number,
                'description' => $transaction->description,
                'amount' => (float) $transaction->total_debit,
                'expense_account' => $transaction->journalEntries->firstWhere('debit_amount', '>', 0)?->account?->name,
                'paid_from' => $transaction->journalEntries->firstWhere('credit_amount', '>', 0)?->account?->name,
                'attachments' => $transaction->attachments->map(fn ($file) => [
                    'id' => $file->id,
                    'name' => $file->original_name,
                ])->values(),
            ]);

        $filterAccounts = Account::where('company_id', $company->id)->where('is_active', true)
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->where('type', 'expense')->orWhereIn('subtype', ['cash', 'bank']))
            ->orderBy('code')->get(['id', 'code', 'name']);

        return Inertia::render('accounting/expenses/Index', [
            'expenses' => $expenses,
            'filterAccounts' => $filterAccounts,
            'filters' => [
                'account_id' => $accountId ?? '',
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
                'search' => $search ?? '',
            ],
            'currency' => $company->base_currency ?? 'PKR',
        ]);
    }

    public function create(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $expenseAccounts = Account::where('company_id', $company->id)->where('is_active', true)
            ->whereNull('deleted_at')->where('type', 'expense')->orderBy('code')->get(['id', 'code', 'name']);
        $paymentAccounts = Account::where('company_id', $company->id)->where('is_active', true)
            ->whereNull('deleted_at')->whereIn('subtype', ['cash', 'bank'])->orderBy('code')->get(['id', 'code', 'name']);

        return Inertia::render('accounting/expenses/Create', [
            'expenseAccounts' => $expenseAccounts,
            'paymentAccounts' => $paymentAccounts,
            'currency' => $company->base_currency ?? 'PKR',
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        // The file is attached here rather than passed through the command bus: the same
        // 'expense.create' action is dispatched from the CLI palette, where there is no
        // upload, and an UploadedFile has no business travelling as a command parameter.
        $validated = $request->validated();
        unset($validated['attachment']);

        try {
            $result = app(CommandBus::class)->dispatch('expense.create', $validated, $request->user());

            if ($request->hasFile('attachment')) {
                $transaction = Transaction::where('company_id', $company->id)
                    ->findOrFail($result['data']['id']);
                app(TransactionAttachmentService::class)
                    ->store($transaction, $request->file('attachment'), $request->user()?->id);
            }
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('expenses.index', ['company' => $company->slug])
            ->with('success', 'Expense recorded.');
    }

    /**
     * Serve an attached document. Private disk, so it only ever reaches the browser
     * through here, after the company scope and the permission have both been checked.
     */
    public function downloadAttachment(Request $request, string $company, string $attachment): StreamedResponse
    {
        $companyModel = app(CurrentCompany::class)->get();

        abort_unless($request->user()?->hasCompanyPermission(Permissions::EXPENSE_VIEW), 403);

        $record = TransactionAttachment::where('company_id', $companyModel->id)
            ->findOrFail($attachment);

        return Storage::disk($record->disk)->download($record->path, $record->original_name);
    }
}
