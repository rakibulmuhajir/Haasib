<?php

namespace App\Http\Controllers;

use App\Models\TaxComponent;
use App\Models\TaxReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TaxDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display the tax dashboard.
     */
    public function index(Request $request)
    {
        $companyId = Auth::user()->current_company_id;
        $period = $request->input('period', 'current_year'); // current_month, current_quarter, current_year, last_year
        $dateRange = $this->getDateRange($period);

        // Summary metrics
        $totalSalesTax = TaxComponent::where('company_id', $companyId)
            ->notReversed()
            ->whereHas('taxRate', function ($q) {
                $q->where('tax_type', 'sales')->orWhere('tax_type', 'both');
            })
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->sum('tax_amount');

        $totalPurchaseTax = TaxComponent::where('company_id', $companyId)
            ->notReversed()
            ->whereHas('taxRate', function ($q) {
                $q->where('tax_type', 'purchase')->orWhere('tax_type', 'both');
            })
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->sum('tax_amount');

        $netTaxDue = $totalSalesTax - $totalPurchaseTax;

        $totalTaxCollected = TaxComponent::where('company_id', $companyId)
            ->notReversed()
            ->paid()
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->sum('paid_amount');

        // Tax returns status
        $returnsStatus = TaxReturn::where('company_id', $companyId)
            ->whereBetween('filing_period_start', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('
                COUNT(*) as total_returns,
                COUNT(CASE WHEN status = \'draft\' THEN 1 END) as draft_returns,
                COUNT(CASE WHEN status = \'prepared\' THEN 1 END) as prepared_returns,
                COUNT(CASE WHEN status = \'filed\' THEN 1 END) as filed_returns,
                COUNT(CASE WHEN status = \'paid\' THEN 1 END) as paid_returns,
                COUNT(CASE WHEN status = \'overdue\' THEN 1 END) as overdue_returns,
                SUM(CASE WHEN payment_status = \'unpaid\' THEN total_amount_due ELSE 0 END) as amount_due
            ')
            ->first();

        // Top tax rates by usage
        $topTaxRates = TaxComponent::where('company_id', $companyId)
            ->notReversed()
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('
                tax_rate_id,
                tax_name,
                tax_code,
                SUM(tax_amount) as total_tax,
                COUNT(*) as usage_count
            ')
            ->groupBy('tax_rate_id', 'tax_name', 'tax_code')
            ->orderBy('total_tax', 'desc')
            ->limit(10)
            ->get();

        // Monthly tax trend
        $monthlyTrend = TaxComponent::where('tax_components.company_id', $companyId)
            ->notReversed()
            ->whereBetween('tax_components.created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('
                DATE_TRUNC(\'month\', tax_components.created_at) as month,
                SUM(CASE WHEN tax_rates.tax_type IN (\'sales\', \'both\') THEN tax_amount ELSE 0 END) as sales_tax,
                SUM(CASE WHEN tax_rates.tax_type IN (\'purchase\', \'both\') THEN tax_amount ELSE 0 END) as purchase_tax
            ')
            ->join('acct.tax_rates', 'tax_components.tax_rate_id', '=', 'tax_rates.id')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Tax liability by jurisdiction
        $liabilityByJurisdiction = TaxComponent::where('tax_components.company_id', $companyId)
            ->notReversed()
            ->whereBetween('tax_components.created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('
                COALESCE(tax_rates.country_code, tax_rates.state_province, tax_rates.city, \'Other\') as jurisdiction,
                SUM(CASE WHEN tax_amount > paid_amount + credited_amount THEN tax_amount - (paid_amount + credited_amount) ELSE 0 END) as unpaid_tax,
                SUM(CASE WHEN tax_amount <= paid_amount + credited_amount THEN paid_amount ELSE 0 END) as paid_tax
            ')
            ->join('acct.tax_rates', 'tax_components.tax_rate_id', '=', 'tax_rates.id')
            ->groupBy('jurisdiction')
            ->orderBy('unpaid_tax', 'desc')
            ->get();

        // Upcoming tax returns
        $upcomingReturns = TaxReturn::where('company_id', $companyId)
            ->whereIn('status', ['draft', 'prepared'])
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(90))
            ->with(['taxAgency'])
            ->orderBy('due_date')
            ->limit(5)
            ->get();

        return Inertia::render('TaxDashboard/Index', [
            'summary' => [
                'total_sales_tax' => $totalSalesTax,
                'total_purchase_tax' => $totalPurchaseTax,
                'net_tax_due' => $netTaxDue,
                'total_tax_collected' => $totalTaxCollected,
            ],
            'returnsStatus' => $returnsStatus,
            'topTaxRates' => $topTaxRates,
            'monthlyTrend' => $monthlyTrend,
            'liabilityByJurisdiction' => $liabilityByJurisdiction,
            'upcomingReturns' => $upcomingReturns,
            'period' => $period,
            'dateRange' => $dateRange,
        ]);
    }

    /**
     * Get date range based on period
     */
    protected function getDateRange($period)
    {
        $now = now();

        switch ($period) {
            case 'current_month':
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth(),
                ];

            case 'current_quarter':
                $quarter = ceil($now->month / 3);
                $startMonth = (($quarter - 1) * 3) + 1;

                return [
                    'start' => $now->copy()->month($startMonth)->startOfMonth(),
                    'end' => $now->copy()->month($startMonth + 2)->endOfMonth(),
                ];

            case 'current_year':
                return [
                    'start' => $now->copy()->startOfYear(),
                    'end' => $now->copy()->endOfYear(),
                ];

            case 'last_year':
                return [
                    'start' => $now->copy()->subYear()->startOfYear(),
                    'end' => $now->copy()->subYear()->endOfYear(),
                ];

            default:
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth(),
                ];
        }
    }
}
