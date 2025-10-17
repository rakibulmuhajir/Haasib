# Bank Reconciliation Monitoring Guide

## Overview

This document outlines monitoring expectations for the bank reconciliation system, including performance metrics, error tracking, and operational health indicators.

## System Architecture Monitoring

### Core Components to Monitor

1. **Queue System** - Laravel Horizon
   - Statement import jobs
   - Auto-match processing jobs
   - Report generation jobs
   - Normalization workflows

2. **Database Performance**
   - `ops.bank_statements` table growth
   - `ops.bank_statement_lines` query performance
   - `ledger.bank_reconciliations` locking behavior
   - `ledger.bank_reconciliation_matches` indexing efficiency

3. **File Storage**
   - Statement upload storage usage
   - Report generation temporary files
   - Storage disk availability

4. **WebSocket Broadcasting**
   - Real-time update latency
   - Channel subscription health
   - Event broadcasting throughput

## Key Performance Indicators (KPIs)

### Processing Metrics
- **Statement Import Time**: < 30 seconds for typical files (< 1000 lines)
- **Auto-Match Processing**: < 60 seconds for < 500 statement lines
- **Report Generation**: < 45 seconds for PDF reports
- **UI Response Time**: < 200ms for reconciliation workspace

### Business Metrics
- **Reconciliation Completion Rate**: Percentage of reconciliations completed vs started
- **Variance Resolution Time**: Average time to achieve zero variance
- **User Activity**: Daily active reconcilers and concurrent sessions
- **Error Rate**: Failed imports, matches, and adjustments per 1000 operations

### System Health Metrics
- **Queue Throughput**: Jobs processed per minute
- **Database Connection Pool**: Active connections vs pool size
- **Memory Usage**: Peak memory during report generation
- **File Storage Utilization**: Available space thresholds

## Monitoring Setup

### Laravel Horizon Configuration

```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['bank-statements', 'bank-reconciliation', 'reports'],
            'balance' => 'simple',
            'processes' => 3,
            'tries' => 3,
            'nice' => 0,
        ],
    ],
],
```

### Custom Metrics Collection

Create monitoring service for bank reconciliation metrics:

```php
// app/Services/Monitoring/BankReconciliationMetricsService.php
class BankReconciliationMetricsService
{
    public function collectMetrics(): array
    {
        return [
            'active_reconciliations' => $this->getActiveReconciliations(),
            'queue_depth' => $this->getQueueDepth(),
            'processing_times' => $this->getAverageProcessingTimes(),
            'error_rates' => $this->getErrorRates(),
        ];
    }
}
```

### Database Query Monitoring

Monitor critical queries:

```sql
-- Monitor slow reconciliation queries
SELECT query, mean_exec_time, calls 
FROM pg_stat_statements 
WHERE query LIKE '%bank_reconciliation%' 
ORDER BY mean_exec_time DESC;

-- Monitor table sizes
SELECT 
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as size
FROM pg_tables 
WHERE schemaname IN ('ops', 'ledger') 
    AND tablename LIKE '%bank_reconciliation%'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;
```

## Alerting Rules

### Critical Alerts (Page/SMS)

1. **Queue Failure**
   - Condition: Queue depth > 100 jobs for > 5 minutes
   - Impact: Statement processing stalled

2. **Database Connection Exhaustion**
   - Condition: > 80% of connection pool in use
   - Impact: System performance degradation

3. **File Storage Full**
   - Condition: < 10% available storage
   - Impact: Cannot process new statements

4. **High Error Rate**
   - Condition: Error rate > 5% over 15 minutes
   - Impact: User experience degradation

### Warning Alerts (Email/Slack)

1. **Slow Processing**
   - Condition: Import time > 2x baseline
   - Impact: User wait times increased

2. **Low Match Rate**
   - Condition: Auto-match success rate < 60%
   - Impact: Increased manual work

3. **Concurrent User Limit**
   - Condition: > 50 concurrent reconciliations
   - Impact: Performance concerns

## Log Monitoring

### Application Logs

Monitor these log patterns:

```bash
# Failed statement imports
grep "bank:statement:import.*failed" storage/logs/laravel.log

# Reconciliation completion errors
grep "bank.reconciliation.complete.*error" storage/logs/laravel.log

# Queue job failures
grep "Failed processing bank reconciliation job" storage/logs/laravel.log
```

