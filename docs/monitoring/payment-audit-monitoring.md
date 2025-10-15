# Payment Audit & Reporting Monitoring Guide

## Overview

This guide covers monitoring strategies, metrics, and alerting for the Payment Audit & Reporting system. Proper monitoring ensures system reliability, performance optimization, and compliance requirements.

## Architecture Overview

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Payment API   │───▶│  Audit Events   │───▶│  Audit Logs     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Metrics       │    │   Event Queue   │    │   Database      │
│   Collection    │    │                 │    │   Indexes       │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Monitoring    │    │   Real-time     │    │   Report        │
│   Dashboard     │    │   Broadcasting  │    │   Generation    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## Key Performance Indicators (KPIs)

### 1. System Health Metrics

#### API Response Times
- **Audit Trail Endpoint**: Target < 200ms (p95)
- **Metrics Endpoint**: Target < 100ms (p95)  
- **Report Generation**: Target < 5s for standard reports
- **WebSocket Latency**: Target < 50ms

#### Error Rates
- **API Error Rate**: Target < 0.1%
- **Audit Event Processing**: Target < 0.01% failure rate
- **Database Query Failures**: Target < 0.05%
- **Queue Processing Failures**: Target < 0.1%

#### Throughput Metrics
- **Audit Events/sec**: Baseline 100 events/sec
- **Concurrent Users**: Support 500+ concurrent users
- **Report Generation**: Support 10+ concurrent reports
- **Database Connections**: Maintain < 80% connection pool usage

### 2. Business Metrics

#### Audit Completeness
- **Audit Event Coverage**: 100% of payment operations
- **Event Timestamp Accuracy**: < 1s variance
- **Metadata Completeness**: 100% required fields populated
- **Data Integrity**: 99.99% consistency rate

#### Compliance Metrics
- **Audit Trail Immutability**: 100% tamper-proof verification
- **Data Retention**: Meet regulatory requirements (7 years)
- **Access Log Completeness**: 100% of access attempts logged
- **Change Tracking**: 100% of modifications tracked

## Monitoring Stack

### 1. Application Monitoring

#### Laravel Telescope
```php
// config/telescope.php
'watchers' => [
    'requests' => ['enabled' => true],
    'commands' => ['enabled' => true],
    'schedules' => ['enabled' => true],
    'queries' => ['enabled' => true],
    'models' => ['enabled' => true],
    'events' => ['enabled' => true],
    'notifications' => ['enabled' => true],
    'mail' => ['enabled' => true],
    'cache' => ['enabled' => true],
    'redis' => ['enabled' => true],
],
```

#### Custom Metrics Collection
```php
// App/Providers/MetricsServiceProvider.php
namespace App\Providers;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider;

class MetricsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Record audit event metrics
        Event::listen('payment.audit.created', function ($event) {
            $this->incrementCounter('payment.audit.events.total', [
                'action' => $event->action,
                'actor_type' => $event->actor_type
            ]);
        });
    }

    private function incrementCounter($name, $tags = [])
    {
        $key = "metrics:{$name}:" . implode(':', $tags);
        Redis::incrby($key, 1);
        Redis::expire($key, 3600); // 1 hour TTL
    }
}
```

### 2. Database Monitoring

#### Query Performance Monitoring
```sql
-- Enable PostgreSQL query statistics
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;

-- Monitor slow queries
SELECT 
    query,
    calls,
    total_time,
    mean_time,
    rows
FROM pg_stat_statements 
WHERE query LIKE '%payment_audit_log%'
ORDER BY mean_time DESC
LIMIT 10;
```

#### Index Usage Monitoring
```sql
-- Monitor audit log index usage
SELECT 
    schemaname,
    tablename,
    indexname,
    idx_scan,
    idx_tup_read,
    idx_tup_fetch
FROM pg_stat_user_indexes 
WHERE tablename = 'payment_audit_log';
```

#### Table Size Monitoring
```sql
-- Monitor audit log table growth
SELECT 
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as size,
    pg_total_relation_size(schemaname||'.'||tablename) as size_bytes
FROM pg_tables 
WHERE tablename LIKE '%audit%';
```

### 3. Infrastructure Monitoring

#### Redis Monitoring
```bash
# Redis memory usage
redis-cli info memory | grep used_memory_human

# Redis connection stats
redis-cli info stats | grep connected_clients

# Redis slow log
redis-cli slowlog get 10
```

