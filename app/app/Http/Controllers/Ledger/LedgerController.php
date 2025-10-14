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

        return Inertia::render('Ledger/LedgerIndex', [
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

        return Inertia::render('Ledger/LedgerCreate', [
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

        $user = Auth::user();

        // Super admin can view any entry
        if ($user->isSuperAdmin()) {
            $entry = JournalEntry::query()
                ->where('id', $id)
                ->with(['journalLines.ledgerAccount', 'createdBy', 'postedBy'])
                ->firstOrFail();
        } else {
            $company = $user->currentCompany;
            $entry = JournalEntry::query()
                ->where('company_id', $company->id)
                ->where('id', $id)
                ->with(['journalLines.ledgerAccount', 'createdBy', 'postedBy'])
                ->firstOrFail();
        }

        return Inertia::render('Ledger/LedgerShow', [
            'entry' => $entry,
        ]);
    }

    public function edit($id)
    {
        $this->authorize('ledger.entries.update');

        $user = Auth::user();

        // Super admin can edit any entry
        if ($user->isSuperAdmin()) {
            $entry = JournalEntry::query()
                ->where('id', $id)
                ->with(['journalLines.ledgerAccount', 'createdBy', 'postedBy'])
                ->firstOrFail();
        } else {
            $company = $user->currentCompany;
            $entry = JournalEntry::query()
                ->where('company_id', $company->id)
                ->where('id', $id)
                ->with(['journalLines.ledgerAccount', 'createdBy', 'postedBy'])
                ->firstOrFail();
        }

        // Check if entry is posted
        if ($entry->status === 'posted') {
            abort(403, 'Cannot edit posted entries');
        }

        // Get accounts for the entry's company
        $entryCompanyId = $entry->company_id;
        $accounts = LedgerAccount::query()
            ->where('company_id', $entryCompanyId)
            ->where('active', true)
            ->orderBy('code')
            ->get();

        return Inertia::render('Ledger/LedgerEdit', [
            'entry' => $entry,
            'accounts' => $accounts,
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->authorize('ledger.entries.update');

        $user = Auth::user();

        // Super admin can update any entry
        if ($user->isSuperAdmin()) {
            $entry = JournalEntry::query()
                ->where('id', $id)
                ->firstOrFail();
        } else {
            $company = $user->currentCompany;
            $entry = JournalEntry::query()
                ->where('company_id', $company->id)
                ->where('id', $id)
                ->firstOrFail();
        }

        // Check if entry is posted - check before validation
        if ($entry->status === 'posted') {
            abort(403, 'Cannot update posted entries');
        }

        // Now validate
        $validated = $request->validate([
            'description' => 'required|string',
            'date' => 'required|date',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|uuid|exists:acct.ledger_accounts,id',
            'lines.*.debit_amount' => 'required|numeric|min:0',
            'lines.*.credit_amount' => 'required|numeric|min:0',
        ]);

        $entry = $this->ledgerService->updateJournalEntry($entry, $validated);

        return redirect()->route('ledger.show', $entry->id)
            ->with('success', 'Journal entry updated successfully.');
    }

    public function destroy($id)
    {
        $this->authorize('ledger.entries.delete');

        $user = Auth::user();

        // Super admin can delete any entry
        if ($user->isSuperAdmin()) {
            $entry = JournalEntry::query()
                ->where('id', $id)
                ->firstOrFail();
        } else {
            $company = $user->currentCompany;
            $entry = JournalEntry::query()
                ->where('company_id', $company->id)
                ->where('id', $id)
                ->firstOrFail();
        }

        // Check if entry is posted
        if ($entry->status === 'posted') {
            abort(403, 'Cannot delete posted entries');
        }

        $this->ledgerService->deleteJournalEntry($entry);

        return redirect()->route('ledger.index')
            ->with('success', 'Journal entry deleted successfully.');
    }

    public function post(Request $request, $id)
    {
        $this->authorize('ledger.post');

        $user = Auth::user();

        // Super admin can post any entry
        if ($user->isSuperAdmin()) {
            $entry = JournalEntry::query()
                ->where('id', $id)
                ->firstOrFail();
        } else {
            $company = $user->currentCompany;
            $entry = JournalEntry::query()
                ->where('company_id', $company->id)
                ->where('id', $id)
                ->firstOrFail();
        }

        $entry = $this->ledgerService->postJournalEntry($entry);

        return back()->with('success', 'Journal entry posted successfully');
    }

    public function void(Request $request, $id)
    {
        $this->authorize('ledger.void');

        $validated = $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        $user = Auth::user();

        // Super admin can void any entry
        if ($user->isSuperAdmin()) {
            $entry = JournalEntry::query()
                ->where('id', $id)
                ->firstOrFail();
        } else {
            $company = $user->currentCompany;
            $entry = JournalEntry::query()
                ->where('company_id', $company->id)
                ->where('id', $id)
                ->firstOrFail();
        }

        // Check if entry is not posted
        if ($entry->status !== 'posted') {
            abort(403, 'Cannot void unposted entries');
        }

        $entry = $this->ledgerService->voidJournalEntry($entry, $validated['reason']);

        return back()->with('success', 'Journal entry voided successfully');
    }
}
