<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Services\FinancialReportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class LedgerReportController extends Controller
{
    public function __construct(
        private FinancialReportsService $reportsService
    ) {
        $this->middleware('auth');
    }

    /**
     * Display the trial balance page.
     */
    public function trialBalance(Request $request): Response
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            if (! $company) {
                return Inertia::render('Accounting/Reports/TrialBalance', [
                    'trialBalance' => null,
                    'error' => 'No company selected',
                    'filters' => $this->getTrialBalanceFilters($request),
                ]);
            }

            $trialBalance = $this->reportsService->generateTrialBalance($company, [
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
                'currency' => $request->get('currency'),
                'include_zero_balances' => $request->boolean('include_zero_balances', false),
            ]);

            return Inertia::render('Accounting/Reports/TrialBalance', [
                'trialBalance' => $trialBalance,
                'filters' => $this->getTrialBalanceFilters($request),
            ]);

        } catch (\Exception $e) {
            Log::error('Trial balance generation failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return Inertia::render('Accounting/Reports/TrialBalance', [
                'trialBalance' => null,
                'error' => 'Failed to generate trial balance',
                'filters' => $this->getTrialBalanceFilters($request),
            ]);
        }
    }

    /**
     * Get trial balance data as JSON
     */
    public function trialBalanceData(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            if (! $company) {
                return response()->json(['error' => 'No company selected'], 400);
            }

            $trialBalance = $this->reportsService->generateTrialBalance($company, [
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
                'currency' => $request->get('currency'),
                'include_zero_balances' => $request->boolean('include_zero_balances', false),
            ]);

            return response()->json($trialBalance);

        } catch (\Exception $e) {
            Log::error('Trial balance API failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to generate trial balance'], 500);
        }
    }

    /**
     * Display the balance sheet page.
     */
    public function balanceSheet(Request $request): Response
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            if (! $company) {
                return Inertia::render('Accounting/Reports/BalanceSheet', [
                    'balanceSheet' => null,
                    'error' => 'No company selected',
                    'filters' => $this->getBalanceSheetFilters($request),
                ]);
            }

            $balanceSheet = $this->reportsService->generateBalanceSheet($company, [
                'date_to' => $request->get('date_to'),
                'currency' => $request->get('currency'),
            ]);

            return Inertia::render('Accounting/Reports/BalanceSheet', [
                'balanceSheet' => $balanceSheet,
                'filters' => $this->getBalanceSheetFilters($request),
            ]);

        } catch (\Exception $e) {
            Log::error('Balance sheet generation failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return Inertia::render('Accounting/Reports/BalanceSheet', [
                'balanceSheet' => null,
                'error' => 'Failed to generate balance sheet',
                'filters' => $this->getBalanceSheetFilters($request),
            ]);
        }
    }

    /**
     * Get balance sheet data as JSON
     */
    public function balanceSheetData(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            if (! $company) {
                return response()->json(['error' => 'No company selected'], 400);
            }

            $balanceSheet = $this->reportsService->generateBalanceSheet($company, [
                'date_to' => $request->get('date_to'),
                'currency' => $request->get('currency'),
            ]);

            return response()->json($balanceSheet);

        } catch (\Exception $e) {
            Log::error('Balance sheet API failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to generate balance sheet'], 500);
        }
    }

    /**
     * Display the income statement page.
     */
    public function incomeStatement(Request $request): Response
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            if (! $company) {
                return Inertia::render('Accounting/Reports/IncomeStatement', [
                    'incomeStatement' => null,
                    'error' => 'No company selected',
                    'filters' => $this->getIncomeStatementFilters($request),
                ]);
            }

            $incomeStatement = $this->reportsService->generateIncomeStatement($company, [
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
                'currency' => $request->get('currency'),
            ]);

            return Inertia::render('Accounting/Reports/IncomeStatement', [
                'incomeStatement' => $incomeStatement,
                'filters' => $this->getIncomeStatementFilters($request),
            ]);

        } catch (\Exception $e) {
            Log::error('Income statement generation failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return Inertia::render('Accounting/Reports/IncomeStatement', [
                'incomeStatement' => null,
                'error' => 'Failed to generate income statement',
                'filters' => $this->getIncomeStatementFilters($request),
            ]);
        }
    }

    /**
     * Get income statement data as JSON
     */
    public function incomeStatementData(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            if (! $company) {
                return response()->json(['error' => 'No company selected'], 400);
            }

            $incomeStatement = $this->reportsService->generateIncomeStatement($company, [
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
                'currency' => $request->get('currency'),
            ]);

            return response()->json($incomeStatement);

        } catch (\Exception $e) {
            Log::error('Income statement API failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to generate income statement'], 500);
        }
    }

    /**
     * Get trial balance filters from request
     */
    private function getTrialBalanceFilters(Request $request): array
    {
        return [
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'currency' => $request->get('currency'),
            'include_zero_balances' => $request->boolean('include_zero_balances', false),
        ];
    }

    /**
     * Get balance sheet filters from request
     */
    private function getBalanceSheetFilters(Request $request): array
    {
        return [
            'date_to' => $request->get('date_to'),
            'currency' => $request->get('currency'),
        ];
    }

    /**
     * Get income statement filters from request
     */
    private function getIncomeStatementFilters(Request $request): array
    {
        return [
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'currency' => $request->get('currency'),
        ];
    }
}
