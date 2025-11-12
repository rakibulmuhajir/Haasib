<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalBatch;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JournalController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $company = $request->user()->currentCompany();

        $journalEntries = JournalEntry::with(['journalLines.account', 'batch', 'createdBy'])
            ->where('company_id', $company->id)
            ->orderBy('entry_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        $batches = JournalBatch::where('company_id', $company->id)
            ->orderBy('batch_date', 'desc')
            ->get();

        // Calculate statistics
        $statistics = [
            'total_entries' => JournalEntry::where('company_id', $company->id)->count(),
            'posted_entries' => JournalEntry::where('company_id', $company->id)->where('status', 'posted')->count(),
            'draft_entries' => JournalEntry::where('company_id', $company->id)->where('status', 'draft')->count(),
            'this_month' => JournalEntry::where('company_id', $company->id)
                ->whereMonth('entry_date', now()->month)
                ->whereYear('entry_date', now()->year)
                ->count(),
        ];

        return Inertia::render('Ledger/Journal/Index', [
            'journalEntries' => $journalEntries,
            'batches' => $batches,
            'statistics' => $statistics,
            'can' => [
                'view' => $user->can('ledger.view'),
                'create' => $user->can('ledger.entries.create'),
                'update' => $user->can('ledger.entries.update'),
                'delete' => $user->can('ledger.entries.delete'),
                'post' => $user->can('ledger.entries.post'),
                'void' => $user->can('ledger.entries.void'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $company = $request->session()->get('active_company');

        $accounts = Account::where('company_id', $company->id)
            ->where('active', true)
            ->orderBy('code')
            ->get();

        $batches = JournalBatch::where('company_id', $company->id)
            ->where('status', 'open')
            ->orderBy('name')
            ->get();

        return Inertia::render('Ledger/Journal/Create', [
            'accounts' => $accounts,
            'batches' => $batches,
            'currencies' => [
                ['code' => 'USD', 'name' => 'US Dollar'],
                ['code' => 'EUR', 'name' => 'Euro'],
                ['code' => 'GBP', 'name' => 'British Pound'],
            ],
            'can' => [
                'create' => Auth::user()->can('ledger.entries.create'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'reference' => 'nullable|string|max:50',
            'description' => 'required|string|max:500',
            'batch_id' => 'nullable|exists:journal_batches,id',
            'currency' => 'required|string|size:3',
            'journal_lines' => 'required|array|min:2',
            'journal_lines.*.account_id' => 'required|exists:accounts,id',
            'journal_lines.*.description' => 'nullable|string|max:255',
            'journal_lines.*.debit' => 'required|numeric|min:0',
            'journal_lines.*.credit' => 'required|numeric|min:0',
        ]);

        // Validate that each line has either debit or credit, not both
        foreach ($validated['journal_lines'] as $index => $line) {
            if (($line['debit'] > 0 && $line['credit'] > 0) ||
                ($line['debit'] == 0 && $line['credit'] == 0)) {
                return back()->withErrors([
                    "journal_lines.{$index}" => 'Each line must have either a debit or credit amount, not both and not zero.',
                ]);
            }
        }

        // Calculate totals
        $totalDebits = collect($validated['journal_lines'])->sum('debit');
        $totalCredits = collect($validated['journal_lines'])->sum('credit');

        // Validate that the entry balances
        if (abs($totalDebits - $totalCredits) > 0.01) {
            return back()->withErrors([
                'balance' => "Journal entry must balance. Debits: {$totalDebits}, Credits: {$totalCredits}",
            ]);
        }

        $company = $request->session()->get('active_company');
        $user = Auth::user();

        DB::transaction(function () use ($validated, $company, $user) {
            // Create journal entry
            $journalEntry = JournalEntry::create([
                'company_id' => $company->id,
                'date' => $validated['date'],
                'reference' => $validated['reference'] ?? $this->generateReference(),
                'description' => $validated['description'],
                'batch_id' => $validated['batch_id'] ?? null,
                'currency' => $validated['currency'],
                'total_debits' => collect($validated['journal_lines'])->sum('debit'),
                'total_credits' => collect($validated['journal_lines'])->sum('credit'),
                'status' => 'posted', // Default to posted for now
                'created_by' => $user->id,
            ]);

            // Create journal lines
            foreach ($validated['journal_lines'] as $lineData) {
                $journalEntry->journalLines()->create([
                    'account_id' => $lineData['account_id'],
                    'description' => $lineData['description'] ?? $validated['description'],
                    'debit' => $lineData['debit'],
                    'credit' => $lineData['credit'],
                    'created_by' => $user->id,
                ]);
            }

            // Update account balances
            foreach ($validated['journal_lines'] as $lineData) {
                $account = Account::findOrFail($lineData['account_id']);
                $balanceChange = $lineData['debit'] - $lineData['credit'];

                if ($account->normal_balance === 'credit') {
                    $balanceChange = -$balanceChange;
                }

                $account->increment('current_balance', $balanceChange);
                $account->touch('last_updated_at');
            }
        });

        return redirect()->route('ledger.journal.index')
            ->with('success', 'Journal entry created successfully.');
    }

    public function show(Request $request, string $id): Response
    {
        $company = $request->session()->get('active_company');

        $journalEntry = JournalEntry::with([
            'journalLines.account',
            'batch',
            'createdBy',
            'postedBy',
            'voidedBy',
        ])
            ->where('company_id', $company->id)
            ->findOrFail($id);

        return Inertia::render('Ledger/Journal/Show', [
            'journalEntry' => $journalEntry,
            'can' => [
                'view' => Auth::user()->can('ledger.view'),
                'update' => Auth::user()->can('ledger.entries.update'),
                'delete' => Auth::user()->can('ledger.entries.delete'),
                'post' => Auth::user()->can('ledger.entries.post'),
                'void' => Auth::user()->can('ledger.entries.void'),
            ],
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $company = $request->session()->get('active_company');

        $journalEntry = JournalEntry::with(['journalLines.account'])
            ->where('company_id', $company->id)
            ->where('status', 'draft') // Only allow editing draft entries
            ->findOrFail($id);

        $accounts = Account::where('company_id', $company->id)
            ->where('active', true)
            ->orderBy('code')
            ->get();

        $batches = JournalBatch::where('company_id', $company->id)
            ->where('status', 'open')
            ->orderBy('name')
            ->get();

        return Inertia::render('Ledger/Journal/Edit', [
            'journalEntry' => $journalEntry,
            'accounts' => $accounts,
            'batches' => $batches,
            'currencies' => [
                ['code' => 'USD', 'name' => 'US Dollar'],
                ['code' => 'EUR', 'name' => 'Euro'],
                ['code' => 'GBP', 'name' => 'British Pound'],
            ],
        ]);
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'reference' => 'nullable|string|max:50',
            'description' => 'required|string|max:500',
            'batch_id' => 'nullable|exists:journal_batches,id',
            'currency' => 'required|string|size:3',
            'journal_lines' => 'required|array|min:2',
            'journal_lines.*.account_id' => 'required|exists:accounts,id',
            'journal_lines.*.description' => 'nullable|string|max:255',
            'journal_lines.*.debit' => 'required|numeric|min:0',
            'journal_lines.*.credit' => 'required|numeric|min:0',
        ]);

        $company = $request->session()->get('active_company');

        $journalEntry = JournalEntry::where('company_id', $company->id)
            ->where('status', 'draft')
            ->findOrFail($id);

        // Similar validation as store method
        foreach ($validated['journal_lines'] as $index => $line) {
            if (($line['debit'] > 0 && $line['credit'] > 0) ||
                ($line['debit'] == 0 && $line['credit'] == 0)) {
                return back()->withErrors([
                    "journal_lines.{$index}" => 'Each line must have either a debit or credit amount.',
                ]);
            }
        }

        $totalDebits = collect($validated['journal_lines'])->sum('debit');
        $totalCredits = collect($validated['journal_lines'])->sum('credit');

        if (abs($totalDebits - $totalCredits) > 0.01) {
            return back()->withErrors([
                'balance' => 'Journal entry must balance.',
            ]);
        }

        DB::transaction(function () use ($validated, $journalEntry) {
            // Delete existing lines
            $journalEntry->journalLines()->delete();

            // Update journal entry
            $journalEntry->update([
                'date' => $validated['date'],
                'reference' => $validated['reference'],
                'description' => $validated['description'],
                'batch_id' => $validated['batch_id'] ?? null,
                'currency' => $validated['currency'],
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
            ]);

            // Create new journal lines
            foreach ($validated['journal_lines'] as $lineData) {
                $journalEntry->journalLines()->create([
                    'account_id' => $lineData['account_id'],
                    'description' => $lineData['description'] ?? $validated['description'],
                    'debit' => $lineData['debit'],
                    'credit' => $lineData['credit'],
                    'created_by' => Auth::id(),
                ]);
            }
        });

        return redirect()->route('ledger.journal.index')
            ->with('success', 'Journal entry updated successfully.');
    }

    public function post(Request $request, string $id)
    {
        $company = $request->session()->get('active_company');

        $journalEntry = JournalEntry::where('company_id', $company->id)
            ->where('status', 'draft')
            ->findOrFail($id);

        DB::transaction(function () use ($journalEntry) {
            $journalEntry->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ]);

            // Update account balances
            foreach ($journalEntry->journalLines as $line) {
                $account = Account::findOrFail($line->account_id);
                $balanceChange = $line->debit - $line->credit;

                if ($account->normal_balance === 'credit') {
                    $balanceChange = -$balanceChange;
                }

                $account->increment('current_balance', $balanceChange);
                $account->touch('last_updated_at');
            }
        });

        return back()->with('success', 'Journal entry posted successfully.');
    }

    public function void(Request $request, string $id)
    {
        $company = $request->session()->get('active_company');

        $journalEntry = JournalEntry::where('company_id', $company->id)
            ->where('status', 'posted')
            ->findOrFail($id);

        DB::transaction(function () use ($journalEntry) {
            // Reverse account balances
            foreach ($journalEntry->journalLines as $line) {
                $account = Account::findOrFail($line->account_id);
                $balanceChange = $line->debit - $line->credit;

                if ($account->normal_balance === 'credit') {
                    $balanceChange = -$balanceChange;
                }

                $account->decrement('current_balance', $balanceChange);
                $account->touch('last_updated_at');
            }

            $journalEntry->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => Auth::id(),
            ]);
        });

        return back()->with('success', 'Journal entry voided successfully.');
    }

    private function generateReference(): string
    {
        $date = now();
        $year = $date->format('Y');
        $month = $date->format('m');
        $day = $date->format('d');
        $random = str_pad(mt_rand(0, 999), 3, '0', STR_PAD_LEFT);

        return "JE{$year}{$month}{$day}{$random}";
    }
}
