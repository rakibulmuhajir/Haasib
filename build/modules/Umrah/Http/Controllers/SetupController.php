<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Driver;
use App\Modules\Umrah\Models\Hotel;
use App\Modules\Umrah\Models\PricingCategory;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\TransportService;
use App\Modules\Umrah\Models\VisaVendor;
use App\Services\CurrentCompany;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_PRICING_VIEW), 403);

        return Inertia::render('Umrah/Settings/Index', [
            'company' => [
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'counts' => [
                'agents' => Agent::where('company_id', $company->id)->where('is_active', true)->count(),
                'visa_vendors' => VisaVendor::where('company_id', $company->id)->where('service_type', '!=', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->where('is_active', true)->count(),
                'transport_vendors' => VisaVendor::where('company_id', $company->id)->where('service_type', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->where('is_active', true)->count(),
                'transport_services' => TransportService::where('company_id', $company->id)->where('is_active', true)->count(),
                'transport_fares' => TransportFare::where('company_id', $company->id)->where('is_active', true)->count(),
                'hotels' => Hotel::where('company_id', $company->id)->where('is_active', true)->count(),
                'drivers' => Driver::where('company_id', $company->id)->where('is_active', true)->count(),
                'pricing_categories' => PricingCategory::where('company_id', $company->id)->where('is_active', true)->count(),
            ],
        ]);
    }
}
