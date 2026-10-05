<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreCustomerCategoryRequest;
use App\Modules\Accounting\Http\Requests\UpdateCustomerCategoryRequest;
use App\Modules\Accounting\Models\CustomerCategory;
use App\Services\CommandBus;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomerCategoryController extends Controller
{
    public function index(): Response
    {
        $company = CompanyContext::getCompany();

        $categories = CustomerCategory::where('company_id', $company->id)
            ->withCount(['customers'])
            ->orderBy('name')
            ->get(['id', 'name', 'description'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'customers_count' => $c->customers_count,
            ]);

        return Inertia::render('accounting/customer-categories/Index', [
            'company' => ['id' => $company->id, 'name' => $company->name, 'slug' => $company->slug],
            'categories' => $categories,
        ]);
    }

    public function store(StoreCustomerCategoryRequest $request): RedirectResponse
    {
        $result = app(CommandBus::class)->dispatch('customer_category.create', $request->validated(), $request->user());

        return back()->with('success', $result['message']);
    }

    public function update(UpdateCustomerCategoryRequest $request): RedirectResponse
    {
        $params = array_merge($request->validated(), ['id' => (string) $request->route('category')]);
        $result = app(CommandBus::class)->dispatch('customer_category.update', $params, $request->user());

        return back()->with('success', $result['message']);
    }

    public function destroy(\Illuminate\Http\Request $request): RedirectResponse
    {
        $result = app(CommandBus::class)->dispatch('customer_category.delete', ['id' => (string) $request->route('category')], $request->user());

        return back()->with('success', $result['message']);
    }
}
