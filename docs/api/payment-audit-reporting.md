# Payment Audit & Reporting API Documentation

## Overview

The Payment Audit & Reporting API provides comprehensive endpoints for tracking payment operations, accessing audit trails, and generating detailed reports. This API ensures full traceability of payment-related activities within the Haasib system.

## Base URL

```
/api/accounting/payments
```

## Authentication

All endpoints require:
- `Authorization: Bearer {token}` header
- `X-Company-Id: {company_id}` header for multi-tenant isolation

## Audit Endpoints

### Get Company Audit Trail

Retrieve a paginated list of all audit events for the current company.

**Endpoint:** `GET /api/accounting/payments/audit`

**Query Parameters:**
- `start_date` (string, optional): Filter by start date (YYYY-MM-DD)
- `end_date` (string, optional): Filter by end date (YYYY-MM-DD)
- `actions` (string|array, optional): Filter by action types
- `actor_types` (string|array, optional): Filter by actor types
- `payment_methods` (string|array, optional): Filter by payment methods
- `payment_statuses` (string|array, optional): Filter by payment statuses
- `entity_id` (string, optional): Filter by specific entity ID
- `min_amount` (number, optional): Filter by minimum amount
- `max_amount` (number, optional): Filter by maximum amount
- `search` (string, optional): Search by payment number or entity name
- `page` (integer, optional): Page number (default: 1)
- `limit` (integer, optional): Items per page (default: 50, max: 100)
- `sort_by` (string, optional): Sort field (default: 'timestamp')
- `sort_direction` (string, optional): Sort direction ('asc' or 'desc', default: 'desc')

**Response:**
```json
{
  "audit_trail": [
    {
      "id": "audit-uuid",
      "payment_id": "payment-uuid",
      "payment_number": "PAY-2025-001",
      "action": "payment_created",
      "actor_id": "user-uuid",
      "actor_type": "user",
      "timestamp": "2025-01-15T10:30:00Z",
      "metadata": {
        "payment_method": "bank_transfer",
        "amount": 1000.00,
        "ip_address": "192.168.1.1",
        "user_agent": "Mozilla/5.0..."
      },
      "payment_details": {
        "payment_method": "bank_transfer",
        "amount": 1000.00,
        "currency_id": "usd-uuid",
        "entity_id": "customer-uuid",
        "entity_name": "Acme Corp"
      },
      "company_id": "company-uuid"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 50,
    "total": 150,
    "last_page": 3
  },
  "filters_applied": ["start_date", "actions"]
}
```

### Get Payment Audit Trail

Retrieve audit trail for a specific payment.

**Endpoint:** `GET /api/accounting/payments/audit/{paymentId}`

**Query Parameters:**
- `start_date` (string, optional): Filter by start date
- `end_date` (string, optional): Filter by end date
- `actions` (string|array, optional): Filter by action types
- `actor_types` (string|array, optional): Filter by actor types

**Response:**
```json
{
  "payment_id": "payment-uuid",
  "payment_number": "PAY-2025-001",
  "audit_trail": [
    {
      "id": "audit-uuid",
      "action": "payment_created",
      "actor_id": "user-uuid",
      "actor_type": "user",
      "timestamp": "2025-01-15T10:30:00Z",
      "metadata": {
        "payment_method": "bank_transfer",
        "amount": 1000.00
      }
    }
  ],
  "total_entries": 3
}
```

### Get Bank Reconciliation Audit

Retrieve bank reconciliation audit data.

**Endpoint:** `GET /api/accounting/payments/audit/reconciliation`

**Query Parameters:**
- `start_date` (string, optional): Filter by start date
- `end_date` (string, optional): Filter by end date
- `payment_number` (string, optional): Filter by payment number
- `reconciled_only` (boolean, optional): Show only reconciled payments
- `unreconciled_only` (boolean, optional): Show only unreconciled payments

**Response:**
```json
{
  "reconciliation_audit": [
    {
      "payment_id": "payment-uuid",
      "payment_number": "PAY-2025-001",
      "payment_method": "bank_transfer",
      "amount": 1000.00,
      "payment_date": "2025-01-15",
      "status": "completed",
      "reconciled": true,
      "reconciled_date": "2025-01-16T09:00:00Z",
      "entity": {
        "id": "customer-uuid",
        "name": "Acme Corp"
      },
      "last_audit": {
        "timestamp": "2025-01-16T09:00:00Z",
        "action": "bank_reconciled"
      }
    }
  ],
  "total_payments": 50,
  "reconciled_count": 45,
  "filters_applied": ["start_date", "end_date"]
}
```

