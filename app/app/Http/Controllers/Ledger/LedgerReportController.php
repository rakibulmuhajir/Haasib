<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LedgerReportController extends Controller
{
    public function trialBalance(Request $request)
    {
        return Inertia::render('Ledger/Reports/TrialBalance');
    }

    public function balanceSheet(Request $request)
    {
        return Inertia::render('Ledger/Reports/BalanceSheet');
    }

    public function incomeStatement(Request $request)
    {
        return Inertia::render('Ledger/Reports/IncomeStatement');
    }
}
