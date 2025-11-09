<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LedgerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:ledger.view')->only(['index', 'show']);
        $this->middleware('permission:ledger.create')->only(['create', 'store']);
        $this->middleware('permission:ledger.update')->only(['edit', 'update']);
        $this->middleware('permission:ledger.delete')->only(['destroy']);
        $this->middleware('permission:ledger.post')->only(['post']);
        $this->middleware('permission:ledger.void')->only(['void']);
    }

    /**
     * Display a listing of journal entries.
     */
    public function index(Request $request): Response
    {
        $company = $request->user()->currentCompany();
        
        $journalEntries = JournalEntry::where('company_id', $company->id)
            ->with(['createdBy', 'journalLines.account'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Ledger/Index', [
            'journalEntries' => $journalEntries,
        ]);
    }

    /**
     * Show the form for creating a new journal entry.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Ledger/Create', [
            'accounts' => $this->getAccountsForCompany($request->user()->currentCompany()),
        ]);
    }

    /**
     * Store a newly created journal entry.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'reference' => 'nullable|string|max:100',
            'journal_date' => 'required|date',
            'journal_lines' => 'required|array|min:2',
            'journal_lines.*.account_id' => 'required|uuid|exists:acct.accounts,id',
            'journal_lines.*.description' => 'nullable|string|max:255',
            'journal_lines.*.debit_amount' => 'required|numeric|min:0',
            'journal_lines.*.credit_amount' => 'required|numeric|min:0',
        ]);

        // Validate that debits equal credits
        $totalDebits = array_sum(array_column($validated['journal_lines'], 'debit_amount'));
        $totalCredits = array_sum(array_column($validated['journal_lines'], 'credit_amount'));

        if (abs($totalDebits - $totalCredits) > 0.01) {
            return back()->withErrors(['journal_lines' => 'Total debits must equal total credits.']);
        }

        $company = $request->user()->currentCompany();
        
        $journalEntry = JournalEntry::create([
            'company_id' => $company->id,
            'journal_number' => $this->generateJournalNumber($company),
            'description' => $validated['description'],
            'reference' => $validated['reference'] ?? null,
            'journal_date' => $validated['journal_date'],
            'status' => 'draft',
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'created_by_user_id' => $request->user()->id,
        ]);

        // Create journal lines
        foreach ($validated['journal_lines'] as $line) {
            $journalEntry->journalLines()->create([
                'account_id' => $line['account_id'],
                'description' => $line['description'] ?? $validated['description'],
                'debit_amount' => $line['debit_amount'],
                'credit_amount' => $line['credit_amount'],
            ]);
        }

        return redirect()->route('ledger.show', $journalEntry->id)
            ->with('success', 'Journal entry created successfully.');
    }

    /**
     * Display the specified journal entry.
     */
    public function show(Request $request, JournalEntry $journalEntry): Response
    {
        $this->authorize('view', $journalEntry);

        $journalEntry->load(['createdBy', 'postedBy', 'journalLines.account']);

        return Inertia::render('Ledger/Show', [
            'journalEntry' => $journalEntry,
        ]);
    }

    /**
     * Show the form for editing the specified journal entry.
     */
    public function edit(Request $request, JournalEntry $journalEntry): Response
    {
        $this->authorize('update', $journalEntry);

        if ($journalEntry->status !== 'draft') {
            abort(403, 'Only draft journal entries can be edited.');
        }

        $journalEntry->load(['journalLines.account']);

        return Inertia::render('Ledger/Edit', [
            'journalEntry' => $journalEntry,
            'accounts' => $this->getAccountsForCompany($request->user()->currentCompany()),
        ]);
    }

    /**
     * Update the specified journal entry.
     */
    public function update(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('update', $journalEntry);

        if ($journalEntry->status !== 'draft') {
            abort(403, 'Only draft journal entries can be edited.');
        }

        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'reference' => 'nullable|string|max:100',
            'journal_date' => 'required|date',
            'journal_lines' => 'required|array|min:2',
            'journal_lines.*.account_id' => 'required|uuid|exists:acct.accounts,id',
            'journal_lines.*.description' => 'nullable|string|max:255',
            'journal_lines.*.debit_amount' => 'required|numeric|min:0',
            'journal_lines.*.credit_amount' => 'required|numeric|min:0',
        ]);

        // Validate that debits equal credits
        $totalDebits = array_sum(array_column($validated['journal_lines'], 'debit_amount'));
        $totalCredits = array_sum(array_column($validated['journal_lines'], 'credit_amount'));

        if (abs($totalDebits - $totalCredits) > 0.01) {
            return back()->withErrors(['journal_lines' => 'Total debits must equal total credits.']);
        }

        $journalEntry->update([
            'description' => $validated['description'],
            'reference' => $validated['reference'] ?? null,
            'journal_date' => $validated['journal_date'],
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
        ]);

        // Remove existing journal lines and create new ones
        $journalEntry->journalLines()->delete();
        
        foreach ($validated['journal_lines'] as $line) {
            $journalEntry->journalLines()->create([
                'account_id' => $line['account_id'],
                'description' => $line['description'] ?? $validated['description'],
                'debit_amount' => $line['debit_amount'],
                'credit_amount' => $line['credit_amount'],
            ]);
        }

        return redirect()->route('ledger.show', $journalEntry->id)
            ->with('success', 'Journal entry updated successfully.');
    }

    /**
     * Remove the specified journal entry.
     */
    public function destroy(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('delete', $journalEntry);

        if ($journalEntry->status !== 'draft') {
            abort(403, 'Only draft journal entries can be deleted.');
        }

        $journalEntry->delete();

        return redirect()->route('ledger.index')
            ->with('success', 'Journal entry deleted successfully.');
    }

    /**
     * Post the journal entry.
     */
    public function post(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('post', $journalEntry);

        if ($journalEntry->status !== 'draft') {
            abort(403, 'Only draft journal entries can be posted.');
        }

        $journalEntry->update([
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by_user_id' => $request->user()->id,
        ]);

        // TODO: Update account balances

        return redirect()->route('ledger.show', $journalEntry->id)
            ->with('success', 'Journal entry posted successfully.');
    }

    /**
     * Void the journal entry.
     */
    public function void(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('void', $journalEntry);

        if ($journalEntry->status !== 'posted') {
            abort(403, 'Only posted journal entries can be voided.');
        }

        $journalEntry->update([
            'status' => 'void',
            'voided_at' => now(),
            'voided_by_user_id' => $request->user()->id,
        ]);

        // TODO: Create reversing entry and update account balances

        return redirect()->route('ledger.show', $journalEntry->id)
            ->with('success', 'Journal entry voided successfully.');
    }

    /**
     * Generate next journal number for company.
     */
    protected function generateJournalNumber(Company $company): string
    {
        $lastNumber = JournalEntry::where('company_id', $company->id)
            ->where('journal_number', 'LIKE', 'JE-%')
            ->orderByRaw('CAST(SUBSTRING(journal_number, 4) AS INTEGER) DESC')
            ->value('journal_number');

        if ($lastNumber) {
            $sequence = (int) str_replace('JE-', '', $lastNumber) + 1;
        } else {
            $sequence = 1;
        }

        return 'JE-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get accounts for company dropdown.
     */
    protected function getAccountsForCompany(Company $company): array
    {
        // TODO: Implement account retrieval
        return [];
    }
}