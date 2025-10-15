<?php

namespace Modules\Accounting\Domain\Customers\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Domain\Customers\Models\Customer;
use Modules\Accounting\Domain\Customers\Models\CustomerCreditLimit;

class CustomerCreditService
{
    /**
     * Calculate the current credit exposure for a customer
     */
    public function calculateExposure(Customer $customer): float
    {
        // Get outstanding invoice amounts
        $outstandingInvoices = DB::table('invoices')
            ->where('customer_id', $customer->id)
            ->where('company_id', $customer->company_id)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'void')
            ->sum('balance_due');

        // Get outstanding credit notes (which reduce exposure)
        $outstandingCreditNotes = DB::table('credit_notes')
            ->where('customer_id', $customer->id)
            ->where('company_id', $customer->company_id)
            ->where('status', '!=', 'applied')
            ->where('status', '!=', 'void')
            ->sum('balance_due');

        return max(0, $outstandingInvoices - $outstandingCreditNotes);
    }

    /**
     * Get the current credit limit for a customer
     */
    public function getCurrentCreditLimit(Customer $customer): ?float
    {
        $activeLimit = CustomerCreditLimit::getActiveForCustomer($customer->id);

        return $activeLimit?->limit_amount;
    }

    /**
     * Get the credit limit that will be active on a specific date
     */
    public function getCreditLimitOnDate(Customer $customer, \DateTime $date): ?float
    {
        $limit = CustomerCreditLimit::getForCustomerOnDate($customer->id, $date);

        return $limit?->limit_amount;
    }

    /**
     * Check if a customer can create an invoice for the given amount
     */
    public function canCreateInvoice(Customer $customer, float $amount, array $options = []): array
    {
        // Check if customer is blocked
        if ($customer->status === 'blocked') {
            return [
                'allowed' => false,
                'reason' => 'customer_blocked',
                'message' => 'Cannot create invoices for blocked customers',
                'details' => [],
            ];
        }

        // Check if customer is inactive
        if ($customer->status === 'inactive') {
            return [
                'allowed' => false,
                'reason' => 'customer_inactive',
                'message' => 'Cannot create invoices for inactive customers',
                'details' => [],
            ];
        }

        // Get current credit limit
        $creditLimit = $this->getCurrentCreditLimit($customer);

        // If no credit limit is set, allow unlimited credit
        if ($creditLimit === null) {
            return [
                'allowed' => true,
                'reason' => 'no_limit',
                'message' => 'No credit limit set',
                'details' => [
                    'credit_limit' => null,
                    'current_exposure' => $this->calculateExposure($customer),
                    'invoice_amount' => $amount,
                ],
            ];
        }

        $currentExposure = $this->calculateExposure($customer);
        $totalExposure = $currentExposure + $amount;
        $availableCredit = $creditLimit - $currentExposure;

        // Check if invoice would exceed credit limit
        if ($totalExposure > $creditLimit) {
            $excessAmount = $totalExposure - $creditLimit;

            return [
                'allowed' => false,
                'reason' => 'credit_limit_exceeded',
                'message' => "Invoice amount exceeds available credit by {$excessAmount}",
                'details' => [
                    'credit_limit' => $creditLimit,
                    'current_exposure' => $currentExposure,
                    'invoice_amount' => $amount,
                    'total_exposure' => $totalExposure,
                    'available_credit' => $availableCredit,
                    'excess_amount' => $excessAmount,
                ],
            ];
        }

        return [
            'allowed' => true,
            'reason' => 'within_limit',
            'message' => 'Invoice is within credit limit',
            'details' => [
                'credit_limit' => $creditLimit,
                'current_exposure' => $currentExposure,
                'invoice_amount' => $amount,
                'available_credit' => $availableCredit,
                'utilization_percentage' => $creditLimit > 0 ? round(($totalExposure / $creditLimit) * 100, 2) : 0,
            ],
        ];
    }

    /**
     * Get credit utilization percentage
     */
    public function getCreditUtilization(Customer $customer): float
    {
        $creditLimit = $this->getCurrentCreditLimit($customer);

        if ($creditLimit === null || $creditLimit <= 0) {
            return 0;
        }

        $exposure = $this->calculateExposure($customer);

        return round(($exposure / $creditLimit) * 100, 2);
    }

    /**
     * Get credit utilization status
     */
    public function getCreditUtilizationStatus(Customer $customer): array
    {
        $utilization = $this->getCreditUtilization($customer);
        $creditLimit = $this->getCurrentCreditLimit($customer);
        $exposure = $this->calculateExposure($customer);

        $status = 'healthy';
        if ($utilization >= 90) {
            $status = 'critical';
        } elseif ($utilization >= 75) {
            $status = 'warning';
        } elseif ($utilization >= 50) {
            $status = 'moderate';
        }

        return [
            'utilization_percentage' => $utilization,
            'credit_limit' => $creditLimit,
            'current_exposure' => $exposure,
            'available_credit' => $creditLimit ? max(0, $creditLimit - $exposure) : null,
            'status' => $status,
        ];
    }

    /**
     * Get customers approaching their credit limit
     */
    public function getCustomersApproachingLimit($companyId, float $threshold = 75.0): \Illuminate\Support\Collection
    {
        return Customer::where('company_id', $companyId)
            ->where('status', 'active')
            ->whereNotNull('credit_limit')
            ->get()
            ->filter(function ($customer) use ($threshold) {
                return $this->getCreditUtilization($customer) >= $threshold;
            })
            ->map(function ($customer) {
                return [
                    'customer' => $customer,
                    'utilization' => $this->getCreditUtilizationStatus($customer),
                ];
            })
            ->sortByDesc('utilization.utilization_percentage')
            ->values();
    }

    /**
     * Get customers who have exceeded their credit limit
     */
    public function getCustomersOverLimit($companyId): \Illuminate\Support\Collection
    {
        return Customer::where('company_id', $companyId)
            ->where('status', 'active')
            ->whereNotNull('credit_limit')
            ->get()
            ->filter(function ($customer) {
                return $this->calculateExposure($customer) > $this->getCurrentCreditLimit($customer);
            })
            ->map(function ($customer) {
                $exposure = $this->calculateExposure($customer);
                $limit = $this->getCurrentCreditLimit($customer);

                return [
                    'customer' => $customer,
                    'excess_amount' => $exposure - $limit,
                    'utilization' => $this->getCreditUtilizationStatus($customer),
                ];
            })
            ->sortByDesc('excess_amount')
            ->values();
    }

    /**
     * Get credit risk assessment for a customer
     */
    public function getCreditRiskAssessment(Customer $customer): array
    {
        $utilization = $this->getCreditUtilization($customer);
        $exposure = $this->calculateExposure($customer);
        $limit = $this->getCurrentCreditLimit($customer);

        $riskScore = 0;
        $riskFactors = [];

        // Utilization-based risk
        if ($utilization >= 90) {
            $riskScore += 40;
            $riskFactors[] = 'Very high credit utilization (>= 90%)';
        } elseif ($utilization >= 75) {
            $riskScore += 25;
            $riskFactors[] = 'High credit utilization (>= 75%)';
        } elseif ($utilization >= 50) {
            $riskScore += 10;
            $riskFactors[] = 'Moderate credit utilization (>= 50%)';
        }

        // Customer status-based risk
        if ($customer->status === 'inactive') {
            $riskScore += 20;
            $riskFactors[] = 'Customer is inactive';
        } elseif ($customer->status === 'blocked') {
            $riskScore += 50;
            $riskFactors[] = 'Customer is blocked';
        }

        // Payment history (placeholder - would need actual payment data)
        // This would require integration with payment history analysis

        $riskLevel = 'low';
        if ($riskScore >= 70) {
            $riskLevel = 'high';
        } elseif ($riskScore >= 40) {
            $riskLevel = 'medium';
        }

        return [
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'risk_factors' => $riskFactors,
            'utilization' => $utilization,
            'exposure' => $exposure,
            'limit' => $limit,
        ];
    }

    /**
     * Check if credit limit override is allowed
     */
    public function canOverrideCreditLimit(Customer $customer, ?\App\Models\User $user = null): bool
    {
        if (! $user) {
            return false;
        }

        // Check if user has override permission
        if (! $user->can('accounting.customers.override_credit_limits')) {
            return false;
        }

        // Check if customer is in a state that allows overrides
        if ($customer->status === 'blocked') {
            return false; // No overrides for blocked customers
        }

        return true;
    }

    /**
     * Get credit limit history for a customer
     */
    public function getCreditLimitHistory(Customer $customer, int $limit = 10): \Illuminate\Support\Collection
    {
        return CustomerCreditLimit::where('customer_id', $customer->id)
            ->with('changedBy')
            ->orderBy('effective_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($creditLimit) {
                return [
                    'id' => $creditLimit->id,
                    'limit_amount' => $creditLimit->limit_amount,
                    'effective_at' => $creditLimit->effective_at,
                    'expires_at' => $creditLimit->expires_at,
                    'status' => $creditLimit->status,
                    'reason' => $creditLimit->reason,
                    'approval_reference' => $creditLimit->approval_reference,
                    'changed_by' => $creditLimit->changedBy?->name,
                    'created_at' => $creditLimit->created_at,
                ];
            });
    }
}
