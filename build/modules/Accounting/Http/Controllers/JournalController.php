<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Http\Requests\StoreJournalRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CommandBus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JournalController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $query = Transaction::where('company_id', $company->id)
            ->withCount('journalEntries')
            ->orderByDesc('transaction_date');

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('transaction_type', $request->type);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('transaction_number', 'ilike', "%{$term}%")
                    ->orWhere('description', 'ilike', "%{$term}%");
            });
        }

        $journals = $query->paginate(20)->withQueryString();

        $transactionTypes = Transaction::where('company_id', $company->id)
            ->select('transaction_type')
            ->distinct()
            ->orderBy('transaction_type')
            ->pluck('transaction_type')
            ->values();

        return Inertia::render('accounting/journals/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'journals' => $journals,
            'filters' => [
                'search' => $request->search ?? '',
                'status' => $request->status ?? 'all',
                'type' => $request->type ?? 'all',
            ],
            'transactionTypes' => $transactionTypes,
        ]);
    }

    public function create(): Response
    {
        $company = CompanyContext::getCompany();

        $accounts = Account::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'subtype']);

        return Inertia::render('accounting/journals/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'accounts' => $accounts,
        ]);
    }

    public function store(StoreJournalRequest $request)
    {
        $company = CompanyContext::getCompany();
        $commandBus = app(CommandBus::class);

        $result = $commandBus->dispatch('journal.create', $request->validated(), $request->user());

        return redirect()
            ->route('journals.index', ['company' => $company->slug])
            ->with('success', $result['message']);
    }

    public function show(Request $request): Response
    {
        $company = CompanyContext::getCompany();
        $journalId = $request->route('journal');

        $transaction = Transaction::where('company_id', $company->id)
            ->with(['journalEntries.account'])
            ->findOrFail($journalId);

        return Inertia::render('accounting/journals/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'journal' => $transaction,
        ]);
    }
}
