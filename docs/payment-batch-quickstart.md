# Payment Batch Processing — Quick Start Guide

## Overview

The Payment Batch Processing feature enables efficient bulk processing of payment receipts from multiple sources:

- **CSV Import**: Upload and process payment files from banks or payment processors
- **Manual Entry**: Create batches of payments manually 
- **Bank Feeds**: Automated import from bank statement files
- **Background Processing**: Asynchronous processing with progress tracking
- **Real-time Monitoring**: Live status updates and error reporting

## 🚀 Quick Start

### 1. Processing Your First Batch

#### Via Web UI

1. Navigate to **Accounting → Payments → Batches**
2. Click **"Create New Batch"**
3. Choose your source type:
   - **CSV File**: Upload a CSV file with payment data
   - **Manual Entry**: Enter payments manually
4. Fill in the required information and submit
5. Monitor processing progress in real-time

#### Via CLI

```bash
# Import from CSV file
php artisan payment:batch:import \
  --source=csv \
  --file=/path/to/payments.csv \
  --dry-run

# Create manual batch
php artisan payment:batch:import \
  --source=manual \
  --entries='[{"entity_id":"uuid","amount":100.00,"payment_method":"bank_transfer"}]'

# Monitor batch status
php artisan payment:batch:status BATCH-20250115-001 --refresh
```

#### Via API

```bash
# Create batch with manual entries
curl -X POST http://localhost:8000/api/accounting/payment-batches \
  -H "Content-Type: application/json" \
  -H "X-Company-Id: your-company-uuid" \
  -H "Idempotency-Key: unique-uuid" \
  -d '{
    "source_type": "manual",
    "entries": [
      {
        "entity_id": "customer-uuid",
        "payment_method": "bank_transfer",
        "amount": 500.00,
        "currency_id": "usd-uuid",
        "payment_date": "2025-01-15",
        "reference_number": "PAY-001",
        "auto_allocate": true,
        "allocation_strategy": "fifo"
      }
    ]
  }'
```

### 2. CSV File Format

Your CSV file should include the following columns:

```csv
entity_id,payment_method,amount,currency,payment_date,reference_number,notes,auto_allocate,allocation_strategy
customer-uuid-1,bank_transfer,500.00,USD,2025-01-15,PAY-001,Invoice payment,true,fifo
customer-uuid-2,card,250.00,USD,2025-01-15,PAY-002,Overpayment,true,proportional
```

**Required Fields:**
- `entity_id`: Customer UUID
- `payment_method`: One of: cash, bank_transfer, card, cheque, other
- `amount`: Payment amount (decimal)
- `currency`: 3-letter currency code
- `payment_date`: YYYY-MM-DD format

**Optional Fields:**
- `reference_number`: Payment reference (max 100 chars)
- `notes`: Payment notes
- `auto_allocate`: true/false (default: false)
- `allocation_strategy`: fifo, proportional, overdue_first, largest_first, percentage_based, custom_priority

### 3. Batch Status Monitoring

#### Web UI Dashboard

The Batches page provides:
- **Real-time progress** bars with percentage completion
- **Status indicators**: Pending, Processing, Completed, Failed
- **Error details** with row-by-row validation errors
- **Processing statistics** and performance metrics
- **Batch actions**: Retry, view details, download reports

#### CLI Monitoring

```bash
# Check current status
php artisan payment:batch:status BATCH-20250115-001

# Real-time monitoring (updates every 5 seconds)
php artisan payment:batch:status BATCH-20250115-001 --refresh

# Show associated payments
php artisan payment:batch:status BATCH-20250115-001 --payments

# JSON output for scripting
php artisan payment:batch:status BATCH-20250115-001 --format=json
```

#### API Status Check

```bash
# Get batch status
curl -X GET "http://localhost:8000/api/accounting/payment-batches/{batchId}" \
  -H "X-Company-Id: your-company-uuid"

# List all batches with filtering
curl -X GET "http://localhost:8000/api/accounting/payment-batches?status=processing&limit=10" \
  -H "X-Company-Id: your-company-uuid"
```

## 📊 Understanding Batch Processing

### Batch Lifecycle

1. **Pending**: Batch created, waiting for processing
2. **Processing**: actively processing payments
3. **Completed**: all payments processed successfully
4. **Completed with Errors**: some payments failed
5. **Failed**: batch processing failed entirely

### Processing Flow

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Batch Created │───▶│   Validation     │───▶│   Processing    │
└─────────────────┘    └──────────────────┘    └─────────────────┘
                                │                        │
                                ▼                        ▼
                       ┌──────────────────┐    ┌─────────────────┐
                       │   Error Store    │    │   Payment       │
                       └──────────────────┘    │   Creation      │
                                │               └─────────────────┘
                                ▼                        │
                       ┌──────────────────┐              ▼
                       │   Status Update  │    ┌─────────────────┐
                       └──────────────────┘    │   Allocation    │
                                                       └─────────────────┘
