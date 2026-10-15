<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\CustomerPeriodDiscountRequest;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Services\CustomerPeriodDiscountService;
use App\Modules\Accounting\Services\CustomerStatementService;
use App\Services\CommandBus;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerPeriodDiscountController extends Controller
{
    public function create(CustomerPeriodDiscountRequest $request, string $company, string $customer): Response
    {
        $company = app(CurrentCompany::class)->get();
        $customer = Customer::where('company_id', $company->id)->findOrFail($customer);
        $preview = app(CustomerPeriodDiscountService::class)->preview($company, $customer, $request->validated());

        return Inertia::render('accounting/customers/PeriodDiscount', [
            'company' => $company->only(['id', 'slug', 'name', 'base_currency']),
            'customer' => $customer->only(['id', 'name']), 'filters' => $request->validated(),
            // The complete immutable source list is saved on post; initial preview stays small.
            'preview' => collect($preview)->except('snapshot')->all() + [
                'eligible_amount' => $preview['snapshot']['eligible_amount'], 'litres' => $preview['snapshot']['litres'],
                'token' => $preview['snapshot']['token'],
                'closing_balance' => app(CustomerStatementService::class)->statement($customer, null, $request->validated('credit_date'))['closing_balance'],
            ],
        ]);
    }

    public function store(CustomerPeriodDiscountRequest $request, string $company, string $customer): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        try {
            $result = app(CommandBus::class)->dispatch('customer.period_discount', $request->validated() + ['customer_id' => $customer], $request->user());

            return redirect()->route('credit-notes.show', ['company' => $company->slug, 'credit_note' => $result['data']['id']])->with('success', $result['message']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The discount could not be posted. '.$e->getMessage())->withInput();
        }
    }
}
