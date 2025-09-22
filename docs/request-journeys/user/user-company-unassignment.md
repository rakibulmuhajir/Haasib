# Request Journey: User Company Unassignment

This document outlines the step-by-step process of removing a user from a company.

## Flow Diagram

```
[SuperAdmin/Company Owner] -> Admin/Users/Index.vue -> POST /commands -> CompanyUnassign Action -> Remove Company-User Relationship -> Database
```

---

### 1. User Interaction (Frontend)

-   **Files:** 
    -   Admin Users List: `resources/js/Pages/Admin/Users/Index.vue`
    -   Company User Management: `resources/js/Pages/Admin/Companies/Users.vue`
    -   User Profile: `resources/js/Pages/Admin/Users/Show.vue`
-   **Action:** An authorized user clicks "Remove from Company" or "Unassign" button.

### 2. Client-Side Logic (Frontend)

-   **Action:** Confirmation modal appears, then unassignment request is sent.
-   **HTTP Request:** Sends a `POST` request to `/commands`.
    -   **Payload:**
        ```json
        {
            "email": "user@example.com",
            "company": "company-uuid-or-slug"
        }
        ```
    -   **Headers:**
        -   `Accept: application/json`
        -   `X-Action: company.unassign`
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
    1.  Reads `X-Action: company.unassign` header
    2.  Validates command format
    3.  Dispatches to `CompanyUnassign` action class

### 5. Authorization & Validation (Backend)

-   **File:** `app/Actions/DevOps/CompanyUnassign.php`
-   **Method:** `handle()`
-   **Validation Rules:**
    -   `email`: required|email
    -   `company`: required|string

### 6. Business Logic (Backend)

-   **File:** `app/Actions/DevOps/CompanyUnassign.php`
-   **Method:** `handle()`
-   **Action (within DB transaction):**
    1.  Validate input data
    2.  Find company by UUID, slug, or name
    3.  Find user by email
    4.  Check permissions:
        -   SuperAdmins can unassign from any company
        -   Others must be company owner
        -   Users cannot unassign themselves
    5.  Verify user is actually assigned to company
    6.  Remove company-user relationship

### 7. Database Interaction (Backend)

-   **Table:** `company_user` (pivot table)
-   **Operation:** DELETE relationship record
-   **Conditions:**
    -   `company_id` = specified company
    -   `user_id` = specified user

### 8. Permission Checks (Backend)

-   **SuperAdmin Check:** `$actor->isSuperAdmin()`
-   **Company Owner Check:**
    ```php
    $this->lookup->userHasRole($company->id, $actor->id, ['owner'])
    ```
-   **Self-Unassignment Prevention:** Users cannot remove themselves
-   **Current Company Check:** Non-superadmins must be in the company

### 9. Session Management (Backend)

-   **Important Side Effect:** If unassigning from current company:
    -   Clear `current_company_id` from session
    -   Force user to select another company
    -   Redirect if no other companies available

### 10. HTTP Response (Backend)

-   **Success Response (200):**
    ```json
    {
        "message": "User unassigned from company successfully",
        "data": {
            "user_id": "user-uuid",
            "company_id": "company-uuid",
            "removed_at": "2024-01-15T10:30:00Z"
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

-   **Not Found Error (422):**
    ```json
    {
        "message": "User is not assigned to this company"
    }
    ```

### 11. Response Handling (Frontend)

-   **On Success:** Shows success toast and updates UI
-   **On Error:** Shows error toast with specific message
-   **UI Updates:** Removes user from company list, updates user's company assignments

---

## Key Implementation Details

### Authorization Rules
1. **SuperAdmins**: Can unassign any user from any company
2. **Company Owners**: Can unassign users from their own company
3. **Users**: Cannot unassign themselves (prevents lockout)
4. **Other Roles**: Cannot unassign users

### Safety Checks
- Prevents self-unassignment (maintains at least one owner)
- Verifies user is actually assigned before removing
- Validates company and user existence

### Company Lookup
Same flexible lookup as assignment:
1. UUID (exact match)
2. Slug (exact match)
3. Name (partial match via OR condition)

### Session Impact
When unassigning from current company:
- Session is cleared
- User must select new company
- If no companies left, redirect to company selection

### Transaction Safety
- Operations wrapped in database transaction
- Ensures atomic unassignment
- Rolls back on errors

### Audit Trail
- Consider logging unassignments for security
- Track who removed whom and when
- Useful for compliance

### Edge Cases Handled
- Last owner cannot be removed (company would be orphaned)
- User not assigned to specified company
- Company doesn't exist
- User doesn't exist
- Attempting to unassign self

### Side Effects
- User loses access to company resources
- User cannot switch to unassigned company
- Company disappears from user's company list
- User's current company session is cleared

### UI Considerations
- Show confirmation modal before unassignment
- Display warning when removing last owner
- Update user's available companies immediately
- Show success/error notifications

### Email Notifications
Consider sending:
- Removal notification to user
- Notification to company owners
- Access revocation confirmation

### Cleanup Operations
After unassignment, consider:
- Revoking company-specific API tokens
- Clearing company-specific cache
- Removing company-specific preferences
- Archiving company-specific data if needed