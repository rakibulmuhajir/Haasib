# Period Close Monitoring Runbook

## Overview

This document provides comprehensive monitoring and operational procedures for the monthly period close system. It covers health checks, performance monitoring, troubleshooting, and maintenance tasks for the period close workflow.

## System Architecture

### Components
- **Period Close Service** (`Modules\Ledger\Services\PeriodCloseService`)
- **Template Management** (`PeriodCloseTemplateController`, `TemplateDrawer.vue`)
- **Action Handlers** (`SyncPeriodCloseTemplateAction`, `UpdatePeriodCloseTemplateAction`, `ArchivePeriodCloseTemplateAction`)
- **Database Tables**: `ledger.period_closes`, `ledger.period_close_templates`, `ledger.period_close_tasks`
- **Background Jobs**: `GeneratePeriodCloseReportsJob`

### Key Workflows
1. **Period Close Workflow**: Start → Validate → Lock → Complete
2. **Template Management**: Create → Edit → Sync → Archive
3. **Task Management**: Assign → Complete → Track Progress
4. **Reporting**: Generate → Download → Archive

## Monitoring Dashboard

### Key Performance Indicators (KPIs)

#### Period Close Metrics
- **Average Close Time**: Target < 5 business days
- **Task Completion Rate**: Target > 95%
- **Validation Success Rate**: Target > 98%
- **Reopen Rate**: Target < 5%
- **Template Usage**: Track most used templates

#### System Performance Metrics
- **API Response Times**: 
  - Period close operations: < 2s (p95)
  - Template operations: < 1s (p95)
  - Task updates: < 500ms (p95)
- **Database Query Performance**: < 100ms (p95)
- **Background Job Processing**: < 30s average
- **Error Rates**: < 0.1% for all operations

### Alert Thresholds

#### Critical Alerts (PagerDuty)
- Period close failure rate > 10%
- API response times > 5s for > 5 minutes
- Database connection failures
- Background job queue backlog > 100 jobs

#### Warning Alerts (Email)
- Period close takes > 3 business days
- Template sync failures > 5%
- Task completion rate < 80%
- High memory usage > 80%

## Health Checks

### Automated Health Checks

#### API Health Endpoint
```bash
curl -X GET "https://your-domain.com/api/v1/health" \
  -H "Accept: application/json"
```

Expected response:
```json
{
  "success": true,
  "message": "API is running",
  "timestamp": "2025-10-17T10:00:00Z",
  "period_close": {
    "status": "healthy",
    "active_closes": 2,
    "pending_tasks": 15
  }
}
```

#### Database Connectivity Check
```sql
-- Check ledger schema connectivity
SELECT COUNT(*) FROM ledger.period_closes WHERE status IN ('in_review', 'locked');

-- Check template availability
SELECT COUNT(*) FROM ledger.period_close_templates WHERE active = true;
```

#### Background Job Queue Check
```bash
# Check Horizon dashboard
curl -X GET "https://your-domain.com/horizon/api/jobs/failed"
```

### Manual Health Checks

#### Daily Checks
1. **Review Active Period Closes**
   ```sql
   SELECT pc.name, pc.status, COUNT(pct.id) as pending_tasks
   FROM ledger.period_closes pc
   LEFT JOIN ledger.period_close_tasks pct ON pc.id = pct.period_close_id 
     AND pct.status = 'pending'
   WHERE pc.status IN ('in_review', 'locked')
   GROUP BY pc.id, pc.name, pc.status;
   ```

2. **Check Template Usage**
   ```sql
   SELECT pct.name, COUNT(pcs.id) as usage_count
   FROM ledger.period_close_templates pct
   LEFT JOIN ledger.period_close_tasks pctt ON pct.id = pctt.template_task_id
   LEFT JOIN ledger.period_close_tasks pcs ON pctt.id = pcs.template_task_id
   WHERE pct.active = true
   GROUP BY pct.id, pct.name
   ORDER BY usage_count DESC;
   ```

3. **Review Error Logs**
   ```bash
   tail -f storage/logs/laravel.log | grep -i "period.close\|template"
   ```

