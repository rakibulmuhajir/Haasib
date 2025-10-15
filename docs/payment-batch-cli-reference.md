# Payment Batch Processing — CLI Reference

## Overview

The payment batch processing system provides comprehensive CLI commands for creating, monitoring, and managing payment batches. This reference covers all available commands, options, and usage patterns.

## Commands

### 1. payment:batch:import

Create and import payment batches from various sources.

#### Basic Usage

```bash
php artisan payment:batch:import --source={source_type} [options]
```

#### Options

| Option | Description | Required |
|--------|-------------|----------|
| `--source` | Source type: `csv`, `manual`, `bank_feed` | Yes |
| `--file` | Path to CSV file (required for csv source) | Conditional |
| `--entries` | JSON string with payment entries (manual source) | Conditional |
| `--dry-run` | Validate without processing | No |
| `--notes` | Batch notes/description | No |
| `--force` | Skip confirmation prompts | No |

#### CSV Import

```bash
# Basic CSV import
php artisan payment:batch:import \
  --source=csv \
  --file=/path/to/payments.csv \
  --notes="Monthly bank statement import"

# Dry run to validate CSV format
php artisan payment:batch:import \
  --source=csv \
  --file=/path/to/payments.csv \
  --dry-run

# Import with custom notes
php artisan payment:batch:import \
  --source=csv \
  --file=/path/to/payments.csv \
  --notes="Q1 2025 payment receipts"
```

**CSV Format Requirements:**
```csv
entity_id,payment_method,amount,currency,payment_date,reference_number,notes,auto_allocate,allocation_strategy
customer-uuid-1,bank_transfer,500.00,USD,2025-01-15,PAY-001,Invoice payment,true,fifo
customer-uuid-2,card,250.00,USD,2025-01-15,PAY-002,Overpayment,true,proportional
```

#### Manual Entry

```bash
# Single payment entry
php artisan payment:batch:import \
  --source=manual \
  --entries='[{"entity_id":"customer-uuid","payment_method":"bank_transfer","amount":500.00,"currency_id":"usd-uuid","payment_date":"2025-01-15","reference_number":"PAY-001","auto_allocate":true}]'

# Multiple payment entries
php artisan payment:batch:import \
  --source=manual \
  --entries='[
    {
      "entity_id": "customer-uuid-1",
      "payment_method": "bank_transfer", 
      "amount": 500.00,
      "currency_id": "usd-uuid",
      "payment_date": "2025-01-15",
      "reference_number": "PAY-001",
      "auto_allocate": true,
      "allocation_strategy": "fifo"
    },
    {
      "entity_id": "customer-uuid-2",
      "payment_method": "card",
      "amount": 250.00, 
      "currency_id": "usd-uuid",
      "payment_date": "2025-01-15",
      "reference_number": "PAY-002",
      "auto_allocate": true,
      "allocation_strategy": "overdue_first"
    }
  ]'
```

#### Bank Feed Import

```bash
# Bank feed import (future feature)
php artisan payment:batch:import \
  --source=bank_feed \
  --file=/path/to/bank-statement.ofx \
  --notes="Daily bank sync"
```

#### Output Examples

**Successful Import:**
```
✅ Batch created successfully!

Batch Details:
├── ID: 12345678-1234-1234-1234-123456789abc
├── Batch Number: BATCH-20250115-001
├── Status: pending
├── Source Type: csv_import
├── Receipt Count: 150
├── Total Amount: $15,750.00
├── Currency: USD
├── Created By: John Doe
└── Created At: 2025-01-15 10:30:00 UTC

📋 Next Steps:
• Monitor processing with: php artisan payment:batch:status BATCH-20250115-001
• View batch details in the UI: /accounting/payments/batches/BATCH-20250115-001
• Processing will start automatically in the background

⏱️  Estimated completion: 2-5 minutes for 150 payments
```

**Dry Run Output:**
```
🔍 CSV Validation Results

File Information:
├── File: /path/to/payments.csv
├── Size: 45.2 KB
├── Estimated Rows: 150

Validation Summary:
├── ✅ Valid format: CSV
├── ✅ Required columns present
├── ✅ No duplicate entity IDs in sample
├── ⚠️  3 rows with missing reference numbers
├── ❌ 2 rows with invalid customer UUIDs

Errors Found:
Row 45: Invalid customer UUID format
Row 67: Customer not found: invalid-uuid-format
Row 89: Missing required field: payment_date

📊 Validation Result: 145 valid, 5 invalid rows
💡 Fix the errors above and re-run without --dry-run to process
```

