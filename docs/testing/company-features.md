# Manual Testing Scenarios - Company Registration Multi-Company

## Overview

This document provides comprehensive manual testing scenarios for the company registration and multi-company creation features. Test scenarios cover API endpoints, CLI commands, web interface, and edge cases.

## Test Environment Setup

### Prerequisites

1. **Database Setup**:
   ```bash
   php artisan migrate:fresh --seed
   php artisan db:seed --class=CompanyDemoSeeder
   ```

2. **Authentication**:
   ```bash
   # Login as admin user
   php artisan tinker
   $user = App\Models\User::where('email', 'admin@demo.com')->first();
   $token = $user->createToken('test-token')->plainTextToken;
   ```

3. **Test Users**:
   ```
   admin@demo.com / password     (Super Admin)
   ahmed@demo.com / password     (Owner of Tech Solutions Arabia)
   sarah@demo.com / password     (Owner of Global Trading Co.)
   khalid@demo.com / password    (Owner of Riyadh Manufacturing)
   fatima@demo.com / password    (Owner of Digital Agency KSA)
   john@demo.com / password      (Owner of Gulf Logistics LLC)
   ```

## API Testing Scenarios

### 1. Company CRUD Operations

#### 1.1 Create Company (Happy Path)

**Request:**
```bash
curl -X POST http://localhost:8000/api/companies \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Company Ltd",
    "base_currency": "SAR",
    "country": "SA",
    "timezone": "Asia/Riyadh",
    "language": "en",
    "locale": "en_US"
  }'
```

**Expected Response:**
- Status: 201 Created
- Company object with all fields
- Meta information about fiscal year and chart of accounts creation

**Validation Points:**
- [ ] Company is created in database
- [ ] Company slug is auto-generated correctly
- [ ] Fiscal year is created for the company
- [ ] Chart of accounts is created
- [ ] Creator is automatically added as owner

#### 1.2 Create Company - Validation Errors

**Test Cases:**
```bash
# Missing required fields
curl -X POST http://localhost:8000/api/companies \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"name": ""}'

# Invalid currency code
curl -X POST http://localhost:8000/api/companies \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Company",
    "base_currency": "INVALID"
  }'

# Duplicate company name
curl -X POST http://localhost:8000/api/companies \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Tech Solutions Arabia",
    "base_currency": "SAR"
  }'
```

**Expected Response:**
- Status: 422 Unprocessable Entity
- Error messages for invalid fields
- Field-specific validation errors

#### 1.3 List Companies

**Request:**
```bash
# Basic list
curl -X GET http://localhost:8000/api/companies \
  -H "Authorization: Bearer {token}"

# With filters
curl -X GET "http://localhost:8000/api/companies?country=SA&is_active=true" \
  -H "Authorization: Bearer {token}"

# With search
curl -X GET "http://localhost:8000/api/companies?search=Solutions" \
  -H "Authorization: Bearer {token}"

# Paginated
curl -X GET "http://localhost:8000/api/companies?page=1&per_page=5" \
  -H "Authorization: Bearer {token}"
```

**Validation Points:**
- [ ] Only user's accessible companies are returned
- [ ] Filters work correctly
- [ ] Search returns relevant results
- [ ] Pagination works properly
- [ ] Response includes metadata

#### 1.4 Get Company Details

**Request:**
```bash
curl -X GET http://localhost:8000/api/companies/{company_id} \
  -H "Authorization: Bearer {token}"
```

**Validation Points:**
- [ ] Company details are complete
- [ ] Related data (users, fiscal years) is included
- [ ] Access control enforced (can't access others' companies)

#### 1.5 Update Company

**Request:**
```bash
curl -X PUT http://localhost:8000/api/companies/{company_id} \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Updated Company Name",
    "settings": {
      "features": ["accounting", "reporting"]
    }
  }'
```

**Validation Points:**
- [ ] Only accessible fields can be updated
- [ ] Validation rules apply
- [ ] Owner and admin can update

#### 1.6 Delete Company

**Request:**
```bash
curl -X DELETE http://localhost:8000/api/companies/{company_id} \
  -H "Authorization: Bearer {token}"
```

**Validation Points:**
- [ ] Only owner can delete
- [ ] Soft delete is applied
- [ ] Company is marked as inactive

### 2. Company Invitations

#### 2.1 Send Invitation

**Request:**
```bash
curl -X POST http://localhost:8000/api/companies/{company_id}/invitations \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "newuser@example.com",
    "role": "admin",
    "message": "Welcome to our team!",
    "expires_in_days": 7
  }'
```

**Validation Points:**
- [ ] Invitation is created
- [ ] Token is generated
- [ ] Email validation works
- [ ] Role validation works
- [ ] Duplicate invitation prevention

