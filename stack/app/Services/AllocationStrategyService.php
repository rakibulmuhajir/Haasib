<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Collection;

class AllocationStrategyService
{
    /**
     * Apply FIFO (First In, First Out) allocation strategy.
     */
    public function fifo(Collection $invoices, float $availableAmount): array
    {
        $allocations = [];
        $remainingAmount = $availableAmount;

        // Sort by due date (oldest first)
        $sortedInvoices = $invoices->sortBy('due_date');

        foreach ($sortedInvoices as $invoice) {
            if ($remainingAmount <= 0) {
                break;
            }

            $allocateAmount = min($remainingAmount, $invoice->balance_due);

            if ($allocateAmount > 0) {
                $allocations[] = [
                    'invoice_id' => $invoice->id,
                    'amount' => $allocateAmount,
                    'notes' => 'FIFO allocation - oldest invoice paid first',
                ];

                $remainingAmount -= $allocateAmount;
            }
        }

        return $allocations;
    }

    /**
     * Apply proportional allocation strategy.
     */
    public function proportional(Collection $invoices, float $availableAmount): array
    {
        $allocations = [];
        $totalBalanceDue = $invoices->sum('balance_due');

        if ($totalBalanceDue <= 0) {
            return $allocations;
        }

        foreach ($invoices as $invoice) {
            if ($invoice->balance_due <= 0) {
                continue;
            }

            // Calculate proportional share
            $proportion = $invoice->balance_due / $totalBalanceDue;
            $allocateAmount = min($availableAmount * $proportion, $invoice->balance_due);

            if ($allocateAmount > 0) {
                $allocations[] = [
                    'invoice_id' => $invoice->id,
                    'amount' => round($allocateAmount, 2),
                    'notes' => 'Proportional allocation - distributed by balance ratio',
                ];
            }
        }

        return $allocations;
    }

    /**
     * Apply priority allocation strategy (overdue invoices first).
     */
    public function overdueFirst(Collection $invoices, float $availableAmount): array
    {
        $allocations = [];
        $remainingAmount = $availableAmount;

        // Separate overdue and non-overdue invoices
        $overdueInvoices = $invoices->filter(fn ($invoice) => $invoice->is_overdue);
        $nonOverdueInvoices = $invoices->filter(fn ($invoice) => !$invoice->is_overdue);

        // Process overdue invoices first, sorted by days overdue
        $sortedOverdue = $overdueInvoices->sortByDesc('days_overdue');

        foreach ($sortedOverdue as $invoice) {
            if ($remainingAmount <= 0) {
                break;
            }

            $allocateAmount = min($remainingAmount, $invoice->balance_due);

            if ($allocateAmount > 0) {
                $allocations[] = [
                    'invoice_id' => $invoice->id,
                    'amount' => $allocateAmount,
                    'notes' => 'Priority allocation - overdue invoice paid first',
                ];

                $remainingAmount -= $allocateAmount;
            }
        }

        // Then process non-overdue invoices by due date
        $sortedNonOverdue = $nonOverdueInvoices->sortBy('due_date');

        foreach ($sortedNonOverdue as $invoice) {
            if ($remainingAmount <= 0) {
                break;
            }

            $allocateAmount = min($remainingAmount, $invoice->balance_due);

            if ($allocateAmount > 0) {
                $allocations[] = [
                    'invoice_id' => $invoice->id,
                    'amount' => $allocateAmount,
                    'notes' => 'Priority allocation - non-overdue invoice',
                ];

                $remainingAmount -= $allocateAmount;
            }
        }

        return $allocations;
    }

    /**
     * Apply amount-based allocation strategy (largest balances first).
     */
    public function largestFirst(Collection $invoices, float $availableAmount): array
    {
        $allocations = [];
        $remainingAmount = $availableAmount;

        // Sort by balance due (largest first)
        $sortedInvoices = $invoices->sortByDesc('balance_due');

        foreach ($sortedInvoices as $invoice) {
            if ($remainingAmount <= 0) {
                break;
            }

            $allocateAmount = min($remainingAmount, $invoice->balance_due);

            if ($allocateAmount > 0) {
                $allocations[] = [
                    'invoice_id' => $invoice->id,
                    'amount' => $allocateAmount,
                    'notes' => 'Amount-based allocation - largest balance paid first',
                ];

                $remainingAmount -= $allocateAmount;
            }
        }

        return $allocations;
    }