### 2. payment:batch:status

Monitor and display batch processing status.

#### Basic Usage

```bash
php artisan payment:batch:status {batch-id} [options]
```

#### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--format` | Output format: `table`, `json` | `table` |
| `--refresh` | Continuously refresh every 5 seconds | `false` |
| `--payments` | Show associated payments | `false` |

#### Status Monitoring

```bash
# Check current status
php artisan payment:batch:status BATCH-20250115-001

# Real-time monitoring (updates every 5 seconds)
php artisan payment:batch:status BATCH-20250115-001 --refresh

# Show associated payments
php artisan payment:batch:status BATCH-20250115-001 --payments

# JSON output for scripting
php artisan payment:batch:status BATCH-20250115-001 --format=json

# Monitor a processing batch
php artisan payment:batch:status BATCH-20250115-001 --refresh --payments
```

#### Output Examples

**Processing Batch:**
```
📋 Batch Status Report
==================

┌─────────────────┬─────────────────────────────────────┐
│ Field           │ Value                               │
├─────────────────┼─────────────────────────────────────┤
│ Batch ID        │ 12345678-1234-1234-1234-123456789abc │
│ Batch Number    │ BATCH-20250115-001                  │
│ Status          │ 🔄 Processing (45% complete)        │
│ Source Type     │ csv_import                          │
│ Receipt Count   │ 150                                 │
│ Total Amount    │ USD 15,750.00                       │
│ Progress        │ 45.0%                               │
│ Created By      │ John Doe                            │
│ Created At      │ 2025-01-15 10:30:00                 │
│ Processing Start│ 2025-01-15 10:30:15                 │
└─────────────────┴─────────────────────────────────────┘

📊 Processing Statistics:
┌─────────────────┬─────────┐
│ Metric          │ Value   │
├─────────────────┼─────────┤
│ Processed       │ 68      │
│ Failed          │ 0       │
│ Success Rate    │ 100.0%  │
└─────────────────┴─────────┘

⏰ Estimated Completion: 2025-01-15 10:35:00

🎯 Next Steps:
• Wait for processing to complete
• Check status again with: php artisan payment:batch:status BATCH-20250115-001
• View audit trail in the UI

🔄 Refreshing in 5 seconds... (Press Ctrl+C to stop)
```

**Completed Batch:**
```
📋 Batch Status Report
==================

┌─────────────────┬─────────────────────────────────────┐
│ Field           │ Value                               │
├─────────────────┼─────────────────────────────────────┤
│ Batch ID        │ 12345678-1234-1234-1234-123456789abc │
│ Batch Number    │ BATCH-20250115-001                  │
│ Status          │ ✅ Completed                        │
│ Source Type     │ csv_import                          │
│ Receipt Count   │ 150                                 │
│ Total Amount    │ USD 15,750.00                       │
│ Progress        │ 100.0%                              │
│ Created By      │ John Doe                            │
│ Created At      │ 2025-01-15 10:30:00                 │
│ Processing Start│ 2025-01-15 10:30:15                 │
│ Processed At    │ 2025-01-15 10:33:42                 │
└─────────────────┴─────────────────────────────────────┘

📊 Processing Statistics:
┌─────────────────┬─────────┐
│ Metric          │ Value   │
├─────────────────┼─────────┤
│ Processed       │ 150     │
│ Failed          │ 0       │
│ Success Rate    │ 100.0%  │
└─────────────────┴─────────┘

🎯 Next Steps:
• ✅ Batch completed successfully
• Review created payments in the payments module
• View batch audit trail
```

