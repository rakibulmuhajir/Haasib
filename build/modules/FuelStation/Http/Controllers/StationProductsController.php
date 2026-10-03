<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Services\StationProductsService;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Products & stock: what the station sells, what it costs and what is on hand, in one register.
 * New products are added from here (fuel.products.setup).
 */
class StationProductsController extends Controller
{
    public function index(Request $request, StationProductsService $service): Response
    {
        $company = app(CurrentCompany::class)->get();
        $user = $request->user();
        abort_unless(
            $user->isGodMode()
                || $user->hasCompanyPermission(Permissions::ITEM_VIEW)
                || $user->hasCompanyPermission(Permissions::STOCK_VIEW),
            403
        );

        $result = $service->run($company->id);

        return Inertia::render('FuelStation/Products/Index', [
            'company' => ['id' => $company->id, 'name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'rows' => $result['rows'],
            'summary' => ['total' => count($result['rows']), 'low' => $result['low_count']],
            'period' => ['from' => Carbon::today()->startOfMonth()->toDateString(), 'to' => Carbon::today()->toDateString()],
            'fuelTanks' => Warehouse::where('company_id', $company->id)
                ->where('warehouse_type', 'tank')->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'capacity', 'linked_item_id'])
                ->toArray(),
        ]);
    }
}
