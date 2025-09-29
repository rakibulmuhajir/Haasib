# Error Budget & Alert Threshold Review for ServiceContext Rollout

## 📊 Error Budget Overview

### Service-Level Objectives (SLOs)
Based on the ServiceContext rollout, we need to define and monitor error budgets for each affected service.

#### Critical Services (99.9% Uptime = 43m 49s downtime/month)
- PaymentService
- InvoiceService
- LedgerIntegrationService

#### Standard Services (99.5% Uptime = 3h 39m downtime/month)
- All other services

### Error Budget Calculation
```
Error Budget = (100% - SLO%) × Period
Example: 99.9% SLO × 30 days = 43m 49s error budget per month
```

## 🚨 Alert Thresholds

### ServiceContext-Specific Alerts

#### 1. ServiceContext Creation Failures
```yaml
alert: ServiceContextCreationFailed
expr: rate(servicecontext_creation_errors_total[5m]) > 0.1
for: 2m
labels:
  severity: critical
  service: all
  component: ServiceContext
annotations:
  summary: "ServiceContext creation failures detected"
  description: "{{ $value }} ServiceContext creation failures in 5 minutes"
  runbook_url: "https://runbooks.example.com/servicecontext-creation"
```

#### 2. Missing ServiceContext Parameter
```yaml
alert: MissingServiceContextParameter
expr: increase(missing_servicecontext_errors_total[5m]) > 3
for: 1m
labels:
  severity: critical
  service: all
  component: Services
annotations:
  summary: "Services called without required ServiceContext"
  description: "{{ $value }} service method calls missing ServiceContext parameter"
  runbook_url: "https://runbooks.example.com/missing-context"
```

#### 3. ServiceContext Propagation Failure
```yaml
alert: ServiceContextPropagationFailed
expr: rate(servicecontext_propagation_errors_total[5m]) > 0.05
for: 2m
labels:
  severity: warning
  service: all
  component: ServiceContext
annotations:
  summary: "ServiceContext not properly propagated"
  description: "ServiceContext propagation failure rate is {{ $value }}%"
  runbook_url: "https://runbooks.example.com/context-propagation"
```

### PaymentService Alerts

#### 1. Payment Processing Error Rate
```yaml
alert: PaymentProcessingErrorRateHigh
expr: |
  (
    rate(payment_errors_total{status="5xx"}[5m]) /
    rate(payment_requests_total[5m])
  ) * 100 > 1
for: 3m
labels:
  severity: critical
  service: PaymentService
  error_budget_impact: high
annotations:
  summary: "Payment processing error rate above 1%"
  description: "Payment service error rate is {{ $value }}% (threshold: 1%)"
  runbook_url: "https://runbooks.example.com/payment-errors"
```

#### 2. Payment Processing Latency
```yaml
alert: PaymentProcessingLatencyHigh
expr: |
  histogram_quantile(0.95, 
    rate(payment_processing_duration_seconds_bucket[5m])
  ) > 3
for: 5m
labels:
  severity: warning
  service: PaymentService
annotations:
  summary: "Payment processing latency high"
  description: "95th percentile payment processing time is {{ $value }}s (threshold: 3s)"
  runbook_url: "https://runbooks.example.com/payment-latency"
```

#### 3. Payment Allocation Race Conditions
```yaml
alert: PaymentAllocationRaceConditions
expr: increase(payment_allocation_lock_errors_total[5m]) > 1
for: 1m
labels:
  severity: critical
  service: PaymentService
annotations:
  summary: "Payment allocation race condition detected"
  description: "{{ $value }} allocation lock errors detected"
  runbook_url: "https://runbooks.example.com/allocation-race"
```

### InvoiceService Alerts

#### 1. Invoice Creation Error Rate
```yaml
alert: InvoiceCreationErrorRateHigh
expr: |
  (
    rate(invoice_errors_total{status="5xx"}[5m]) /
    rate(invoice_requests_total[5m])
  ) * 100 > 1.5
for: 3m
labels:
  severity: critical
  service: InvoiceService
  error_budget_impact: high
annotations:
  summary: "Invoice creation error rate above 1.5%"
  description: "Invoice service error rate is {{ $value }}% (threshold: 1.5%)"
  runbook_url: "https://runbooks.example.com/invoice-errors"
```

### LedgerIntegrationService Alerts

#### 1. Ledger Posting Failures
```yaml
alert: LedgerPostingFailed
expr: increase(ledger_posting_errors_total[5m]) > 2
for: 2m
labels:
  severity: critical
  service: LedgerIntegrationService
  error_budget_impact: high
annotations:
  summary: "Ledger posting failures detected"
  description: "{{ $value }} ledger posting failures in 5 minutes"
  runbook_url: "https://runbooks.example.com/ledger-posting"
```

### Audit Logging Alerts

#### 1. Audit Logging Coverage Drop
```yaml
alert: AuditLoggingCoverageLow
expr: |
  (
    sum(rate(audit_log_entries_total{action!="system"}[1h])) /
    sum(rate(service_method_calls_total[1h]))
  ) * 100 < 90
for: 10m
labels:
  severity: warning
  service: all
  component: AuditLogging
annotations:
  summary: "Audit logging coverage below threshold"
  description: "Audit logging coverage is {{ $value }}% (threshold: 90%)"
  runbook_url: "https://runbooks.example.com/audit-coverage"
```

#### 2. Audit Logging Latency
```yaml
alert: AuditLoggingLatencyHigh
expr: |
  histogram_quantile(0.95, 
    rate(audit_logging_duration_seconds_bucket[5m])
  ) > 0.5
for: 5m
labels:
  severity: warning
  service: all
  component: AuditLogging
annotations:
  summary: "Audit logging latency high"
  description: "95th percentile audit logging time is {{ $value }}s (threshold: 0.5s)"
  runbook_url: "https://runbooks.example.com/audit-latency"
```

