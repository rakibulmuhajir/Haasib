# API Contract: Command Templates

**Endpoints**:
- `GET /api/commands/templates` - List user's command templates
- `POST /api/commands/templates` - Create a new template
- `PUT /api/commands/templates/{id}` - Update a template
- `DELETE /api/commands/templates/{id}` - Delete a template

**Authentication**: Required (Bearer token)
**Authorization**: User owns the template or template is shared

## Purpose
Manages command templates for quick reuse of frequently used commands with pre-filled parameters (FR-011).

## GET /api/commands/templates

### Request
- **Query Parameters**:
  - `shared` (boolean, optional): Include shared templates from other users
  - `command_name` (string, optional): Filter by command type

### Response (200 OK)
```json
{
  "templates": [
    {
      "id": "uuid",
      "name": "Monthly Invoice Template",
      "command_name": "create-invoice",
      "parameter_values": {
        "currency": "USD",
        "tax_rate": 0.08,
        "due_date": "30"
      },
      "is_shared": false,
      "created_at": "2025-10-13T10:00:00Z",
      "updated_at": "2025-10-13T10:00:00Z"
    }
  ]
}
```

## POST /api/commands/templates

### Request Body
```json
{
  "name": "Quick Invoice",
  "command_name": "create-invoice",
  "parameter_values": {
    "customer_id": "uuid",
    "currency": "USD",
    "items": [...]
  },
  "is_shared": false
}
```

### Response (201 Created)
```json
{
  "id": "uuid",
  "name": "Quick Invoice",
  "command_name": "create-invoice",
  "parameter_values": { ... },
  "is_shared": false,
  "created_at": "2025-10-13T16:00:00Z"
}
```

## PUT /api/commands/templates/{id}

### Request Body
```json
{
  "name": "Updated Quick Invoice",
  "parameter_values": {
    "customer_id": "new-uuid",
    "currency": "EUR"
  },
  "is_shared": true
}
```

### Response (200 OK)
```json
{
  "id": "uuid",
  "name": "Updated Quick Invoice",
  "command_name": "create-invoice",
  "parameter_values": { ... },
  "is_shared": true,
  "updated_at": "2025-10-13T16:05:00Z"
}
```

## DELETE /api/commands/templates/{id}

### Response (204 No Content)

## Error Responses (All Endpoints)
- `400 Bad Request`: Invalid template data
- `401 Unauthorized`: Invalid authentication
- `403 Forbidden`: Template not owned by user and not shared
- `404 Not Found`: Template does not exist
- `422 Unprocessable Entity`: Invalid command name or parameters

## Business Rules
- Users can create personal templates
- Templates can be marked as shared for team use
- Shared templates visible to all company users (FR-013)
- Templates validated against command parameter schemas
- Template names must be unique per user

## Performance Requirements
- CRUD operations < 100ms
- Template listing with filtering < 200ms

## Testing Scenarios
- Create, read, update, delete templates
- Shared template visibility
- Parameter validation
- Permission checks
- Company isolation
