# Request Journey: Company Deactivation

This document outlines the step-by-step process of deactivating a company. This action is typically performed by a SuperAdmin and temporarily disables the company without deleting it.

## Flow Diagram

```
User (SuperAdmin) -> Admin/Companies/Index.vue -> PATCH /web/companies/{id}/deactivate -> CompanyController@deactivate -> Company Model -> Database
```

---

### 1. User Interaction (Frontend)

-   **Files:** 
    -   Admin Companies List: `resources/js/Pages/Admin/Companies/Index.vue`
    -   Company Show Page: `resources/js/Pages/Admin/Companies/Show.vue`
-   **Action:** A SuperAdmin clicks the "Deactivate" button for an active company.

### 2. Client-Side Logic (Frontend)

-   **Action:** A confirmation modal appears to confirm the deactivation. Upon confirmation, an HTTP request is sent.
-   **HTTP Request:** It sends a `PATCH` request to `/web/companies/{company_id}/deactivate`.
    -   **Payload:** `{ }` (empty body - company ID in URL)
    -   **Headers:**
        -   `Accept: application/json`
        -   `X-CSRF-TOKEN`: Laravel CSRF token
        -   `Authorization: Bearer {token}` (if using API auth)

### 3. API Routing & Authorization (Backend)

-   **File:** `routes/web.php`
-   **Route:** `Route::patch('/web/companies/{company}/deactivate', [\App\Http\Controllers\CompanyController::class, 'deactivate']);`
-   **Middleware:** `auth` (web authentication)
-   **Authorization:** The controller method checks `$user->isSuperAdmin()` directly.

### 4. Business Logic (Backend)

-   **File:** `app/Http/Controllers/CompanyController.php`
-   **Method:** `deactivate(string $company)`
-   **Action:**
    1.  Verifies the authenticated user is a SuperAdmin (`abort_unless($user->isSuperAdmin(), 403)`)
    2.  Finds the company by slug or UUID
    3.  Calls `$companyModel->deactivate()` method on the Company model
    4.  Returns JSON success response

### 5. Model Logic

-   **File:** `app/Models/Company.php`
-   **Method:** `deactivate()`
-   **Action:**
    1.  Updates the company's `is_active` status to `false`
    2.  Sets `deactivated_at` timestamp
    3.  Sets `deactivated_by` to the current user's ID
    4.  Saves the model

### 6. HTTP Response & Frontend Handling

-   **Backend:** Returns a `200 OK` response:
    ```json
    {
        "message": "Company deactivated successfully"
    }
    ```
-   **Frontend:**
    -   **On Success:** Shows a success toast/notification and updates the UI to reflect the inactive status
    -   **On Error:** Shows error based on HTTP status code (403, 404)

---

## Key Implementation Details

### Authorization
- Only users with `system_role = 'superadmin'` can deactivate companies
- Regular users cannot access this endpoint

### Company Lookup
- Controller first tries to find company by slug
- Falls back to UUID if slug not found
- Returns 404 if company doesn't exist

### State Changes
- `is_active` boolean field in database (set to false)
- `deactivated_at` timestamp set when deactivated
- `deactivated_by` stores which user deactivated it

### Reversibility
- Deactivation is temporary
- Company can be reactivated using the activate endpoint
- Unlike deletion, no data is hidden or soft-deleted

### Effects of Deactivation
- Company still exists in database
- Users cannot switch to deactivated company
- Company features become unavailable
- Company appears in admin list but marked as inactive

### Error Handling
- 403 Forbidden: Non-superadmin users
- 404 Not Found: Company doesn't exist
- No specific validation for already deactivated companies (idempotent operation)

### Audit Trail
- Deactivation is logged through timestamps and user tracking
- Can track who deactivated and when
- Useful for compliance and debugging

### UI Considerations
- Deactivated companies should be visually distinct
- Show "Activate" button instead of "Deactivate"
- May show deactivation date and who deactivated it