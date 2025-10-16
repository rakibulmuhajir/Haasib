<?php

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Domain\JournalEntries\Events\BatchApproved;
use Modules\Accounting\Domain\JournalEntries\Events\BatchCreated;
use Modules\Accounting\Domain\JournalEntries\Events\BatchDeleted;
use Modules\Accounting\Domain\JournalEntries\Events\BatchPosted;
use Modules\Accounting\Domain\JournalEntries\Events\EntryAddedToBatch;
use Modules\Accounting\Domain\JournalEntries\Events\EntryRemovedFromBatch;
use Modules\Accounting\Http\Requests\JournalBatchRequest;

class JournalBatchController extends Controller
{
    /**
     * Display a listing of journal batches.
     */
    public function index(Request $request): JsonResponse
    {
        // Use caching for statistics to improve performance
        $cacheKey = "batch_stats_{$request->user()->current_company_id}";
        $stats = Cache::remember($cacheKey, 300, function () use ($request) {
            return $this->statistics($request);
        });

        $query = \App\Models\JournalBatch::withCount(['journalEntries'])
            ->where('company_id', $request->user()->current_company_id);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Search by name or description with sanitization
        if ($request->has('search')) {
            $search = trim($request->get('search'));
            if (strlen($search) > 0) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', '%'.addcslashes($search, '%_').'%')
                        ->orWhere('description', 'ilike', '%'.addcslashes($search, '%_').'%');
                });
            }
        }

        // Filter by date range
        if ($request->has('created_from')) {
            $query->where('created_at', '>=', $request->get('created_from'));
        }

        if ($request->has('created_to')) {
            $query->where('created_at', '<=', $request->get('created_to'));
        }

        // Filter by entry count
        if ($request->has('min_entries')) {
            $query->where('total_entries', '>=', $request->get('min_entries'));
        }

        if ($request->has('max_entries')) {
            $query->where('total_entries', '<=', $request->get('max_entries'));
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        if (in_array($sortBy, ['name', 'status', 'created_at', 'updated_at', 'total_entries'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = min(max(1, $request->get('per_page', 15)), 100);
        $batches = $query->paginate($perPage);

        // Load statistics for each batch only if needed
        if ($request->get('include_stats', false)) {
            $batches->getCollection()->transform(function ($batch) {
                $batch->statistics = $this->getBatchStatistics($batch);

                return $batch;
            });
        }

        return response()->json([
            'data' => $batches->items(),
            'meta' => [
                'current_page' => $batches->currentPage(),
                'last_page' => $batches->lastPage(),
                'per_page' => $batches->perPage(),
                'total' => $batches->total(),
                'statistics' => $stats,
            ],
        ]);
    }

    /**
     * Store a newly created journal batch.
     */
    public function store(JournalBatchRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            return DB::transaction(function () use ($validated, $request) {
                // Verify all entries belong to the company and are in appropriate status
                $entries = \App\Models\JournalEntry::whereIn('id', $validated['journal_entry_ids'])
                    ->where('company_id', $request->user()->company_id)
                    ->whereIn('status', ['draft', 'approved'])
                    ->get();

                if ($entries->count() !== count($validated['journal_entry_ids'])) {
                    return response()->json([
                        'message' => 'Some journal entries are invalid or cannot be added to batch',
                    ], 422);
                }

                $batch = \App\Models\JournalBatch::create([
                    'company_id' => $request->user()->company_id,
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'status' => 'pending',
                    'total_entries' => $entries->count(),
                    'created_by' => Auth::id(),
                ]);

                // Associate entries with batch
                $batch->journalEntries()->attach($entries->pluck('id'));

                // Fire batch created event
                event(new BatchCreated($batch, Auth::id()));

                return response()->json([
                    'message' => 'Journal batch created successfully',
                    'data' => $batch->load(['journalEntries', 'statistics' => fn () => $this->getBatchStatistics($batch)]),
                ], 201);
            });

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create journal batch',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified journal batch.
     */
    public function show(Request $request, string $batchId): JsonResponse
    {
        $batch = \App\Models\JournalBatch::with(['journalEntries.transactions.account', 'journalEntries' => fn ($query) => $query->orderBy('date')])
            ->where('company_id', $request->user()->company_id)
            ->where('id', $batchId)
            ->firstOrFail();

        $batch->statistics = $this->getBatchStatistics($batch);

        return response()->json($batch);
    }

    /**
     * Update the specified journal batch.
     */
    public function update(Request $request, string $batchId): JsonResponse
    {
        $batch = \App\Models\JournalBatch::where('company_id', $request->user()->company_id)
            ->where('id', $batchId)
            ->firstOrFail();

        if ($batch->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending batches can be updated',
            ], 422);
        }

        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string|max:1000',
            ]);

            $batch->update($validated);

            return response()->json([
                'message' => 'Journal batch updated successfully',
                'data' => $batch->fresh(['journalEntries']),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update journal batch',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified journal batch.
     */
    public function destroy(Request $request, string $batchId): JsonResponse
    {
        $batch = \App\Models\JournalBatch::where('company_id', $request->user()->company_id)
            ->where('id', $batchId)
            ->firstOrFail();

        if ($batch->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending batches can be deleted',
            ], 422);
        }

        try {
            DB::transaction(function () use ($batch) {
                // Remove batch association from entries
                $batch->journalEntries()->detach();

                // Delete batch
                $batch->delete();

                // Fire batch deleted event
                event(new BatchDeleted($batch, Auth::id()));
            });

            return response()->json([
                'message' => 'Journal batch deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete journal batch',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve a journal batch.
     */
    public function approve(Request $request, string $batchId): JsonResponse
    {
        $batch = \App\Models\JournalBatch::where('company_id', $request->user()->company_id)
            ->where('id', $batchId)
            ->firstOrFail();

        if ($batch->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending batches can be approved',
            ], 422);
        }

        try {
            return DB::transaction(function () use ($batch) {
                // Update batch status
                $batch->update([
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                ]);

                // Update all associated entries to approved
                $batch->journalEntries()->update([
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                ]);

                // Fire batch approved event
                event(new BatchApproved($batch, Auth::id()));

                return response()->json([
                    'message' => 'Journal batch approved successfully',
                    'data' => $batch->fresh(['journalEntries']),
                ]);
            });

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to approve journal batch',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Post a journal batch.
     */
    public function post(Request $request, string $batchId): JsonResponse
    {
        $batch = \App\Models\JournalBatch::where('company_id', $request->user()->company_id)
            ->where('id', $batchId)
            ->firstOrFail();

        if ($batch->status !== 'approved') {
            return response()->json([
                'message' => 'Only approved batches can be posted',
            ], 422);
        }

        try {
            return DB::transaction(function () use ($batch) {
                // Validate all entries can be posted
                $entries = $batch->journalEntries()->get();

                foreach ($entries as $entry) {
                    if ($entry->status !== 'approved') {
                        throw new \Exception("Entry {$entry->id} is not in approved status");
                    }

                    // Validate entry is balanced
                    $debits = $entry->transactions()->where('debit_credit', 'debit')->sum('amount');
                    $credits = $entry->transactions()->where('debit_credit', 'credit')->sum('amount');

                    if (abs($debits - $credits) > 0.01) {
                        throw new \Exception("Entry {$entry->id} is not balanced");
                    }
                }

                // Update batch status
                $batch->update([
                    'status' => 'posted',
                    'posted_by' => Auth::id(),
                    'posted_at' => now(),
                ]);

                // Update all associated entries to posted
                $batch->journalEntries()->update([
                    'status' => 'posted',
                    'posted_by' => Auth::id(),
                    'posted_at' => now(),
                ]);

                // Fire batch posted event
                event(new BatchPosted($batch, Auth::id()));

                return response()->json([
                    'message' => 'Journal batch posted successfully',
                    'data' => $batch->fresh(['journalEntries']),
                ]);
            });

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to post journal batch',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add entries to an existing batch.
     */
    public function addEntries(Request $request, string $batchId): JsonResponse
    {
        $batch = \App\Models\JournalBatch::where('company_id', $request->user()->company_id)
            ->where('id', $batchId)
            ->where('status', 'pending')
            ->firstOrFail();

        try {
            $validated = $request->validate([
                'journal_entry_ids' => 'required|array|min:1',
                'journal_entry_ids.*' => 'uuid|exists:journal_entries,id',
            ]);

            return DB::transaction(function () use ($batch, $validated, $request) {
                // Verify all entries belong to the company and are in appropriate status
                $entries = \App\Models\JournalEntry::whereIn('id', $validated['journal_entry_ids'])
                    ->where('company_id', $request->user()->company_id)
                    ->whereIn('status', ['draft', 'approved'])
                    ->whereDoesntHave('batches', fn ($query) => $query->where('batch_id', $batch->id))
                    ->get();

                if ($entries->isEmpty()) {
                    return response()->json([
                        'message' => 'No valid entries to add to batch',
                    ], 422);
                }

                // Associate entries with batch
                $batch->journalEntries()->attach($entries->pluck('id'));

                // Update total entries count
                $batch->update(['total_entries' => $batch->journalEntries()->count()]);

                // Fire entry added events
                foreach ($entries as $entry) {
                    event(new EntryAddedToBatch($batch, $entry, Auth::id()));
                }

                return response()->json([
                    'message' => 'Entries added to batch successfully',
                    'data' => $batch->fresh(['journalEntries']),
                ]);
            });

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add entries to batch',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove entries from a batch.
     */
    public function removeEntries(Request $request, string $batchId): JsonResponse
    {
        $batch = \App\Models\JournalBatch::where('company_id', $request->user()->company_id)
            ->where('id', $batchId)
            ->where('status', 'pending')
            ->firstOrFail();

        try {
            $validated = $request->validate([
                'journal_entry_ids' => 'required|array|min:1',
                'journal_entry_ids.*' => 'uuid|exists:journal_entries,id',
            ]);

            return DB::transaction(function () use ($batch, $validated) {
                // Get the entries being removed for event firing
                $removedEntries = $batch->journalEntries()
                    ->whereIn('journal_entries.id', $validated['journal_entry_ids'])
                    ->get();

                // Remove specified entries from batch
                $batch->journalEntries()->detach($validated['journal_entry_ids']);

                // Update total entries count
                $batch->update(['total_entries' => $batch->journalEntries()->count()]);

                // Fire entry removed events
                foreach ($removedEntries as $entry) {
                    event(new EntryRemovedFromBatch($batch, $entry, Auth::id()));
                }

                return response()->json([
                    'message' => 'Entries removed from batch successfully',
                    'data' => $batch->fresh(['journalEntries']),
                ]);
            });

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove entries from batch',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get batch statistics.
     */
    protected function getBatchStatistics(\App\Models\JournalBatch $batch): array
    {
        $entries = $batch->journalEntries;

        return [
            'total_entries' => $entries->count(),
            'draft_entries' => $entries->where('status', 'draft')->count(),
            'approved_entries' => $entries->where('status', 'approved')->count(),
            'posted_entries' => $entries->where('status', 'posted')->count(),
            'total_amount' => $entries->sum(function ($entry) {
                return $entry->transactions()->where('debit_credit', 'debit')->sum('amount');
            }),
            'can_approve' => $batch->status === 'pending',
            'can_post' => $batch->status === 'approved',
            'can_edit' => $batch->status === 'pending',
            'can_delete' => $batch->status === 'pending',
        ];
    }

    /**
     * Get batch statistics for all batches.
     */
    public function statistics(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        $stats = [
            'total_batches' => \App\Models\JournalBatch::where('company_id', $companyId)->count(),
            'pending_batches' => \App\Models\JournalBatch::where('company_id', $companyId)->where('status', 'pending')->count(),
            'approved_batches' => \App\Models\JournalBatch::where('company_id', $companyId)->where('status', 'approved')->count(),
            'posted_batches' => \App\Models\JournalBatch::where('company_id', $companyId)->where('status', 'posted')->count(),
            'total_entries_in_batches' => \App\Models\JournalBatch::where('company_id', $companyId)->sum('total_entries'),
            'average_entries_per_batch' => \App\Models\JournalBatch::where('company_id', $companyId)->avg('total_entries'),
            'largest_batch' => \App\Models\JournalBatch::where('company_id', $companyId)->max('total_entries'),
            'created_this_month' => \App\Models\JournalBatch::where('company_id', $companyId)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
        ];

        return response()->json($stats);
    }
}
