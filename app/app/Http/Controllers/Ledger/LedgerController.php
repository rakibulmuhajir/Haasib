<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\StoreJournalEntryRequest;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LedgerController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledgerService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('ledger.view');

        $company = $request->user()->currentCompany;

        $entries = JournalEntry::query()
            ->where('company_id', $company->id)
            ->with(['journalLines.ledgerAccount', 'createdBy', 'postedBy'])
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->date_from, fn ($q, $date) => $q->where('date', '>=', $date))
            ->when($request->date_to, fn ($q, $date) => $q->where('date', '<=', $date))
            ->latest('date')
            ->latest()
            ->paginate(20);

        return Inertia::render('Ledger/Index', [
            'entries' => $entries,
            'filters' => $request->only(['status', 'date_from', 'date_to']),
        ]);
    }

    public function create()
    {
        $this->authorize('ledger.create');

        $company = Auth::user()->currentCompany;

        $accounts = LedgerAccount::query()
            ->where('company_id', $company->id)
            ->where('active', true)
            ->orderBy('code')
            ->get();

        return Inertia::render('Ledger/Create', [
            'accounts' => $accounts,
        ]);
    }

    public function store(StoreJournalEntryRequest $request, LedgerService $ledgerService)
    {
        $validated = $request->validated();

        $journalEntry = $ledgerService->createJournalEntry(
            company: $request->user()->currentCompany,
            description: $validated['description'],
            lines: $validated['lines'],
            reference: $validated['reference'] ?? null,
            date: $validated['date']
        );

        // If the user wants to post it immediately
        if ($request->input('post_now')) {
            $ledgerService->postJournalEntry($journalEntry);
        }

        return redirect()->route('ledger.show', $journalEntry->id)
            ->with('success', 'Journal entry created successfully.');
    }

    public function show($id)
    {
        $this->authorize('ledger.view');

        $company = Auth::user()->currentCompany;

        $entry = JournalEntry::query()
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->with(['journalLines.ledgerAccount', 'createdBy', 'postedBy'])
            ->firstOrFail();

        return Inertia::render('Ledger/Show', [
            'entry' => $entry,
        ]);
    }

    public function post(Request $request, $id)
    {
        $this->authorize('ledger.post');

        $company = Auth::user()->currentCompany;

        $entry = JournalEntry::query()
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        $entry = $this->ledgerService->postJournalEntry($entry);

        return back()->with('success', 'Journal entry posted successfully');
    }

    public function void(Request $request, $id)
    {
        $this->authorize('ledger.void');

        $validated = $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        $company = Auth::user()->currentCompany;

        $entry = JournalEntry::query()
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        $entry = $this->ledgerService->voidJournalEntry($entry, $validated['reason']);

        return back()->with('success', 'Journal entry voided successfully');
    }
}
