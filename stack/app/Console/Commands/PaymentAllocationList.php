<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Console\Command;

class PaymentAllocationList extends Command
{
    protected $signature = 'payment:allocation:list 
                            {--payment= : Filter by payment ID or number}
                            {--invoice= : Filter by invoice ID or number}
                            {--customer= : Filter by customer ID}
                            {--status= : Filter by allocation status (active, reversed, all)}
                            {--strategy= : Filter by allocation strategy}
                            {--date-from= : Start date for allocation date filter (YYYY-MM-DD)}
                            {--date-to= : End date for allocation date filter (YYYY-MM-DD)}
                            {--limit=50 : Number of records to display}
                            {--sort=created_at : Sort field (created_at, allocated_amount, allocation_date)}
                            {--order=desc : Sort order (asc, desc)}
                            {--format=table : Output format (table, json, csv)}
                            {--headers : Include headers in CSV output}';

    protected $description = 'List payment allocations with filtering and sorting options';

    public function handle(): int
    {
        $this->info('📋 Payment Allocations List');
        $this->info('=============================');

        try {
            // Get company from context
            $company = $this->getCompanyFromContext();

            // Build query
            $query = $this->buildAllocationQuery($company);

            // Apply filters
            $this->applyFilters($query);

            // Apply sorting
            $this->applySorting($query);

            // Get results
            $limit = (int) $this->option('limit');
            $allocations = $query->limit($limit)->get();

            if ($allocations->isEmpty()) {
                $this->info('No payment allocations found matching the specified criteria.');

                return self::SUCCESS;
            }

            // Display results
            $this->displayResults($allocations);

            // Show summary
            $this->displaySummary($allocations);

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('❌ Error retrieving allocations: '.$e->getMessage());
            $this->line('Stack trace:', 'error');
            $this->line($e->getTraceAsString(), 'error');

            return self::FAILURE;
        }
    }

