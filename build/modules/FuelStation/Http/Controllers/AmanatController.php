<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Customer;
use App\Modules\FuelStation\Http\Requests\AmanatDepositRequest;
use App\Modules\FuelStation\Http\Requests\AmanatWithdrawRequest;
use App\Modules\FuelStation\Models\AmanatTransaction;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Modules\FuelStation\Services\AmanatService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AmanatController extends Controller
{
    public function __construct(
        private AmanatService $amanatService
    ) {}

    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        // Get all amanat holders with their profiles and customers
        $amanatHolders = CustomerProfile::where('company_id', $company->id)
            ->where('is_amanat_holder', true)
            ->with('customer')
            ->orderByDesc('amanat_balance')
            ->paginate(50);

        // Get summary
        $summary = $this->amanatService->getAmanatSummary($company->id);

        return Inertia::render('FuelStation/Amanat/Index', [
            'amanatHolders' => $amanatHolders,
            'summary' => $summary,
        ]);
    }

    public function show(Customer $customer): Response
    {
        $company = app(CurrentCompany::class)->get();

        // Get or create profile
        $profile = CustomerProfile::getOrCreateForCustomer($company->id, $customer->id);

        // Get transaction history
        $transactions = AmanatTransaction::where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->with(['fuelItem', 'recordedBy'])
            ->orderByDesc('created_at')
            ->paginate(50);

        return Inertia::render('FuelStation/Amanat/Show', [
            'customer' => $customer,
            'profile' => $profile,
            'transactions' => $transactions,
        ]);
    }

    public function deposit(AmanatDepositRequest $request, Customer $customer): RedirectResponse
    {
        try {
            $this->amanatService->deposit($customer, $request->validated());

            return redirect()->back()->with('success', 'Amanat deposit recorded successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function withdraw(AmanatWithdrawRequest $request, Customer $customer): RedirectResponse
    {
        try {
            $this->amanatService->withdraw($customer, $request->validated());

            return redirect()->back()->with('success', 'Amanat withdrawal processed successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
