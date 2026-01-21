<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CreditSaleController extends Controller
{
    /**
     * List credit sales extracted from daily close transactions.
     */
    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();

        // Date filters
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $customerId = $request->input('customer_id', 'all');

        // Get daily close transactions with credit sales
        $dailyCloses = Transaction::where('company_id', $company->id)
            ->where('transaction_type', 'daily_close')
            ->whereIn('status', ['posted', 'locked'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderByDesc('transaction_date')
            ->get();

        // Extract credit sales from metadata
        $creditSales = [];
        foreach ($dailyCloses as $close) {
            $metadata = $close->metadata ?? [];
            $sales = $metadata['credit_sales'] ?? [];

            foreach ($sales as $sale) {
                // Filter by customer if specified
                if ($customerId !== 'all' && ($sale['customer_id'] ?? '') !== $customerId) {
                    continue;
                }

                $creditSales[] = [
                    'id' => $close->id . '-' . ($sale['customer_id'] ?? 'unknown'),
                    'transaction_id' => $close->id,
                    'date' => $close->transaction_date->format('Y-m-d'),
                    'customer_id' => $sale['customer_id'] ?? null,
                    'customer_name' => $sale['customer_name'] ?? 'Unknown',
                    'fuel_name' => $sale['fuel_name'] ?? 'Fuel',
                    'liters' => (float) ($sale['liters'] ?? 0),
                    'rate' => (float) ($sale['rate'] ?? 0),
                    'amount' => (float) ($sale['amount'] ?? 0),
                    'vehicle_number' => $sale['vehicle_number'] ?? null,
                    'driver_name' => $sale['driver_name'] ?? null,
                ];
            }
        }

        // Get customers for filter dropdown
        $customers = DB::table('acct.customers')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'customer_number as code']);

        // Calculate stats
        $stats = [
            'total_sales' => count($creditSales),
            'total_liters' => array_sum(array_column($creditSales, 'liters')),
            'total_amount' => array_sum(array_column($creditSales, 'amount')),
            'unique_customers' => count(array_unique(array_column($creditSales, 'customer_id'))),
        ];

        return Inertia::render('FuelStation/CreditSales/Index', [
            'sales' => $creditSales,
            'customers' => $customers,
            'stats' => $stats,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'customer_id' => $customerId,
            ],
            'currency' => $company->base_currency ?? 'PKR',
        ]);
    }
}
