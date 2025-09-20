# Haasib Invoicing System API Documentation

## Overview

The Haasib Invoicing System provides a comprehensive REST API for managing invoices, payments, customers, and multi-currency operations. This API is designed for businesses that need robust invoicing capabilities with international currency support.

**Base URL**: `https://api.haasib.com/v1`  
**Documentation**: `https://api.haasib.com/v1/docs`

## Authentication

All API requests require authentication using Bearer tokens. Include the following header in all requests:

```
Authorization: Bearer YOUR_API_TOKEN
```

## Rate Limiting

- **60 requests per minute** per authenticated user
- **120 requests per minute** per IP address (for unauthenticated endpoints)
- Rate limit headers are included in all responses

## Idempotency

All write operations (POST, PUT, PATCH, DELETE) require an `Idempotency-Key` header to prevent duplicate processing:

```
Idempotency-Key: uuid-or-unique-string
```

## Error Responses

The API uses standardized error responses:

```json
{
  "success": false,
  "error": "ERROR_CODE",
  "message": "Human-readable error message",
  "code": "ERROR_CODE"
}
```

### Common Error Codes

| HTTP Status | Error Code | Description |
|-------------|------------|-------------|
| 400 | BAD_REQUEST | Invalid request parameters |
| 401 | UNAUTHORIZED | Missing or invalid authentication |
| 403 | FORBIDDEN | Insufficient permissions |
| 404 | NOT_FOUND | Resource not found |
| 422 | VALIDATION_ERROR | Invalid input data |
| 429 | RATE_LIMIT_EXCEEDED | Too many requests |
| 500 | INTERNAL_SERVER_ERROR | Server error |

## Core Entities

### Invoices

Invoices represent bills sent to customers for products or services.

#### Key Fields
- `status`: draft, sent, posted, paid, cancelled, void
- `total_amount`: Total invoice amount in invoice currency
- `balance_due`: Remaining amount to be paid
- `currency`: Multi-currency support

#### Workflow
1. **draft** → **sent** → **posted** → **paid**
2. Can be **cancelled** at any stage before posting
3. Can be **void** after posting for accounting purposes

### Payments

Payments represent funds received from customers.

#### Key Fields
- `status`: pending, allocated, void, refunded
- `amount`: Payment amount
- `allocated_amount`: Amount applied to invoices
- `unallocated_amount`: Amount available for allocation

#### Allocation Logic
- Payments can be automatically or manually allocated to invoices
- Supports partial and over-payments
- Allocation can be voided and payments refunded

### Customers

Customers are the recipients of invoices and source of payments.

#### Key Features
- Multi-currency support per customer
- Configurable payment terms
- Address and contact information
- Account statements and statistics

### Currencies

Multi-currency support with real-time exchange rates.

#### Key Features
- Company-specific currency configuration
- Exchange rate management and history
- Currency conversion and formatting
- Balance reporting in multiple currencies

## API Endpoints

### Health Check
```
GET /health
```
Check API health and availability.

### Invoices

#### List Invoices
```
GET /invoices
```
Retrieve paginated list of invoices with filtering options.

**Query Parameters:**
- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 15, max: 100)
- `status`: Filter by status (draft, sent, posted, paid, cancelled, void)
- `customer_id`: Filter by customer
- `currency_id`: Filter by currency
- `date_from`, `date_to`: Date range filter
- `search`: Search in invoice number, customer name, notes
- `sort_by`: Sort field (created_at, invoice_date, due_date, total_amount, status)
- `sort_direction`: asc or desc

#### Create Invoice
```
POST /invoices
Headers:
  Idempotency-Key: required

Body:
{
  "customer_id": 123,
  "currency_id": 1,
  "invoice_date": "2025-09-13",
  "due_date": "2025-10-13",
  "notes": "Monthly services",
  "terms": "Payment due within 30 days",
  "items": [
    {
      "description": "Web Development",
      "quantity": 10,
      "unit_price": 100.00,
      "tax_rate": 15.00
    }
  ]
}
```

#### Get Invoice
```
GET /invoices/{id}
```
Retrieve detailed invoice information.

#### Update Invoice
```
PUT /invoices/{id}
Headers:
  Idempotency-Key: required
```
Update invoice (only draft invoices can be updated).

