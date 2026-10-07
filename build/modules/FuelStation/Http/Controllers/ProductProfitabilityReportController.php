<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Http\Requests\ViewProductProfitabilityRequest;
use App\Modules\FuelStation\Services\ProductProfitabilityReportService;
use App\Modules\FuelStation\Services\ProfitValueTrailPresenter;
use App\Services\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ProductProfitabilityReportController extends Controller
{
    public function __construct(private readonly ProductProfitabilityReportService $reportService) {}

    public function index(ViewProductProfitabilityRequest $request): Response|RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $startDate = $this->date($request->query('start_date'), now()->startOfMonth());
        $endDate = $this->date($request->query('end_date'), now());
        if ($startDate->greaterThan($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $includeTrail = $request->user()->showsValueTrails()
            && $request->header('X-Inertia-Partial-Component') === 'FuelStation/Reports/ProductProfitability'
            && in_array('valueTrail', explode(',', (string) $request->header('X-Inertia-Partial-Data')), true);

        try {
            $report = $this->reportService->run(
                $company->id,
                $startDate->toDateString(),
                $endDate->toDateString(),
                (string) $request->query('group_by', 'day'),
                (string) $request->query('product', 'all'),
                $request->query('category_id') ? (string) $request->query('category_id') : null,
                $includeTrail,
            );
            $trail = isset($report['valueTrail'])
                ? app(ProfitValueTrailPresenter::class)->present($report['valueTrail'], $company, $request->user())
                : null;
        } catch (\Throwable $exception) {
            Log::error('Could not load Fuel Profit.', ['company_id' => $company->id, 'exception' => $exception]);
            if (! $includeTrail) {
                return redirect('/'.$company->slug.'/fuel/dashboard')
                    ->with('error', 'Could not load Fuel Profit. Please try again.');
            }

            return Inertia::render('FuelStation/Reports/ProductProfitability', [
                'valueTrail' => Inertia::optional(fn () => ['error' => 'Could not load this calculation. Please try again.']),
            ]);
        }

        unset($report['valueTrail']);

        return Inertia::render('FuelStation/Reports/ProductProfitability', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency ?? 'PKR',
            ],
            ...$report,
            'valueTrail' => Inertia::optional(fn () => $trail),
        ]);
    }

    private function date(mixed $value, Carbon $fallback): Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return $fallback->copy();
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return $fallback->copy();
        }
    }
}