#### Weekly Checks
1. **Performance Review**
   - Analyze slow query logs
   - Review API response time trends
   - Check background job processing times

2. **Template Audit**
   - Identify unused templates
   - Review template task completion rates
   - Check for duplicate template names

3. **User Access Review**
   - Verify permissions for period close users
   - Review recent activity logs

## Troubleshooting Guide

### Common Issues

#### Period Close Won't Start
**Symptoms**: Start button disabled, error message about permissions
**Causes**: 
- Missing permissions (`period-close.start`)
- Period already closed
- Invalid accounting period

**Resolution**:
```sql
-- Check user permissions
SELECT u.name, p.name as permission
FROM users u
JOIN model_has_permissions mp ON u.id = mp.model_id
JOIN permissions p ON mp.permission_id = p.id
WHERE u.id = [user_id] AND p.name LIKE 'period-close%';

-- Check period status
SELECT * FROM accounting_periods WHERE id = [period_id];
```

#### Template Sync Fails
**Symptoms**: Error message when syncing template to period close
**Causes**:
- Template has no tasks
- Period close is in invalid state
- Permission issues

**Resolution**:
```sql
-- Check template tasks
SELECT COUNT(*) FROM ledger.period_close_template_tasks WHERE template_id = [template_id];

-- Check period close status
SELECT status FROM ledger.period_closes WHERE id = [period_close_id];
```

#### Validation Errors
**Symptoms**: Validation fails with trial balance variance
**Causes**:
- Unbalanced journal entries
- Missing reconciliations
- Data integrity issues

**Resolution**:
```sql
-- Check trial balance
SELECT 
  SUM(CASE WHEN debit_credit = 'debit' THEN amount ELSE 0 END) as total_debits,
  SUM(CASE WHEN debit_credit = 'credit' THEN amount ELSE 0 END) as total_credits
FROM ledger.journal_lines 
WHERE period_id = [period_id];

-- Check for unposted entries
SELECT COUNT(*) FROM ledger.journal_entries 
WHERE period_id = [period_id] AND status != 'posted';
```

#### Performance Issues
**Symptoms**: Slow page loads, timeouts
**Causes**:
- Large dataset queries
- Missing database indexes
- High concurrent usage

**Resolution**:
```sql
-- Check slow queries
SELECT query, mean_time, calls 
FROM pg_stat_statements 
WHERE query LIKE '%period_close%' 
ORDER BY mean_time DESC;

-- Add missing indexes if needed
CREATE INDEX CONCURRENTLY idx_period_closes_company_status 
ON ledger.period_closes(company_id, status);
```

### Emergency Procedures

#### Rollback Failed Period Close
```bash
# 1. Stop any running jobs
php artisan queue:stop

# 2. Reset period close status
php artisan tinker
>>> $periodClose = \Modules\Ledger\Domain\PeriodClose\Models\PeriodClose::find('uuid');
>>> $periodClose->update(['status' => 'draft']);

# 3. Remove any created tasks
>>> \Modules\Ledger\Domain\PeriodClose\Models\PeriodCloseTask::where('period_close_id', 'uuid')->delete();

# 4. Restart queue
php artisan queue:start
```

#### Restore Deleted Template
```sql
-- Restore from backup if available
-- Or recreate with same tasks
BEGIN;

INSERT INTO ledger.period_close_templates (id, name, description, frequency, company_id, created_at, updated_at)
VALUES ('new-uuid', 'Template Name', 'Description', 'monthly', 'company-id', NOW(), NOW());

-- Recreate tasks...
COMMIT;
```

## Maintenance Tasks

### Scheduled Maintenance

#### Daily (Automated)
1. **Database Health Checks**
   - Monitor connection pool
   - Check table sizes
   - Verify backup integrity

2. **Application Monitoring**
   - Log file rotation
   - Cache cleanup
   - Queue processing

#### Weekly (Manual)
1. **Performance Optimization**
   - Analyze slow queries
   - Update statistics
   - Optimize indexes

2. **Security Review**
   - Check access logs
   - Review failed login attempts
   - Update security patches

