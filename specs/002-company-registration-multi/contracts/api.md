# API Contracts - Company Registration Multi-Company Creation

**Feature**: Company Registration - Multi-Company Creation  
**Date**: 2025-10-07  

## Company API Contract

### Create Company
```json
POST /api/companies
{
  "name": "string (required, max:255)",
  "currency": "string (required, 3-letter ISO code)",
  "timezone": "string (required, valid timezone)",
  "country": "string (optional, 2-letter ISO code)",
  "language": "string (optional, default: 'en')",
  "locale": "string (optional, default: 'en_US')"
}

Response 201:
{
  "data": {
    "id": "uuid",
    "name": "string",
    "slug": "string",
    "currency": "string",
    "timezone": "string",
    "country": "string|null",
    "language": "string",
    "locale": "string",
    "is_active": true,
    "created_at": "datetime",
    "updated_at": "datetime"
  },
  "meta": {
    "fiscal_year_created": true,
    "chart_of_accounts_created": true
  }
}
```

### Get Company
```json
GET /api/companies/{id}

Response 200:
{
  "data": {
    "id": "uuid",
    "name": "string",
    "slug": "string",
    "currency": "string",
    "timezone": "string",
    "country": "string|null",
    "language": "string",
    "locale": "string",
    "is_active": true,
    "created_at": "datetime",
    "updated_at": "datetime",
    "fiscal_year": {
      "id": "uuid",
      "name": "string",
      "start_date": "date",
      "end_date": "date",
      "is_current": true
    },
    "user_role": "owner|admin|accountant|viewer"
  }
}
```

### List User Companies
```json
GET /api/companies?include=current_role,fiscal_year

Response 200:
{
  "data": [
    {
      "id": "uuid",
      "name": "string",
      "slug": "string",
      "currency": "string",
      "is_active": true,
      "current_role": "owner",
      "fiscal_year": {
        "id": "uuid",
        "name": "string",
        "is_current": true
      }
    }
  ],
  "meta": {
    "total": 1,
    "current_company_id": "uuid"
  }
}
```

## Company User Management Contract

### Invite User to Company
```json
POST /api/companies/{company_id}/invitations
{
  "email": "string (required, email)",
  "role": "string (required, enum: owner|admin|accountant|viewer)",
  "expires_in_days": "integer (optional, default: 7)"
}

Response 201:
{
  "data": {
    "id": "uuid",
    "email": "string",
    "role": "string",
    "status": "pending",
    "expires_at": "datetime",
    "created_at": "datetime"
  }
}
```

### Accept Invitation
```json
POST /api/company-invitations/{token}/accept

Response 200:
{
  "data": {
    "id": "uuid",
    "company": {
      "id": "uuid",
      "name": "string",
      "slug": "string"
    },
    "user": {
      "id": "uuid",
      "name": "string",
      "email": "string"
    },
    "role": "string",
    "joined_at": "datetime"
  }
}
```

### Assign User to Company
```json
POST /api/companies/{company_id}/users
{
  "user_id": "uuid (required)",
  "role": "string (required, enum: owner|admin|accountant|viewer)"
}

Response 200:
{
  "data": {
    "company_id": "uuid",
    "user_id": "uuid",
    "role": "string",
    "created_at": "datetime"
  }
}
```

### Update User Role
```json
PUT /api/companies/{company_id}/users/{user_id}
{
  "role": "string (required, enum: owner|admin|accountant|viewer)"
}

Response 200:
{
  "data": {
    "company_id": "uuid",
    "user_id": "uuid",
    "role": "string",
    "updated_at": "datetime"
  }
}
```

### Remove User from Company
```json
DELETE /api/companies/{company_id}/users/{user_id}

Response 204: No Content
```

## Company Members Contract

### List Company Members
```json
GET /api/companies/{company_id}/members?include=invited_by

Response 200:
{
  "data": [
    {
      "id": "uuid",
      "user": {
        "id": "uuid",
        "name": "string",
        "email": "string"
      },
      "role": "string",
      "invited_by": {
        "id": "uuid",
        "name": "string"
      }|null,
      "joined_at": "datetime",
      "is_current_user": true
    }
  ],
  "meta": {
    "total": 1,
    "roles": ["owner", "admin", "accountant", "viewer"]
  }
}
```

