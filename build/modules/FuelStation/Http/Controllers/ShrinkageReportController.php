<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\Fuel;
use App\Modules\FuelStation\Models\Tank;
use App\Services\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ShrinkageReportController extends Controller
{
    /**
     * Display shrinkage report.
     */
    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();

        // Date filters
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $fuelId = $request->input('fuel_id', 'all');

        // Get tanks with fuel info
        $tanks = Tank::where('company_id', $company->id)
            ->with('fuel')
            ->get();

        // Get fuels for filter
        $fuels = Fuel::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        // Get daily closes with shrinkage data
        $dailyCloses = Transaction::where('company_id', $company->id)
            ->where('transaction_type', 'daily_close')
            ->whereIn('status', ['posted', 'locked'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date')
            ->get();

        // Extract shrinkage data from metadata
        $shrinkageData = [];
        $dailyShrinkage = [];

        foreach ($dailyCloses as $close) {
            $metadata = $close->metadata ?? [];
            $tankReadings = $metadata['tank_readings'] ?? [];

            $dayShrinkage = [
                'date' => $close->transaction_date->format('Y-m-d'),
                'total_shrinkage' => 0,
                'total_value' => 0,
                'tanks' => [],
            ];

            foreach ($tankReadings as $reading) {
                $tankId = $reading['tank_id'] ?? null;
                $tank = $tanks->firstWhere('id', $tankId);

                if (!$tank) continue;

                // Filter by fuel if specified
                if ($fuelId !== 'all' && $tank->fuel_id !== $fuelId) {
                    continue;
                }

                $shrinkage = (float) ($reading['shrinkage'] ?? 0);
                $rate = (float) ($reading['rate'] ?? $tank->fuel->current_rate ?? 0);
                $value = abs($shrinkage) * $rate;

                if ($shrinkage != 0) {
                    $entry = [
                        'date' => $close->transaction_date->format('Y-m-d'),
                        'tank_id' => $tankId,
                        'tank_name' => $tank->name,
                        'fuel_id' => $tank->fuel_id,
                        'fuel_name' => $tank->fuel->name ?? 'Unknown',
                        'opening' => (float) ($reading['opening'] ?? 0),
                        'closing' => (float) ($reading['closing'] ?? 0),
                        'receipts' => (float) ($reading['receipts'] ?? 0),
                        'sales' => (float) ($reading['sales'] ?? 0),
                        'expected' => (float) ($reading['expected'] ?? 0),
                        'shrinkage' => $shrinkage,
                        'rate' => $rate,
                        'value' => $value,
                    ];

                    $shrinkageData[] = $entry;
                    $dayShrinkage['total_shrinkage'] += $shrinkage;
                    $dayShrinkage['total_value'] += $value;
                    $dayShrinkage['tanks'][] = $entry;
                }
            }

            if (count($dayShrinkage['tanks']) > 0) {
                $dailyShrinkage[] = $dayShrinkage;
            }
        }

        // Calculate summary by fuel
        $fuelSummary = [];
        foreach ($shrinkageData as $entry) {
            $fuelName = $entry['fuel_name'];
            if (!isset($fuelSummary[$fuelName])) {
                $fuelSummary[$fuelName] = [
                    'fuel_name' => $fuelName,
                    'total_shrinkage' => 0,
                    'total_value' => 0,
                    'count' => 0,
                ];
            }
            $fuelSummary[$fuelName]['total_shrinkage'] += $entry['shrinkage'];
            $fuelSummary[$fuelName]['total_value'] += $entry['value'];
            $fuelSummary[$fuelName]['count']++;
        }

        // Calculate stats
        $stats = [
            'total_shrinkage' => array_sum(array_column($shrinkageData, 'shrinkage')),
            'total_value' => array_sum(array_column($shrinkageData, 'value')),
            'days_with_shrinkage' => count($dailyShrinkage),
            'avg_daily_shrinkage' => count($dailyShrinkage) > 0
                ? array_sum(array_column($shrinkageData, 'shrinkage')) / count($dailyShrinkage)
                : 0,
        ];

        return Inertia::render('FuelStation/Reports/ShrinkageReport', [
            'shrinkageData' => $shrinkageData,
            'dailyShrinkage' => $dailyShrinkage,
            'fuelSummary' => array_values($fuelSummary),
            'fuels' => $fuels,
            'stats' => $stats,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'fuel_id' => $fuelId,
            ],
            'currency' => $company->base_currency ?? 'PKR',
        ]);
    }

    /**
     * Export shrinkage report as CSV.
     */
    public function export(Request $request)
    {
        $company = app(CurrentCompany::class)->get();

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $fuelId = $request->input('fuel_id', 'all');

        $tanks = Tank::where('company_id', $company->id)
            ->with('fuel')
            ->get();

        $dailyCloses = Transaction::where('company_id', $company->id)
            ->where('transaction_type', 'daily_close')
            ->whereIn('status', ['posted', 'locked'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date')
            ->get();

        $rows = [];
        $rows[] = ['Date', 'Tank', 'Fuel', 'Opening', 'Receipts', 'Sales', 'Expected', 'Closing', 'Shrinkage', 'Rate', 'Value'];

        foreach ($dailyCloses as $close) {
            $metadata = $close->metadata ?? [];
            $tankReadings = $metadata['tank_readings'] ?? [];

            foreach ($tankReadings as $reading) {
                $tankId = $reading['tank_id'] ?? null;
                $tank = $tanks->firstWhere('id', $tankId);

                if (!$tank) continue;
                if ($fuelId !== 'all' && $tank->fuel_id !== $fuelId) continue;

                $shrinkage = (float) ($reading['shrinkage'] ?? 0);
                if ($shrinkage == 0) continue;

                $rate = (float) ($reading['rate'] ?? $tank->fuel->current_rate ?? 0);

                $rows[] = [
                    $close->transaction_date->format('Y-m-d'),
                    $tank->name,
                    $tank->fuel->name ?? 'Unknown',
                    number_format((float) ($reading['opening'] ?? 0), 2),
                    number_format((float) ($reading['receipts'] ?? 0), 2),
                    number_format((float) ($reading['sales'] ?? 0), 2),
                    number_format((float) ($reading['expected'] ?? 0), 2),
                    number_format((float) ($reading['closing'] ?? 0), 2),
                    number_format($shrinkage, 2),
                    number_format($rate, 2),
                    number_format(abs($shrinkage) * $rate, 2),
                ];
            }
        }

        $filename = "shrinkage_report_{$startDate}_to_{$endDate}.csv";

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
