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
     * Record batch creation metric
     */
    public static function batchCreated(string $companyId, string $sourceType, int $receiptCount, float $totalAmount): void
    {
        self::increment('batch_created_total', [
            'company_id' => $companyId,
            'source_type' => $sourceType,
        ]);
        
        self::histogram('batch_receipt_count', $receiptCount, [
            'company_id' => $companyId,
            'source_type' => $sourceType,
        ]);
        
        self::histogram('batch_total_amount', $totalAmount, [
            'company_id' => $companyId,
            'source_type' => $sourceType,
        ]);
        
        Log::info('Payment batch created', [
            'event' => 'payment.batch.created',
            'company_id' => $companyId,
            'source_type' => $sourceType,
            'receipt_count' => $receiptCount,
            'total_amount' => $totalAmount,
            'timestamp' => now()->toISOString(),
        ]);
    }
    
    /**
     * Record batch processing metric
     */
    public static function batchProcessed(string $companyId, string $sourceType, int $processedCount, float $processedAmount): void
    {
        self::increment('batch_processed_total', [
            'company_id' => $companyId,
            'source_type' => $sourceType,
        ]);
        
        self::histogram('batch_processed_count', $processedCount, [
            'company_id' => $companyId,
            'source_type' => $sourceType,
        ]);
        
        self::histogram('batch_processed_amount', $processedAmount, [
            'company_id' => $companyId,
            'source_type' => $sourceType,
        ]);
        
        Log::info('Payment batch processed', [
            'event' => 'payment.batch.processed',
            'company_id' => $companyId,
            'source_type' => $sourceType,
            'processed_count' => $processedCount,
            'processed_amount' => $processedAmount,
            'timestamp' => now()->toISOString(),
        ]);
    }
    
    /**
     * Record batch failure metric
     */
    public static function batchFailed(string $companyId, string $sourceType, int $failedCount): void
    {
        self::increment('batch_failed_total', [
            'company_id' => $companyId,
            'source_type' => $sourceType,
        ]);
        
        self::histogram('batch_failure_count', $failedCount, [
            'company_id' => $companyId,
            'source_type' => $sourceType,
        ]);
        
        Log::warning('Payment batch failed', [
            'event' => 'payment.batch.failed',
            'company_id' => $companyId,
            'source_type' => $sourceType,
            'failed_count' => $failedCount,
            'timestamp' => now()->toISOString(),
        ]);
    }
    
    /**
     * Record batch processing errors metric
     */
    public static function batchErrors(string $companyId, string $sourceType, int $errorCount): void
    {
        self::increment('batch_error_total', [
            'company_id' => $companyId,
            'source_type' => $sourceType,
        ]);
        
        self::histogram('batch_error_count', $errorCount, [
            'company_id' => $companyId,
            'source_type' => $sourceType,
        ]);
        
        Log::warning('Payment batch errors recorded', [
            'event' => 'payment.batch.errors',
            'company_id' => $companyId,
            'source_type' => $sourceType,
            'error_count' => $errorCount,
            'timestamp' => now()->toISOString(),
        ]);
    }
    
    /**
     * Record batch processing time metric
     */
    public static function batchProcessingTime(string $companyId, string $sourceType, float $processingTimeSeconds): void
    {
        self::histogram('batch_processing_time_seconds', $processingTimeSeconds, [
            'company_id' => $companyId,
            'source_type' => $sourceType,
        ]);
        
        Log::info('Payment batch processing time recorded', [
            'event' => 'payment.batch.processing_time',
            'company_id' => $companyId,
            'source_type' => $sourceType,
            'processing_time_seconds' => $processingTimeSeconds,
            'timestamp' => now()->toISOString(),
        ]);
    }
    
    /**
     * Record batch retry metric
     */
    public static function batchRetry(string $companyId, string $batchId, string $reason): void
    {
        self::increment('batch_retry_total', [
            'company_id' => $companyId,
            'reason' => $reason,
        ]);
        
        Log::info('Payment batch retry initiated', [
            'event' => 'payment.batch.retry',
            'company_id' => $companyId,
            'batch_id' => $batchId,
            'reason' => $reason,
            'timestamp' => now()->toISOString(),
        ]);
    }
    
    /**
     * Record allocation reversal metric for batch context
     */
    public static function allocationReversed(string $companyId, float $originalAmount, float $refundAmount): void
    {
        self::increment('allocation_reversal_total', [
            'company_id' => $companyId,
        ]);
        
        self::histogram('allocation_reversal_original_amount', $originalAmount, [
            'company_id' => $companyId,
        ]);
        
        self::histogram('allocation_reversal_refund_amount', $refundAmount, [
            'company_id' => $companyId,
        ]);
        
        Log::info('Allocation reversed', [
            'event' => 'payment.allocation.reversed',
            'company_id' => $companyId,
            'original_amount' => $originalAmount,
            'refund_amount' => $refundAmount,
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