#### Monthly (Manual)
1. **Template Maintenance**
   - Review template usage
   - Archive unused templates
   - Update template documentation

2. **User Training**
   - Review user feedback
   - Update training materials
   - Conduct knowledge sharing

### Database Maintenance

#### Index Optimization
```sql
-- Rebuild indexes monthly
REINDEX INDEX CONCURRENTLY idx_period_closes_company_status;

-- Update statistics
ANALYZE ledger.period_closes;
ANALYZE ledger.period_close_templates;
ANALYZE ledger.period_close_tasks;
```

#### Data Cleanup
```sql
-- Archive old completed period closes (older than 2 years)
UPDATE ledger.period_closes 
SET archived = true, archived_at = NOW()
WHERE status = 'closed' AND completed_at < NOW() - INTERVAL '2 years';

-- Clean up old audit logs (older than 1 year)
DELETE FROM audit_logs 
WHERE created_at < NOW() - INTERVAL '1 year' 
  AND subject_type LIKE '%PeriodClose%';
```

## Performance Optimization

### Query Optimization

#### Critical Queries
```sql
-- Period close listing with filters (optimized)
SELECT pc.*, 
       COUNT(pct.id) as total_tasks,
       COUNT(CASE WHEN pct.status = 'completed' THEN 1 END) as completed_tasks
FROM ledger.period_closes pc
LEFT JOIN ledger.period_close_tasks pct ON pc.id = pct.period_close_id
WHERE pc.company_id = :company_id
  AND (:status_filter IS NULL OR pc.status = :status_filter)
GROUP BY pc.id
ORDER BY pc.created_at DESC
LIMIT 20 OFFSET :offset;

-- Template usage statistics (optimized)
SELECT pct.*, 
       COUNT(DISTINCT pcs.period_close_id) as usage_count,
       MAX(pcs.created_at) as last_used
FROM ledger.period_close_templates pct
LEFT JOIN ledger.period_close_template_tasks pctt ON pct.id = pctt.template_id
LEFT JOIN ledger.period_close_tasks pcs ON pctt.id = pcs.template_task_id
WHERE pct.company_id = :company_id AND pct.active = true
GROUP BY pct.id
ORDER BY usage_count DESC, pct.name ASC;
```

#### Caching Strategy
```php
// Cache template statistics for 1 hour
Cache::remember("company.{$companyId}.template-stats", 3600, function () use ($companyId) {
    return $this->periodCloseService->getTemplateStatistics($companyId, $user);
});

// Cache period close status for 15 minutes
Cache::remember("period-close.{$periodCloseId}.status", 900, function () use ($periodCloseId) {
    return PeriodClose::find($periodCloseId)->status;
});
```

### Background Job Optimization

#### Job Prioritization
```php
// High priority: Period close validations
GeneratePeriodCloseReportsJob::dispatch($periodCloseId)->onQueue('high');

// Medium priority: Template operations
SyncTemplateAction::dispatch($templateId, $periodCloseId)->onQueue('medium');

// Low priority: Reports and analytics
PeriodCloseAnalyticsJob::dispatch($companyId)->onQueue('low');
```

## Security Considerations

### Access Control
- Implement least privilege principle
- Regular permission audits
- IP whitelisting for sensitive operations

### Data Protection
- Encrypt sensitive period close data
- Implement audit logging for all changes
- Regular security assessments

### Monitoring Security
- Monitor failed authentication attempts
- Track unauthorized access attempts
- Alert on suspicious activity patterns

## Documentation Updates

This runbook should be reviewed and updated:
- **Monthly**: Update with new features and procedures
- **Quarterly**: Complete review and validation
- **Annually**: Major revision and restructuring

## Contact Information

### Primary Contacts
- **System Administrator**: admin@company.com
- **Database Administrator**: dba@company.com
- **Development Team**: dev@company.com

### Emergency Contacts
- **On-call Engineer**: oncall@company.com
- **System Manager**: manager@company.com

### Related Documentation
- [System Architecture](../architecture/overview.md)
- [API Documentation](../api/period-close.md)
- [User Guide](../user-guide/period-close.md)
- [Database Schema](../database/ledger-schema.md)