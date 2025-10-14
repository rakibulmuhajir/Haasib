# User Request Journeys

This document outlines the complete request journeys for user operations in the application.

## Table of Contents

1. [User Creation/Registration Journey](#user-creationregistration-journey)
2. [User Activation Journey](#user-activation-journey)
3. [User Deactivation Journey](#user-deactivation-journey)
4. [User Deletion Journey](#user-deletion-journey)
5. [User Company Assignment Journey](#user-company-assignment-journey)
6. [User Company Unassignment Journey](#user-company-unassignment-journey)

---

## User Creation/Registration Journey

### Flow Diagram
```
[User] → [Register Page] → [Fill Form] → [Submit] → [Create User] → [Auto-Login] → [Dashboard]
```

### Request Sequence

1. **Registration Form Access**
   - Method: `GET`
   - Endpoint: `/register`
   - Controller: `RegisteredUserController@create`

2. **Registration Submission**
   - Method: `POST`
   - Endpoint: `/register`
   - Controller: `RegisteredUserController@store`
   - Validation: Built-in Laravel validation

3. **Processing Flow**
   ```php
   RegisteredUserController@store()
   ├── Validate user data
   ├── Create user record
   ├── Log user in automatically
   └── Redirect to dashboard
   ```

### Request Payload
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

---

## User Activation Journey

Note: User activation in this context refers to email verification, not manual activation like companies.

### Flow Diagram
```
[User] → [Registration] → [Email Sent] → [Click Link] → [Verify Email] → [Activated]
```

### Request Sequence

1. **Email Verification Request**
   - Method: `GET`
   - Endpoint: `/verify-email/{id}/{hash}`
   - Middleware: `signed`, `throttle:6,1`
   - Controller: `VerifyEmailController`

2. **Resend Verification Email**
   - Method: `POST`
   - Endpoint: `/email/verification-notification`
   - Middleware: `auth`, `throttle:6,1`
   - Controller: `EmailVerificationNotificationController`

---

## User Deactivation Journey

Note: Users don't have a direct deactivation field like companies. User deactivation is achieved by:

1. Removing all company assignments
2. Changing system_role (if applicable)
3. Blocking login (if using a blocked status)

### Flow Diagram
```
[SuperAdmin] → [Admin/Users] → [Select User] → [Remove Companies] → [Change Role] → [Deactivated]
```

---

## User Deletion Journey

### Flow Diagram
```
[SuperAdmin] → [Admin/Users] → [Select User] → [Delete] → [Confirm] → [Soft Delete]
```

### Request Sequence

Note: User deletion would typically be implemented with soft deletes, similar to companies.

---

## User Company Assignment Journey

### Flow Diagram
```
[SuperAdmin/Company Owner] → [Admin/Users] → [Select User] → [Assign Company] → [Set Role] → [Success]
```

### Request Sequence

1. **Assignment Request**
   - Method: `POST`
   - Endpoint: `/commands` (with X-Action header)
   - Action: `company.assign`
   - Controller: `CommandController`
   - Action Class: `CompanyAssign`

2. **Processing Flow**
   ```php
   CompanyAssign@handle()
   ├── Validate email, company, and role
   ├── Find user by email
   ├── Verify permissions
   ├── Assign user to company
   └── Return success response
   ```

### Request Payload
```json
{
  "email": "user@example.com",
  "company": "company-uuid-or-slug",
  "role": "admin"
}
```

---

## User Company Unassignment Journey

### Flow Diagram
```
[SuperAdmin/Company Owner] → [Admin/Users] → [Select User] → [Unassign Company] → [Confirm] → [Success]
```

### Request Sequence

1. **Unassignment Request**
   - Method: `POST`
   - Endpoint: `/commands` (with X-Action header)
   - Action: `company.unassign`
   - Controller: `CommandController`
   - Action Class: `CompanyUnassign`

2. **Processing Flow**
   ```php
   CompanyUnassign@handle()
   ├── Validate email and company
   ├── Find user by email
   ├── Verify permissions
   ├── Remove user from company
   └── Return success response
   ```

---

## Key Implementation Details

### User Roles
- **superadmin**: System-wide administrator
- **owner**: Company owner (can manage company users)
- **admin**: Company administrator
- **accountant**: Accounting permissions
- **viewer**: Read-only access

### Authorization Rules
1. **Company Assignment/Unassignment**:
   - SuperAdmins can assign to any company
   - Company owners can only assign to their own company
   - Must have appropriate role

2. **User Creation**:
   - Public registration enabled by default
   - SuperAdmins can create users via admin panel

### Security Considerations
- All user operations require authentication (except registration)
- Email verification for new users
- Role-based access control
- Audit logging for all assignment changes

### Error Handling
- Validation errors return 422 status
- Authorization errors return 403 status
- Not found errors return 404 status