#### Queue Monitoring
```php
// Custom Artisan command for queue health
class QueueHealthCommand extends Command
{
    public function handle()
    {
        $queues = ['audit_events', 'report_generation'];
        
        foreach ($queues as $queue) {
            $size = Queue::size($queue);
            $failed = Queue::failed()->where('queue', $queue)->count();
            
            $this->info("Queue: {$queue}");
            $this->line("  Pending: {$size}");
            $this->line("  Failed: {$failed}");
            
            if ($size > 1000) {
                $this->warn("  ⚠️  High queue size detected!");
            }
            
            if ($failed > 10) {
                $this->error("  ❌ High failure rate detected!");
            }
        }
    }
}
```

## Alerting Configuration

### 1. Critical Alerts

#### System Health
```yaml
# Prometheus alerting rules
groups:
  - name: payment-audit-critical
    rules:
      - alert: AuditAPIHighErrorRate
        expr: rate(http_requests_total{status=~"5..",handler="*audit*"}[5m]) > 0.01
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "High error rate on audit API endpoints"
          description: "Error rate is {{ $value }} errors per second"

      - alert: AuditEventProcessingFailure
        expr: rate(queue_failures_total{queue="audit_events"}[5m]) > 0.001
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: "Audit event processing failures detected"
          description: "Failure rate: {{ $value }} per second"

      - alert: DatabaseConnectionExhaustion
        expr: pg_stat_database_numbackends / pg_settings_max_connections > 0.9
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: "Database connections nearly exhausted"
          description: "{{ $value | humanizePercentage }} of connections used"
```

#### Business Logic
```yaml
      - alert: AuditEventGap
        expr: time() - audit_events_last_timestamp > 300
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "No audit events received in 5 minutes"
          description: "Last audit event: {{ $value }} seconds ago"

      - alert: AuditLogIntegrityFailure
        expr: audit_log_integrity_check_result != 1
        for: 0m
        labels:
          severity: critical
        annotations:
          summary: "Audit log integrity check failed"
          description: "Potential tampering detected in audit logs"
```

### 2. Warning Alerts

```yaml
  - name: payment-audit-warnings
    rules:
      - alert: AuditAPIHighLatency
        expr: histogram_quantile(0.95, rate(http_request_duration_seconds_bucket{handler="*audit*"}[5m])) > 0.5
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High latency on audit API endpoints"
          description: "95th percentile latency: {{ $value }}s"

      - alert: AuditQueueBacklog
        expr: queue_size{queue="audit_events"} > 500
        for: 10m
        labels:
          severity: warning
        annotations:
          summary: "Audit event queue backlog growing"
          description: "Queue size: {{ $value }} pending events"

      - alert: ReportGenerationSlow
        expr: histogram_quantile(0.95, rate(report_generation_duration_seconds_bucket[5m])) > 30
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Slow report generation detected"
          description: "95th percentile: {{ $value }}s"
```

## Logging Strategy

### 1. Structured Logging

#### Audit Event Logging
```php
// App/Logging/AuditLogger.php
namespace App\Logging;

use Illuminate\Support\Facades\Log;

class AuditLogger
{
    public static function logAuditEvent(array $event): void
    {
        Log::info('payment_audit_event', [
            'event_type' => 'audit',
            'payment_id' => $event['payment_id'],
            'company_id' => $event['company_id'],
            'action' => $event['action'],
            'actor_id' => $event['actor_id'],
            'actor_type' => $event['actor_type'],
            'timestamp' => $event['timestamp'],
            'metadata' => $event['metadata'],
            'request_id' => request()->header('X-Request-ID'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public static function logPerformanceMetrics(string $operation, float $duration, array $context = []): void
    {
        Log::info('performance_metric', [
            'event_type' => 'performance',
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'context' => $context,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
```

#### Error Logging
```php
// App/Exceptions/Handler.php
public function report(Throwable $exception)
{
    if ($exception instanceof QueryException) {
        Log::error('database_error', [
            'exception_type' => get_class($exception),
            'message' => $exception->getMessage(),
            'sql' => $exception->getSql(),
            'bindings' => $exception->getBindings(),
            'request_id' => request()->header('X-Request-ID'),
            'user_id' => auth()->id(),
            'company_id' => request()->header('X-Company-Id'),
        ]);
    }

    parent::report($exception);
}
```

