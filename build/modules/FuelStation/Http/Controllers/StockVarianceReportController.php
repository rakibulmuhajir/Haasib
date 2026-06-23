<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Services\StockVarianceReportService;
use App\Services\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockVarianceReportController extends Controller
{
    public function __construct(private readonly StockVarianceReportService $reportService)
    {
    }

    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();

        $startDate = $this->date($request->query('start_date'), now()->startOfMonth());
        $endDate = $this->date($request->query('end_date'), now());
        if ($startDate->greaterThan($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $report = $this->reportService->run(
            $company->id,
            $startDate->toDateString(),
            $endDate->toDateString(),
            (string) $request->query('tank_id', 'all'),
            (string) $request->query('product_id', 'all'),
            (string) $request->query('variance_type', 'all'),
            (string) $request->query('claim_status', 'all'),
        );

        return Inertia::render('FuelStation/Reports/StockVariance', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency ?? 'PKR',
            ],
            ...$report,
        ]);
    }

    public function legacyRedirect(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        return redirect()->route('fuel.reports.stock-variance', [
            'company' => $company->slug,
            ...$request->query(),
        ]);
    }

    private function date(mixed $value, Carbon $fallback): Carbon
    {
        if (!is_string($value) || trim($value) === '') {
            return $fallback->copy();
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return $fallback->copy();
        }
    }
}