    /**
     * Apply percentage-based allocation strategy.
     */
    public function percentageBased(Collection $invoices, float $availableAmount, array $percentages): array
    {
        $allocations = [];

        foreach ($invoices as $index => $invoice) {
            $percentage = $percentages[$index] ?? 0;
            
            if ($percentage <= 0) {
                continue;
            }

            $allocateAmount = min($availableAmount * ($percentage / 100), $invoice->balance_due);

            if ($allocateAmount > 0) {
                $allocations[] = [
                    'invoice_id' => $invoice->id,
                    'amount' => round($allocateAmount, 2),
                    'notes' => "Percentage-based allocation - {$percentage}% of payment",
                ];
            }
        }

        return $allocations;
    }

    /**
     * Apply equal distribution allocation strategy.
     */
    public function equalDistribution(Collection $invoices, float $availableAmount): array
    {
        $allocations = [];
        $invoiceCount = $invoices->where('balance_due', '>', 0)->count();

        if ($invoiceCount === 0) {
            return $allocations;
        }

        $amountPerInvoice = $availableAmount / $invoiceCount;

        foreach ($invoices as $invoice) {
            if ($invoice->balance_due <= 0) {
                continue;
            }

            $allocateAmount = min($amountPerInvoice, $invoice->balance_due);

            if ($allocateAmount > 0) {
                $allocations[] = [
                    'invoice_id' => $invoice->id,
                    'amount' => round($allocateAmount, 2),
                    'notes' => 'Equal distribution allocation - payment split equally',
                ];
            }
        }

        return $allocations;
    }

    /**
     * Apply custom priority allocation strategy.
     */
    public function customPriority(Collection $invoices, float $availableAmount, array $priorityOrder): array
    {
        $allocations = [];
        $remainingAmount = $availableAmount;

        // Create a priority map
        $priorityMap = array_flip($priorityOrder);

        // Sort invoices by custom priority
        $sortedInvoices = $invoices->sortBy(function ($invoice) use ($priorityMap) {
            return $priorityMap[$invoice->id] ?? 999;
        });

        foreach ($sortedInvoices as $invoice) {
            if ($remainingAmount <= 0) {
                break;
            }

            $allocateAmount = min($remainingAmount, $invoice->balance_due);

            if ($allocateAmount > 0) {
                $priorityIndex = $priorityMap[$invoice->id] ?? 'unspecified';
                $allocations[] = [
                    'invoice_id' => $invoice->id,
                    'amount' => $allocateAmount,
                    'notes' => "Custom priority allocation - priority #{$priorityIndex}",
                ];

                $remainingAmount -= $allocateAmount;
            }
        }

        return $allocations;
    }

    /**
     * Get available allocation strategies.
     */
    public function getAvailableStrategies(): array
    {
        return [
            'fifo' => [
                'name' => 'First In, First Out (FIFO)',
                'description' => 'Pays oldest invoices first based on due date',
                'best_for' => 'Standard accounts receivable management',
            ],
            'proportional' => [
                'name' => 'Proportional',
                'description' => 'Distributes payment proportionally based on invoice balances',
                'best_for' => 'Fair distribution across multiple invoices',
            ],
            'overdue_first' => [
                'name' => 'Overdue Priority',
                'description' => 'Prioritizes overdue invoices, sorted by days overdue',
                'best_for' => 'Collections and cash flow optimization',
            ],
            'largest_first' => [
                'name' => 'Largest Balance First',
                'description' => 'Pays invoices with largest balances first',
                'best_for' => 'Reducing the number of outstanding invoices',
            ],
            'percentage_based' => [
                'name' => 'Percentage-Based',
                'description' => 'Allocates based on specified percentages per invoice',
                'best_for' => 'Strategic payment distribution',
            ],
            'equal_distribution' => [
                'name' => 'Equal Distribution',
                'description' => 'Splits payment equally across all invoices',
                'best_for' => 'Simple, fair allocation method',
            ],
            'custom_priority' => [
                'name' => 'Custom Priority',
                'description' => 'Uses custom priority order for invoice selection',
                'best_for' => 'Specific business requirements',
            ],
        ];
    }
}