### 2. Log Aggregation

#### ELK Stack Configuration
```yaml
# docker-compose.yml
version: '3.8'
services:
  elasticsearch:
    image: docker.elastic.co/elasticsearch/elasticsearch:8.5.0
    environment:
      - discovery.type=single-node
      - "ES_JAVA_OPTS=-Xms512m -Xmx512m"
    ports:
      - "9200:9200"

  logstash:
    image: docker.elastic.co/logstash/logstash:8.5.0
    volumes:
      - ./logstash.conf:/usr/share/logstash/pipeline/logstash.conf
    ports:
      - "5044:5044"
    depends_on:
      - elasticsearch

  kibana:
    image: docker.elastic.co/kibana/kibana:8.5.0
    ports:
      - "5601:5601"
    environment:
      - ELASTICSEARCH_HOSTS=http://elasticsearch:9200
    depends_on:
      - elasticsearch
```

#### Logstash Configuration
```ruby
# logstash.conf
input {
  beats {
    port => 5044
  }
}

filter {
  if [fields][app] == "haasib-payment-audit" {
    json {
      source => "message"
    }

    date {
      match => [ "timestamp", "ISO8601" ]
    }

    if [event_type] == "audit" {
      mutate {
        add_tag => [ "audit_event" ]
      }
    }

    if [event_type] == "performance" {
      mutate {
        add_tag => [ "performance_metric" ]
      }
    }
  }
}

output {
  elasticsearch {
    hosts => ["elasticsearch:9200"]
    index => "haasib-payment-audit-%{+YYYY.MM.dd}"
  }
}
```

## Health Checks

### 1. Application Health Endpoints

```php
// routes/api.php
Route::prefix('api/accounting/health')->group(function () {
    Route::get('/', 'HealthController@index');
    Route::get('/audit', 'HealthController@audit');
    Route::get('/database', 'HealthController@database');
    Route::get('/queue', 'HealthController@queue');
    Route::get('/redis', 'HealthController@redis');
});

// app/Http/Controllers/HealthController.php
class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version'),
            'checks' => [
                'database' => $this->checkDatabase(),
                'redis' => $this->checkRedis(),
                'queue' => $this->checkQueue(),
                'audit_system' => $this->checkAuditSystem(),
            ]
        ]);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'healthy', 'response_time' => $this->measureDatabaseQueryTime()];
        } catch (Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            Redis::ping();
            return ['status' => 'healthy', 'response_time' => $this->measureRedisResponseTime()];
        } catch (Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        $size = Queue::size('audit_events');
        $failed = Queue::failed()->where('queue', 'audit_events')->count();
        
        return [
            'status' => ($size < 1000 && $failed < 10) ? 'healthy' : 'degraded',
            'pending_jobs' => $size,
            'failed_jobs' => $failed,
        ];
    }

    private function checkAuditSystem(): array
    {
        try {
            $lastEvent = DB::table('invoicing.payment_audit_log')
                ->orderBy('timestamp', 'desc')
                ->value('timestamp');
                
            $secondsSinceLastEvent = $lastEvent 
                ? now()->diffInSeconds(Carbon::parse($lastEvent))
                : 999999;
                
            return [
                'status' => $secondsSinceLastEvent < 300 ? 'healthy' : 'degraded',
                'last_event' => $lastEvent,
                'seconds_since_last_event' => $secondsSinceLastEvent,
            ];
        } catch (Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }
}
```

### 2. Database Health Monitoring

```sql
-- Database health check query
SELECT 
    'database_health' as check_type,
    now() as timestamp,
    (SELECT count(*) FROM pg_stat_activity WHERE state = 'active') as active_connections,
    (SELECT count(*) FROM pg_stat_activity WHERE state = 'idle') as idle_connections,
    (SELECT count(*) FROM pg_stat_activity WHERE wait_event_type IS NOT NULL) as waiting_connections,
    (SELECT count(*) FROM pg_locks WHERE granted = false) as blocked_queries,
    pg_database_size('haasib_production') as database_size_bytes,
    pg_size_pretty(pg_database_size('haasib_production')) as database_size;

-- Audit table health check
SELECT 
    'audit_table_health' as check_type,
    now() as timestamp,
    count(*) as total_audit_records,
    count(*) FILTER (WHERE timestamp >= now() - interval '24 hours') as last_24h_records,
    count(*) FILTER (WHERE timestamp >= now() - interval '1 hour') as last_1h_records,
    min(timestamp) as earliest_record,
    max(timestamp) as latest_record
FROM invoicing.payment_audit_log;
```

