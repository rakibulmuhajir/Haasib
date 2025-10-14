# API Contract: Get Command History

**Endpoint**: `GET /api/commands/history`
**Method**: GET
**Authentication**: Required (Bearer token)
**Authorization**: User can only see their own command history

## Purpose
Retrieves the user's command execution history for review and repeat execution (FR-009).

## Request

### Query Parameters
- `page` (integer, optional, default 1): Page number for pagination
- `per_page` (integer, optional, default 20): Items per page (max 100)
- `command_name` (string, optional): Filter by specific command
- `status` (string, optional): Filter by execution status (success, failed, partial)
- `date_from` (date, optional): Filter executions after this date
- `date_to` (date, optional): Filter executions before this date

### Headers
- `Authorization: Bearer {token}`
- `X-Company-ID: {company_uuid}` (for tenancy)

## Response

### Success Response (200 OK)
```json
{
  "history": [
    {
      "execution_id": "uuid",
      "command_name": "create-invoice",
      "input_text": "create invoice for ACME Corp",
      "executed_at": "2025-10-13T16:00:00Z",
      "status": "success",
      "parameters_used": {
        "customer_id": "uuid",
        "amount": 1500.00
      },
      "result_summary": "Invoice INV-001 created",
      "audit_reference": "uuid",
      "execution_time_ms": 250
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total_items": 45,
    "total_pages": 3,
    "has_next": true,
    "has_prev": false
  }
}
```

### Error Responses
- `401 Unauthorized`: Invalid authentication
- `403 Forbidden`: User lacks access to command palette
- `422 Unprocessable Entity`: Invalid filter parameters

## Business Rules
- Users can only see their own command history
- Company tenancy enforced (FR-013)
- History includes successful and failed executions
- Audit references provided for traceability (FR-008)
- Results paginated for performance

## Performance Requirements
- Response time < 200ms for typical queries
- Efficient pagination with database indexes
- Optional filtering without performance degradation

## Testing Scenarios
- Retrieve user's command history
- Filter by command name
- Filter by date range
- Filter by execution status
- Pagination works correctly
- Company isolation maintained
- Audit references are valid
