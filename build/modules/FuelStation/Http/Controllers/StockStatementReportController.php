<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Services\StockStatementService;
use App\Services\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockStatementReportController extends Controller
{
    public function __construct(private readonly StockStatementService $service)
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

        // The product list comes with every run, empty or not.
        $products = $this->service->run($company->id, '', $startDate->toDateString(), $endDate->toDateString())['products'];
        $itemId = (string) $request->query('item', '');
        if (! collect($products)->contains('id', $itemId)) {
            $itemId = $products[0]['id'] ?? '';
        }

        $report = $this->service->run($company->id, $itemId, $startDate->toDateString(), $endDate->toDateString());

        return Inertia::render('FuelStation/Reports/StockStatement', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency ?? 'PKR',
            ],
            'filters' => [
                'item' => $itemId,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            ...$report,
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