### Get Audit Metrics

Retrieve audit metrics and statistics for a given period.

**Endpoint:** `GET /api/accounting/payments/audit/metrics`

**Query Parameters:**
- `start_date` (string, optional): Start date (default: 30 days ago)
- `end_date` (string, optional): End date (default: today)

**Response:**
```json
{
  "metrics": {
    "action_counts": {
      "payment_created": 120,
      "payment_allocated": 95,
      "bank_reconciled": 80,
      "allocation_reversed": 5
    },
    "actor_type_distribution": {
      "user": 180,
      "system": 25,
      "api": 15
    },
    "daily_activity": [
      {
        "date": "2025-01-15",
        "count": 15
      }
    ],
    "payment_method_distribution": {
      "bank_transfer": 80,
      "card": 30,
      "cash": 10
    },
    "reconciliation_metrics": {
      "total_payments": 100,
      "reconciled_payments": 85,
      "unreconciled_payments": 15,
      "reconciliation_rate": 85.0
    },
    "total_audit_events": 220
  },
  "date_range": {
    "start_date": "2024-12-16",
    "end_date": "2025-01-15"
  },
  "generated_at": "2025-01-15T11:00:00Z"
}
```

## Payment Endpoints (Enhanced with Audit Support)

### Create Payment

**Endpoint:** `POST /api/accounting/payments`

**Request Body:**
```json
{
  "entity_id": "customer-uuid",
  "payment_method": "bank_transfer",
  "amount": 1000.00,
  "currency_id": "usd-uuid",
  "payment_date": "2025-01-15",
  "reference_number": "INV-2025-001",
  "notes": "Payment for invoice INV-2025-001",
  "auto_allocate": false,
  "allocation_strategy": "fifo",
  "allocation_options": {}
}
```

**Response:** (201 Created)
```json
{
  "payment": {
    "id": "payment-uuid",
    "payment_number": "PAY-2025-001",
    "entity_id": "customer-uuid",
    "entity_name": "Acme Corp",
    "amount": 1000.00,
    "currency": {
      "id": "usd-uuid",
      "code": "USD",
      "symbol": "$"
    },
    "payment_method": "bank_transfer",
    "payment_date": "2025-01-15",
    "reference_number": "INV-2025-001",
    "status": "pending",
    "notes": "Payment for invoice INV-2025-001",
    "created_at": "2025-01-15T10:30:00Z"
  },
  "remaining_amount": 1000.00,
  "is_fully_allocated": false,
  "message": "Payment recorded successfully"
}
```

**Audit Events Generated:**
- `payment_created`: Recorded when payment is successfully created

### Allocate Payment

**Endpoint:** `POST /api/accounting/payments/{paymentId}/allocate`

**Request Body:**
```json
{
  "allocations": [
    {
      "invoice_id": "invoice-uuid",
      "amount": 800.00,
      "apply_early_payment_discount": false,
      "notes": "Partial payment allocation"
    }
  ]
}
```

**Response:** (201 Created)
```json
{
  "payment_id": "payment-uuid",
  "allocations_created": 1,
  "total_allocated": 800.00,
  "remaining_amount": 200.00,
  "payment_status": "pending",
  "is_fully_allocated": false,
  "allocations": [
    {
      "allocation_id": "allocation-uuid",
      "invoice_id": "invoice-uuid",
      "allocated_amount": 800.00,
      "original_amount": 800.00,
      "discount_amount": 0.00,
      "discount_percent": 0.00,
      "notes": "Partial payment allocation"
    }
  ],
  "message": "Payment allocated successfully"
}
```

**Audit Events Generated:**
- `payment_allocated`: Recorded when payment is allocated to invoices

## CLI Commands

### Payment Allocation Report

Generate comprehensive payment allocation reports with audit trail support.

**Command:** `php artisan payment:allocation:report`

**Options:**
- `--start-date=`: Report start date (YYYY-MM-DD)
- `--end-date=`: Report end date (YYYY-MM-DD)
- `--payment=`: Filter by specific payment number
- `--customer=`: Filter by customer ID
- `--reconciled=`: Filter by reconciliation status (true/false)
- `--format=table`: Output format (table, json, csv)
- `--output=`: Output file path (for CSV format)
- `--include-audit`: Include audit trail summary
- `--include-metrics`: Include performance metrics

