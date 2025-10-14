# Request Journey: User Company Assignment

This document outlines the step-by-step process of assigning a user to a company with a specific role.

## Flow Diagram

```
[SuperAdmin/Company Owner] -> Admin/Users/Index.vue -> POST /commands -> CompanyAssign Action -> Company-User Relationship -> Database
```

---

### 1. User Interaction (Frontend)

-   **Files:** 
    -   Admin Users List: `resources/js/Pages/Admin/Users/Index.vue`
    -   Company User Management: `resources/js/Pages/Admin/Companies/Users.vue`
-   **Action:** An authorized user (SuperAdmin or Company Owner) selects a user and company to assign.

### 2. Client-Side Logic (Frontend)

-   **Action:** The assignment form is submitted with user, company, and role information.
-   **HTTP Request:** Sends a `POST` request to `/commands`.
    -   **Payload:**
        ```json
        {
            "email": "user@example.com",
            "company": "company-uuid-or-slug",
            "role": "admin"
        }
        ```
    -   **Headers:**
        -   `Accept: application/json`
        -   `X-Action: company.assign`
        -   `X-CSRF-TOKEN`: Laravel CSRF token
        -   `Content-Type: application/json`

### 3. API Routing (Backend)

-   **File:** `routes/web.php`
-   **Route:** `Route::post('/commands', [CommandController::class, 'execute']);`
-   **Middleware:** `auth`, `verified`

### 4. Command Controller (Backend)

-   **File:** `app/Http/Controllers/CommandController.php`
-   **Method:** `execute()`
-   **Action:**
    1.  Reads `X-Action: company.assign` header
    2.  Validates command format
    3.  Dispatches to `CompanyAssign` action class

### 5. Authorization & Validation (Backend)

-   **File:** `app/Actions/DevOps/CompanyAssign.php`
-   **Method:** `handle()`
-   **Validation Rules:**
    -   `email`: required|email
    -   `company`: required|string
    -   `role`: required|in:owner,admin,accountant,viewer

### 6. Business Logic (Backend)

-   **File:** `app/Actions/DevOps/CompanyAssign.php`
-   **Method:** `handle()`
-   **Action (within DB transaction):**
    1.  Validate input data
    2.  Find company by UUID, slug, or name
    3.  Find user by email
    4.  Check permissions:
        -   SuperAdmins can assign to any company
        -   Others must be company owner
    5.  Verify user not already assigned
    6.  Create company-user relationship

### 7. Database Interaction (Backend)

-   **Tables:** `company_user` (pivot table)
-   **Operation:** INSERT relationship record
-   **Fields:**
    -   `company_id`: UUID
    -   `user_id`: UUID
    -   `role`: string (owner, admin, accountant, viewer)
    -   `created_at`, `updated_at`: Timestamps
    -   `invited_by_user_id`: ID of user who made assignment

### 8. Permission Checks (Backend)

-   **SuperAdmin Check:** `$actor->isSuperAdmin()`
-   **Company Owner Check:**
    ```php
    $this->lookup->userHasRole($company->id, $actor->id, ['owner'])
    ```
-   **Current Company Check:** Non-superadmins must be in the company they're assigning to

### 9. HTTP Response (Backend)

-   **Success Response (200):**
    ```json
    {
        "message": "User assigned",
        "data": {
            "id": "user-uuid",
            "name": "John Doe",
            "email": "john@example.com",
            "role": "admin"
        }
    }
    ```

-   **Validation Error (422):**
    ```json
    {
        "message": "The given data was invalid.",
        "errors": {
            "email": ["User not found"],
            "company": ["Company not found"]
        }
    }
    ```

-   **Authorization Error (403):**
    ```json
    {
        "message": "This action is unauthorized."
    }
    ```

-   **Conflict Error (422):**
    ```json
    {
        "message": "User is already assigned to this company"
    }
    ```

### 10. Response Handling (Frontend)

-   **On Success:** Shows success toast and updates user list
-   **On Error:** Shows error toast with specific message
-   **UI Updates:** Refreshes company user list, shows new assignment

---

## Key Implementation Details

### Role Hierarchy
- **owner**: Full control over company, can manage users
- **admin**: Can manage company data but not users
- **accountant**: Access to financial features
- **viewer**: Read-only access

### Authorization Rules
1. **SuperAdmins**: Can assign any user to any company
2. **Company Owners**: Can assign users to their own company
3. **Other Roles**: Cannot assign users

### Company Lookup
The action looks up companies by:
1. UUID (exact match)
2. Slug (exact match)
3. Name (partial match via OR condition)

### Transaction Safety
- All operations wrapped in `DB::transaction()`
- Ensures data consistency
- Rolls back on any failure

### Duplicate Prevention
- Checks if user already assigned to company
- Returns 422 error if duplicate
- Uses `syncWithoutDetaching()` to prevent duplicates

### Audit Trail
- `invited_by_user_id` tracks who made the assignment
- Timestamps record when assignment occurred
- Useful for compliance and debugging

### Side Effects
- User gains access to company resources
- User can switch to assigned company
- Company appears in user's company list
- User inherits company-specific permissions

### Email Notifications
Consider sending:
- Welcome to company email
- Role assignment confirmation
- Access instructions for new company

### Error Scenarios Handled
- User not found
- Company not found
- Insufficient permissions
- User already assigned
- Invalid role specified
- Database errors