## Fiscal Year Contract

### Create Fiscal Year
```json
POST /api/companies/{company_id}/fiscal-years
{
  "name": "string (required)",
  "start_date": "date (required)",
  "end_date": "date, required, after:start_date)",
  "create_periods": "boolean (optional, default: true)"
}

Response 201:
{
  "data": {
    "id": "uuid",
    "name": "string",
    "start_date": "date",
    "end_date": "date",
    "is_current": true,
    "is_locked": false,
    "periods_created": 12,
    "created_at": "datetime"
  }
}
```

### List Fiscal Years
```json
GET /api/companies/{company_id}/fiscal-years

Response 200:
{
  "data": [
    {
      "id": "uuid",
      "name": "string",
      "start_date": "date",
      "end_date": "date",
      "is_current": true,
      "is_locked": false,
      "periods_count": 12,
      "created_at": "datetime"
    }
  ]
}
```

## Chart of Accounts Contract

### Create Chart of Accounts
```json
POST /api/companies/{company_id}/charts-of-accounts
{
  "name": "string (required)",
  "description": "string (optional)",
  "template": "string (optional, enum: basic|detailed|industry_specific)"
}

Response 201:
{
  "data": {
    "id": "uuid",
    "name": "string",
    "description": "string|null",
    "is_template": false,
    "is_active": true,
    "accounts_count": 25,
    "created_at": "datetime"
  }
}
```

### Get Chart of Accounts
```json
GET /api/companies/{company_id}/charts-of-accounts/{id}?include=accounts,groups

Response 200:
{
  "data": {
    "id": "uuid",
    "name": "string",
    "description": "string|null",
    "accounts": [
      {
        "id": "uuid",
        "code": "string",
        "name": "string",
        "type": "string",
        "is_active": true,
        "parent_account_id": "uuid|null"
      }
    ],
    "groups": [
      {
        "id": "uuid",
        "name": "string",
        "account_type": "string",
        "accounts_count": 5
      }
    ]
  }
}
```

## Company Context Switching Contract

### Switch Active Company
```json
POST /api/company-context/switch
{
  "company_id": "uuid (required)"
}

Response 200:
{
  "data": {
    "company": {
      "id": "uuid",
      "name": "string",
      "slug": "string",
      "currency": "string"
    },
    "user_role": "owner",
    "fiscal_year": {
      "id": "uuid",
      "name": "string"
    },
    "switched_at": "datetime"
  }
}
```

### Get Current Context
```json
GET /api/company-context/current

Response 200:
{
  "data": {
    "company": {
      "id": "uuid",
      "name": "string",
      "slug": "string",
      "currency": "string"
    }|null,
    "user_role": "string|null",
    "fiscal_year": {
      "id": "uuid",
      "name": "string",
      "is_current": true
    }|null,
    "available_companies": [
      {
        "id": "uuid",
        "name": "string",
        "slug": "string",
        "role": "string"
      }
    ]
  }
}
```

## Error Responses

### Validation Errors (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name field is required."],
    "currency": ["The currency must be a valid 3-letter ISO code."]
  }
}
```

### Authorization Errors (403)
```json
{
  "message": "You do not have permission to perform this action.",
  "error": "authorization_required",
  "required_permission": "companies.create"
}
```

### Not Found Errors (404)
```json
{
  "message": "Company not found.",
  "error": "resource_not_found"
}
```

### Business Logic Errors (422)
```json
{
  "message": "User already has a role in this company.",
  "error": "user_already_assigned"
}
```

## Pagination

List endpoints support standard Laravel pagination:
```json
{
  "data": [...],
  "links": {
    "first": "...",
    "last": "...",
    "prev": "...",
    "next": "..."
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 15,
    "to": 5,
    "total": 5
  }
}
```

## Rate Limiting

Company creation and invitation endpoints are rate limited:
- Company creation: 5 per hour per user
- User invitations: 20 per hour per user
- Role assignments: 50 per hour per user

## Idempotency

All POST/PUT/DELETE requests support idempotency keys:
```json
POST /api/companies
Idempotency-Key: uuid-v4
```