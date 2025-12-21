<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Http\Requests\StoreFuelSaleRequest;
use App\Modules\FuelStation\Services\FuelSaleService;
use Illuminate\Http\RedirectResponse;

class FuelSaleController extends Controller
{
    public function __construct(
        private FuelSaleService $fuelSaleService
    ) {}

    public function store(StoreFuelSaleRequest $request): RedirectResponse
    {
        try {
            $this->fuelSaleService->createSale($request->validated());

            return redirect()->back()->with('success', 'Fuel sale recorded successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
