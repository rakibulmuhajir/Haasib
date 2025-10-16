<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class LedgerReportController extends Controller
{
    /**
     * Display the trial balance page.
     */
    public function trialBalance(Request $request): Response
    {
        $user = $request->user();

        // Get trial balance data from API
        $apiResponse = Http::withToken($user->currentAccessToken()?->token ?? session('auth_token'))
            ->get('/api/ledger/trial-balance', [
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
                'currency' => $request->get('currency'),
                'include_zero_balances' => $request->boolean('include_zero_balances', false),
            ]);

        if (! $apiResponse->successful()) {
            // Return empty data structure if API fails
            $trialBalance = [
                'period' => [
                    'date_from' => $request->get('date_from'),
                    'date_to' => $request->get('date_to'),
                ],
                'generated_at' => now()->toISOString(),
                'company_id' => $user->company_id,
                'currency' => $request->get('currency'),
                'accounts' => [],
                'summary' => [
                    'total_debits' => 0,
                    'total_credits' => 0,
                    'total_difference' => 0,
                    'is_balanced' => true,
                    'account_count' => 0,
                ],
                'metadata' => [
                    'include_zero_balances' => $request->boolean('include_zero_balances', false),
                    'date_filter_applied' => $request->has('date_from') || $request->has('date_to'),
                    'account_filter_applied' => false,
                    'currency_filter_applied' => $request->has('currency'),
                ],
            ];
        } else {
            $trialBalance = $apiResponse->json();
        }

        return Inertia::render('Accounting/JournalEntries/TrialBalance', [
            'trialBalance' => $trialBalance,
            'filters' => [
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
                'currency' => $request->get('currency'),
                'include_zero_balances' => $request->boolean('include_zero_balances', false),
            ],
        ]);
    }

    /**
     * Display the balance sheet page.
     */
    public function balanceSheet(Request $request): Response
    {
        // TODO: Implement balance sheet logic
        return Inertia::render('Accounting/Reports/BalanceSheet', [
            'data' => [],
            'filters' => [],
        ]);
    }

    /**
     * Display the income statement page.
     */
    public function incomeStatement(Request $request): Response
    {
        // TODO: Implement income statement logic
        return Inertia::render('Accounting/Reports/IncomeStatement', [
            'data' => [],
            'filters' => [],
        ]);
    }
}