    private function buildAllocationQuery(Company $company)
    {
        return PaymentAllocation::query()
            ->whereHas('payment', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })
            ->with([
                'payment',
                'invoice',
                'invoice.customer',
                'reversedByUser',
            ]);
    }

    private function applyFilters($query): void
    {
        // Filter by payment
        if ($paymentId = $this->option('payment')) {
            $payment = $this->findPayment($paymentId);
            $query->where('payment_id', $payment->id);
        }

        // Filter by invoice
        if ($invoiceId = $this->option('invoice')) {
            $invoice = $this->findInvoice($invoiceId);
            $query->where('invoice_id', $invoice->id);
        }

        // Filter by customer
        if ($customerId = $this->option('customer')) {
            $query->whereHas('invoice', function ($query) use ($customerId) {
                $query->where('customer_id', $customerId);
            });
        }

        // Filter by status
        if ($status = $this->option('status')) {
            match ($status) {
                'active' => $query->whereNull('reversed_at'),
                'reversed' => $query->whereNotNull('reversed_at'),
                'all' => null,
                default => throw new \InvalidArgumentException("Invalid status: {$status}")
            };
        }

        // Filter by strategy
        if ($strategy = $this->option('strategy')) {
            $query->where('allocation_strategy', $strategy);
        }

        // Filter by date range
        if ($dateFrom = $this->option('date-from')) {
            $this->validateDate($dateFrom);
            $query->whereDate('allocation_date', '>=', $dateFrom);
        }

        if ($dateTo = $this->option('date-to')) {
            $this->validateDate($dateTo);
            $query->whereDate('allocation_date', '<=', $dateTo);
        }
    }

    private function applySorting($query): void
    {
        $sortField = $this->option('sort');
        $order = $this->option('order');

        $validSortFields = [
            'created_at', 'allocation_date', 'allocated_amount',
            'payment_number', 'invoice_number', 'allocation_strategy',
        ];

        if (! in_array($sortField, $validSortFields)) {
            throw new \InvalidArgumentException("Invalid sort field: {$sortField}");
        }

        if (! in_array($order, ['asc', 'desc'])) {
            throw new \InvalidArgumentException("Invalid sort order: {$order}");
        }

        // Handle related field sorting
        if (in_array($sortField, ['payment_number', 'invoice_number'])) {
            $relation = $sortField === 'payment_number' ? 'payment' : 'invoice';
            $field = $sortField === 'payment_number' ? 'payment_number' : 'invoice_number';

            $query->join("acct.{$relation}s", "acct.payment_allocations.{$relation}_id", '=', "acct.{$relation}s.id")
                ->orderBy("acct.{$relation}s.{$field}", $order)
                ->select('acct.payment_allocations.*');
        } else {
            $query->orderBy($sortField, $order);
        }
    }

    private function displayResults($allocations): void
    {
        $format = $this->option('format');

        switch ($format) {
            case 'json':
                $this->line(json_encode($allocations->toArray(), JSON_PRETTY_PRINT));
                break;

            case 'csv':
                $this->displayCsvResults($allocations);
                break;

            case 'table':
            default:
                $this->displayTableResults($allocations);
                break;
        }
    }

    private function displayTableResults($allocations): void
    {
        $headers = [
            'ID',
            'Payment',
            'Invoice',
            'Customer',
            'Amount',
            'Strategy',
            'Date',
            'Status',
        ];

        $rows = $allocations->map(function ($allocation) {
            return [
                substr($allocation->id, 0, 8),
                $allocation->payment->payment_number,
                $allocation->invoice->invoice_number,
                $allocation->invoice->customer->name,
                number_format($allocation->allocated_amount, 2),
                $allocation->allocation_strategy ?? 'manual',
                $allocation->allocation_date->format('Y-m-d'),
                $allocation->is_reversed ? 'Reversed' : 'Active',
            ];
        })->toArray();

        $this->table($headers, $rows);
    }

    private function displayCsvResults($allocations): void
    {
        $headers = [
            'id', 'payment_id', 'payment_number', 'invoice_id', 'invoice_number',
            'customer_id', 'customer_name', 'allocated_amount', 'allocation_method',
            'allocation_strategy', 'allocation_date', 'reversed_at', 'reversal_reason',
            'created_at', 'updated_at',
        ];

        if ($this->option('headers')) {
            $this->line(implode(',', $headers));
        }

        foreach ($allocations as $allocation) {
            $row = [
                $allocation->id,
                $allocation->payment_id,
                $allocation->payment->payment_number,
                $allocation->invoice_id,
                $allocation->invoice->invoice_number,
                $allocation->invoice->customer_id,
                '"'.str_replace('"', '""', $allocation->invoice->customer->name).'"',
                $allocation->allocated_amount,
                $allocation->allocation_method,
                $allocation->allocation_strategy ?? '',
                $allocation->allocation_date->format('Y-m-d H:i:s'),
                $allocation->reversed_at?->format('Y-m-d H:i:s') ?? '',
                '"'.str_replace('"', '""', $allocation->reversal_reason ?? '').'"',
                $allocation->created_at->format('Y-m-d H:i:s'),
                $allocation->updated_at->format('Y-m-d H:i:s'),
            ];

            $this->line(implode(',', $row));
        }
    }

    private function displaySummary($allocations): void
    {
        $totalAmount = $allocations->sum('allocated_amount');
        $activeCount = $allocations->where('reversed_at', null)->count();
        $reversedCount = $allocations->where('reversed_at', '!=', null)->count();

        $this->info("\n📊 Summary:");
        $this->info('Total allocations: '.$allocations->count());
        $this->info('Total amount: '.number_format($totalAmount, 2));
        $this->info("Active allocations: {$activeCount}");
        $this->info("Reversed allocations: {$reversedCount}");
        $this->info('Average allocation: '.number_format($allocations->count() > 0 ? $totalAmount / $allocations->count() : 0, 2));
    }

    private function findPayment(string $identifier): Payment
    {
        $query = Payment::query();

        // Try by UUID
        if ($this->isUuid($identifier)) {
            $payment = $query->where('id', $identifier)->first();
            if ($payment) {
                return $payment;
            }
        }

        // Try by payment number
        $payment = $query->where('payment_number', $identifier)->first();
        if ($payment) {
            return $payment;
        }

        throw new \InvalidArgumentException("Payment '{$identifier}' not found");
    }

    private function findInvoice(string $identifier): Invoice
    {
        $query = Invoice::query();

        // Try by UUID
        if ($this->isUuid($identifier)) {
            $invoice = $query->where('id', $identifier)->first();
            if ($invoice) {
                return $invoice;
            }
        }

        // Try by invoice number
        $invoice = $query->where('invoice_number', $identifier)->first();
        if ($invoice) {
            return $invoice;
        }

        throw new \InvalidArgumentException("Invoice '{$identifier}' not found");
    }

    private function getCompanyFromContext(): Company
    {
        // This would be implemented based on your context system
        // For now, return the first company
        $company = Company::first();
        if (! $company) {
            throw new \RuntimeException('No company found. Please specify --company=<id>');
        }

        return $company;
    }

    private function validateDate(string $date): void
    {
        if (! strtotime($date)) {
            throw new \InvalidArgumentException("Invalid date format: {$date}. Use YYYY-MM-DD format.");
        }
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }
}