## 📈 Error Budget Burn Rate

### Error Budget Consumption
```yaml
# Critical error budget burn
alert: ErrorBudgetBurnCritical
expr: |
  (
    rate(error_budget_consumed_total[1h]) / 
    (error_budget_total * (1 / (30 * 24 * 3600)))
  ) * 100 > 50
for: 5m
labels:
  severity: critical
annotations:
  summary: "Error budget burning at critical rate"
  description: "Error budget consumption rate is {{ $value }}% per hour"
```

### Error Budget Forecast
```yaml
alert: ErrorBudgetExhaustionForecast
expr: |
  predict_linear(error_budget_remaining[6h], 3600 * 24) < 0
for: 5m
labels:
  severity: critical
annotations:
  summary: "Error budget will be exhausted in 24 hours"
  description: "At current burn rate, error budget will be exhausted within 24 hours"
```

## 🎯 Alert Tiers

### Tier 1: Critical (Page Immediately)
- ServiceContext creation failures
- Payment processing errors > 1%
- Missing ServiceContext parameters
- Error budget burn > 50%/hour

### Tier 2: High (Page within 15 minutes)
- Invoice creation errors > 1.5%
- Ledger posting failures
- Payment allocation race conditions
- Error budget exhaustion forecast

### Tier 3: Warning (Ticket within 1 hour)
- High latency (>95th percentile)
- Audit logging coverage < 90%
- ServiceContext propagation failures
- Error budget burn > 20%/hour

### Tier 4: Info (Daily report)
- Performance degradation
- Cache hit rate changes
- Usage pattern anomalies

## 📊 Dashboard Metrics

### ServiceContext Health Dashboard

#### Panel Configuration
1. **Error Rates**
   - Overall API error rate
   - Service-specific error rates
   - ServiceContext-related errors

2. **Performance**
   - ServiceContext creation time (p95)
   - Service method latency
   - Database query time

3. **Error Budget**
   - Current error budget remaining
   - Burn rate
   - Time to exhaustion

4. **Audit Logging**
   - Coverage percentage
   - Success rate
   - Latency

### Alert Summary Widget
```json
{
  "title": "Active Alerts",
  "type": "table",
  "targets": [
    {
      "expr": "ALERTS{alertstate=\"firing\"}",
      "format": "table",
      "instant": true
    }
  ],
  "columns": [
    {"text": "Alert Name"},
    {"text": "Severity"},
    {"text": "Service"},
    {"text": "Duration"},
    {"text": "Summary"}
  ]
}
```

## 🔧 Alert Suppression Rules

### Maintenance Windows
```yaml
# Suppress alerts during scheduled maintenance
- match:
    alertname: PaymentProcessingErrorRateHigh
  startTime: 2025-01-15T02:00:00Z
  endTime: 2025-01-15T04:00:00Z
  matcher:
    - name: maintenance
      value: "true"
```

### Known Issues
```yaml
# Suppress for known issues with active tickets
- match:
    alertname: ServiceContextCreationFailed
  matcher:
    - name: jira_ticket
      value: "PROJ-1234"
```

## 🚨 Escalation Policy

### Critical Alerts (Tier 1)
1. **0-5 minutes**: Page on-call engineer
2. **5-15 minutes**: Page engineering lead
3. **15+ minutes**: Page VP of Engineering
4. **30+ minutes**: Declare incident

### High Alerts (Tier 2)
1. **0-15 minutes**: Create Slack incident channel
2. **15-30 minutes**: Page on-call engineer
3. **30+ minutes**: Escalate to team lead

### Warning Alerts (Tier 3)
1. **0-1 hour**: Create Jira ticket
2. **1-4 hours**: Assign to appropriate team
3. **4+ hours**: Escalate to manager

## 📋 Alert Response Playbook

### ServiceContext Creation Failures
1. **Immediate Actions**
   - Check authentication service status
   - Verify database connectivity
   - Check ServiceContext service health

2. **Investigation Steps**
   - Review error logs for patterns
   - Check recent deployments
   - Monitor system resources

3. **Mitigation**
   - Enable graceful fallback mode
   - Scale up ServiceContext service
   - Deploy hotfix if needed

### Payment Processing Errors
1. **Immediate Actions**
   - Verify payment gateway status
   - Check database connections
   - Monitor queue processing

2. **Investigation**
   - Review payment error logs
   - Check for recent code changes
   - Analyze failure patterns

3. **Mitigation**
   - Switch to backup payment processor
   - Enable maintenance mode
   - Rollback if necessary

## 📈 Monthly Review Process

### Error Budget Review
1. **Calculate monthly error budget consumption**
2. **Analyze top error sources**
3. **Review alert effectiveness**
4. **Adjust thresholds if needed**

### Alert Tuning
1. **Review false positive rate**
2. **Adjust alert thresholds**
3. **Update suppression rules**
4. **Refine escalation policies**

### Performance Review
1. **Analyze SLO achievement**
2. **Review performance trends**
3. **Identify improvement areas**
4. **Set targets for next month**

## 🎯 Success Metrics

### Alert Effectiveness
- False positive rate < 5%
- Mean time to acknowledge < 5 minutes (critical)
- Mean time to resolve < 30 minutes (critical)

### Error Budget Management
- Error budget utilization < 80%
- No error budget exhaustion
- Improved SLO achievement quarter-over-quarter

### System Reliability
- Uptime > 99.9% for critical services
- P95 latency < 3 seconds
- Error rate < 1% for all services

---

This alert configuration ensures that ServiceContext-related issues are caught early, with appropriate escalation and response procedures to maintain system reliability and meet our SLOs.