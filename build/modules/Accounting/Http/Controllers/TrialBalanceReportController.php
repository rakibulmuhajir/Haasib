<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\TrialBalanceReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrialBalanceReportController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CompanyContext::getCompany();
        $asOf = $request->query('as_of') ?? now()->toDateString();

        return Inertia::render('accounting/reports/TrialBalance', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'filters' => ['as_of' => $asOf],
            'report' => app(TrialBalanceReportService::class)->run($company->id, $asOf),
        ]);
    }
}