**Examples:**

Generate a table report for the last 30 days:
```bash
php artisan payment:allocation:report --start-date=2025-01-01 --format=table
```

Generate a JSON report with audit trail:
```bash
php artisan payment:allocation:report --format=json --include-audit --include-metrics
```

Export to CSV:
```bash
php artisan payment:allocation:report --format=csv --output=/tmp/allocations.csv
```

**Sample Output (Table Format):**
```
📊 PAYMENT ALLOCATION REPORT
================================================================================
Generated: 2025-01-15T11:00:00Z
Period: 2025-01-01 to 2025-01-15
Total Payments: 25
Currency: USD

📈 SUMMARY
----------------------------------------
Total Allocations: 45
Total Amount Allocated: $125,750.00
Total Discounts Applied: $2,340.00
Total Unallocated Cash: $8,250.00
Reconciliation Rate: 88.00%

💳 PAYMENT ALLOCATIONS
------------------------------------------------------------------------------------------------------
| Payment #    | Entity       | Amount      | Allocated   | Unallocated | Count | Reconciled |
------------------------------------------------------------------------------------------------------
| PAY-2025-001 | Acme Corp    | $5,000.00   | $4,500.00   | $500.00     | 3     | ✓         |
| PAY-2025-002 | Beta Inc     | $3,200.00   | $3,200.00   | $0.00       | 2     | ✓         |
------------------------------------------------------------------------------------------------------
```

## Event System

### Audit Events

The system emits the following audit events:

#### PaymentAudited
**Payload:**
```json
{
  "payment_id": "payment-uuid",
  "company_id": "company-uuid",
  "actor_id": "user-uuid",
  "actor_type": "user",
  "action": "payment_created",
  "timestamp": "2025-01-15T10:30:00Z",
  "metadata": {
    "payment_method": "bank_transfer",
    "amount": 1000.00,
    "ip_address": "192.168.1.1",
    "user_agent": "Mozilla/5.0..."
  }
}
```

#### AllocationReversed
**Payload:**
```json
{
  "allocation_id": "allocation-uuid",
  "payment_id": "payment-uuid",
  "company_id": "company-uuid",
  "actor_id": "user-uuid",
  "actor_type": "user",
  "action": "allocation_reversed",
  "timestamp": "2025-01-15T11:00:00Z",
  "metadata": {
    "reason": "Customer requested refund",
    "refund_amount": 500.00,
    "original_amount": 1000.00
  }
}
```

#### BankReconciliationMarker
**Payload:**
```json
{
  "payment_id": "payment-uuid",
  "company_id": "company-uuid",
  "actor_id": "user-uuid",
  "actor_type": "user",
  "action": "bank_reconciled",
  "timestamp": "2025-01-16T09:00:00Z",
  "metadata": {
    "reconciliation_method": "automated",
    "reconciliation_date": "2025-01-16"
  }
}
```

### Real-time Broadcasting

All audit events are broadcast through WebSocket channels:

- `company.{company_id}.payments`: Company-specific payment updates
- `user.{user_id}.payments`: User-specific payment updates  
- `bank.reconciliation`: Bank reconciliation events

**Channel Format:**
```javascript
// Listen for payment audit events
Echo.channel(`company.${companyId}.payments`)
    .listen('PaymentAudited', (e) => {
        console.log('Payment audit event:', e.data);
    });

// Listen for bank reconciliation events
Echo.channel('bank.reconciliation')
    .listen('BankReconciliationMarker', (e) => {
        console.log('Bank reconciliation:', e.data);
    });
```

## Error Handling

### Standard Error Response

```json
{
  "error": "Error Type",
  "message": "Human-readable error message",
  "errors": {
    "field_name": ["Validation error details"]
  }
}
```

### Common HTTP Status Codes

- `200 OK`: Successful request
- `201 Created`: Resource created successfully
- `400 Bad Request`: Invalid request parameters
- `401 Unauthorized`: Authentication required
- `403 Forbidden`: Insufficient permissions
- `404 Not Found`: Resource not found
- `422 Unprocessable Entity`: Validation failed
- `500 Internal Server Error`: Server error

## Rate Limiting

- Audit endpoints: 100 requests per minute per user
- Report generation: 10 requests per minute per user
- Standard endpoints: 1000 requests per minute per user

