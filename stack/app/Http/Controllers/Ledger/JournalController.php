<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\JournalEntries\PostJournalEntryRequest;
use App\Http\Requests\JournalEntries\StoreJournalEntryRequest;
use App\Http\Requests\JournalEntries\UpdateJournalEntryRequest;
use App\Http\Requests\JournalEntries\VoidJournalEntryRequest;
use App\Models\Account;
use App\Models\JournalBatch;
use App\Models\JournalEntry;
use App\Services\ServiceContextHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class JournalController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:ledger.view')->only(['index', 'show']);
        $this->middleware('permission:ledger.entries.create')->only(['create', 'store']);
        $this->middleware('permission:ledger.entries.update')->only(['edit', 'update']);
        $this->middleware('permission:ledger.entries.post')->only(['post']);
        $this->middleware('permission:ledger.entries.void')->only(['void']);
    }

    /**
     * Display a listing of journal entries.
     */
    public function index(StoreJournalEntryRequest $request): Response
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);
            $company = $context->getCompany();
            $user = $context->getUser();

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

        } catch (\Exception $e) {
            Log::error('Journal entry listing failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'company_id' => $context->getCompanyId() ?? null,
            ]);

            return Inertia::render('Ledger/Journal/Index', [
                'journalEntries' => collect(),
                'batches' => collect(),
                'statistics' => [],
                'error' => 'Failed to load journal entries',
            ]);
        }
    }

    /**
     * Show the form for creating a new journal entry.
     */
    public function create(StoreJournalEntryRequest $request): Response
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);
            $company = $context->getCompany();

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
                    'create' => $context->getUser()->can('ledger.entries.create'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Journal entry creation form failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return Inertia::render('Ledger/Journal/Create', [
                'accounts' => collect(),
                'batches' => collect(),
                'currencies' => [],
                'error' => 'Failed to load creation form',
            ]);
        }
    }

    /**
     * Store a newly created journal entry.
     */
    public function store(StoreJournalEntryRequest $request): RedirectResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);

            $journalEntry = Bus::dispatch('journal_entries.create', [
                'date' => $request->validated('date'),
                'reference' => $request->validated('reference'),
                'description' => $request->validated('description'),
                'batch_id' => $request->validated('batch_id'),
                'currency' => $request->validated('currency'),
                'journal_lines' => $request->validated('journal_lines'),
            ], $context);

            return redirect()->route('ledger.journal.index')
                ->with('success', 'Journal entry created successfully.');

        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();

        } catch (\Exception $e) {
            Log::error('Journal entry creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()->id,
                'company_id' => $context->getCompanyId(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to create journal entry. Please try again.')
                ->withInput();
        }
    }

    /**
     * Display the specified journal entry.
     */
    public function show(StoreJournalEntryRequest $request, string $id): Response
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);
            $company = $context->getCompany();
            $user = $context->getUser();

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
                    'view' => $user->can('ledger.view'),
                    'update' => $user->can('ledger.entries.update'),
                    'delete' => $user->can('ledger.entries.delete'),
                    'post' => $user->can('ledger.entries.post'),
                    'void' => $user->can('ledger.entries.void'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Journal entry display failed', [
                'error' => $e->getMessage(),
                'journal_entry_id' => $id,
                'user_id' => $request->user()->id,
            ]);

            return redirect()->route('ledger.journal.index')
                ->with('error', 'Failed to load journal entry');
        }
    }

    /**
     * Show the form for editing the specified journal entry.
     */
    public function edit(UpdateJournalEntryRequest $request, JournalEntry $journalEntry): Response
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);
            $company = $context->getCompany();

            $journalEntry->load(['journalLines.account']);

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

        } catch (\Exception $e) {
            Log::error('Journal entry edit form failed', [
                'error' => $e->getMessage(),
                'journal_entry_id' => $journalEntry->id,
                'user_id' => $request->user()->id,
            ]);

            return redirect()->route('ledger.journal.show', $journalEntry->id)
                ->with('error', 'Failed to load edit form');
        }
    }

    /**
     * Update the specified journal entry.
     */
    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry): RedirectResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);

            $updatedJournalEntry = Bus::dispatch('journal_entries.update', [
                'id' => $journalEntry->id,
                'date' => $request->validated('date'),
                'reference' => $request->validated('reference'),
                'description' => $request->validated('description'),
                'batch_id' => $request->validated('batch_id'),
                'currency' => $request->validated('currency'),
                'journal_lines' => $request->validated('journal_lines'),
            ], $context);

            return redirect()->route('ledger.journal.show', $updatedJournalEntry->id)
                ->with('success', 'Journal entry updated successfully.');

        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();

        } catch (\Exception $e) {
            Log::error('Journal entry update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'journal_entry_id' => $journalEntry->id,
                'user_id' => $request->user()->id,
                'company_id' => $context->getCompanyId(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to update journal entry. Please try again.')
                ->withInput();
        }
    }

    /**
     * Post the specified journal entry.
     */
    public function post(PostJournalEntryRequest $request, JournalEntry $journalEntry): RedirectResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);

            $postedJournalEntry = Bus::dispatch('journal_entries.post', [
                'id' => $journalEntry->id,
            ], $context);

            return redirect()->back()
                ->with('success', 'Journal entry posted successfully.');

        } catch (\Exception $e) {
            Log::error('Journal entry posting failed', [
                'error' => $e->getMessage(),
                'journal_entry_id' => $journalEntry->id,
                'user_id' => $request->user()->id,
                'company_id' => $context->getCompanyId(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to post journal entry. Please try again.');
        }
    }

    /**
     * Void the specified journal entry.
     */
    public function void(VoidJournalEntryRequest $request, JournalEntry $journalEntry): RedirectResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);

            $voidedJournalEntry = Bus::dispatch('journal_entries.void', [
                'id' => $journalEntry->id,
                'reason' => $request->validated('reason'),
            ], $context);

            return redirect()->back()
                ->with('success', 'Journal entry voided successfully.');

        } catch (\Exception $e) {
            Log::error('Journal entry voiding failed', [
                'error' => $e->getMessage(),
                'journal_entry_id' => $journalEntry->id,
                'user_id' => $request->user()->id,
                'company_id' => $context->getCompanyId(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to void journal entry. Please try again.');
        }
    }
}
