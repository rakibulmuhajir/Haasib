# API Contract: Get Command Suggestions

**Endpoint**: `GET /api/commands/suggestions`
**Method**: GET
**Authentication**: Required (Bearer token)
**Authorization**: User must have access to command palette

## Purpose
Provides contextual command suggestions based on user input and permissions. Supports natural language processing and smart autocomplete (FR-002, FR-003, FR-004).

## Request

### Query Parameters
- `input` (string, required): The current user input text
- `context` (string, optional): Current application context (page, module)
- `limit` (integer, optional, default 10): Maximum number of suggestions to return

### Headers
- `Authorization: Bearer {token}`
- `X-Company-ID: {company_uuid}` (for tenancy)

## Response

### Success Response (200 OK)
```json
{
  "suggestions": [
    {
      "command_id": "uuid",
      "name": "create-invoice",
      "display_name": "Create Invoice",
      "description": "Create a new invoice for a customer",
      "confidence_score": 0.95,
      "parameters": [
        {
          "name": "customer_id",
          "type": "uuid",
          "required": true,
          "description": "Customer to create invoice for"
        }
      ],
      "category": "invoicing"
    }
  ],
  "total_count": 1,
  "has_more": false
}
```

### Error Responses
- `400 Bad Request`: Invalid input parameters
- `401 Unauthorized`: Invalid or missing authentication
- `403 Forbidden`: User lacks permission to access command palette
- `422 Unprocessable Entity`: Invalid context or input format

## Business Rules
- Suggestions filtered by user permissions (FR-010)
- Contextual suggestions based on current app state (FR-004)
- Natural language processing for input interpretation (FR-002)
- Results ordered by confidence score descending
- Company tenancy enforced (FR-013)

## Performance Requirements
- Response time < 100ms for typical queries
- Support for partial matching and fuzzy search
- Cached suggestions for common inputs

## Testing Scenarios
- Empty input returns general suggestions
- Partial command names return matches
- Natural language queries parsed correctly
- Permission filtering works
- Company context isolation maintained