#### 2.2 List Invitations

**Request:**
```bash
curl -X GET http://localhost:8000/api/companies/{company_id}/invitations \
  -H "Authorization: Bearer {token}"

# With filters
curl -X GET "http://localhost:8000/api/companies/{company_id}/invitations?status=pending" \
  -H "Authorization: Bearer {token}"
```

#### 2.3 Accept/Reject Invitation

**Test through Web Interface:**
1. Visit invitation URL: `http://localhost:8000/invitations/{token}`
2. Test acceptance flow
3. Test rejection flow
4. Test expired invitation handling

### 3. Company Context Management

#### 3.1 Switch Company Context

**Request:**
```bash
curl -X POST http://localhost:8000/api/company-context/switch \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "company_id": "{company_id}"
  }'
```

**Validation Points:**
- [ ] Context is switched
- [ ] User permissions are updated
- [ ] Current company session is set

#### 3.2 Get Current Context

**Request:**
```bash
curl -X GET http://localhost:8000/api/company-context/current \
  -H "Authorization: Bearer {token}"
```

**Validation Points:**
- [ ] Current company is returned
- [ ] User role is included
- [ ] Available companies are listed

### 4. Company Members Management

#### 4.1 List Members

**Request:**
```bash
curl -X GET http://localhost:8000/api/companies/{company_id}/members \
  -H "Authorization: Bearer {token}"
```

#### 4.2 Update Member Role

**Request:**
```bash
curl -X PUT http://localhost:8000/api/companies/{company_id}/members/{user_id}/role \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "role": "admin"
  }'
```

#### 4.3 Remove Member

**Request:**
```bash
curl -X DELETE http://localhost:8000/api/companies/{company_id}/members/{user_id} \
  -H "Authorization: Bearer {token}"
```

## CLI Testing Scenarios

### 1. Company Management Commands

#### 1.1 Create Company

**Interactive Mode:**
```bash
php artisan company:create --interactive
```

**Validation Points:**
- [ ] Interactive prompts work
- [ ] Required fields are validated
- [ ] Company is created successfully
- [ ] User is added as owner

**Non-Interactive Mode:**
```bash
php artisan company:create \
  --name="CLI Test Company" \
  --currency="SAR" \
  --country="SA"
```

#### 1.2 List Companies

```bash
php artisan company:list
php artisan company:list --all
php artisan company:list --format=json
php artisan company:list --country=SA
```

#### 1.3 Show Company Details

```bash
php artisan company:show {company_id}
php artisan company:show {company_id} --with-users
php artisan company:show {company_id} --format=json
```

#### 1.4 Update Company

```bash
php artisan company:update {company_id} --interactive
php artisan company:update {company_id} --name="Updated Name"
```

#### 1.5 Delete Company

```bash
php artisan company:delete {company_id}
php artisan company:delete {company_id} --force
```

### 2. Invitation Commands

#### 2.1 Send Invitation

```bash
php artisan company:invite {company_id} newuser@example.com
php artisan company:invite {company_id} newuser@example.com --role=admin
php artisan company:invite {company_id} newuser@example.com --message="Welcome!"
```

#### 2.2 List Invitations

```bash
php artisan company:invitations {company_id}
php artisan company:invitations {company_id} --status=pending
```

### 3. Context Management

#### 3.1 Switch Company

```bash
php artisan company:switch {company_id}
php artisan company:switch {company_id} --global
```

## Web Interface Testing Scenarios

### 1. Company Registration Flow

#### 1.1 Access Company Creation

**Steps:**
1. Login as user
2. Navigate to `/companies/create`
3. Fill in company details
4. Submit form

**Validation Points:**
- [ ] Form validation works client-side
- [ ] Required fields are enforced
- [ ] Company is created successfully
- [ ] User is redirected to company dashboard
- [ ] User is automatically set as company owner

#### 1.2 Company Dashboard Access

**Steps:**
1. Login as user with companies
2. Navigate to `/companies`
3. Click on company name

**Validation Points:**
- [ ] Company details are displayed
- [ ] Navigation shows company context
- [ ] User role is displayed
- [ ] Company actions are available based on permissions

### 2. Company Management

#### 2.1 Edit Company Information

**Steps:**
1. Go to company settings
2. Update company details
3. Save changes

**Validation Points:**
- [ ] Form loads with current data
- [ ] Validation works
- [ ] Changes are saved
- [ ] Success message is shown

#### 2.2 Manage Company Members

**Steps:**
1. Go to company members section
2. Invite new member
3. Update member roles
4. Remove member

**Validation Points:**
- [ ] Member list displays correctly
- [ ] Invitation form works
- [ ] Role updates work
- [ ] Member removal works
- [ ] Permission checks are enforced

### 3. Company Switching

#### 3.1 Context Switcher

