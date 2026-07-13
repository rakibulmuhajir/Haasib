<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\VisaGroup;
use App\Services\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function earnings(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        $start = $request->input('start', Carbon::today()->startOfMonth()->toDateString());
        $end = $request->input('end', Carbon::today()->endOfMonth()->toDateString());

        $groups = VisaGroup::where('company_id', $company->id)
            ->with('agent:id,name')
            ->whereBetween('created_at', [Carbon::parse($start)->startOfDay(), Carbon::parse($end)->endOfDay()])
            ->where('status', '!=', VisaGroup::STATUS_CANCELLED)
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Umrah/Reports/Earnings', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'filters' => ['start' => $start, 'end' => $end],
            'summary' => [
                'groups' => $groups->count(),
                'receivable' => (float) $groups->sum('total_receivable'),
                'paid' => (float) $groups->sum('total_paid'),
                'balance' => (float) $groups->sum('balance'),
                'cost' => (float) $groups->sum(fn ($group) => (float) $group->visa_cost_amount + (float) $group->transport_cost_amount),
                'profit' => (float) $groups->sum('profit'),
                'agent_balance' => (float) Agent::where('company_id', $company->id)->sum('balance'),
                'payments' => (float) GroupPayment::where('company_id', $company->id)
                    ->where('direction', GroupPayment::DIRECTION_RECEIVED)
                    ->whereBetween('payment_date', [$start, $end])
                    ->sum('base_amount'),
            ],
            'groups' => $groups,
        ]);
    }
}
