<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\ProfitLossReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfitLossReportController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $start = $request->query('start') ?? now()->startOfMonth()->toDateString();
        $end = $request->query('end') ?? now()->toDateString();

        $report = app(ProfitLossReportService::class)->run($company->id, $start, $end);

        return Inertia::render('accounting/reports/ProfitLoss', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'filters' => [
                'start' => $start,
                'end' => $end,
            ],
            'report' => $report,
        ]);
    }
}

