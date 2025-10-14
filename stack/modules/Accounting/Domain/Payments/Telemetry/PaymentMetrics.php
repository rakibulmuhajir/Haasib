<?php

namespace Modules\Accounting\Domain\Payments\Telemetry;

use Illuminate\Support\Facades\Log;

class PaymentMetrics
{
    private const PREFIX = 'payment_';
    
    /**
     * Record payment creation metric
     */
    public static function paymentCreated(string $companyId, string $paymentMethod, float $amount): void
    {
        self::increment('created_total', [
            'company_id' => $companyId,
            'payment_method' => $paymentMethod,
        ]);
        
        self::histogram('created_amount', $amount, [
            'company_id' => $companyId,
            'payment_method' => $paymentMethod,
        ]);
        
        Log::info('Payment created', [
            'event' => 'payment.created',
            'company_id' => $companyId,
            'payment_method' => $paymentMethod,
            'amount' => $amount,
            'timestamp' => now()->toISOString(),
        ]);
    }
    
    /**
     * Record payment allocation metric
     */
    public static function allocationApplied(string $companyId, string $strategy, int $allocationCount, float $totalAmount): void
    {
        self::increment('allocation_applied_total', [
            'company_id' => $companyId,
            'strategy' => $strategy,
        ]);
        
        self::histogram('allocation_amount', $totalAmount, [
            'company_id' => $companyId,
            'strategy' => $strategy,
        ]);
        
        self::histogram('allocation_count', $allocationCount, [
            'company_id' => $companyId,
            'strategy' => $strategy,
        ]);
        
        Log::info('Payment allocation applied', [
            'event' => 'payment.allocation.applied',
            'company_id' => $companyId,
            'strategy' => $strategy,
            'allocation_count' => $allocationCount,
            'total_amount' => $totalAmount,
            'timestamp' => now()->toISOString(),
        ]);
    }
    
    /**
     * Record payment failure metric
     */
    public static function paymentFailed(string $companyId, string $paymentMethod, string $reason): void
    {
        self::increment('failure_total', [
            'company_id' => $companyId,
            'payment_method' => $paymentMethod,
            'reason' => $reason,
        ]);
        
        Log::warning('Payment failed', [
            'event' => 'payment.failed',
            'company_id' => $companyId,
            'payment_method' => $paymentMethod,
            'reason' => $reason,
            'timestamp' => now()->toISOString(),
        ]);
    }
    
    /**
     * Record allocation failure metric
     */
    public static function allocationFailed(string $companyId, string $strategy, string $reason): void
    {
        self::increment('allocation_failure_total', [
            'company_id' => $companyId,
            'strategy' => $strategy,
            'reason' => $reason,
        ]);
        
        Log::warning('Payment allocation failed', [
            'event' => 'payment.allocation.failed',
            'company_id' => $CompanyId,
            'strategy' => $strategy,
            'reason' => $reason,
            'timestamp' => now()->toISOString(),
        ]);
    }
    
    /**
     * Record batch processing metric
     */
    public static function batchProcessed(string $companyId, int $paymentCount, int $successCount, int $failureCount): void
    {
        self::increment('batch_processed_total', [
            'company_id' => $companyId,
        ]);
        
        self::histogram('batch_payment_count', $paymentCount, [
            'company_id' => $companyId,
        ]);
        
        self::histogram('batch_success_rate', $failureCount > 0 ? $successCount / ($successCount + $failureCount) : 1.0, [
            'company_id' => $companyId,
        ]);
        
        Log::info('Payment batch processed', [
            'event' => 'payment.batch.processed',
            'company_id' => $companyId,
            'payment_count' => $paymentCount,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'success_rate' => $failureCount > 0 ? $successCount / ($successCount + $failureCount) : 1.0,
            'timestamp' => now()->toISOString(),
        ]);
    }
    
    /**
     * Record payment reversal metric
     */
    public static function paymentReversed(string $companyId, string $reason, float $amount): void
    {
        self::increment('reversal_total', [
            'company_id' => $companyId,
            'reason' => $reason,
        ]);
        
        self::histogram('reversal_amount', $amount, [
            'company_id' => $companyId,
            'reason' => $reason,
        ]);
        
        Log::info('Payment reversed', [
            'event' => 'payment.reversed',
            'company_id' => $companyId,
            'reason' => $reason,
            'amount' => $amount,
            'timestamp' => now()->toISOString(),
        ]);
    }
    
    /**
     * Increment counter metric
     */
    private static function increment(string $name, array $tags = []): void
    {
        // This would integrate with Prometheus/StatsD or similar
        // For now, we'll log to application logs
        Log::debug('Metric increment', [
            'metric' => self::PREFIX . $name,
            'value' => 1,
            'tags' => $tags,
        ]);
    }
    
    /**
     * Record histogram metric
     */
    private static function histogram(string $name, float $value, array $tags = []): void
    {
        // This would integrate with Prometheus/StatsD or similar
        // For now, we'll log to application logs
        Log::debug('Metric histogram', [
            'metric' => self::PREFIX . $name,
            'value' => $value,
            'tags' => $tags,
        ]);
    }
}