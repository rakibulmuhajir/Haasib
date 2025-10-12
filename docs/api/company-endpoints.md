# Company API Documentation

## Overview

The Company API provides endpoints for managing multi-company operations including company creation, user invitations, and context switching. All endpoints require authentication and proper RBAC permissions.

## Base URL
```
https://api.haasib.com/api
```

## Authentication
All endpoints require a valid Bearer token:
```
Authorization: Bearer {access_token}
```

## Endpoints

### Companies

#### List Companies
```http
GET /api/companies
```

**Query Parameters:**
- `search` (string, optional) - Search by company name
- `country` (string, optional) - Filter by 2-letter ISO country code
- `is_active` (boolean, optional) - Filter by active status
- `sort` (string, optional) - Sort field (name, created_at, updated_at)
- `order` (string, optional) - Sort direction (asc, desc)
- `page` (integer, optional) - Page number (default: 1)
- `per_page` (integer, optional) - Items per page (default: 15, max: 100)

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "string",
      "slug": "string",
      "country": "string|null",
      "base_currency": "string",
      "currency_id": "uuid|null",
      "timezone": "string|null",
      "language": "string",
      "locale": "string",
      "is_active": boolean,
      "created_at": "datetime",
      "updated_at": "datetime",
      "users_count": integer,
      "current_fiscal_year": {
        "id": "uuid",
        "name": "string",
        "start_date": "date",
        "end_date": "date",
        "is_current": boolean
      }
    }
  ],
  "links": {
    "first": "string",
    "last": "string",
    "prev": "string|null",
    "next": "string|null"
  },
  "meta": {
    "current_page": integer,
    "from": integer,
    "last_page": integer,
    "per_page": integer,
    "to": integer,
    "total": integer
  }
}
```

#### Create Company
```http
POST /api/companies
```

**Request Body:**
```json
{
  "name": "string (required, max:255)",
  "slug": "string (optional, auto-generated if not provided)",
  "country": "string (optional, 2-letter ISO code)",
  "country_id": "uuid (optional, reference to countries table)",
  "base_currency": "string (required, 3-letter ISO code)",
  "currency_id": "uuid (optional, reference to currencies table)",
  "timezone": "string (optional, valid timezone)",
  "language": "string (optional, default: 'en')",
  "locale": "string (optional, default: 'en_US')",
  "settings": "object (optional, default: {})"
}
```

**Response 201:**
```json
{
  "data": {
    "id": "uuid",
    "name": "string",
    "slug": "string",
    "country": "string|null",
    "country_id": "uuid|null",
    "base_currency": "string",
    "currency_id": "uuid|null",
    "timezone": "string|null",
    "language": "string",
    "locale": "string",
    "settings": "object",
    "is_active": true,
    "created_by_user_id": "uuid",
    "created_at": "datetime",
    "updated_at": "datetime"
  },
  "meta": {
    "fiscal_year_created": true,
    "chart_of_accounts_created": true,
    "default_user_assigned": true
  }
}
```

**Response 422:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name field is required."],
    "base_currency": ["The base currency must be a valid 3-letter ISO code."]
  }
}
```

#### Get Company
```http
GET /api/companies/{id}
```

**Response 200:**
```json
{
  "data": {
    "id": "uuid",
    "name": "string",
    "slug": "string",
    "country": "string|null",
    "country_id": "uuid|null",
    "base_currency": "string",
    "currency_id": "uuid|null",
    "timezone": "string|null",
    "language": "string",
    "locale": "string",
    "settings": "object",
    "is_active": boolean,
    "created_by_user_id": "uuid",
    "created_at": "datetime",
    "updated_at": "datetime",
    "users": [
      {
        "id": "uuid",
        "name": "string",
        "email": "string",
        "pivot": {
          "role": "owner|admin|accountant|manager|employee|viewer",
          "is_active": boolean,
          "joined_at": "datetime"
        }
      }
    ],
    "fiscal_years": [
      {
        "id": "uuid",
        "name": "string",
        "start_date": "date",
        "end_date": "date",
        "is_current": boolean,
        "is_locked": boolean
      }
    ],
    "chart_of_accounts": {
      "id": "uuid",
      "name": "string",
      "is_active": boolean,
      "accounts_count": integer
    }
  }
}
```

**Response 404:**
```json
{
  "message": "Company not found."
}
```

#### Update Company
```http
PUT /api/companies/{id}
```

**Request Body:**
```json
{
  "name": "string (optional, max:255)",
  "country": "string (optional, 2-letter ISO code)",
  "base_currency": "string (optional, 3-letter ISO code)",
  "timezone": "string (optional, valid timezone)",
  "language": "string (optional)",
  "locale": "string (optional)",
  "settings": "object (optional)",
  "is_active": "boolean (optional)"
}
```

**Response 200:**
```json
{
  "data": {
    "id": "uuid",
    "name": "string",
    // ... same structure as GET response
  }
}
```

#### Delete Company
```http
DELETE /api/companies/{id}
```

**Response 204:** No Content

**Response 403:**
```json
{
  "message": "You do not have permission to delete this company."
}
```

### Company Invitations

#### Send Invitation
```http
POST /api/companies/{company_id}/invitations
```

**Request Body:**
```json
{
  "email": "string (required, email)",
  "role": "string (required, enum: owner,admin,accountant,manager,employee,viewer)",
  "message": "string (optional, custom message for invitee)",
  "expires_in_days": "integer (optional, default: 7, max: 30)"
}
```

**Response 201:**
```json
{
  "data": {
    "id": "uuid",
    "company_id": "uuid",
    "email": "string",
    "role": "string",
    "token": "string",
    "invited_by_user_id": "uuid",
    "status": "pending",
    "expires_at": "datetime",
    "created_at": "datetime"
  },
  "meta": {
    "invitation_url": "https://app.haasib.com/invitations/{token}",
    "expires_in_hours": integer
  }
}
```

