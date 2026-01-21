<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SalesReportController extends Controller
{
    /**
     * Display sales report with filters.
     */
    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();

        // Default to current month
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $fuelType = $request->input('fuel_type', 'all');
        $groupBy = $request->input('group_by', 'day'); // day, week, month

        // Get daily close transactions within date range
        $query = Transaction::where('company_id', $company->id)
            ->where('transaction_type', 'daily_close')
            ->whereIn('status', ['posted', 'locked'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date');

        $dailyCloses = $query->get();

        // Extract sales data from metadata
        $salesData = [];
        $fuelSummary = [];
        $dailySales = [];

        foreach ($dailyCloses as $close) {
            $metadata = $close->metadata ?? [];
            $salesLines = $metadata['sales'] ?? [];
            $date = $close->transaction_date->format('Y-m-d');

            $dayTotal = 0;
            $dayLiters = 0;

            foreach ($salesLines as $sale) {
                $fuelName = $sale['fuel_name'] ?? 'Unknown';
                $liters = (float) ($sale['liters'] ?? 0);
                $amount = (float) ($sale['amount'] ?? 0);

                // Filter by fuel type if specified
                if ($fuelType !== 'all' && $fuelName !== $fuelType) {
                    continue;
                }

                $dayTotal += $amount;
                $dayLiters += $liters;

                // Aggregate by fuel type
                if (!isset($fuelSummary[$fuelName])) {
                    $fuelSummary[$fuelName] = [
                        'fuel_name' => $fuelName,
                        'total_liters' => 0,
                        'total_amount' => 0,
                        'avg_rate' => 0,
                        'days_sold' => 0,
                    ];
                }
                $fuelSummary[$fuelName]['total_liters'] += $liters;
                $fuelSummary[$fuelName]['total_amount'] += $amount;
            }

            $dailySales[] = [
                'date' => $date,
                'formatted_date' => Carbon::parse($date)->format('M d, Y'),
                'day_name' => Carbon::parse($date)->format('l'),
                'total_liters' => $dayLiters,
                'total_amount' => $dayTotal,
                'transaction_id' => $close->id,
            ];
        }

        // Calculate average rates
        foreach ($fuelSummary as $key => $fuel) {
            if ($fuel['total_liters'] > 0) {
                $fuelSummary[$key]['avg_rate'] = $fuel['total_amount'] / $fuel['total_liters'];
            }
            // Count days sold
            $fuelSummary[$key]['days_sold'] = count(array_filter($dailySales, fn($d) => $d['total_liters'] > 0));
        }

        // Get available fuel types for filter
        $fuelTypes = Item::where('company_id', $company->id)
            ->whereNotNull('fuel_category')
            ->orderBy('name')
            ->pluck('name')
            ->toArray();

        // Group sales data if needed
        $groupedSales = $this->groupSalesData($dailySales, $groupBy);

        // Calculate totals
        $totals = [
            'total_days' => count($dailySales),
            'total_liters' => array_sum(array_column($dailySales, 'total_liters')),
            'total_amount' => array_sum(array_column($dailySales, 'total_amount')),
            'avg_daily_liters' => count($dailySales) > 0
                ? array_sum(array_column($dailySales, 'total_liters')) / count($dailySales)
                : 0,
            'avg_daily_amount' => count($dailySales) > 0
                ? array_sum(array_column($dailySales, 'total_amount')) / count($dailySales)
                : 0,
        ];

        // Trend data for chart (last 7 periods)
        $trendData = array_slice($groupedSales, -7);

        return Inertia::render('FuelStation/Reports/SalesReport', [
            'dailySales' => $groupedSales,
            'fuelSummary' => array_values($fuelSummary),
            'fuelTypes' => $fuelTypes,
            'totals' => $totals,
            'trendData' => $trendData,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'fuel_type' => $fuelType,
                'group_by' => $groupBy,
            ],
            'currency' => $company->base_currency ?? 'PKR',
        ]);
    }

    /**
     * Group sales data by period.
     */
    private function groupSalesData(array $dailySales, string $groupBy): array
    {
        if ($groupBy === 'day') {
            return $dailySales;
        }

        $grouped = [];

        foreach ($dailySales as $sale) {
            $date = Carbon::parse($sale['date']);

            if ($groupBy === 'week') {
                $key = $date->startOfWeek()->format('Y-m-d');
                $label = 'Week of ' . $date->startOfWeek()->format('M d');
            } else { // month
                $key = $date->format('Y-m');
                $label = $date->format('F Y');
            }

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'date' => $key,
                    'formatted_date' => $label,
                    'day_name' => '',
                    'total_liters' => 0,
                    'total_amount' => 0,
                    'days_count' => 0,
                ];
            }

            $grouped[$key]['total_liters'] += $sale['total_liters'];
            $grouped[$key]['total_amount'] += $sale['total_amount'];
            $grouped[$key]['days_count']++;
        }

        return array_values($grouped);
    }

    /**
     * Export sales report to CSV.
     */
    public function export(Request $request)
    {
        $company = app(CurrentCompany::class)->get();

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $dailyCloses = Transaction::where('company_id', $company->id)
            ->where('transaction_type', 'daily_close')
            ->whereIn('status', ['posted', 'locked'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date')
            ->get();

        $rows = [];
        $rows[] = ['Date', 'Fuel Type', 'Liters', 'Rate', 'Amount'];

        foreach ($dailyCloses as $close) {
            $metadata = $close->metadata ?? [];
            $salesLines = $metadata['sales'] ?? [];
            $date = $close->transaction_date->format('Y-m-d');

            foreach ($salesLines as $sale) {
                $rows[] = [
                    $date,
                    $sale['fuel_name'] ?? 'Unknown',
                    $sale['liters'] ?? 0,
                    $sale['rate'] ?? 0,
                    $sale['amount'] ?? 0,
                ];
            }
        }

        $filename = "sales_report_{$startDate}_to_{$endDate}.csv";
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
