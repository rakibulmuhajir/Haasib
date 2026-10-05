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
        $known = collect($products)->pluck('id')->all();
        $picked = array_values(array_intersect($known, array_filter(explode(',', (string) $request->query('items', '')))));
        $from = $startDate->toDateString();
        $to = $endDate->toDateString();

        // "All in {category}": every sellable item filed under the category.
        $categories = \Illuminate\Support\Facades\DB::table('inv.item_categories as c')
            ->where('c.company_id', $company->id)->whereNull('c.deleted_at')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('inv.items as i')->whereColumn('i.category_id', 'c.id')
                ->where('i.is_sellable', true)->whereNull('i.deleted_at'))
            ->orderBy('c.name')->get(['c.id', 'c.name'])->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all();
        $categoryId = (string) $request->query('category', '');
        $categoryItems = [];
        if ($categoryId !== '' && collect($categories)->contains('id', $categoryId)) {
            $categoryItems = \Illuminate\Support\Facades\DB::table('inv.items')->where('company_id', $company->id)
                ->where('category_id', $categoryId)->where('is_sellable', true)->whereNull('deleted_at')
                ->pluck('id')->all();
            $categoryItems = array_values(array_intersect($known, $categoryItems));
        } else {
            $categoryId = '';
        }

        if ($categoryId !== '') {
            $report = $this->service->runMany($company->id, $categoryItems, $from, $to);
        } elseif ($itemId === 'all') {
            $report = $this->service->runMany($company->id, $known, $from, $to);
        } elseif ($picked) {
            $report = $this->service->runMany($company->id, $picked, $from, $to);
        } else {
            if (! in_array($itemId, $known, true)) {
                $itemId = $products[0]['id'] ?? '';
            }
            $report = $this->service->run($company->id, $itemId, $from, $to);
        }

        return Inertia::render('FuelStation/Reports/StockStatement', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency ?? 'PKR',
            ],
            'filters' => [
                'item' => $categoryId !== '' ? 'cat:'.$categoryId : (! empty($report['combined']) ? $report['item']['id'] : $itemId),
                'category' => $categoryId,
                'items' => $picked,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            // A station that carries last month's stock into the next (Settings: month-end stock
            // valuation at the next month's rate) reads its statement with the opening counted in.
            'includeOpeningDefault' => \Illuminate\Support\Facades\DB::table('fuel.station_settings')
                ->where('company_id', $company->id)->value('month_end_stock_valuation') === 'next_month_purchase_rate',
            // One tank fuel: how its profit over the range is worked out (bottom of the page).
            'categories' => $categories,
            'profitWorking' => empty($report['combined']) && $itemId !== '' && $categoryId === ''
                ? $this->service->profitWorking($company->id, $itemId, $from, $to, $report)
                : null,
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
