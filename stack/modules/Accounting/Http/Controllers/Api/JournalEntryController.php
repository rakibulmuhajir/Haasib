<?php

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Accounting\Services\LedgerService;

class JournalEntryController extends Controller
{
    public function __construct(
        private LedgerService $ledgerService
    ) {}

    /**
     * Display a listing of journal entries.
     */
    public function index(Request $request): JsonResponse
    {
        $query = JournalEntry::with(['transactions.account', 'reversalOf', 'reversedBy'])
            ->where('company_id', $request->user()->company_id);

        // Include sources if requested
        if ($request->get('include_sources')) {
            $query->with(['sources']);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->get('date_to'));
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->get('type'));
        }

        // Filter by auto-generated status
        if ($request->has('auto_generated')) {
            $query->where('auto_generated', $request->boolean('auto_generated'));
        }

        // Filter by source type
        if ($request->has('source_type')) {
            $query->whereHas('sources', function ($q) use ($request) {
                $q->where('source_type', $request->get('source_type'));
            });
        }

        // Search by description, reference, or notes
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'ilike', "%{$search}%")
                    ->orWhere('reference', 'ilike', "%{$search}%")
                    ->orWhere('notes', 'ilike', "%{$search}%");
            });
        }

        // Advanced search - filter by account
        if ($request->has('account_id')) {
            $query->whereHas('transactions', function ($q) use ($request) {
                $q->where('account_id', $request->get('account_id'));
            });
        }

        // Advanced search - filter by amount range
        if ($request->has('amount_min')) {
            $query->whereHas('transactions', function ($q) use ($request) {
                $q->where('amount', '>=', $request->get('amount_min'));
            });
        }

        if ($request->has('amount_max')) {
            $query->whereHas('transactions', function ($q) use ($request) {
                $q->where('amount', '<=', $request->get('amount_max'));
            });
        }

        // Filter by created date range
        if ($request->has('created_from')) {
            $query->where('created_at', '>=', $request->get('created_from'));
        }

        if ($request->has('created_to')) {
            $query->where('created_at', '<=', $request->get('created_to'));
        }

        // Sorting options
        $sortBy = $request->get('sort_by', 'date');
        $sortOrder = $request->get('sort_order', 'desc');

        if (in_array($sortBy, ['date', 'description', 'reference', 'status', 'type', 'created_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Default secondary sort
        $query->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        $perPage = min($request->get('per_page', 15), 100); // Cap at 100 for performance
        $entries = $query->paginate($perPage);

        return response()->json($entries);
    }

    /**
     * Store a newly created journal entry.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->ledgerService->validateManualJournalEntry(
                array_merge($request->all(), [
                    'company_id' => $request->user()->company_id,
                ])
            );

            $entry = $this->ledgerService->createJournalEntry($validated, $validated['lines'] ?? []);

            return response()->json([
                'message' => 'Journal entry created successfully',
                'data' => $entry->load(['transactions.account']),
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create journal entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified journal entry.
     */
    public function show(Request $request, string $journalEntryId): JsonResponse
    {
        $query = JournalEntry::where('company_id', $request->user()->company_id)
            ->where('id', $journalEntryId);

        // Include related data based on request
        if ($request->get('include_transactions')) {
            $query->with(['transactions.account']);
        }

        if ($request->get('include_sources')) {
            $query->with(['sources']);
        }

        if ($request->get('include_reversals')) {
            $query->with(['reversalOf', 'reversedBy']);
        }

        $entry = $query->firstOrFail();

        return response()->json($entry);
    }

    /**
     * Update the specified journal entry.
     */
    public function update(Request $request, string $journalEntryId): JsonResponse
    {
        $entry = JournalEntry::where('company_id', $request->user()->company_id)
            ->where('id', $journalEntryId)
            ->firstOrFail();

        if (! $this->ledgerService->canModifyEntry($entry)) {
            return response()->json([
                'message' => 'Journal entry cannot be modified in its current status',
            ], 422);
        }

        try {
            $validated = $this->ledgerService->validateManualJournalEntry(
                array_merge($request->all(), [
                    'company_id' => $request->user()->company_id,
                ])
            );

            // Update basic fields
            $entry->update([
                'description' => $validated['description'],
                'date' => $validated['date'],
                'type' => $validated['type'],
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'attachment_url' => $validated['attachment_url'] ?? null,
            ]);

            // Handle transaction updates
            if (isset($validated['lines'])) {
                // Remove existing transactions
                $entry->transactions()->delete();

                // Add new transactions
                foreach ($validated['lines'] as $line) {
                    $entry->transactions()->create([
                        'account_id' => $line['account_id'],
                        'debit_credit' => $line['debit_credit'],
                        'amount' => $line['amount'],
                        'currency' => $validated['currency'],
                        'description' => $line['description'] ?? null,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Journal entry updated successfully',
                'data' => $entry->fresh(['transactions.account']),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update journal entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified journal entry.
     */
    public function destroy(Request $request, string $journalEntryId): JsonResponse
    {
        $entry = JournalEntry::where('company_id', $request->user()->company_id)
            ->where('id', $journalEntryId)
            ->firstOrFail();

        if (! $this->ledgerService->canModifyEntry($entry)) {
            return response()->json([
                'message' => 'Journal entry cannot be deleted in its current status',
            ], 422);
        }

        try {
            $entry->transactions()->delete();
            $entry->delete();

            return response()->json([
                'message' => 'Journal entry deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete journal entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit journal entry for approval.
     */
    public function submit(Request $request, string $journalEntryId): JsonResponse
    {
        $entry = JournalEntry::where('company_id', $request->user()->company_id)
            ->where('id', $journalEntryId)
            ->firstOrFail();

        if ($entry->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft entries can be submitted for approval',
            ], 422);
        }

        try {
            $this->ledgerService->validateStatusTransition($entry, 'submitted');

            // Dispatch command via command bus
            $result = app('command.bus')->dispatch('journal.submit', [
                'journal_entry_id' => $entry->id,
                'submitted_by' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Journal entry submitted for approval successfully',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to submit journal entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve journal entry.
     */
    public function approve(Request $request, string $journalEntryId): JsonResponse
    {
        $entry = JournalEntry::where('company_id', $request->user()->company_id)
            ->where('id', $journalEntryId)
            ->firstOrFail();

        if ($entry->status !== 'submitted') {
            return response()->json([
                'message' => 'Only submitted entries can be approved',
            ], 422);
        }

        try {
            $this->ledgerService->validateStatusTransition($entry, 'approved');

            // Dispatch command via command bus
            $result = app('command.bus')->dispatch('journal.approve', [
                'journal_entry_id' => $entry->id,
                'approved_by' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Journal entry approved successfully',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to approve journal entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Post journal entry to ledger.
     */
    public function post(Request $request, string $journalEntryId): JsonResponse
    {
        $entry = JournalEntry::where('company_id', $request->user()->company_id)
            ->where('id', $journalEntryId)
            ->firstOrFail();

        if (! $this->ledgerService->canPostEntry($entry)) {
            return response()->json([
                'message' => 'Journal entry cannot be posted in its current status',
            ], 422);
        }

        try {
            $this->ledgerService->validateStatusTransition($entry, 'posted');

            // Dispatch command via command bus
            $result = app('command.bus')->dispatch('journal.post', [
                'journal_entry_id' => $entry->id,
                'posted_by' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Journal entry posted successfully',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to post journal entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reverse journal entry.
     */
    public function reverse(Request $request, string $journalEntryId): JsonResponse
    {
        $entry = JournalEntry::where('company_id', $request->user()->company_id)
            ->where('id', $journalEntryId)
            ->firstOrFail();

        $validated = $request->validate([
            'reversal_date' => 'required|date|before_or_equal:today',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            // Create reversal entry via LedgerService
            $reversalEntry = $this->ledgerService->createReversalEntry(
                $entry,
                $validated['reversal_date'],
                $validated['reason'] ?? null
            );

            return response()->json([
                'message' => 'Journal entry reversal created successfully',
                'data' => $reversalEntry->load(['transactions.account']),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create journal entry reversal',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Void journal entry.
     */
    public function void(Request $request, string $journalEntryId): JsonResponse
    {
        $entry = JournalEntry::where('company_id', $request->user()->company_id)
            ->where('id', $journalEntryId)
            ->firstOrFail();

        if (! in_array($entry->status, ['draft', 'submitted', 'approved', 'posted'])) {
            return response()->json([
                'message' => 'Journal entry cannot be voided in its current status',
            ], 422);
        }

        try {
            $this->ledgerService->validateStatusTransition($entry, 'void');

            // Dispatch command via command bus
            $result = app('command.bus')->dispatch('journal.void', [
                'journal_entry_id' => $entry->id,
                'voided_by' => Auth::id(),
                'reason' => $request->get('reason', 'Voided by user'),
            ]);

            return response()->json([
                'message' => 'Journal entry voided successfully',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to void journal entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get audit timeline for a journal entry.
     */
    public function audit(Request $request, string $journalEntryId): JsonResponse
    {
        $entry = JournalEntry::where('company_id', $request->user()->company_id)
            ->where('id', $journalEntryId)
            ->firstOrFail();

        $auditRecords = $entry->audit()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($audit) {
                return [
                    'id' => $audit->id,
                    'journal_entry_id' => $audit->journal_entry_id,
                    'event_type' => $audit->event_type,
                    'payload' => $audit->payload,
                    'actor_id' => $audit->actor_id,
                    'created_at' => $audit->created_at->toISOString(),
                    'actor' => $audit->actor_id ? [
                        'id' => $audit->actor_id,
                        'name' => $audit->actor?->name ?? 'Unknown User',
                        'email' => $audit->actor?->email ?? null,
                    ] : null,
                    'timestamp' => $audit->created_at->toISOString(),
                ];
            });

        return response()->json([
            'journal_entry' => [
                'id' => $entry->id,
                'description' => $entry->description,
                'status' => $entry->status,
                'type' => $entry->type,
                'date' => $entry->date->format('Y-m-d'),
                'reference' => $entry->reference,
                'auto_generated' => $entry->auto_generated,
            ],
            'audit_timeline' => $auditRecords,
            'summary' => [
                'total_events' => $auditRecords->count(),
                'last_updated' => $auditRecords->last()?->timestamp,
                'has_errors' => $auditRecords->filter(fn ($item) => str_contains($item['event_type'], 'error') ||
                    str_contains($item['event_type'], 'failed')
                )->isNotEmpty(),
            ],
        ]);
    }

    /**
     * Get comprehensive summary of journal entry.
     */
    public function summary(Request $request, string $journalEntryId): JsonResponse
    {
        $entry = JournalEntry::with([
            'transactions.account',
            'sources',
            'reversalOf',
            'reversedBy',
            'audit' => fn ($query) => $query->orderBy('created_at', 'asc'),
        ])->where('company_id', $request->user()->company_id)
            ->where('id', $journalEntryId)
            ->firstOrFail();

        $totalDebits = $entry->transactions->where('debit_credit', 'debit')->sum('amount');
        $totalCredits = $entry->transactions->where('debit_credit', 'credit')->sum('amount');

        return response()->json([
            'entry' => $entry->toArray(),
            'totals' => [
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
                'balanced' => abs($totalDebits - $totalCredits) < 0.01,
            ],
            'transaction_count' => $entry->transactions->count(),
            'has_sources' => $entry->sources->isNotEmpty(),
            'source_count' => $entry->sources->count(),
            'has_reversals' => $entry->reversalOf || $entry->reversedBy,
            'audit_event_count' => $entry->audit->count(),
        ]);
    }
}
