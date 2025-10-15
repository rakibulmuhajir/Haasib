# Customer Management Metrics & Monitoring

**Feature**: Customer Management Lifecycle  
**Implementation Date**: 2025-10-15  
**Version**: 1.0

## Overview

This document captures the observability implementation for the customer management system, including metrics, logging, events, and monitoring dashboards.

## Metrics Categories

### 1. Customer Lifecycle Metrics

#### Creation Metrics
- **customer_created_total**: Counter for new customer creation
  - Labels: `company_id`, `source`, `status`
  - Trigger: Customer created via UI, CLI, or API
  
- **customer_updated_total**: Counter for customer updates
  - Labels: `company_id`, `field_changed`
  - Trigger: Customer profile modifications

- **customer_deleted_total**: Counter for customer deletions
  - Labels: `company_id`, `reason`
  - Trigger: Customer soft/hard deletion

#### Status Management
- **customer_status_changed_total**: Counter for status transitions
  - Labels: `company_id`, `from_status`, `to_status`
  - Trigger: Status changes (active → inactive → blocked)

### 2. Credit Management Metrics

#### Credit Limits
- **customer_credit_limit_adjusted_total**: Credit limit modifications
  - Labels: `company_id`, `adjustment_type`, `status`
  - Trigger: Credit limit increases/decreases

- **customer_credit_breach_total**: Credit limit violations
  - Labels: `company_id`, `severity`, `override_used`
  - Trigger: Invoice creation exceeding credit limits

#### Credit Utilization
- **customer_credit_utilization_histogram**: Distribution of credit usage
  - Labels: `company_id`, `utilization_bucket` (0-25%, 25-50%, 50-75%, 75-100%, 100%+)
  - Update: Nightly batch calculation

### 3. Statement & Aging Metrics

#### Statement Generation
- **customer_statement_generated_total**: Statement creation metrics
  - Labels: `company_id`, `format`, `delivery_method`
  - Trigger: PDF/CSV statement generation

- **customer_statement_generation_duration_seconds**: Statement processing time
  - Labels: `company_id`, `format`, `period_months`
  - Measurement: Time from request to completion

#### Aging Analysis
- **customer_aging_updated_total**: Aging snapshot updates
  - Labels: `company_id`, `trigger` (scheduled/on_demand)
  - Trigger: Manual or automatic aging calculations

- **customer_aging_bucket_distribution**: Aging bucket analysis
  - Labels: `company_id`, `bucket` (current, 1-30, 31-60, 61-90, 90+)
  - Update: During aging calculations

### 4. Import/Export Metrics

#### Data Import
- **customer_import_processed_total**: Import operation metrics
  - Labels: `company_id`, `format`, `status` (success/failure/partial)
  - Trigger: CSV/JSON file imports

- **customer_import_processing_duration_seconds**: Import processing time
  - Labels: `company_id`, `format`, `record_count`
  - Measurement: File parsing to database completion

#### Data Export
- **customer_export_generated_total**: Export operation metrics
  - Labels: `company_id`, `format`, `filter_type`
  - Trigger: Customer data exports

- **customer_export_file_size_bytes**: Export file sizes
  - Labels: `company_id`, `format`
  - Measurement: Generated file dimensions

### 5. Communication Metrics

#### Contact Management
- **customer_contact_created_total**: New contact additions
  - Labels: `company_id`, `contact_type`, `is_primary`
  - Trigger: Contact record creation

- **customer_communication_logged_total**: Interaction logging
  - Labels: `company_id`, `channel`, `direction`
  - Trigger: Communication entries (email, phone, meeting, note)

## Event System

### Customer Events
```php
// Customer lifecycle events
CustomerCreated::class
CustomerUpdated::class  
CustomerDeleted::class
CustomerStatusChanged::class

// Credit management events
CreditLimitAdjustmentRequested::class
CreditLimitAdjusted::class
CreditLimitBreached::class

// Statement and aging events
StatementGenerated::class
AgingSnapshotRefreshed::class

// Import/Export events
CustomersImported::class
CustomerImportBatchCompleted::class
CustomersExported::class
```

### Event Structure
All customer events include:
- `company_id`: Tenant identifier
- `user_id`: Acting user (when applicable)
- `customer_id`: Related customer UUID
- `timestamp`: ISO 8601 datetime
- `metadata`: Event-specific data
- `correlation_id`: Request tracing identifier

## Logging Strategy

### Structured Logging
All customer operations generate structured JSON logs with consistent schema:

```json
{
  "timestamp": "2025-10-15T10:30:00Z",
  "level": "info",
  "message": "Customer created successfully",
  "context": {
    "company_id": "uuid",
    "user_id": "uuid", 
    "customer_id": "uuid",
    "customer_number": "CUST-001",
    "operation": "customer.create",
    "source": "web_ui",
    "duration_ms": 245,
    "correlation_id": "req_uuid"
  }
}
```

### Log Levels
- **INFO**: Normal operations (create, update, successful imports/exports)
- **WARN**: Business rule violations (credit limit breaches, validation failures)
- **ERROR**: System failures (import errors, statement generation failures)
- **DEBUG**: Detailed troubleshooting information (enabled per environment)

## Performance Monitoring

