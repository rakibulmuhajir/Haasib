<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Services\StationPerformanceReportService;
use App\Services\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StationPerformanceReportController extends Controller
{
    public function __construct(private readonly StationPerformanceReportService $reportService)
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

        $groupBy = (string) $request->query('group_by', 'day');
        $product = (string) $request->query('product', 'all');

        $report = $this->reportService->run(
            $company->id,
            $startDate->toDateString(),
            $endDate->toDateString(),
            $groupBy,
            $product
        );

        return Inertia::render('FuelStation/Reports/StationPerformance', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency ?? 'PKR',
            ],
            ...$report,
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
