<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreCustomerUnitRequest;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\CustomerUnit;
use Illuminate\Http\RedirectResponse;

/** A customer's own vehicles, sites, rooms or departments -- picked on a sale instead of typed. */
class CustomerUnitController extends Controller
{
    public function store(StoreCustomerUnitRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();
        $customer = Customer::where('company_id', $company->id)->findOrFail($request->route('customer'));

        CustomerUnit::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'name' => trim($request->validated('name')),
        ]);

        return redirect()->back()->with('success', 'Unit added');
    }

    public function update(StoreCustomerUnitRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();
        $customer = Customer::where('company_id', $company->id)->findOrFail($request->route('customer'));
        $unit = CustomerUnit::where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->findOrFail($request->route('unit'));

        $unit->update([
            'name' => trim($request->validated('name')),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $unit->is_active,
        ]);

        return redirect()->back()->with('success', 'Unit updated');
    }
}
