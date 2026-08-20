<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Dashboard\DashboardPresenter;
use App\Http\Controllers\Controller;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Services\CurrentCompany;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private TravelAccessService $access) {}

    public function index(Request $request, DashboardPresenter $presenter): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_GROUP_VIEW), 403);

        $isMember = $this->access->isAgentMember($company->id, $request->user());
        $isOperations = $this->access->companyRole($company->id, $request->user()) === 'operations';

        return Inertia::render('Umrah/Dashboard/Index', [
            'company' => $this->companyPayload($company),
            'tabs' => $presenter->present($request->user(), $company, 'umrah', $request->string('tab')->toString() ?: null),
            'isAgent' => $isMember,
            'isOperations' => $isOperations,
            'capabilities' => [
                'canCreateGroup' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_GROUP_CREATE),
                'canCreateVoucher' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_VOUCHER_CREATE),
                'canViewAccounting' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_GROUP_ACCOUNTING_VIEW),
                'canViewAgents' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_AGENT_VIEW),
                'canViewVendors' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_VENDOR_VIEW),
                'canViewReports' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_REPORT_VIEW),
                'canViewPayments' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_PAYMENT_VIEW),
            ],
        ]);
    }

    private function companyPayload($company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'base_currency' => $company->base_currency,
        ];
    }
}