## Permissions Required

### Payment Audit Permissions
- `accounting.payments.view`: View payment details and audit trails
- `accounting.payments.create`: Create payments
- `accounting.payments.allocate`: Allocate payments
- `accounting.payments.reconcile`: Perform bank reconciliation

## Monitoring & Telemetry

### Key Metrics Tracked

1. **Audit Event Volume**: Total audit events generated
2. **API Response Times**: Response time percentiles for audit endpoints
3. **Error Rates**: Failed audit operations
4. **Database Performance**: Audit log query performance
5. **Real-time Connections**: Active WebSocket connections

### Health Checks

- `/api/accounting/health`: Overall system health
- `/api/accounting/health/audit`: Audit subsystem health
- `/api/accounting/health/database`: Database connectivity

### Logging

All audit operations are logged with:
- Request ID for tracing
- User identification
- Company context
- Action performed
- Result status
- Performance metrics

## Integration Examples

### JavaScript/TypeScript Client

```typescript
class PaymentAuditClient {
  private baseUrl: string;
  private headers: Record<string, string>;

  constructor(baseUrl: string, token: string, companyId: string) {
    this.baseUrl = baseUrl;
    this.headers = {
      'Authorization': `Bearer ${token}`,
      'X-Company-Id': companyId,
      'Content-Type': 'application/json'
    };
  }

  async getAuditTrail(filters?: AuditFilters): Promise<AuditResponse> {
    const params = new URLSearchParams(filters as any);
    const response = await fetch(`${this.baseUrl}/audit?${params}`, {
      headers: this.headers
    });
    
    if (!response.ok) {
      throw new Error(`Failed to fetch audit trail: ${response.statusText}`);
    }
    
    return response.json();
  }

  async getMetrics(startDate?: string, endDate?: string): Promise<MetricsResponse> {
    const params = new URLSearchParams({
      start_date: startDate || '',
      end_date: endDate || ''
    });
    
    const response = await fetch(`${this.baseUrl}/audit/metrics?${params}`, {
      headers: this.headers
    });
    
    return response.json();
  }
}
```

### Python Client

```python
import requests
from typing import Optional, Dict, Any

class PaymentAuditClient:
    def __init__(self, base_url: str, token: str, company_id: str):
        self.base_url = base_url
        self.headers = {
            'Authorization': f'Bearer {token}',
            'X-Company-Id': company_id,
            'Content-Type': 'application/json'
        }

    def get_audit_trail(self, filters: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
        response = requests.get(
            f'{self.base_url}/audit',
            params=filters or {},
            headers=self.headers
        )
        response.raise_for_status()
        return response.json()

    def get_metrics(self, start_date: Optional[str] = None, 
                   end_date: Optional[str] = None) -> Dict[str, Any]:
        params = {}
        if start_date:
            params['start_date'] = start_date
        if end_date:
            params['end_date'] = end_date
            
        response = requests.get(
            f'{self.base_url}/audit/metrics',
            params=params,
            headers=self.headers
        )
        response.raise_for_status()
        return response.json()
```

## Best Practices

1. **Pagination**: Always use pagination for large audit trails
2. **Date Filtering**: Use date range filters to improve performance
3. **Caching**: Cache metrics data that doesn't change frequently
4. **Error Handling**: Implement proper error handling and retry logic
5. **Security**: Validate all inputs and check user permissions
6. **Performance**: Monitor database query performance for audit operations
7. **Compliance**: Ensure audit logs are immutable and tamper-proof

## Testing

### API Testing Examples

```bash
# Get audit trail
curl -H "Authorization: Bearer $TOKEN" \
     -H "X-Company-Id: $COMPANY_ID" \
     "https://api.haasib.com/api/accounting/payments/audit?start_date=2025-01-01&limit=10"

# Get metrics
curl -H "Authorization: Bearer $TOKEN" \
     -H "X-Company-Id: $COMPANY_ID" \
     "https://api.haasib.com/api/accounting/payments/audit/metrics?start_date=2025-01-01"
```

### Load Testing

Recommended load testing scenarios:
- 100 concurrent users accessing audit trails
- 10 users generating large reports simultaneously
- WebSocket connection stress testing
- Database query performance under load

## Support

For API support and questions:
- Documentation: https://docs.haasib.com/api/accounting
- Issue Tracker: https://github.com/haasib/accounting/issues
- Email: api-support@haasib.com