#### Delete Invoice
```
DELETE /invoices/{id}
Headers:
  Idempotency-Key: required
```
Delete invoice (only draft invoices can be deleted).

#### Invoice Actions
```
POST /invoices/{id}/send      # Mark as sent
POST /invoices/{id}/post      # Post to ledger
POST /invoices/{id}/cancel    # Cancel invoice
POST /invoices/{id}/generate-pdf  # Generate PDF
POST /invoices/{id}/send-email   # Send via email
POST /invoices/{id}/duplicate    # Create copy
```

#### Invoice Statistics
```
GET /invoices/statistics
```
Get aggregated invoice statistics.

#### Bulk Operations
```
POST /invoices/bulk
Headers:
  Idempotency-Key: required

Body:
{
  "action": "send|post|cancel|delete",
  "invoice_ids": [1, 2, 3]
}
```

### Payments

#### List Payments
```
GET /payments
```
Retrieve paginated list of payments.

#### Create Payment
```
POST /payments
Headers:
  Idempotency-Key: required

Body:
{
  "customer_id": 123,
  "amount": 1150.00,
  "currency_id": 1,
  "payment_method": "bank_transfer",
  "payment_date": "2025-09-15",
  "reference_number": "TXN-2025-12345",
  "notes": "Payment for services",
  "auto_allocate": true,
  "invoice_ids": [456, 789]  // Optional
}
```

#### Payment Actions
```
POST /payments/{id}/allocate      # Manual allocation
POST /payments/{id}/auto-allocate  # Auto allocation
POST /payments/{id}/void          # Void payment
POST /payments/{id}/refund        # Refund payment
```

#### Payment Statistics
```
GET /payments/statistics
```

### Customers

#### List Customers
```
GET /customers
```

#### Create Customer
```
POST /customers
Headers:
  Idempotency-Key: required

Body:
{
  "name": "Acme Corporation",
  "email": "billing@acme.com",
  "phone": "+1-555-0123",
  "address": "123 Business Street",
  "city": "New York",
  "state": "NY",
  "postal_code": "10001",
  "country": "United States",
  "currency_id": 1,
  "payment_terms": 30,
  "tax_id": "TAX123456789",
  "notes": "Enterprise customer",
  "status": "active"
}
```

#### Customer Relations
```
GET /customers/{id}/invoices         # Customer invoices
GET /customers/{id}/payments         # Customer payments
GET /customers/{id}/statement        # Account statement
GET /customers/{id}/statistics       # Customer statistics
```

#### Search Customers
```
GET /customers/search?q=query&limit=10
```

### Currencies

#### Company Currencies
```
GET /currencies/company      # Enabled currencies
GET /currencies/available    # All available currencies
```

#### Exchange Rates
```
GET /currencies/exchange-rate?from=USD&to=EUR  # Get rate
POST /currencies/exchange-rate                # Update rate

Body:
{
  "from_currency": "USD",
  "to_currency": "EUR",
  "rate": 0.85,
  "date": "2025-09-13"
}
```

#### Currency Operations
```
POST /currencies/convert         # Convert amount
POST /currencies/enable          # Enable currency
POST /currencies/disable         # Disable currency
GET  /currencies/exchange-rate-history  # Rate history
GET  /currencies/latest-exchange-rates   # Latest rates
GET  /currencies/balances        # Currency balances
POST /currencies/currency-impact  # Calculate impact
POST /currencies/sync-exchange-rates  # Sync rates
GET  /currencies/symbol?currency=USD  # Get symbol
POST /currencies/format-money    # Format amount
```

## Multi-Currency Support

### Currency Conversion
The API supports real-time currency conversion with historical rates:

```bash
# Convert 100 USD to EUR
POST /currencies/convert
{
  "amount": 100.00,
  "from_currency": "USD",
  "to_currency": "EUR",
  "date": "2025-09-13"
}

# Response
{
  "success": true,
  "data": {
    "original_amount": 100.00,
    "original_currency": "USD",
    "converted_amount": 85.00,
    "converted_currency": "EUR",
    "exchange_rate": 0.85,
    "date": "2025-09-13"
  }
}
```

### Exchange Rate Management
- Automatic exchange rate synchronization
- Historical rate tracking
- Company-specific rate overrides
- Impact analysis for currency fluctuations

