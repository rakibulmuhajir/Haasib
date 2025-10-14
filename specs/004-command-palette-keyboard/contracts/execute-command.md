# API Contract: Execute Command

**Endpoint**: `POST /api/commands/execute`
**Method**: POST
**Authentication**: Required (Bearer token)
**Authorization**: User must have permission for the specific command

## Purpose
Executes a command through the command palette, supporting both single and batch execution (FR-006, FR-014). All executions go through the command bus for consistency.

## Request

### Headers
- `Authorization: Bearer {token}`
- `X-Company-ID: {company_uuid}` (for tenancy)
- `Idempotency-Key: {uuid}` (optional, for preventing duplicates)

### Body
```json
{
  "command_name": "create-invoice",
  "parameters": {
    "customer_id": "uuid",
    "amount": 1000.00,
    "currency": "USD"
  },
  "batch_mode": false
}
```

For batch execution:
```json
{
  "commands": [
    {
      "command_name": "create-invoice",
      "parameters": { ... },
      "idempotency_key": "uuid1"
    },
    {
      "command_name": "send-invoice",
      "parameters": { ... },
      "idempotency_key": "uuid2"
    }
  ],
  "batch_mode": true,
  "continue_on_error": false
}
```

## Response

### Success Response (200 OK)
```json
{
  "execution_id": "uuid",
  "status": "completed",
  "result": {
    "invoice_id": "uuid",
    "audit_reference": "uuid",
    "message": "Invoice created successfully"
  },
  "execution_time_ms": 150
}
```

For batch execution:
```json
{
  "batch_id": "uuid",
  "executions": [
    {
      "execution_id": "uuid1",
      "command_name": "create-invoice",
      "status": "completed",
      "result": { ... }
    },
    {
      "execution_id": "uuid2",
      "command_name": "send-invoice",
      "status": "completed",
      "result": { ... }
    }
  ],
  "overall_status": "completed",
  "total_execution_time_ms": 300
}
```

### Error Responses
- `400 Bad Request`: Invalid command name or parameters
- `401 Unauthorized`: Invalid authentication
- `403 Forbidden`: User lacks permission for command
- `409 Conflict`: Idempotency key collision
- `422 Unprocessable Entity`: Command execution failed
- `429 Too Many Requests`: Rate limit exceeded

## Business Rules
- All executions routed through command bus (Command-Bus Supremacy)
- Company context maintained (FR-013)
- Audit logging required (FR-008)
- Idempotency support for safe retries
- Permission validation before execution (FR-010)
- Batch execution stops on first error unless continue_on_error=true

## Performance Requirements
- Single command execution < 500ms
- Batch execution < 2000ms for up to 10 commands
- Async processing for long-running commands

## Testing Scenarios
- Valid command execution with audit logging
- Permission denied for unauthorized commands
- Invalid parameters return validation errors
- Batch execution with mixed success/failure
- Idempotency prevents duplicate executions
- Company isolation maintained
