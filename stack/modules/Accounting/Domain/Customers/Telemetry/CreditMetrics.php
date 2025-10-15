<?php

namespace Modules\Accounting\Domain\Customers\Telemetry;

use Illuminate\Support\Facades\Log;
use Modules\Accounting\Domain\Customers\Events\CreditLimitAdjusted;

class CreditMetrics
{
    /**
     * Track credit limit adjustment
     */
    public static function trackCreditLimitAdjusted(CreditLimitAdjusted $event): void
    {
        $creditLimit = $event->creditLimit;
        $customer = $event->customer;
        $options = $event->options;

        // Log the adjustment
        Log::info('Credit limit adjusted', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'credit_limit_id' => $creditLimit->id,
            'old_limit' => $options['old_limit'] ?? null,
            'new_limit' => $creditLimit->limit_amount,
            'effective_at' => $creditLimit->effective_at,
            'expires_at' => $creditLimit->expires_at,
            'status' => $creditLimit->status,
            'changed_by_user_id' => $creditLimit->changed_by_user_id,
            'reason' => $creditLimit->reason,
            'approval_reference' => $creditLimit->approval_reference,
            'timestamp' => now(),
        ]);

        // Increment appropriate counters
        self::incrementCreditLimitCounter($creditLimit, $customer);