### Response Time Targets
| Operation | Target (p95) | Alert Threshold |
|-----------|-------------|-----------------|
| Customer List | 1.2s | 2.0s |
| Customer Detail | 0.8s | 1.5s |
| Customer Create | 1.0s | 2.0s |
| Statement Generation | 2.0s | 5.0s |
| Aging Calculation | 5.0s | 10.0s |
| Import Processing | 30s | 60s |
| Export Generation | 15s | 30s |

### Resource Utilization
- **Database Connection Pool**: Monitor for connection exhaustion during bulk operations
- **Queue Processing**: Track aging update job queue depth and processing time
- **File Storage**: Monitor statement document storage and CDN performance
- **Memory Usage**: Track peak memory during import/export operations

## Alerting Rules

### Business Metrics
```yaml
# High credit breach rate
- alert: CustomerCreditBreachRateHigh
  expr: rate(customer_credit_breach_total[5m]) > 0.1
  for: 2m
  labels:
    severity: warning
  annotations:
    summary: "High customer credit breach rate detected"

# Failed imports
- alert: CustomerImportFailureRateHigh  
  expr: rate(customer_import_failed_total[10m]) / rate(customer_import_processed_total[10m]) > 0.1
  for: 5m
  labels:
    severity: critical
  annotations:
    summary: "Customer import failure rate exceeds 10%"

# Statement generation failures
- alert: CustomerStatementGenerationFailing
  expr: rate(customer_statement_generation_failed_total[5m]) > 0.05
  for: 3m
  labels:
    severity: warning
  annotations:
    summary: "Customer statement generation failure rate high"
```

### Technical Metrics
```yaml
# Slow customer queries
- alert: CustomerDatabaseQuerySlow
  expr: customer_request_duration_seconds{quantile="0.95"} > 2.0
  for: 5m
  labels:
    severity: warning
  annotations:
    summary: "Customer database queries responding slowly"

# High memory usage during imports
- alert: CustomerImportMemoryHigh
  expr: process_resident_memory_bytes / 1024 / 1024 > 512
  for: 10m
  labels:
    severity: warning
  annotations:
    summary: "High memory usage during customer import processing"
```

## Dashboard Configuration

### Grafana Dashboard Panels

#### Overview Panel
- Customer creation rate (last 7 days)
- Active customer count by company
- Credit utilization distribution
- Recent statement generation volume

#### Performance Panel  
- API response times by endpoint
- Database query performance
- Queue processing metrics
- Error rates by operation

#### Business Metrics Panel
- Customer lifecycle funnel
- Credit breach incidents
- Statement delivery success rate
- Import/export processing volumes

## Data Retention

### Metrics Retention
- **High-resolution metrics**: 7 days (1-minute granularity)
- **Medium-resolution metrics**: 30 days (5-minute granularity)  
- **Low-resolution metrics**: 1 year (1-hour granularity)

### Log Retention
- **Application logs**: 30 days
- **Audit logs**: 7 years (compliance requirement)
- **Access logs**: 90 days
- **Error logs**: 1 year

## Integration Points

### External Monitoring Services
- **Prometheus**: Metrics collection and storage
- **Grafana**: Visualization and alerting
- **Loki**: Log aggregation and search
- **AlertManager**: Alert routing and notification

### Internal Integration
- **Application Metrics**: Custom business metrics via CustomerMetrics class
- **Database Metrics**: PostgreSQL performance statistics
- **Queue Metrics**: Redis/Laravel Horizon job processing metrics
- **Web Server Metrics**: Nginx/Octane performance statistics

## Troubleshooting Guide

### Common Issues
1. **High Credit Breach Rate**: Check credit limit policies and customer payment history
2. **Slow Aging Calculations**: Review database indexes and query optimization
3. **Import Failures**: Validate file format and data quality rules
4. **Statement Generation Issues**: Check PDF generation service and storage availability

### Diagnostic Commands
```bash
# Check customer metrics
curl http://localhost:9090/api/v1/query?query=customer_created_total

# Search customer logs
grep "customer.create" /var/log/laravel/app.log | tail -20

# Monitor queue processing
php artisan queue:monitor --queue=customer-aging

# Check database performance
php artisan db:show --counts
```

## Security & Privacy

### Data Protection
- **PII Filtering**: Personal data masked in logs and metrics
- **Access Control**: Metrics access restricted by company scope
- **Audit Trail**: All metric-generating operations audited
- **Data Minimization**: Only necessary telemetry data collected

### Compliance
- **GDPR**: Customer metrics respect data protection requirements
- **SOX**: Audit trails maintained for financial operations
- **Retention**: Compliant data retention policies enforced

## Future Enhancements

### Planned Improvements
- **Real-time Dashboards**: WebSocket-based live metric updates
- **Predictive Analytics**: Customer churn prediction models
- **Anomaly Detection**: ML-based unusual pattern identification
- **Custom Alerts**: User-configurable alert thresholds

### Scalability Considerations
- **Metrics Federation**: Multi-region metrics aggregation
- **Sampling Strategies**: High-volume metric sampling for performance
- **Caching Layers**: Redis-based metric caching for frequently accessed data
- **Load Distribution**: Distributed metric processing for large deployments