<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LedgerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:ledger.view')->only(['index']);
    }

    /**
     * Display the main ledger dashboard.
     */
    public function index(Request $request): Response
    {
        $company = $request->user()->currentCompany();

        // Get ledger statistics
        $statistics = [
            'total_entries' => JournalEntry::where('company_id', $company->id)->count(),
            'posted_entries' => JournalEntry::where('company_id', $company->id)->where('status', 'posted')->count(),
            'draft_entries' => JournalEntry::where('company_id', $company->id)->where('status', 'draft')->count(),
            'this_month' => JournalEntry::where('company_id', $company->id)
                ->whereMonth('entry_date', now()->month)
                ->whereYear('entry_date', now()->year)
                ->count(),
        ];

        return Inertia::render('Ledger/Index', [
            'statistics' => $statistics,
        ]);
    }
}