## Performance Optimization

### 1. Database Optimization

#### Audit Log Partitioning
```sql
-- Create partitioned audit log table
CREATE TABLE invoicing.payment_audit_log_partitioned (
    LIKE invoicing.payment_audit_log INCLUDING ALL
) PARTITION BY RANGE (timestamp);

-- Create monthly partitions
CREATE TABLE invoicing.payment_audit_log_y2025m01 
PARTITION OF invoicing.payment_audit_log_partitioned
FOR VALUES FROM ('2025-01-01') TO ('2025-02-01');

CREATE TABLE invoicing.payment_audit_log_y2025m02 
PARTITION OF invoicing.payment_audit_log_partitioned
FOR VALUES FROM ('2025-02-01') TO ('2025-03-01');

-- Automated partition creation
CREATE OR REPLACE FUNCTION create_monthly_partition()
RETURNS void AS $$
DECLARE
    start_date date;
    end_date date;
    partition_name text;
BEGIN
    start_date := date_trunc('month', CURRENT_DATE + interval '1 month');
    end_date := start_date + interval '1 month';
    partition_name := 'payment_audit_log_y' || to_char(start_date, 'YYYY') || 'm' || to_char(start_date, 'MM');
    
    EXECUTE format('CREATE TABLE IF NOT EXISTS invoicing.%I PARTITION OF invoicing.payment_audit_log_partitioned 
                    FOR VALUES FROM (%L) TO (%L)', 
                    partition_name, start_date, end_date);
END;
$$ LANGUAGE plpgsql;

-- Schedule partition creation
SELECT cron.schedule('create-audit-partition', '0 0 1 * *', 'SELECT create_monthly_partition();');
```

#### Index Optimization
```sql
-- Optimized indexes for audit queries
CREATE INDEX CONCURRENTLY idx_payment_audit_log_timestamp 
ON invoicing.payment_audit_log (timestamp DESC);

CREATE INDEX CONCURRENTLY idx_payment_audit_log_company_timestamp 
ON invoicing.payment_audit_log (company_id, timestamp DESC);

CREATE INDEX CONCURRENTLY idx_payment_audit_log_action_timestamp 
ON invoicing.payment_audit_log (action, timestamp DESC);

CREATE INDEX CONCURRENTLY idx_payment_audit_log_payment_id 
ON invoicing.payment_audit_log (payment_id) WHERE payment_id IS NOT NULL;

-- Partial indexes for common queries
CREATE INDEX CONCURRENTLY idx_payment_audit_log_recent 
ON invoicing.payment_audit_log (timestamp DESC, company_id) 
WHERE timestamp >= now() - interval '30 days';
```

### 2. Caching Strategy

```php
// App/Services/AuditCacheService.php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class AuditCacheService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const METRICS_CACHE_KEY = 'audit_metrics:%s:%s';

    public function getMetrics(string $companyId, string $dateRange): ?array
    {
        $cacheKey = sprintf(self::METRICS_CACHE_KEY, $companyId, $dateRange);
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($companyId, $dateRange) {
            return $this->calculateMetrics($companyId, $dateRange);
        });
    }

    public function invalidateMetricsCache(string $companyId): void
    {
        $pattern = sprintf(self::METRICS_CACHE_KEY, $companyId, '*');
        $keys = Redis::keys($pattern);
        
        if (!empty($keys)) {
            Redis::del($keys);
        }
    }

    private function calculateMetrics(string $companyId, string $dateRange): array
    {
        // Expensive metrics calculation
        return [];
    }
}
```

### 3. Query Optimization

