<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class JournalBatchController extends Controller
{
    /**
     * Display a listing of journal batches.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Get batches data from API
        $apiResponse = Http::withToken($user->currentAccessToken()?->token ?? session('auth_token'))
            ->get('/api/ledger/journal-batches', [
                'status' => $request->get('status'),
                'min_entries' => $request->get('min_entries'),
                'max_entries' => $request->get('max_entries'),
                'search' => $request->get('search'),
                'per_page' => $request->get('per_page', 25),
            ]);

        if (! $apiResponse->successful()) {
            // Return empty data structure if API fails
            $batches = [
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 25,
                'total' => 0,
            ];

            $statistics = [
                'total_batches' => 0,
                'pending_batches' => 0,
                'approved_batches' => 0,
                'posted_batches' => 0,
                'total_entries_in_batches' => 0,
            ];
        } else {
            $batches = $apiResponse->json();

            // Get statistics
            $statsResponse = Http::withToken($user->currentAccessToken()?->token ?? session('auth_token'))
                ->get('/api/ledger/journal-batches/statistics');

            $statistics = $statsResponse->successful() ? $statsResponse->json() : [];
        }

        return Inertia::render('Accounting/JournalEntries/Batches/Index', [
            'batches' => $batches,
            'statistics' => $statistics,
            'filters' => [
                'status' => $request->get('status'),
                'min_entries' => $request->get('min_entries'),
                'max_entries' => $request->get('max_entries'),
                'search' => $request->get('search'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new journal batch.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Accounting/JournalEntries/Batches/Create', [
            'availableEntries' => $this->getAvailableEntries($request),
        ]);
    }

    /**
     * Display the specified journal batch.
     */
    public function show(Request $request, string $batchId): Response
    {
        $user = $request->user();

        // Get batch data from API
        $apiResponse = Http::withToken($user->currentAccessToken()?->token ?? session('auth_token'))
            ->get("/api/ledger/journal-batches/{$batchId}");

        if (! $apiResponse->successful()) {
            abort(404, 'Journal batch not found');
        }

        $batch = $apiResponse->json();

        return Inertia::render('Accounting/JournalEntries/Batches/Show', [
            'batch' => $batch,
            'statistics' => $batch['statistics'] ?? [],
            'journalEntries' => $batch['journal_entries'] ?? [],
        ]);
    }

    /**
     * Show the form for editing the specified journal batch.
     */
    public function edit(Request $request, string $batchId): Response
    {
        $user = $request->user();

        // Get batch data from API
        $apiResponse = Http::withToken($user->currentAccessToken()?->token ?? session('auth_token'))
            ->get("/api/ledger/journal-batches/{$batchId}");

        if (! $apiResponse->successful()) {
            abort(404, 'Journal batch not found');
        }

        $batch = $apiResponse->json();

        return Inertia::render('Accounting/JournalEntries/Batches/Edit', [
            'batch' => $batch,
        ]);
    }

    /**
     * Approve a journal batch.
     */
    public function approve(Request $request, string $batchId): Response
    {
        $user = $request->user();

        $apiResponse = Http::withToken($user->currentAccessToken()?->token ?? session('auth_token'))
            ->post("/api/ledger/journal-batches/{$batchId}/approve");

        if ($apiResponse->successful()) {
            return redirect()->route('journal.batches.show', $batchId)
                ->with('success', 'Journal batch approved successfully.');
        }

        return redirect()->back()->with('error', 'Failed to approve journal batch.');
    }

    /**
     * Post a journal batch.
     */
    public function post(Request $request, string $batchId): Response
    {
        $user = $request->user();

        $apiResponse = Http::withToken($user->currentAccessToken()?->token ?? session('auth_token'))
            ->post("/api/ledger/journal-batches/{$batchId}/post");

        if ($apiResponse->successful()) {
            return redirect()->route('journal.batches.show', $batchId)
                ->with('success', 'Journal batch posted successfully.');
        }

        return redirect()->back()->with('error', 'Failed to post journal batch.');
    }

    /**
     * Remove the specified journal batch.
     */
    public function destroy(Request $request, string $batchId): Response
    {
        $user = $request->user();

        $apiResponse = Http::withToken($user->currentAccessToken()?->token ?? session('auth_token'))
            ->delete("/api/ledger/journal-batches/{$batchId}");

        if ($apiResponse->successful()) {
            return redirect()->route('journal.batches.index')
                ->with('success', 'Journal batch deleted successfully.');
        }

        return redirect()->back()->with('error', 'Failed to delete journal batch.');
    }

    /**
     * Get available journal entries for batch creation.
     */
    private function getAvailableEntries(Request $request): array
    {
        $user = $request->user();

        $apiResponse = Http::withToken($user->currentAccessToken()?->token ?? session('auth_token'))
            ->get('/api/ledger/journal-entries', [
                'status' => 'draft',
                'status' => 'approved',
                'per_page' => 100,
            ]);

        return $apiResponse->successful() ? $apiResponse->json()['data'] : [];
    }
}
