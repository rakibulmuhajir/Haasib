# ServiceContext Monitoring Guide

## Critical Metrics to Watch

### 1. Error Rates by Service Method
```
# Grafana Dashboard Queries
rate(http_requests_total{job="api", status="5xx", method=~"POST|PUT|DELETE"}[5m]) 
  by (method, route)
```

**Alert Thresholds:**
- PaymentService methods: > 1% error rate
- InvoiceService methods: > 2% error rate  
- LedgerIntegrationService: > 0.5% error rate

### 2. ServiceContext Creation Failures
```
# LogQL query for ServiceContext creation issues
{job="api"} |= "ServiceContext" |= "error" |= "Exception"
```

### 3. Audit Logging Coverage
```
# Percentage of service calls with audit logs
(sum(rate(audit_log_entries_total[1h])) / 
 sum(rate(service_method_calls_total[1h]))) * 100
```

**Target:** > 95% coverage

### 4. Context Propagation in Distributed Tracing
```
# Trace context validation
span{name="ServiceMethod"} 
  | json | __line__.context.userId != null
```

## Common Issues to Monitor

### 1. Missing Context in Services
**Symptoms:**
- `ArgumentCountError` for missing ServiceContext parameter
- `TypeError` when null passed to required ServiceContext

**Log Pattern:**
```
ERROR: Too few arguments to method 
App\Services\PaymentService::createPayment(), 
0 passed in /path/to/file.php on line 123 
and exactly 1 expected
```

### 2. Invalid User Context
**Symptoms:**
- Authentication failures
- Authorization errors
- User ID mismatch in audit logs

**Log Pattern:**
```
WARNING: ServiceContext created with invalid user: null
```

### 3. Company Context Mismatch
**Symptoms:**
- Cross-company data access attempts
- Foreign key constraint violations

**Log Pattern:**
```
ERROR: Company context mismatch. User {user_id} 
attempted to access company {company_id} 
without proper authorization
```

## Dashboard Configuration

### Grafana Dashboard: ServiceContext Health

**Row 1: Error Rates**
- Panel 1: Overall API error rate
- Panel 2: Service-specific error rates
- Panel 3: Context-related errors

**Row 2: Performance Impact**
- Panel 4: Service method latency p95
- Panel 5: Context creation overhead
- Panel 6: Queue processing time

**Row 3: Audit Coverage**
- Panel 7: Audit log success rate
- Panel 8: Missing context by service
- Panel 9: Context completeness score

## Alert Definitions

### High Priority Alerts

```yaml
# ServiceContext Creation Failure
- alert: ServiceContextCreationFailed
  expr: increase(servicecontext_creation_errors_total[5m]) > 5
  for: 2m
  labels:
    severity: critical
    team: backend
  annotations:
    summary: "ServiceContext creation failures detected"
    description: "{{ $value }} ServiceContext creation failures in 5 minutes"

# Missing Context in Production
- alert: MissingServiceContextParameter
  expr: increase(missing_servicecontext_errors_total[5m]) > 3
  for: 1m
  labels:
    severity: critical
    team: backend
  annotations:
    summary: "Services called without required ServiceContext"
    description: "{{ $value }} service method calls missing ServiceContext parameter"
```

### Warning Alerts

```yaml
# Audit Logging Coverage Drop
- alert: AuditLoggingCoverageLow
  expr: audit_logging_coverage < 90
  for: 10m
  labels:
    severity: warning
    team: backend
  annotations:
    summary: "Audit logging coverage below threshold"
    description: "Audit logging coverage is {{ $value }}%, below 90% target"

# Context Creation Latency High
- alert: ServiceContextCreationLatencyHigh
  expr: histogram_quantile(0.95, rate(servicecontext_creation_duration_seconds_bucket[5m])) > 0.1
  for: 5m
  labels:
    severity: warning
    team: backend
  annotations:
    summary: "ServiceContext creation latency high"
    description: "95th percentile ServiceContext creation time is {{ $value }}s"
```

## Log Patterns to Monitor

### 1. Successful Context Creation
```
INFO: ServiceContext created successfully {
  "userId": "uuid-here",
  "companyId": "uuid-here", 
  "idempotencyKey": "key-here",
  "source": "controller|job|command"
}
```

### 2. Context Validation Failures
```
ERROR: ServiceContext validation failed {
  "error": "Invalid user context",
  "details": "User not authenticated",
  "stack": "..."
}
```

### 3. Context Propagation Issues
```
WARNING: Context propagation failed {
  "from": "controller",
  "to": "service",
  "missing": "companyId"
}
```

## Health Check Endpoints

Add these endpoints to your monitoring system:

```php
// routes/web.php
Route::get('/health/servicecontext', function () {
    return [
        'status' => 'healthy',
        'context_creation_rate' => Cache::get('context_creation_rate', 0),
        'audit_coverage' => app(AuditService::class)->getCoverage(),
        'errors_5m' => Cache::get('context_errors_5m', 0),
    ];
});
```

## Incident Response Playbook

### Severity 1: Context Creation Widespread Failures
1. **Impact**: Users cannot perform actions
2. **Triage**: Check auth service, database connections
3. **Mitigation**: 
   - Enable graceful fallback mode
   - Deploy hotfix with increased error tolerance
4. **Communication**: Notify stakeholders immediately

### Severity 2: Audit Logging Gap
1. **Impact**: Compliance risk, missing audit trail
2. **Triage**: Check logging infrastructure, queue status
3. **Mitigation**:
   - Enable verbose logging temporarily
   - Replay missed events from application logs
4. **Communication**: Notify compliance team

### Severity 3: Performance Degradation
1. **Impact**: Slower response times
2. **Triage**: Profile context creation, check cache
3. **Mitigation**:
   - Optimize context creation code
   - Increase cache TTL for user/company data
4. **Communication**: Update status page if affects users

## Rollback Procedures

### Partial Rollback
If specific services are failing:
1. Deploy feature flag to disable ServiceContext for affected services
2. Monitor error rates
3. Fix underlying issue
4. Re-enable ServiceContext

### Full Rollback
```bash
# Emergency rollback commands
git checkout pre-service-context-rollback
composer install
php artisan migrate:rollback --step=3
php artisan cache:clear
php artisan config:clear
```

## Post-Deployment Verification

### Smoke Tests
```bash
# Test ServiceContext creation
curl -X POST https://api.example.com/test/context \
  -H "Authorization: Bearer $TOKEN" \
  -H "Idempotency-Key: test-$(date +%s)"

# Verify audit logs
curl -X GET https://logs.example.com/search \
  -H "Authorization: Bearer $LOG_TOKEN" \
  -d '{"query": "ServiceContext", "timeframe": "5m"}'
```

### Integration Tests
```bash
# Run ServiceContext-specific tests
php artisan test --filter=ServiceContext

# Verify all services work with context
php artisan test --filter=".*Service.*Test"
```

Remember to update these thresholds and patterns based on your actual production metrics and error rates.