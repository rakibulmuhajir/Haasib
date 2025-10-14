# Company Request Journeys

This document outlines the complete request journeys for company operations in the application.

## Table of Contents

1. [Company Creation Journey](#company-creation-journey)
2. [Company Activation Journey](#company-activation-journey)
3. [Company Deactivation Journey](#company-deactivation-journey)
4. [Company Deletion Journey](#company-deletion-journey)
5. [Company Switching Journey](#company-switching-journey)
6. [User Invitation Journey](#user-invitation-journey)

---

## Company Creation Journey

### Flow Diagram
```
[User] → [Login] → [Navigate to Admin] → [Companies] → [Create Company] → [Fill Form] → [Submit] → [Success]
```

### Request Sequence

1. **Authentication Check**
   - User must be authenticated
   - User must have SuperAdmin role
   - Route: `GET /admin/companies/create`

2. **Form Submission**
   - Method: `POST`
   - Endpoint: `/companies` (via CommandController)
   - Validation: `CompanyStoreRequest`
   - Required fields: `name`, `base_currency`

3. **Processing Flow**
   ```php
   CompanyController@store()
   ├── Validate request data
   ├── Create company record
   ├── Attach creator as owner
   ├── Set currency relationship
   └── Return JSON response
   ```

### Request Payload
```json
{
  "command": "company.create",
  "payload": {
    "name": "Acme Corporation",
    "base_currency": "USD",
    "language": "en",
    "locale": "en-US",
    "settings": {
      "timezone": "UTC",
      "fiscal_year_start": "01-01"
    }
  }
}
```

### Response
```json
{
  "data": {
    "id": "uuid",
    "name": "Acme Corporation",
    "slug": "acme-corporation",
    "base_currency": "USD",
    "language": "en",
    "locale": "en-US"
  }
}
```

---

## Company Activation Journey

### Flow Diagram
```
[SuperAdmin] → [Companies List] → [Select Inactive Company] → [Activate] → [Confirm] → [Success]
```

### Request Sequence

1. **Authorization Check**
   - User must be SuperAdmin
   - Company must exist and be inactive

2. **Activation Request**
   - Method: `PATCH`
   - Endpoint: `/web/companies/{company}/activate`
   - Controller: `CompanyController@activate`

3. **Processing Flow**
   ```php
   CompanyController@activate()
   ├── Verify SuperAdmin permissions
   ├── Find company (by slug or UUID)
   ├── Execute company->activate()
   └── Return success response
   ```

### Response
```json
{
  "message": "Company activated successfully"
}
```

---

## Company Deactivation Journey

### Flow Diagram
```
[SuperAdmin] → [Companies List] → [Select Active Company] → [Deactivate] → [Confirm] → [Success]
```

### Request Sequence

1. **Authorization Check**
   - User must be SuperAdmin
   - Company must exist and be active

2. **Deactivation Request**
   - Method: `PATCH`
   - Endpoint: `/web/companies/{company}/deactivate`
   - Controller: `CompanyController@deactivate`

3. **Processing Flow**
   ```php
   CompanyController@deactivate()
   ├── Verify SuperAdmin permissions
   ├── Find company (by slug or UUID)
   ├── Execute company->deactivate()
   └── Return success response
   ```

### Response
```json
{
  "message": "Company deactivated successfully"
}
```

---

## Company Deletion Journey

### Flow Diagram
```
[SuperAdmin] → [Companies List] → [Select Company] → [Delete] → [Confirm] → [Success]
```

### Request Sequence

1. **Authorization Check**
   - User must be SuperAdmin
   - Company must exist

2. **Deletion Request**
   - Method: `DELETE`
   - Endpoint: `/web/companies/{company}`
   - Controller: `CompanyController@destroy`

3. **Processing Flow**
   ```php
   CompanyController@destroy()
   ├── Verify SuperAdmin permissions
   ├── Find company (by slug or UUID)
   ├── Execute soft delete
   └── Return success response
   ```

### Response
```json
{
  "message": "Company deleted successfully"
}
```

---

## Company Switching Journey

### Flow Diagram
```
[User] → [Company Selector] → [Select Company] → [Switch Request] → [Update Session] → [Redirect]
```

### Request Sequence

1. **Company Selection**
   - User selects company from dropdown/selector
   - Must have access to selected company

2. **Switch Request**
   - Method: `POST`
   - Endpoint: `/company/{company}/switch`
   - Controller: `CompanySwitchController@switch`

3. **Processing Flow**
   ```php
   CompanySwitchController@switch()
   ├── Verify user has access to company
   ├── Update session with new company ID
   ├── Update request context
   └── Redirect to dashboard
   ```

---

## User Invitation Journey

### Flow Diagram
```
[Company Owner] → [Company Users] → [Invite User] → [Fill Email/Role] → [Send Invitation] → [Email Sent]
```

### Request Sequence

1. **Authorization Check**
   - User must have permission to invite users
   - Must be company owner or admin

2. **Invitation Request**
   - Method: `POST`
   - Endpoint: `/companies/{company}/invite`
   - Controller: `CompanyController@invite`
   - Validation: `CompanyInviteRequest`

3. **Processing Flow**
   ```php
   CompanyController@invite()
   ├── Validate request data
   ├── Check if user exists
   ├── Create or update invitation
   ├── Send invitation email
   └── Return response
   ```

### Request Payload
```json
{
  "email": "user@example.com",
  "role": "admin",
  "message": "Join our team!"
}
```

---

## Error Handling

### Common Error Responses

1. **Validation Errors** (422)
   ```json
   {
     "message": "The given data was invalid.",
     "errors": {
       "name": ["The name field is required."]
     }
   }
   ```

2. **Authorization Errors** (403)
   ```json
   {
     "message": "This action is unauthorized."
   }
   ```

3. **Not Found Errors** (404)
   ```json
   {
     "message": "Company not found."
     }
   ```

---

## Testing

All company operations are tested through:
- E2E Playwright tests (`tests/e2e/company-management.spec.ts`)
- PHP feature tests (`tests/Feature/CompanyTest.php`)
- API integration tests

---

## Best Practices

1. Always check user permissions before company operations
2. Use UUIDs for company identification in URLs
3. Implement soft deletes for company records
4. Log all company management actions
5. Send notifications for important company changes