```php
// App/Services/AuditQueryOptimizer.php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class AuditQueryOptimizer
{
    public function optimizeAuditTrailQuery(array $filters): array
    {
        $query = DB::table('invoicing.payment_audit_log as audit')
            ->select([
                'audit.id',
                'audit.payment_id',
                'audit.action',
                'audit.actor_id',
                'audit.actor_type',
                'audit.timestamp',
                'audit.metadata',
                'p.payment_number',
                'p.payment_method',
                'p.amount',
                'c.name as entity_name',
            ])
            ->leftJoin('acct.payments as p', 'audit.payment_id', '=', 'p.payment_id')
            ->leftJoin('hrm.customers as c', 'p.entity_id', '=', 'c.customer_id');

        // Apply date range filtering first (most selective)
        if (!empty($filters['start_date'])) {
            $query->where('audit.timestamp', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('audit.timestamp', '<=', $filters['end_date'] . ' 23:59:59');
        }

        // Use index hints for better performance
        $query->from('invoicing.payment_audit_log audit USE INDEX (idx_payment_audit_log_company_timestamp)');

        // Optimize for recent data (common case)
        if (empty($filters['start_date']) && empty($filters['end_date'])) {
            $query->where('audit.timestamp', '>=', now()->subDays(30))
                  ->from('invoicing.payment_audit_log audit USE INDEX (idx_payment_audit_log_recent)');
        }

        return $query->orderBy('audit.timestamp', 'desc')
                    ->paginate($filters['limit'] ?? 50);
    }
}
```

## Capacity Planning

### 1. Storage Planning

#### Audit Log Growth Projections
```php
// app/Console/Commands/AuditStorageForecast.php
class AuditStorageForecast extends Command
{
    public function handle()
    {
        $currentSize = DB::selectOne("
            SELECT pg_total_relation_size('invoicing.payment_audit_log') as size_bytes
        ")->size_bytes;

        $dailyGrowthRate = $this->calculateDailyGrowthRate();
        $yearlyProjection = $dailyGrowthRate * 365;
        
        $this->info("Current audit log size: " . $this->formatBytes($currentSize));
        $this->info("Daily growth rate: " . $this->formatBytes($dailyGrowthRate));
        $this->info("Yearly projection: " . $this->formatBytes($yearlyProjection));
        
        // Alert if storage needs attention
        if ($yearlyProjection > 100 * 1024 * 1024 * 1024) { // 100GB
            $this->warn('⚠️  Yearly growth exceeds 100GB - consider archiving strategy');
        }
    }

    private function calculateDailyGrowthRate(): int
    {
        $size7DaysAgo = DB::selectOne("
            SELECT pg_total_relation_size('invoicing.payment_audit_log') - 
                   COALESCE((
                       SELECT sum(pg_total_relation_size('invoicing.payment_audit_log_y*'))
                       FROM pg_tables 
                       WHERE tablename LIKE 'payment_audit_log_y%'
                   ), 0) as size_bytes
            WHERE timestamp >= now() - interval '7 days'
        ")->size_bytes ?? 0;

        return intval($size7DaysAgo / 7);
    }
}
```

### 2. Performance Scaling

#### Horizontal Scaling Considerations
- Database read replicas for audit queries
- Queue workers for audit event processing
- Caching layers for metrics computation
- Microservice decomposition for audit subsystem

## Security Monitoring

### 1. Access Monitoring

```php
// App/Http/Middleware/AuditAccessMonitor.php
class AuditAccessMonitor
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        
        // Log access to audit endpoints
        if ($request->is('api/accounting/payments/audit*')) {
            Log::info('audit_access', [
                'user_id' => auth()->id(),
                'company_id' => $request->header('X-Company-Id'),
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'response_status' => $response->getStatusCode(),
                'filters' => $request->all(),
            ]);
        }
        
        return $response;
    }
}
```

### 2. Data Integrity Monitoring

