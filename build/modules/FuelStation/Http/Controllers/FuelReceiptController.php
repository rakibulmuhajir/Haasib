<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FuelReceiptController extends Controller
{
    /**
     * List fuel receipts (tank fills from suppliers).
     */
    public function index(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        return $this->redirectToBills($company->slug);
    }

    /**
     * Show create form for fuel receipt.
     */
    public function create(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        return $this->redirectToBills($company->slug);
    }

    /**
     * Store a new fuel receipt.
     */
    public function store(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        return $this->redirectToBills($company->slug);
    }

    /**
     * Show a specific fuel receipt.
     */
    public function show(Request $request, string $company, string $receipt): RedirectResponse
    {
        $companyModel = app(CurrentCompany::class)->get();
        return $this->redirectToBills($companyModel->slug);
    }

    private function redirectToBills(string $companySlug): RedirectResponse
    {
        return redirect("/{$companySlug}/bills")
            ->with('success', 'Fuel receipts are now recorded through Bills → Receive Goods.');
    }
}
