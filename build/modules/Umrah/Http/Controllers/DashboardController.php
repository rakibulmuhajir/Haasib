<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Services\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private TravelAccessService $access) {}

    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_GROUP_VIEW), 403);
        $today = Carbon::today();
        $isMember = $this->access->isAgentMember($company->id, $request->user());
        $agentId = $isMember ? $this->access->linkedAgent($company->id, $request->user())?->id : null;

        $groups = VisaGroup::where('company_id', $company->id)
            ->when($isMember, fn ($query) => $agentId ? $query->where('agent_id', $agentId) : $query->whereRaw('1 = 0'))
            ->where('status', '!=', VisaGroup::STATUS_CANCELLED);

        return Inertia::render('Umrah/Dashboard/Index', [
            'company' => $this->companyPayload($company),
            'summary' => [
                'active_groups' => (clone $groups)->count(),
                'passports_in_process' => Passenger::where('company_id', $company->id)
                    ->when($isMember, fn ($query) => $agentId ? $query->whereHas('group', fn ($group) => $group->where('agent_id', $agentId)) : $query->whereRaw('1 = 0'))
                    ->whereNotIn('visa_status', [Passenger::STATUS_DELIVERED, Passenger::STATUS_REJECTED])
                    ->count(),
                'agent_balance' => (float) Agent::where('company_id', $company->id)->when($isMember, fn ($query) => $agentId ? $query->whereKey($agentId) : $query->whereRaw('1 = 0'))->sum('balance'),
                'month_charges' => (float) (clone $groups)
                    ->whereBetween('created_at', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
                    ->sum('total_receivable'),
                'month_profit' => $isMember ? 0 : (float) (clone $groups)
                    ->whereBetween('created_at', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
                    ->sum('profit'),
                'payments_this_month' => (float) GroupPayment::where('company_id', $company->id)
                    ->where('status', GroupPayment::STATUS_POSTED)
                    ->when($isMember, fn ($query) => $agentId ? $query->where('agent_id', $agentId)->where('direction', GroupPayment::DIRECTION_RECEIVED) : $query->whereRaw('1 = 0'))
                    ->where('direction', GroupPayment::DIRECTION_RECEIVED)
                    ->whereBetween('payment_date', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
                    ->sum('base_amount'),
            ],
            'upcomingGroups' => VisaGroup::where('company_id', $company->id)
                ->when($isMember, fn ($query) => $agentId ? $query->where('agent_id', $agentId) : $query->whereRaw('1 = 0'))
                ->with('agent:id,name')
                ->where('status', '!=', VisaGroup::STATUS_CANCELLED)
                ->whereNotNull('travel_date')
                ->whereDate('travel_date', '>=', $today)
                ->orderBy('travel_date')
                ->limit(8)
                ->get(['id', 'agent_id', 'group_number', 'name', 'travel_date', 'passenger_count', 'balance']),
            'recentGroups' => VisaGroup::where('company_id', $company->id)
                ->when($isMember, fn ($query) => $agentId ? $query->where('agent_id', $agentId) : $query->whereRaw('1 = 0'))
                ->with('agent:id,name')
                ->where('status', '!=', VisaGroup::STATUS_CANCELLED)
                ->orderByDesc('created_at')
                ->limit(8)
                ->get(['id', 'agent_id', 'group_number', 'name', 'travel_date', 'passenger_count', 'balance', 'created_at']),
            'isAgent' => $isMember,
            'capabilities' => [
                'canCreateGroup' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_GROUP_CREATE),
                'canCreateVoucher' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_VOUCHER_CREATE),
                'canViewAccounting' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_GROUP_ACCOUNTING_VIEW),
                'canViewAgents' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_AGENT_VIEW),
                'canViewVendors' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_VENDOR_VIEW),
                'canViewReports' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_REPORT_VIEW),
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