**Failed Batch:**
```
📋 Batch Status Report
==================

┌─────────────────┬─────────────────────────────────────┐
│ Field           │ Value                               │
├─────────────────┼─────────────────────────────────────┤
│ Batch ID        │ 12345678-1234-1234-1234-123456789abc │
│ Batch Number    │ BATCH-20250115-001                  │
│ Status          │ ❌ Failed                           │
│ Source Type     │ csv_import                          │
│ Receipt Count   │ 150                                 │
│ Total Amount    │ USD 15,750.00                       │
│ Progress        │ 0.0%                                │
│ Created By      │ John Doe                            │
│ Created At      │ 2025-01-15 10:30:00                 │
└─────────────────┴─────────────────────────────────────┘

❌ Batch Processing Errors:
Error Type: processing_errors
Error Details:
  total_errors: 150
  error_summary: All payments failed to process

🎯 Next Steps:
• ❌ Batch processing failed
• Review error details above
• Fix data issues and retry import
• Contact support if errors persist
```

**JSON Output:**
```json
{
  "batch_id": "12345678-1234-1234-1234-123456789abc",
  "batch_number": "BATCH-20250115-001",
  "status": "completed",
  "status_label": "Completed",
  "source_type": "csv_import",
  "receipt_count": 150,
  "total_amount": 15750.00,
  "currency": "USD",
  "progress_percentage": 100.0,
  "created_at": "2025-01-15T10:30:00Z",
  "processing_started_at": "2025-01-15T10:30:15Z",
  "processed_at": "2025-01-15T10:33:42Z",
  "processing_finished_at": "2025-01-15T10:33:45Z",
  "estimated_completion": null,
  "notes": "Monthly bank import",
  "metadata": {
    "processed_count": 150,
    "failed_count": 0,
    "processed_amount": 15750.00
  },
  "created_by": "John Doe",
  "has_payments": true
}
```

### 3. payment:batch:list

List and filter payment batches.

#### Basic Usage

```bash
php artisan payment:batch:list [options]
```

#### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--status` | Filter by status: `pending`, `processing`, `completed`, `failed`, `completed_with_errors` | `all` |
| `--source` | Filter by source type: `csv_import`, `manual`, `bank_feed` | `all` |
| `--limit` | Number of batches to show | `20` |
| `--format` | Output format: `table`, `json` | `table` |
| `--company` | Filter by company ID | current context |

#### Usage Examples

```bash
# List recent batches
php artisan payment:batch:list

# List only completed batches
php artisan payment:batch:list --status=completed

# List failed batches
php artisan payment:batch:list --status=failed

# List CSV imports only
php artisan payment:batch:list --source=csv_import

# Show 50 most recent batches
php artisan payment:batch:list --limit=50

# JSON output for scripting
php artisan payment:batch:list --status=processing --format=json

# Complex filtering
php artisan payment:batch:list --status=completed --source=csv_import --limit=10
```

#### Output Examples

**Table Output:**
```
┌──────────────────────────────┬─────────────────────┬──────────────┬──────────────┬─────────────┬──────────────┐
│ Batch Number                  │ Status              │ Source       │ Receipts     │ Total        │ Created At    │
├──────────────────────────────┼─────────────────────┼──────────────┼──────────────┼─────────────┼──────────────┤
│ BATCH-20250115-003            │ ✅ Completed        │ csv_import   │ 150          │ $15,750.00   │ 5 mins ago    │
│ BATCH-20250115-002            │ ✅ Completed        │ manual       │ 5            │ $1,250.00    │ 1 hour ago    │
│ BATCH-20250115-001            │ 🔄 Processing (67%) │ csv_import   │ 200          │ $25,000.00   │ 2 hours ago   │
│ BATCH-20250114-005            │ ❌ Failed           │ csv_import   │ 75           │ $8,500.00    │ 1 day ago     │
│ BATCH-20250114-004            │ ✅ Completed        │ manual       │ 12           │ $3,000.00    │ 1 day ago     │
└──────────────────────────────┴─────────────────────┴──────────────┴──────────────┴─────────────┴──────────────┘

Showing 5 of 23 batches total
```

**JSON Output:**
```json
{
  "data": [
    {
      "batch_number": "BATCH-20250115-003",
      "status": "completed",
      "source_type": "csv_import",
      "receipt_count": 150,
      "total_amount": 15750.00,
      "currency": "USD",
      "created_at": "2025-01-15T10:30:00Z"
    }
  ],
  "total": 23,
  "showing": 5
}
```