```

### Error Handling

- **Validation Errors**: CSV format issues, missing required fields
- **Processing Errors**: Customer not found, invalid payment method
- **System Errors**: Database issues, queue failures
- **Partial Failures**: Some payments succeed, others fail

## 🔧 Advanced Features

### Auto-Allocation Strategies

When `auto_allocate: true`, payments are automatically allocated to customer invoices:

```json
{
  "allocation_strategy": "fifo",
  "priority_rules": "due_date ASC"
}
```

**Available Strategies:**
- `fifo`: First In, First Out (oldest invoices first)
- `proportional**: Distribute proportionally across all invoices
- `overdue_first`: Prioritize overdue invoices
- `largest_first`: Pay largest balances first
- `percentage_based`: Custom percentage allocation
- `custom_priority`: Use custom priority rules

### Idempotency

All batch creation requests support idempotency:

```bash
curl -X POST http://localhost:8000/api/accounting/payment-batches \
  -H "Idempotency-Key: $(uuidgen)" \
  # ... request body
```

Same idempotency key = same batch, preventing duplicates.

### File Upload Limits

- **Maximum file size**: 10MB
- **Supported formats**: CSV, TXT
- **Maximum rows**: 10,000 payments per batch

## 📈 Performance & Scaling

### Processing Speed

- **Small batches** (< 100 payments): ~1-2 seconds
- **Medium batches** (100-1000 payments): ~5-15 seconds  
- **Large batches** (1000+ payments): ~30-60 seconds

### Monitoring Metrics

The system tracks comprehensive metrics:

```php
// Metrics automatically recorded
PaymentMetrics::batchCreated($companyId, $sourceType, $receiptCount, $totalAmount);
PaymentMetrics::batchProcessed($companyId, $sourceType, $processedCount, $processedAmount);
PaymentMetrics::batchProcessingTime($companyId, $sourceType, $processingTimeSeconds);
```

### Queue Configuration

For high-volume processing, configure your queues:

```env
# .env
QUEUE_CONNECTION=redis
QUEUE_PAYMENT_BATCH=payment-processing
```

```bash
# Start workers
php artisan queue:work --queue=payment-processing --sleep=1 --tries=3
```

## 🛠️ Troubleshooting

### Common Issues

#### 1. CSV Validation Errors
```
Error: Missing required field 'entity_id' at row 15
```
**Solution**: Check your CSV format matches the required schema

#### 2. Customer Not Found
```
Error: Customer with ID 'uuid' not found
```
**Solution**: Verify customer UUIDs exist in your system

#### 3. Processing Timeout
```
Error: Batch processing timed out after 5 minutes
```
**Solution**: Reduce batch size or check queue worker performance

### Debug Mode

Enable detailed logging:

```bash
# Set log level
php artisan log:level=debug

# View batch logs
tail -f storage/logs/laravel.log | grep "batch"
```

### Manual Recovery

For failed batches, you can:

1. **Fix data issues** in your source file
2. **Retry processing** via UI or CLI
3. **Process remaining entries** individually

```bash
# Retry failed batch
php artisan payment:batch:retry BATCH-20250115-001
```

## 📚 API Reference

### Endpoints

#### Create Batch
```
POST /api/accounting/payment-batches
```

#### Get Batch Status  
```
GET /api/accounting/payment-batches/{batchId}
```

#### List Batches
```
GET /api/accounting/payment-batches?status={status}&limit={limit}&offset={offset}
```

### Response Format

```json
{
  "batch_id": "uuid",
  "batch_number": "BATCH-20250115-001", 
  "status": "processing",
  "receipt_count": 150,
  "total_amount": 15000.00,
  "currency": "USD",
  "progress_percentage": 45.5,
  "created_at": "2025-01-15T10:30:00Z",
  "estimated_completion": "2025-01-15T10:35:00Z",
  "metadata": {
    "processed_count": 68,
    "failed_count": 0,
    "processing_errors": {}
  }
}
```

## 🔐 Security & Permissions

### Required Permissions

- `payment.batch.create`: Create new batches
- `payment.batch.view`: View batch status and details
- `payment.batch.retry`: Retry failed batches
- `payment.batch.delete`: Delete batches (admin only)

### Rate Limiting

- **Batch creation**: 10 batches per minute per company
- **Status checks**: 100 requests per minute per company
- **File uploads**: 5 uploads per minute per company

## 📞 Support

### Getting Help

1. **Check the docs**: Review this guide and API documentation
2. **View logs**: Check Laravel logs for detailed error information  
3. **Contact support**: Provide batch ID and error details

### Best Practices

1. **Test with small batches** first (1-5 payments)
2. **Validate CSV format** before uploading large files
3. **Monitor processing** for large batches
4. **Set up alerts** for failed batches
5. **Regular cleanup** of old completed batches

---

**Ready to process your first batch?** Navigate to **Accounting → Payments → Batches** to get started!