#### List Invitations
```http
GET /api/companies/{company_id}/invitations
```

**Query Parameters:**
- `status` (string, optional) - Filter by status (pending, accepted, rejected, expired)
- `role` (string, optional) - Filter by role
- `page` (integer, optional) - Page number
- `per_page` (integer, optional) - Items per page

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "email": "string",
      "role": "string",
      "status": "string",
      "invited_by": {
        "id": "uuid",
        "name": "string"
      },
      "accepted_by": {
        "id": "uuid",
        "name": "string"
      } | null,
      "expires_at": "datetime",
      "accepted_at": "datetime|null",
      "created_at": "datetime"
    }
  ],
  // ... pagination meta
}
```

#### Cancel Invitation
```http
DELETE /api/companies/{company_id}/invitations/{invitation_id}
```

**Response 204:** No Content

#### Resend Invitation
```http
POST /api/companies/{company_id}/invitations/{invitation_id}/resend
```

**Response 200:**
```json
{
  "message": "Invitation resent successfully.",
  "data": {
    "id": "uuid",
    "expires_at": "datetime"
  }
}
```

### Company Context

#### Switch Company Context
```http
POST /api/company-context/switch
```

**Request Body:**
```json
{
  "company_id": "uuid (required)"
}
```

**Response 200:**
```json
{
  "data": {
    "current_company": {
      "id": "uuid",
      "name": "string",
      "slug": "string",
      "user_role": "string",
      "permissions": ["string"]
    },
    "available_companies": [
      {
        "id": "uuid",
        "name": "string",
        "slug": "string",
        "user_role": "string",
        "is_current": boolean
      }
    ]
  }
}
```

#### Get Current Context
```http
GET /api/company-context/current
```

**Response 200:**
```json
{
  "data": {
    "current_company": {
      "id": "uuid",
      "name": "string",
      "slug": "string",
      "user_role": "string",
      "permissions": ["string"],
      "fiscal_year": {
        "id": "uuid",
        "name": "string",
        "start_date": "date",
        "end_date": "date"
      }
    } | null,
    "available_companies": [
      // ... same structure as switch response
    ]
  }
}
```

### Company Members

#### List Company Members
```http
GET /api/companies/{company_id}/members
```

**Query Parameters:**
- `role` (string, optional) - Filter by role
- `is_active` (boolean, optional) - Filter by active status
- `search` (string, optional) - Search by name or email

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "string",
      "email": "string",
      "pivot": {
        "role": "string",
        "is_active": boolean,
        "joined_at": "datetime",
        "invited_by": {
          "id": "uuid",
          "name": "string"
        }
      },
      "last_login_at": "datetime|null"
    }
  ]
}
```

#### Update Member Role
```http
PUT /api/companies/{company_id}/members/{user_id}/role
```

**Request Body:**
```json
{
  "role": "string (required, enum: owner,admin,accountant,manager,employee,viewer)"
}
```

**Response 200:**
```json
{
  "data": {
    "id": "uuid",
    "name": "string",
    "email": "string",
    "pivot": {
      "role": "string",
      "is_active": boolean,
      "updated_at": "datetime"
    }
  }
}
```

#### Remove Member
```http
DELETE /api/companies/{company_id}/members/{user_id}
```

**Response 204:** No Content

#### Toggle Member Status
```http
POST /api/companies/{company_id}/members/{user_id}/status
```

**Request Body:**
```json
{
  "is_active": "boolean (required)"
}
```

**Response 200:**
```json
{
  "data": {
    "id": "uuid",
    "is_active": boolean
  }
}
```

## Error Responses

### Standard Error Format
```json
{
  "message": "string",
  "errors": {
    "field": ["string"]
  } | null,
  "code": "string",
  "status": integer
}
```

### Common Error Codes
- `401` - Unauthorized (invalid or missing token)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found (resource doesn't exist)
- `422` - Validation Error (invalid request data)
- `429` - Too Many Requests (rate limit exceeded)
- `500` - Internal Server Error

## Rate Limiting

- **Standard endpoints**: 60 requests per minute
- **Bulk operations**: 10 requests per minute
- **Search endpoints**: 30 requests per minute

## Idempotency

POST and PUT requests support idempotency. Include the header:
```
Idempotency-Key: {unique_key}
```

## Webhooks

### Company Events
- `company.created` - Company created
- `company.updated` - Company updated
- `company.deleted` - Company deleted
- `company.user_added` - User added to company
- `company.user_removed` - User removed from company
- `company.invitation_sent` - Invitation sent
- `company.invitation_accepted` - Invitation accepted

## SDK Examples

### JavaScript/TypeScript
```javascript
// List companies
const companies = await haasib.companies.list({
  country: 'SA',
  is_active: true,
  page: 1
});

// Create company
const company = await haasib.companies.create({
  name: 'Tech Solutions',
  base_currency: 'SAR',
  country: 'SA',
  timezone: 'Asia/Riyadh'
});

// Switch context
await haasib.context.switch(company.id);
```

### PHP
```php
// List companies
$companies = Haasib::companies()->list([
    'country' => 'SA',
    'is_active' => true,
    'page' => 1
]);

// Create company
$company = Haasib::companies()->create([
    'name' => 'Tech Solutions',
    'base_currency' => 'SAR',
    'country' => 'SA',
    'timezone' => 'Asia/Riyadh'
]);

// Switch context
Haasib::context()->switch($company->id);
```

## Testing

### Testing Environment
Use the test environment API endpoint:
```
https://api-test.haasib.com/api
```

### Mock Data
Use the provided mock server for development:
```
https://api-mock.haasib.com/api
```