## Advanced Usage

### Environment Setup

Set the required environment variables:

```env
# Company context for CLI commands
APP_COMPANY_ID=your-company-uuid

# Queue configuration for batch processing
QUEUE_CONNECTION=redis
QUEUE_PAYMENT_BATCH=payment-processing

# File upload settings
BATCH_MAX_FILE_SIZE=10240  # 10MB in KB
BATCH_MAX_ROWS=10000
```

### Queue Worker Configuration

For optimal batch processing performance:

```bash
# Start dedicated batch processing worker
php artisan queue:work \
  --queue=payment-processing \
  --sleep=1 \
  --tries=3 \
  --timeout=300 \
  --memory=512

# Monitor queue performance
php artisan queue:monitor payment-processing
```

### Batch Retry Workflow

For failed batches, use this workflow:

```bash
# 1. Check failure details
php artisan payment:batch:status BATCH-20250114-005

# 2. Fix underlying data issues
# (Edit your CSV file or fix customer data)

# 3. Retry the batch (if retryable)
php artisan payment:batch:retry BATCH-20250114-005

# 4. Monitor retry progress
php artisan payment:batch:status BATCH-20250114-005 --refresh
```

### Performance Monitoring

Monitor batch processing performance:

```bash
# Check recent processing times
php artisan payment:batch:list --status=completed --format=json | jq '.data[] | {batch: .batch_number, receipts: .receipt_count, created: .created_at}'

# Monitor active processing
php artisan payment:batch:list --status=processing

# Check error rates
php artisan payment:batch:list --status=failed --limit=10
```

### Integration with Scripts

Use JSON output for automation:

```bash
#!/bin/bash
# monitor-batches.sh

# Get processing batches
processing=$(php artisan payment:batch:list --status=processing --format=json | jq -r '.data | length')

if [ "$processing" -gt 0 ]; then
    echo "🔄 $processing batches currently processing"
    
    # Get details for each processing batch
    php artisan payment:batch:list --status=processing --format=json | \
      jq -r '.data[].batch_number' | \
      while read batch_id; do
        echo "📊 Checking $batch_id..."
        php artisan payment:batch:status "$batch_id" --format=json | \
          jq -r '{batch: .batch_number, progress: .progress_percentage, status: .status}'
      done
else
    echo "✅ No batches currently processing"
fi

# Check for failed batches
failed=$(php artisan payment:batch:list --status=failed --format=json | jq -r '.data | length')
if [ "$failed" -gt 0 ]; then
    echo "❌ $failed batches failed - attention required"
    php artisan payment:batch:list --status=failed
fi
```

## Troubleshooting

### Common Issues

1. **Company Context Not Set**
   ```
   Error: Company context is required. Set APP_COMPANY_ID environment variable.
   ```
   **Solution**: Export the company ID or set in `.env` file
   ```bash
   export APP_COMPANY_ID=your-company-uuid
   ```

2. **File Not Found**
   ```
   Error: File not found: /path/to/file.csv
   ```
   **Solution**: Check file path and permissions

3. **Invalid JSON in Manual Entries**
   ```
   Error: Invalid JSON provided for --entries option
   ```
   **Solution**: Validate JSON syntax and escape properly in shell

4. **Batch Not Found**
   ```
   Error: Batch not found: BATCH-20250115-999
   ```
   **Solution**: Verify batch number or use UUID instead

### Debug Mode

Enable detailed logging:

```bash
# Set log level to debug
php artisan log:level=debug

# Run command with verbose output
php artisan payment:batch:import --source=csv --file=payments.csv -v

# View batch-related logs
tail -f storage/logs/laravel.log | grep -i "batch\|payment"
```

### Performance Issues

For slow batch processing:

1. **Check queue worker status**
   ```bash
   php artisan queue:failed
   php artisan queue:restart
   ```

2. **Monitor system resources**
   ```bash
   top -p $(pgrep -f "queue:work")
   ```

3. **Optimize batch size**
   - Process smaller batches (< 1000 payments)
   - Increase queue worker memory
   - Use faster storage for temporary files

---

**Need help?** Check the [Batch Processing Quick Start Guide](./payment-batch-quickstart.md) for comprehensive usage examples and troubleshooting tips.