### Audit Log Patterns

```bash
# Unexpected status changes
grep "bank reconciliation status changed.*failed" storage/logs/audit.log

# Permission denied events
grep "bank reconciliation.*permission denied" storage/logs/audit.log

# Bulk operations
grep "bulk reconciliation operation" storage/logs/audit.log
```

## Performance Testing

### Load Testing Scenarios

1. **Concurrent Statement Imports**
   - 10 users uploading 100-line statements simultaneously
   - Target: < 45 seconds average processing time

2. **Large Statement Processing**
   - Single 5000-line statement import
   - Target: < 5 minutes total processing time

3. **Multi-User Reconciliation**
   - 20 concurrent users working on different reconciliations
   - Target: < 300ms UI response time

4. **Report Generation Load**
   - 5 concurrent PDF report generations
   - Target: < 90 seconds per report

### Database Performance Tests

```bash
# Test query performance with explain plan
EXPLAIN ANALYZE 
SELECT * FROM ledger.bank_reconciliations br
JOIN ops.bank_statements bs ON br.statement_id = bs.id
WHERE br.company_id = $1
  AND br.status = 'in_progress'
ORDER BY br.created_at DESC;

# Test index usage on large tables
EXPLAIN (ANALYZE, BUFFERS) 
SELECT * FROM ops.bank_statement_lines 
WHERE statement_id = $1 
  AND amount = $2;
```

## Health Checks

### Application Health Endpoint

```php
// routes/api.php
Route::get('/health/bank-reconciliation', function () {
    return [
        'status' => 'healthy',
        'checks' => [
            'database' => DB::connection()->getPdo() ? 'ok' : 'error',
            'queue' => Queue::size() < 100 ? 'ok' : 'warning',
            'storage' => Storage::disk('bank-statements')->getAdapter()->isDirectory('/') ? 'ok' : 'error',
            'redis' => Redis::ping() ? 'ok' : 'error',
        ],
        'metrics' => app(BankReconciliationMetricsService::class)->collectMetrics(),
    ];
});
```

### Scheduled Health Checks

```php
// app/Console/Commands/HealthCheckBankReconciliation.php
class HealthCheckBankReconciliation extends Command
{
    public function handle()
    {
        $checks = [
            'queue_depth' => $this->checkQueueDepth(),
            'database_performance' => $this->checkDatabasePerformance(),
            'storage_availability' => $this->checkStorage(),
            'active_sessions' => $this->checkActiveSessions(),
        ];
        
        $this->reportHealthStatus($checks);
    }
}
```

## Troubleshooting Guide

### Common Issues and Solutions

1. **Slow Statement Imports**
   - Check queue worker status
   - Verify OFX parser performance
   - Monitor database insert batching

2. **Auto-Match Inefficiency**
   - Review confidence score thresholds
   - Check indexing on matchable fields
   - Monitor algorithm performance

3. **Report Generation Timeouts**
   - Verify PDF generation library performance
   - Check available memory
   - Monitor file I/O performance

4. **WebSocket Connection Issues**
   - Check Redis connection health
   - Verify broadcasting configuration
   - Monitor channel subscription limits

## Monitoring Dashboard Configuration

### Grafana Dashboard Panels

1. **Reconciliation Overview**
   - Active reconciliations gauge
   - Completion rate trend
   - Average processing time

2. **System Performance**
   - Queue depth chart
   - Database query times
   - Memory usage graph

3. **User Activity**
   - Concurrent users
   - Actions per minute
   - Geographic distribution

4. **Error Tracking**
   - Error rate by operation
   - Top error messages
   - Failed job count

### DataDog/Laravel Telescope Integration

```php
// app/Providers/TelescopeServiceProvider.php
protected function register()
{
    Telescope::tag(function (Request $request) {
        return $request->is('ledger/bank-reconciliations/*') ? ['bank-reconciliation'] : [];
    });
}
```

## Compliance and Audit Considerations

### Data Retention
- Statement files: 7 years (regulatory requirement)
- Audit logs: 10 years (compliance requirement)
- Reconciliation records: Permanent (historical reference)

### Access Monitoring
- Track all report downloads and exports
- Monitor permission changes and role assignments
- Log all reconciliation status changes with user context

### Performance Baselines
- Establish monthly performance baselines
- Monitor for performance degradation over time
- Track seasonal usage patterns