**Steps:**
1. Access context switcher component
2. Select different company
3. Verify context change

**Validation Points:**
- [ ] Context switcher shows accessible companies
- [ ] Switch works correctly
- [ ] UI updates to reflect new context
- [ ] Navigation updates
- [ ] Permissions are updated

## Edge Cases and Error Handling

### 1. Permission Tests

#### 1.1 Unauthorized Access

**Test Cases:**
- [ ] User tries to access another user's company
- [ ] User without proper role tries to perform restricted actions
- [ ] Expired token access attempts

#### 1.2 Role-Based Access Control

**Test Cases:**
- [ ] Viewer cannot edit company
- [ ] Employee cannot invite users
- [ ] Admin cannot delete company
- [ ] Owner can perform all actions

### 2. Data Integrity Tests

#### 2.1 Concurrent Operations

**Test Cases:**
- [ ] Multiple users trying to update same company
- [ ] Simultaneous invitations to same email
- [ ] Company deletion while users are accessing it

#### 2.2 Data Validation

**Test Cases:**
- [ ] Invalid currency codes
- [ ] Invalid timezone values
- [ ] Invalid email formats
- [ ] SQL injection attempts
- [ ] XSS protection

### 3. Performance Tests

#### 3.1 Large Dataset Handling

**Test Cases:**
- [ ] Company listing with 1000+ companies
- [ ] Company member list with 1000+ members
- [ ] Search performance with large datasets
- [ ] Pagination behavior

#### 3.2 Load Testing

**Test Cases:**
- [ ] Concurrent API requests
- [ ] Multiple simultaneous context switches
- [ ] Bulk invitation operations

## Security Testing

### 1. Authentication & Authorization

#### 1.1 Token Validation

**Test Cases:**
- [ ] Invalid/expired tokens
- [ ] Token refresh scenarios
- [ ] Cross-origin requests

#### 1.2 Permission Bypass Attempts

**Test Cases:**
- [ ] Direct API calls without proper permissions
- [ ] Parameter tampering
- [ ] IDOR (Insecure Direct Object Reference) attempts

### 2. Input Validation

#### 2.1 Malicious Input

**Test Cases:**
- [ ] SQL injection payloads
- [ ] XSS payloads
- [ ] CSRF tokens validation
- [ ] File upload restrictions (if applicable)

### 3. Data Exposure

#### 2.1 Information Disclosure

**Test Cases:**
- [ ] Sensitive data in API responses
- [ ] Error message information leakage
- [ ] Debug information exposure

## Cross-Browser Testing

### 1. Supported Browsers

**Test Matrix:**
- [ ] Chrome (Latest)
- [ ] Firefox (Latest)
- [ ] Safari (Latest)
- [ ] Edge (Latest)

### 2. Mobile Responsiveness

**Test Cases:**
- [ ] Mobile phone viewport
- [ ] Tablet viewport
- [ ] Touch interactions
- [ ] Responsive navigation

## Accessibility Testing

### 1. Screen Reader Support

**Test Cases:**
- [ ] Semantic HTML structure
- [ ] ARIA labels and descriptions
- [ ] Keyboard navigation
- [ ] Focus management

### 2. Visual Accessibility

**Test Cases:**
- [ ] Color contrast ratios
- [ ] Text resizing
- [ ] High contrast mode
- [ ] Reduced motion preferences

## Performance Monitoring

### 1. Response Time Monitoring

**Test Cases:**
- [ ] API response times < 200ms
- [ ] Page load times < 3s
- [ ] Database query performance
- [ ] Memory usage monitoring

### 2. Error Monitoring

**Test Cases:**
- [ ] Error logging completeness
- [ ] Exception handling coverage
- [ ] User error reporting
- [ ] System health monitoring

## Test Data Cleanup

### 1. Test Environment Reset

**Commands:**
```bash
# Clean database
php artisan migrate:fresh

# Re-seed demo data
php artisan db:seed --class=CompanyDemoSeeder

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### 2. Test Artifacts

**Cleanup Steps:**
- [ ] Remove test companies created during testing
- [ ] Revoke test invitations
- [ ] Reset user permissions if modified
- [ ] Clear session data

## Reporting Template

### Test Execution Report

**Test Suite:** {Test Suite Name}
**Date:** {Date}
**Tester:** {Tester Name}
**Environment:** {Environment Details}

**Test Cases Executed:** {Number}
**Passed:** {Number}
**Failed:** {Number}
**Blocked:** {Number}

**Critical Issues:**
- [ ] List of critical failures

**Recommendations:**
- [ ] Performance improvements
- [ ] UI/UX enhancements
- [ ] Security improvements

**Sign-off:**
- [ ] Tester signature
- [ ] Date
- [ ] Test environment details