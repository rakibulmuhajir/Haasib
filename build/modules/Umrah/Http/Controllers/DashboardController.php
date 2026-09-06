<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Dashboard\DashboardPresenter;
use App\Http\Controllers\Controller;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private TravelAccessService $access) {}

    public function index(Request $request, DashboardPresenter $presenter): Response|RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $role = $this->access->companyRole($company->id, $request->user());
        if (in_array($role, config('umrah.operations.landing_roles', []), true)
            && $request->user()?->hasCompanyPermission(Permissions::UMRAH_OPERATIONS_VIEW)) {
            return redirect()->route('umrah.operations.index', ['company' => $company->slug]);
        }

        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_GROUP_VIEW), 403);

        $isMember = $this->access->isAgentMember($company->id, $request->user());
        $isOperations = $role === 'operations';

        return Inertia::render('Umrah/Dashboard/Index', [
            'company' => $this->companyPayload($company),
            // One `dashboard` prop, not a bare `tabs` array: DashboardTabs
            // switches tab with an Inertia partial reload scoped to
            // only: ['dashboard'], so the active tab and the tabs it selects
            // between have to travel under a single top-level key or the
            // reload fetches nothing.
            'dashboard' => $this->dashboardPayload($presenter, $request, $company),
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

    /**
     * @return array{tabs: array<int, mixed>, activeTab: string}
     */
    private function dashboardPayload(DashboardPresenter $presenter, Request $request, $company): array
    {
        $tabs = $presenter->present(
            $request->user(),
            $company,
            'umrah',
            $request->string('tab')->toString() ?: null,
        );

        return [
            'tabs' => $tabs,
            'activeTab' => (string) (collect($tabs)->firstWhere('active', true)['key'] ?? ''),
        ];
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
