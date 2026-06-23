<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Services\CurrentCompany;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();
        $today = Carbon::today();

        $groups = VisaGroup::where('company_id', $company->id)
            ->where('status', '!=', VisaGroup::STATUS_CANCELLED);

        return Inertia::render('Umrah/Dashboard/Index', [
            'company' => $this->companyPayload($company),
            'summary' => [
                'active_groups' => (clone $groups)->whereNotIn('status', [VisaGroup::STATUS_CLOSED])->count(),
                'passports_in_process' => Passenger::where('company_id', $company->id)
                    ->whereNotIn('visa_status', [Passenger::STATUS_DELIVERED, Passenger::STATUS_REJECTED])
                    ->count(),
                'agent_balance' => (float) Agent::where('company_id', $company->id)->sum('balance'),
                'month_revenue' => (float) (clone $groups)
                    ->whereBetween('created_at', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
                    ->sum('total_receivable'),
                'month_profit' => (float) (clone $groups)
                    ->whereBetween('created_at', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
                    ->sum('profit'),
                'payments_this_month' => (float) GroupPayment::where('company_id', $company->id)
                    ->whereBetween('payment_date', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
                    ->sum('amount'),
            ],
            'upcomingGroups' => VisaGroup::where('company_id', $company->id)
                ->with('agent:id,name')
                ->whereNotNull('travel_date')
                ->whereDate('travel_date', '>=', $today)
                ->orderBy('travel_date')
                ->limit(8)
                ->get(),
            'recentGroups' => VisaGroup::where('company_id', $company->id)
                ->with('agent:id,name')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get(),
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
