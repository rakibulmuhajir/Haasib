<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Umrah\Commands\AssignAgentPricingCategory;
use App\Modules\Umrah\Commands\SaveCommercialRate;
use App\Modules\Umrah\Commands\SavePricingCategory;
use App\Modules\Umrah\Commands\SetCommercialPricingStatus;
use App\Modules\Umrah\Http\Requests\SaveCommercialRateRequest;
use App\Modules\Umrah\Http\Requests\SavePricingCategoryRequest;
use App\Modules\Umrah\Http\Requests\UpdateAgentPricingCategoryRequest;
use App\Modules\Umrah\Http\Requests\UpdatePricingStatusRequest;
use App\Modules\Umrah\Services\CommercialPricingCatalog;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Inertia\Inertia;
use Inertia\Response;

class CommercialPricingController extends Controller
{
    public function __construct(private readonly CommercialPricingCatalog $catalog) {}

    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_PRICING_VIEW), 403);

        return Inertia::render('Umrah/Settings/Pricing', [
            'company' => [
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            ...$this->catalog->payload($company->id),
            'canManagePricing' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_PRICING_UPDATE),
            'focus' => [
                'agent_id' => $request->string('agent_id')->toString() ?: null,
                'target_id' => $request->string('target_id')->toString() ?: null,
            ],
        ]);
    }

    public function storeCategory(SavePricingCategoryRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        Bus::dispatch(new SavePricingCategory($company->id, $request->validated()));

        return back()->with('success', 'Pricing category created.');
    }

    public function updateCategory(SavePricingCategoryRequest $request, string $companySlug, string $category): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        Bus::dispatch(new SavePricingCategory($company->id, $request->validated(), $category));

        return back()->with('success', 'Pricing category updated.');
    }

    public function updateCategoryStatus(UpdatePricingStatusRequest $request, string $companySlug, string $category): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        Bus::dispatch(new SetCommercialPricingStatus($company->id, 'category', $category, $request->boolean('is_active')));

        return back()->with('success', $request->boolean('is_active') ? 'Pricing category activated.' : 'Pricing category deactivated.');
    }

    public function storeRate(SaveCommercialRateRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        Bus::dispatch(new SaveCommercialRate($company->id, $request->user()?->id, $request->validated()));

        return back()->with('success', 'Pricing rule created.');
    }

    public function updateRate(SaveCommercialRateRequest $request, string $companySlug, string $rate): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        Bus::dispatch(new SaveCommercialRate($company->id, $request->user()?->id, $request->validated(), $rate));

        return back()->with('success', 'Pricing rule updated.');
    }

    public function updateRateStatus(UpdatePricingStatusRequest $request, string $companySlug, string $rate): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        Bus::dispatch(new SetCommercialPricingStatus($company->id, 'rate', $rate, $request->boolean('is_active')));

        return back()->with('success', $request->boolean('is_active') ? 'Pricing rule activated.' : 'Pricing rule deactivated.');
    }

    public function assignAgentCategory(UpdateAgentPricingCategoryRequest $request, string $companySlug, string $agent): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        Bus::dispatch(new AssignAgentPricingCategory($company->id, $agent, $request->validated('pricing_category_id')));

        return back()->with('success', 'Agent pricing category updated.');
    }
}