```sql
-- Audit log integrity check
CREATE OR REPLACE FUNCTION check_audit_log_integrity()
RETURNS TABLE(check_type text, status text, details jsonb) AS $$
BEGIN
    -- Check for gaps in timestamps (potential data loss)
    RETURN QUERY
    SELECT 
        'timestamp_gap' as check_type,
        CASE WHEN gap_count > 0 THEN 'FAILED' ELSE 'PASSED' END as status,
        jsonb_build_object('gap_count', gap_count, 'gaps', gaps) as details
    FROM (
        SELECT 
            count(*) as gap_count,
            array_agg(gap_start || ' to ' || gap_end) as gaps
        FROM (
            SELECT 
                prev_timestamp + interval '1 second' as gap_start,
                timestamp as gap_end
            FROM (
                SELECT 
                    timestamp,
                    lag(timestamp) OVER (ORDER BY timestamp) as prev_timestamp
                FROM invoicing.payment_audit_log
                WHERE timestamp >= now() - interval '24 hours'
            ) t
            WHERE timestamp - prev_timestamp > interval '1 minute'
        ) gap_analysis
    ) gap_check;
    
    -- Check for missing required fields
    RETURN QUERY
    SELECT 
        'required_fields' as check_type,
        CASE WHEN missing_fields > 0 THEN 'FAILED' ELSE 'PASSED' END as status,
        jsonb_build_object('missing_count', missing_fields, 'affected_records', affected_records) as details
    FROM (
        SELECT 
            count(*) as missing_fields,
            array_agg(id) as affected_records
        FROM invoicing.payment_audit_log
        WHERE timestamp >= now() - interval '24 hours'
        AND (payment_id IS NULL OR company_id IS NULL OR action IS NULL)
    ) field_check;
END;
$$ LANGUAGE plpgsql;

-- Schedule integrity checks
SELECT cron.schedule('audit-integrity-check', '*/10 * * * *', 'SELECT * FROM check_audit_log_integrity()');
```

## Troubleshooting Guide

### Common Issues and Solutions

#### 1. Slow Audit Queries
**Symptoms**: API response times > 2s
**Causes**: Missing indexes, large data volume, inefficient queries
**Solutions**:
```sql
-- Analyze slow queries
EXPLAIN (ANALYZE, BUFFERS) 
SELECT * FROM invoicing.payment_audit_log 
WHERE company_id = 'uuid' 
  AND timestamp >= '2025-01-01' 
ORDER BY timestamp DESC 
LIMIT 50;

-- Add missing indexes
CREATE INDEX CONCURRENTLY idx_audit_company_timestamp 
ON invoicing.payment_audit_log (company_id, timestamp DESC);
```

#### 2. Queue Backlog
**Symptoms**: Audit events not appearing in real-time
**Causes**: Queue worker failures, high volume, resource constraints
**Solutions**:
```bash
# Check queue status
php artisan queue:monitor audit_events

# Restart queue workers
php artisan queue:restart

# Scale workers
php artisan queue:work --queue=audit_events --max-time=3600 &
```

#### 3. Database Connection Exhaustion
**Symptoms**: Connection timeout errors
**Causes**: Too many concurrent connections, connection leaks
**Solutions**:
```php
// Optimize connection pool
'pgsql' => [
    'driver' => 'pgsql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_schema' => '',
    'search_path' => 'public',
    'sslmode' => 'prefer',
    'options' => [
        'max_connections' => 100,
        'connect_timeout' => 5,
        'read_timeout' => 30,
        'write_timeout' => 30,
    ],
],
```

## Disaster Recovery

### 1. Backup Strategy

```bash
#!/bin/bash
# scripts/backup-audit-data.sh

# Database backup
pg_dump -h localhost -U postgres -d haasib_production \
    -t invoicing.payment_audit_log \
    --format=custom \
    --compress=9 \
    --file=/backups/audit_log_$(date +%Y%m%d_%H%M%S).dump

# Verify backup integrity
pg_restore --list /backups/audit_log_*.dump | head -10

# Upload to cloud storage
aws s3 cp /backups/audit_log_*.dump s3://haasib-backups/audit-logs/
```

### 2. Recovery Procedures

```bash
#!/bin/bash
# scripts/recover-audit-data.sh

BACKUP_FILE=$1
RECOVERY_TABLE="payment_audit_log_recovery_$(date +%Y%m%d_%H%M%S)"

# Create recovery table
psql -h localhost -U postgres -d haasib_production << EOF
CREATE TABLE invoicing.${RECOVERY_TABLE} (LIKE invoicing.payment_audit_log INCLUDING ALL);
EOF

# Restore from backup
pg_restore -h localhost -U postgres -d haasib_production \
    --table=${RECOVERY_TABLE} \
    --clean \
    --if-exists \
    ${BACKUP_FILE}

# Verify recovery
psql -h localhost -U postgres -d haasib_production << EOF
SELECT 
    'recovery_verification' as check_type,
    count(*) as recovered_records,
    min(timestamp) as earliest_record,
    max(timestamp) as latest_record
FROM invoicing.${RECOVERY_TABLE};
EOF
```

This comprehensive monitoring guide ensures the Payment Audit & Reporting system maintains high availability, performance, and reliability while meeting compliance requirements.