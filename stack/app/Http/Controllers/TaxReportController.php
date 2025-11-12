<?php

namespace App\Http\Controllers;

use App\Models\TaxComponent;
use App\Models\TaxRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TaxReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Sales tax report.
     */
    public function salesTax(Request $request)
    {
        $query = TaxComponent::where('company_id', Auth::user()->current_company_id)
            ->notReversed()
            ->whereHas('taxRate', function ($q) {
                $q->where('tax_type', 'sales')
                    ->orWhere('tax_type', 'both');
            })
            ->with(['taxRate', 'transaction']);

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $taxComponents = $query->orderBy('created_at', 'desc')
            ->paginate(50)
            ->withQueryString();

        $totals = $query->get([
            'taxable_amount',
            'tax_amount',
        ])->reduce(function ($carry, $component) {
            $carry['taxable_amount'] += $component->taxable_amount;
            $carry['tax_amount'] += $component->tax_amount;

            return $carry;
        }, ['taxable_amount' => 0, 'tax_amount' => 0]);

        return Inertia::render('TaxReports/SalesTax', [
            'taxComponents' => $taxComponents,
            'totals' => $totals,
            'filters' => $request->only(['date_from', 'date_to']),
        ]);
    }

    /**
     * Purchase tax report.
     */
    public function purchaseTax(Request $request)
    {
        $query = TaxComponent::where('company_id', Auth::user()->current_company_id)
            ->notReversed()
            ->whereHas('taxRate', function ($q) {
                $q->where('tax_type', 'purchase')
                    ->orWhere('tax_type', 'both');
            })
            ->with(['taxRate', 'transaction']);

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $taxComponents = $query->orderBy('created_at', 'desc')
            ->paginate(50)
            ->withQueryString();

        $totals = $query->get([
            'taxable_amount',
            'tax_amount',
        ])->reduce(function ($carry, $component) {
            $carry['taxable_amount'] += $component->taxable_amount;
            $carry['tax_amount'] += $component->tax_amount;

            return $carry;
        }, ['taxable_amount' => 0, 'tax_amount' => 0]);

        return Inertia::render('TaxReports/PurchaseTax', [
            'taxComponents' => $taxComponents,
            'totals' => $totals,
            'filters' => $request->only(['date_from', 'date_to']),
        ]);
    }

    /**
     * Tax liability report.
     */
    public function taxLiability(Request $request)
    {
        $query = TaxComponent::where('company_id', Auth::user()->current_company_id)
            ->notReversed()
            ->with(['taxRate', 'transaction']);

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $taxComponents = $query->orderBy('tax_period_start', 'desc')
            ->paginate(50)
            ->withQueryString();

        $liabilitySummary = $query->get()->groupBy(function ($component) {
            return $component->taxRate->name.' ('.$component->tax_period_start->format('M Y').')';
        })->map(function ($components) {
            return [
                'taxable_amount' => $components->sum('taxable_amount'),
                'tax_amount' => $components->sum('tax_amount'),
                'paid_amount' => $components->sum('paid_amount'),
                'unpaid_amount' => $components->sum(function ($component) {
                    return $component->getUnpaidAmountAttribute();
                }),
            ];
        });

        return Inertia::render('TaxReports/TaxLiability', [
            'taxComponents' => $taxComponents,
            'liabilitySummary' => $liabilitySummary,
            'filters' => $request->only(['date_from', 'date_to']),
        ]);
    }

    /**
     * Tax summary report.
     */
    public function taxSummary(Request $request)
    {
        $query = TaxComponent::where('company_id', Auth::user()->current_company_id)
            ->notReversed()
            ->with(['taxRate']);

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $summary = $query->get()->groupBy('tax_rate_id')->map(function ($components) {
            $taxRate = $components->first()->taxRate;

            return [
                'tax_rate' => $taxRate,
                'total_taxable' => $components->sum('taxable_amount'),
                'total_tax' => $components->sum('tax_amount'),
                'total_paid' => $components->sum('paid_amount'),
                'total_unpaid' => $components->sum(function ($component) {
                    return $component->getUnpaidAmountAttribute();
                }),
                'transaction_count' => $components->count(),
            ];
        })->values();

        return Inertia::render('TaxReports/TaxSummary', [
            'summary' => $summary,
            'filters' => $request->only(['date_from', 'date_to']),
        ]);
    }

    /**
     * Tax reconciliation report.
     */
    public function taxReconciliation(Request $request)
    {
        $query = TaxComponent::where('company_id', Auth::user()->current_company_id)
            ->notReversed()
            ->with(['taxRate', 'transaction']);

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $taxComponents = $query->orderBy('tax_period_start', 'desc')
            ->orderBy('tax_rate_id')
            ->paginate(100)
            ->withQueryString();

        // Group by tax period and rate for reconciliation
        $reconciliationData = $query->get()
            ->groupBy(function ($component) {
                return $component->tax_rate_id.'_'.$component->tax_period_start->format('Y-m');
            })
            ->map(function ($components) {
                $firstComponent = $components->first();
                $taxRate = $firstComponent->taxRate;

                $outputTax = $components->filter(function ($component) {
                    return in_array($component->transaction_type, ['App\\Models\\Invoice', 'App\\Models\\SalesInvoice']);
                })->sum('tax_amount');

                $inputTax = $components->filter(function ($component) {
                    return in_array($component->transaction_type, ['App\\Models\\Bill', 'App\\Models\\PurchaseBill']);
                })->sum('tax_amount');

                return [
                    'tax_rate' => $taxRate,
                    'tax_period' => $firstComponent->tax_period_start,
                    'output_tax' => $outputTax,
                    'input_tax' => $inputTax,
                    'net_tax' => $outputTax - $inputTax,
                    'paid_amount' => $components->sum('paid_amount'),
                    'unpaid_amount' => $components->sum(function ($component) {
                        return $component->getUnpaidAmountAttribute();
                    }),
                    'component_count' => $components->count(),
                ];
            })
            ->values()
            ->sortBy('tax_period');

        return Inertia::render('TaxReports/TaxReconciliation', [
            'taxComponents' => $taxComponents,
            'reconciliationData' => $reconciliationData,
            'filters' => $request->only(['date_from', 'date_to']),
        ]);
    }

    /**
     * Tax effectiveness analysis report.
     */
    public function taxEffectiveness(Request $request)
    {
        $query = TaxComponent::where('company_id', Auth::user()->current_company_id)
            ->notReversed()
            ->with(['taxRate']);

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $components = $query->get();

        // Calculate effectiveness metrics
        $effectivenessData = $components->groupBy('tax_rate_id')->map(function ($rateComponents) {
            $taxRate = $rateComponents->first()->taxRate;

            $totalTaxable = $rateComponents->sum('taxable_amount');
            $totalTax = $rateComponents->sum('tax_amount');
            $averageTaxRate = $totalTaxable > 0 ? ($totalTax / $totalTaxable) * 100 : 0;

            // Calculate compliance (on-time payment)
            $onTimePayments = $rateComponents->filter(function ($component) {
                return $component->payment_date &&
                       $component->payment_date <= $component->due_date ?? now()->addDays(30);
            })->count();

            $totalPayments = $rateComponents->filter(function ($component) {
                return $component->payment_date;
            })->count();

            $complianceRate = $totalPayments > 0 ? ($onTimePayments / $totalPayments) * 100 : 0;

            return [
                'tax_rate' => $taxRate,
                'total_taxable' => $totalTaxable,
                'total_tax' => $totalTax,
                'average_tax_rate' => $averageTaxRate,
                'transaction_count' => $rateComponents->count(),
                'compliance_rate' => $complianceRate,
                'paid_amount' => $rateComponents->sum('paid_amount'),
                'unpaid_amount' => $rateComponents->sum(function ($component) {
                    return $component->getUnpaidAmountAttribute();
                }),
            ];
        })->values()->sortBy('total_tax', descending: true);

        // Tax rate utilization
        $activeTaxRates = TaxRate::where('company_id', Auth::user()->current_company_id)
            ->active()
            ->get(['id', 'name', 'rate']);

        $utilizationData = $activeTaxRates->map(function ($taxRate) use ($components) {
            $usedComponents = $components->where('tax_rate_id', $taxRate->id);
            $isUsed = $usedComponents->count() > 0;

            return [
                'tax_rate' => $taxRate,
                'is_used' => $isUsed,
                'usage_count' => $usedComponents->count(),
                'total_tax' => $usedComponents->sum('tax_amount'),
            ];
        })->filter(function ($data) {
            return $data['is_used'];
        })->sortBy('total_tax', descending: true);

        return Inertia::render('TaxReports/TaxEffectiveness', [
            'effectivenessData' => $effectivenessData,
            'utilizationData' => $utilizationData,
            'filters' => $request->only(['date_from', 'date_to']),
        ]);
    }
}
