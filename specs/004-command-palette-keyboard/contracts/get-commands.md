# API Contract: Get Available Commands

**Endpoint**: `GET /api/commands`
**Method**: GET
**Authentication**: Required (Bearer token)
**Authorization**: User must have access to command palette

## Purpose
Retrieves the list of available commands that the user can execute, including parameter definitions (FR-005, FR-010).

## Request

### Query Parameters
- `category` (string, optional): Filter by command category (e.g., 'invoicing', 'customers')
- `search` (string, optional): Search in command names and descriptions

### Headers
- `Authorization: Bearer {token}`
- `X-Company-ID: {company_uuid}` (for tenancy)

## Response

### Success Response (200 OK)
```json
{
  "commands": [
    {
      "id": "uuid",
      "name": "create-invoice",
      "display_name": "Create Invoice",
      "description": "Create a new invoice for a customer",
      "category": "invoicing",
      "parameters": [
        {
          "name": "customer_id",
          "type": "uuid",
          "required": true,
          "description": "Customer to create invoice for",
          "validation": {
            "entity": "customer",
            "exists_in_company": true
          }
        },
        {
          "name": "amount",
          "type": "decimal",
          "required": true,
          "description": "Invoice amount",
          "validation": {
            "min": 0.01,
            "max": 999999.99
          }
        },
        {
          "name": "currency",
          "type": "string",
          "required": false,
          "default": "USD",
          "description": "Invoice currency",
          "validation": {
            "enum": ["USD", "EUR", "GBP"]
          }
        }
      ],
      "required_permissions": ["invoices.create"],
      "examples": [
        "create invoice for customer ACME",
        "new invoice $1000 USD"
      ]
    }
  ],
  "categories": [
    "invoicing",
    "customers",
    "payments",
    "reports"
  ]
}
```

### Error Responses
- `401 Unauthorized`: Invalid authentication
- `403 Forbidden`: User lacks access to command palette

## Business Rules
- Commands filtered by user permissions (FR-010)
- Parameter schemas include validation rules
- Company context affects available commands (FR-013)
- Commands grouped by categories for UI organization
- Examples provided for natural language guidance (FR-002)

## Performance Requirements
- Response time < 150ms
- Commands cached with permission-based keys
- Efficient filtering and search

## Testing Scenarios
- Commands filtered by user permissions
- Parameter schemas are valid
- Categories returned correctly
- Search functionality works
- Company isolation maintained
- Examples help with natural language input
