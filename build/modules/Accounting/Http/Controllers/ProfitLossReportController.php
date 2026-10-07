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

        $evidence = app(\App\Modules\Accounting\Services\StatementValueTrail::class);
        $includeTrail = $evidence->available($request->user()) && $request->header('X-Inertia-Partial-Component') === 'accounting/reports/ProfitLoss' && in_array('valueTrail', explode(',', $request->header('X-Inertia-Partial-Data', '')), true);
        try {
            $report = app(ProfitLossReportService::class)->run($company->id, $start, $end, $includeTrail);
        } catch (\Throwable $e) {
            if (! $includeTrail) {
                throw $e;
            }
            report($e);
            $report = app(ProfitLossReportService::class)->run($company->id, $start, $end);
            $report['valueTrail'] = ['error' => 'Statement evidence could not be loaded. Please try again.'];
        }
        $graph = $report['valueTrail'] ?? null;
        unset($report['valueTrail']);

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
            'valueTrailsAvailable' => app(\App\Modules\Accounting\Services\StatementValueTrail::class)->available($request->user()),
            'valueTrail' => Inertia::optional(fn () => app(\App\Modules\Accounting\Services\StatementValueTrail::class)->present(fn () => $graph, $request->user(), $company->slug)),
            'report' => $report,
        ]);
    }
}
