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

        $evidence = app(\App\Modules\Accounting\Services\StatementValueTrail::class);
        $includeTrail = $evidence->available($request->user()) && $request->header('X-Inertia-Partial-Component') === 'accounting/reports/TrialBalance' && in_array('valueTrail', explode(',', $request->header('X-Inertia-Partial-Data', '')), true);
        try {
            $report = app(TrialBalanceReportService::class)->run($company->id, $asOf, $includeTrail);
        } catch (\Throwable $e) {
            if (! $includeTrail) {
                throw $e;
            }
            report($e);
            $report = app(TrialBalanceReportService::class)->run($company->id, $asOf);
            $report['valueTrail'] = ['error' => 'Statement evidence could not be loaded. Please try again.'];
        }
        $graph = $report['valueTrail'] ?? null;
        unset($report['valueTrail']);

        return Inertia::render('accounting/reports/TrialBalance', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'filters' => ['as_of' => $asOf],
            'valueTrailsAvailable' => $evidence->available($request->user()),
            'valueTrail' => Inertia::optional(fn () => $evidence->present(fn () => $graph, $request->user(), $company->slug)),
            'report' => $report,
        ]);
    }
}
