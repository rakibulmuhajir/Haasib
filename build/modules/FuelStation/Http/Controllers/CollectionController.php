<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    /**
     * List credit collections.
     */
    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();

        // Date filters
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $customerId = $request->input('customer_id', 'all');

        // Get credit collection transactions
        $query = Transaction::where('company_id', $company->id)
            ->where('transaction_type', 'credit_collection')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderByDesc('transaction_date');

        if ($customerId !== 'all') {
            $query->whereRaw("metadata->>'customer_id' = ?", [$customerId]);
        }

        $collections = $query->get()->map(fn($txn) => [
            'id' => $txn->id,
            'date' => $txn->transaction_date->format('Y-m-d'),
            'reference' => $txn->reference,
            'customer_id' => $txn->metadata['customer_id'] ?? null,
            'customer_name' => $txn->metadata['customer_name'] ?? 'Unknown',
            'payment_method' => $txn->metadata['payment_method'] ?? 'cash',
            'amount' => (float) $txn->total_amount,
            'notes' => $txn->description,
            'status' => $txn->status,
        ]);

        // Get customers for filter dropdown
        $customers = DB::table('acct.customers')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'customer_number as code']);

        // Calculate stats
        $stats = [
            'total_collections' => $collections->count(),
            'total_amount' => $collections->sum('amount'),
            'cash_amount' => $collections->where('payment_method', 'cash')->sum('amount'),
            'bank_amount' => $collections->whereIn('payment_method', ['bank', 'transfer', 'cheque'])->sum('amount'),
        ];

        return Inertia::render('FuelStation/Collections/Index', [
            'collections' => $collections,
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

    /**
     * Show create collection form.
     */
    public function create(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();

        // Get all active customers (for credit collections)
        $customers = DB::table('acct.customers')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->select(['id', 'name', 'customer_number as code', DB::raw('0 as current_balance')])
            ->get();

        // Pre-select customer if provided
        $selectedCustomerId = $request->input('customer_id');

        return Inertia::render('FuelStation/Collections/Create', [
            'customers' => $customers,
            'selectedCustomerId' => $selectedCustomerId,
            'currency' => $company->base_currency ?? 'PKR',
        ]);
    }

    /**
     * Store a new collection.
     */
    public function store(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $validated = $request->validate([
            'customer_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,bank,transfer,cheque'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'collection_date' => ['required', 'date'],
        ]);

        // Get customer details
        $customer = DB::table('acct.customers')
            ->where('company_id', $company->id)
            ->where('id', $validated['customer_id'])
            ->first();

        if (!$customer) {
            return redirect()->back()->withErrors(['customer_id' => 'Customer not found.']);
        }

        DB::transaction(function () use ($company, $validated, $customer) {
            // Create collection transaction
            Transaction::create([
                'company_id' => $company->id,
                'transaction_type' => 'credit_collection',
                'transaction_date' => $validated['collection_date'],
                'reference' => $validated['reference'] ?? ('COL-' . strtoupper(Str::random(8))),
                'description' => $validated['notes'] ?? "Collection from {$customer->name}",
                'total_amount' => $validated['amount'],
                'status' => 'posted',
                'metadata' => [
                    'customer_id' => $validated['customer_id'],
                    'customer_name' => $customer->name,
                    'payment_method' => $validated['payment_method'],
                ],
                'created_by' => auth()->id(),
            ]);
        });

        return redirect()
            ->route('fuel.collections.index', ['company' => $company->slug])
            ->with('success', 'Collection recorded successfully.');
    }

    /**
     * Show collection details.
     */
    public function show(Request $request, string $company, string $collection): Response
    {
        $companyModel = app(CurrentCompany::class)->get();

        $transaction = Transaction::where('company_id', $companyModel->id)
            ->where('id', $collection)
            ->where('transaction_type', 'credit_collection')
            ->firstOrFail();

        return Inertia::render('FuelStation/Collections/Show', [
            'collection' => [
                'id' => $transaction->id,
                'date' => $transaction->transaction_date->format('Y-m-d'),
                'reference' => $transaction->reference,
                'customer_id' => $transaction->metadata['customer_id'] ?? null,
                'customer_name' => $transaction->metadata['customer_name'] ?? 'Unknown',
                'payment_method' => $transaction->metadata['payment_method'] ?? 'cash',
                'amount' => (float) $transaction->total_amount,
                'notes' => $transaction->description,
                'status' => $transaction->status,
                'created_at' => $transaction->created_at->format('Y-m-d H:i:s'),
            ],
            'currency' => $companyModel->base_currency ?? 'PKR',
        ]);
    }
}