        // Track significant changes
        self::trackSignificantChanges($creditLimit, $customer, $options);
    }

    /**
     * Track credit limit breach attempts
     */
    public static function trackCreditLimitBreachAttempt(array $data): void
    {
        Log::warning('Credit limit breach attempt detected', [
            'customer_id' => $data['customer_id'],
            'invoice_amount' => $data['invoice_amount'],
            'credit_limit' => $data['credit_limit'],
            'current_exposure' => $data['current_exposure'],
            'excess_amount' => $data['excess_amount'],
            'user_id' => $data['user_id'],
            'timestamp' => now(),
        ]);

        // Increment breach counter
        self::incrementCounter('customer_credit_breach_total');

        // Log high-risk customers
        if ($data['excess_amount'] > 1000) {
            self::incrementCounter('customer_credit_high_risk_breach_total');
        }
    }

    /**
     * Track credit limit overrides
     */
    public static function trackCreditLimitOverride(array $data): void
    {
        Log::info('Credit limit override executed', [
            'customer_id' => $data['customer_id'],
            'invoice_amount' => $data['invoice_amount'],
            'credit_limit' => $data['credit_limit'],
            'current_exposure' => $data['current_exposure'],
            'override_reason' => $data['override_reason'],
            'user_id' => $data['user_id'],
            'timestamp' => now(),
        ]);

        // Increment override counter
        self::incrementCounter('customer_credit_override_total');

        // Track override risk levels
        $excessAmount = $data['invoice_amount'] + $data['current_exposure'] - $data['credit_limit'];
        if ($excessAmount > 5000) {
            self::incrementCounter('customer_credit_high_risk_override_total');
        }
    }

    /**
     * Track credit utilization status
     */
    public static function trackCreditUtilizationStatus(array $utilizationData): void
    {
        $utilizationPercentage = $utilizationData['utilization_percentage'];
        $customerId = $utilizationData['customer_id'];

        // Track utilization thresholds
        if ($utilizationPercentage >= 100) {
            self::incrementCounter('customer_credit_utilization_exceeded_total');
            Log::warning('Customer credit utilization exceeded 100%', $utilizationData);
        } elseif ($utilizationPercentage >= 90) {
            self::incrementCounter('customer_credit_utilization_critical_total');
        } elseif ($utilizationPercentage >= 75) {
            self::incrementCounter('customer_credit_utilization_high_total');
        } elseif ($utilizationPercentage >= 50) {
            self::incrementCounter('customer_credit_utilization_moderate_total');
        }

        // Log high utilization
        if ($utilizationPercentage >= 75) {
            Log::info('High credit utilization detected', [
                'customer_id' => $customerId,
                'utilization_percentage' => $utilizationPercentage,
                'credit_limit' => $utilizationData['credit_limit'],
                'current_exposure' => $utilizationData['current_exposure'],
                'timestamp' => now(),
            ]);
        }
    }

    /**
     * Increment credit limit counter
     */
    private static function incrementCreditLimitCounter($creditLimit, $customer): void
    {
        // General counter
        self::incrementCounter('customer_credit_limit_adjustments_total');

        // Status-specific counters
        if ($creditLimit->status === 'approved') {
            self::incrementCounter('customer_credit_limit_approved_total');
        } elseif ($creditLimit->status === 'pending') {
            self::incrementCounter('customer_credit_limit_pending_total');
        } elseif ($creditLimit->status === 'revoked') {
            self::incrementCounter('customer_credit_limit_revoked_total');
        }

        // Amount range counters
        if ($creditLimit->limit_amount >= 10000) {
            self::incrementCounter('customer_credit_limit_high_value_total');
        } elseif ($creditLimit->limit_amount >= 5000) {
            self::incrementCounter('customer_credit_limit_medium_value_total');
        } else {
            self::incrementCounter('customer_credit_limit_low_value_total');
        }

        // Future effective dates
        if ($creditLimit->effective_at > now()) {
            self::incrementCounter('customer_credit_limit_future_total');
        }
    }

    /**
     * Track significant changes
     */
    private static function trackSignificantChanges($creditLimit, $customer, $options): void
    {
        $oldLimit = $options['old_limit'] ?? 0;
        $newLimit = $creditLimit->limit_amount;

        $changeAmount = abs($newLimit - $oldLimit);
        $changePercentage = $oldLimit > 0 ? ($changeAmount / $oldLimit) * 100 : 100;

        // Track large percentage changes
        if ($changePercentage >= 50) {
            self::incrementCounter('customer_credit_limit_large_change_total');
            Log::info('Large credit limit change detected', [
                'customer_id' => $customer->id,
                'old_limit' => $oldLimit,
                'new_limit' => $newLimit,
                'change_amount' => $changeAmount,
                'change_percentage' => round($changePercentage, 2),
                'timestamp' => now(),
            ]);
        }

        // Track increases vs decreases
        if ($newLimit > $oldLimit) {
            self::incrementCounter('customer_credit_limit_increase_total');
        } elseif ($newLimit < $oldLimit) {
            self::incrementCounter('customer_credit_limit_decrease_total');
        }
    }

    /**
     * Increment a counter metric
     */
    private static function incrementCounter(string $counterName, float $value = 1.0): void
    {
        // This would integrate with your telemetry/metrics system
        // For now, we'll just log the metric
        Log::debug('Telemetry metric incremented', [
            'metric' => $counterName,
            'value' => $value,
            'timestamp' => now(),
        ]);
    }

    /**
     * Get current credit metrics snapshot
     */
    public static function getCurrentMetrics(): array
    {
        // This would query your metrics storage system
        // For now, return a placeholder structure
        return [
            'total_adjustments' => 0,
            'approved_limits' => 0,
            'pending_limits' => 0,
            'breach_attempts' => 0,
            'overrides' => 0,
            'high_utilization_customers' => 0,
            'exceeded_utilization_customers' => 0,
            'average_utilization' => 0,
            'last_updated' => now(),
        ];
    }

    /**
     * Generate daily credit report
     */
    public static function generateDailyReport(): array
    {
        $today = now()->toDateString();

        return [
            'date' => $today,
            'credit_limit_adjustments' => self::getCounterValue("customer_credit_limit_adjustments_total:{$today}"),
            'approved_limits' => self::getCounterValue("customer_credit_limit_approved_total:{$today}"),
            'pending_limits' => self::getCounterValue("customer_credit_limit_pending_total:{$today}"),
            'breach_attempts' => self::getCounterValue("customer_credit_breach_total:{$today}"),
            'overrides' => self::getCounterValue("customer_credit_override_total:{$today}"),
            'high_utilization_customers' => self::getCounterValue("customer_credit_utilization_critical_total:{$today}"),
            'exceeded_utilization_customers' => self::getCounterValue("customer_credit_utilization_exceeded_total:{$today}"),
        ];
    }

    /**
     * Get counter value for a specific date
     */
    private static function getCounterValue(string $counterKey): int
    {
        // This would query your metrics storage system
        // For now, return a placeholder
        return 0;
    }
}