## Webhooks

The API supports webhook notifications for key events:

### Available Events
- `invoice.created`
- `invoice.sent`
- `invoice.posted`
- `invoice.paid`
- `payment.received`
- `payment.allocated`
- `customer.created`

### Webhook Configuration
Webhook endpoints can be configured in the application settings. Each webhook receives a POST request with event details.

## SDKs

### JavaScript
```javascript
const HaasibAPI = require('haasib-api');

const client = new HaasibAPI({
  apiKey: 'your-api-key',
  baseURL: 'https://api.haasib.com/v1'
});

// Create invoice
const invoice = await client.invoices.create({
  customer_id: 123,
  items: [
    {
      description: 'Development Services',
      quantity: 10,
      unit_price: 100.00
    }
  ]
});
```

### Python
```python
import haasib

client = haasib.Client(api_key='your-api-key')

# Create customer
customer = client.customers.create(
    name='Acme Corp',
    email='billing@acme.com',
    currency_id=1
)
```

### PHP
```php
$haasib = new Haasib\Client('your-api-key');

$invoice = $haasib->invoices()->create([
    'customer_id' => 123,
    'items' => [
        [
            'description' => 'Consulting Services',
            'quantity' => 5,
            'unit_price' => 150.00
        ]
    ]
]);
```

## Best Practices

### 1. Error Handling
Always check the `success` field in responses and handle errors gracefully:

```javascript
const response = await api.invoices.create(invoiceData);

if (!response.success) {
  console.error('Error:', response.message);
  // Handle specific error codes
  if (response.code === 'VALIDATION_ERROR') {
    // Show validation errors to user
  }
}
```

### 2. Rate Limiting
Monitor rate limit headers and implement backoff:

```javascript
const remaining = response.headers['x-ratelimit-remaining'];
const resetAt = response.headers['x-ratelimit-reset'];

if (remaining < 5) {
  // Implement backoff or notify user
}
```

### 3. Idempotency
Always generate unique idempotency keys for write operations:

```javascript
const idempotencyKey = crypto.randomUUID();
const response = await api.invoices.create(data, {
  'Idempotency-Key': idempotencyKey
});
```

### 4. Pagination
Handle large result sets with pagination:

```javascript
let page = 1;
let invoices = [];

do {
  const response = await api.invoices.list({ page, per_page: 100 });
  invoices = invoices.concat(response.data);
  page = response.meta.current_page + 1;
} while (page <= response.meta.last_page);
```

## Support

For API support and questions:

- **Documentation**: https://docs.haasib.com
- **API Status**: https://status.haasib.com
- **Support Email**: api-support@haasib.com
- **Community**: https://community.haasib.com

## Changelog

### v1.0.0 (2025-09-13)
- Initial API release
- Core invoice management
- Payment processing and allocation
- Customer management
- Multi-currency support
- Exchange rate management
- PDF generation
- Email integration
- Comprehensive API documentation
## Resource IDs and UUIDs

All primary keys for core resources are UUIDs:

- Invoices: `invoice_id`
- Payments: `payment_id`
- Customers: `customer_id`
- Payment Allocations: `allocation_id`
- Ledger entities (accounts, journal entries, lines): `id` (UUID)

API paths and request validators expect UUID values for all `id` parameters.

- Routes define `whereUuid('id')` (and analogous) to enforce proper format.
- For DB validations, use the correct PK columns, e.g.:
  - `exists:invoices,invoice_id`
  - `exists:payments,payment_id`
  - `exists:customers,customer_id`

## Invoices: Check PDF Availability

Endpoint: `GET /api/invoices/{id}/pdf-exists`

- Path param `{id}` is `invoice_id` (UUID)
- Returns whether a generated PDF exists for the invoice number and, if so, the latest file and its URL

Example response:

```
{
  "success": true,
  "data": {
    "exists": true,
    "latest": {
      "filename": "invoice_INV-20250919_2025-09-19.pdf",
      "url": "https://app.example.com/storage/invoices/invoice_INV-20250919_2025-09-19.pdf",
      "modified_at": "2025-09-19T12:34:56Z"
    }
  }
}
```

Notes:

- PDFs are stored under `storage/app/public/invoices`. The app will create the directory and attempt to ensure the `public/storage` symlink exists when generating PDFs.
