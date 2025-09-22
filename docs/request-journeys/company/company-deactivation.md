# Request Journey: Company Deactivation

This document outlines the step-by-step process of deactivating a company using the command pattern. This action is typically performed by a SuperAdmin and temporarily disables the company without deleting it.

## Flow Diagram

```
User (SuperAdmin) -> Admin/Companies/Index.vue -> POST /commands -> CommandController -> DeactivateCompany Action -> Company Model -> Database
```

---

### 1. User Interaction (Frontend)

-   **Files:** 
    -   Admin Companies List: `resources/js/Pages/Admin/Companies/Index.vue`
    -   Company Show Page: `resources/js/Pages/Admin/Companies/Show.vue`
-   **Action:** A SuperAdmin clicks the "Deactivate" button for an active company.

### 2. Client-Side Logic (Frontend)

-   **Action:** A confirmation modal appears to confirm the deactivation. Upon confirmation, an HTTP request is sent.
-   **HTTP Request:** It sends a `POST` request to `/commands`.
    -   **Payload:**
        ```json
        {
            "command": "company.deactivate",
            "payload": {
                "company": "company-uuid-or-slug"
            }
        }
        ```
    -   **Headers:**
        -   `Accept: application/json`
        -   `X-Action: company.deactivate`
        -   `X-CSRF-TOKEN`: Laravel CSRF token
        -   `Content-Type: application/json`

### 3. API Routing & Authorization (Backend)

-   **File:** `routes/web.php`
-   **Route:** `Route::post('/commands', [CommandController::class, 'execute']);`
-   **Middleware:** `auth` (web authentication)
-   **Authorization:** The CommandController reads `X-Action: company.deactivate` and dispatches to appropriate action.

### 4. Command Processing (Backend)

-   **File:** `app/Actions/Company/DeactivateCompany.php`
-   **Method:** `handle(array $payload, User $actor)`
-   **Validation:** Validates the incoming payload
    -   Required: `company` (string - UUID, slug, or ID)
-   **Authorization:** Direct SuperAdmin check via `abort_unless($actor->isSuperAdmin(), 403)`
-   **Action:**
    1.  Receives validated payload
    2.  Finds company by UUID, slug, or ID
    3.  Checks if company is already inactive (throws validation error if true)
    4.  Calls `$company->deactivate()` method
    5.  Returns success response with company data

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
        "id": "company-uuid",
        "name": "Company Name",
        "is_active": false,
        "deactivated_at": "2025-01-15T10:30:00Z",
        "deactivated_by": "user-uuid"
    }
    ```
-   **Frontend:**
    -   **On Success:** Shows a success toast/notification and updates the UI to reflect the inactive status
    -   **On Error:** Shows error based on HTTP status code (403, 404)

---

## Key Implementation Details

### Authorization
- Only users with `system_role = 'superadmin'` can deactivate companies
- Authorization is handled directly in the DeactivateCompany action: `abort_unless($actor->isSuperAdmin(), 403)`
- The command pattern automatically handles the SuperAdmin check

### Company Lookup
- The action supports multiple identifier types:
  1. UUID (preferred): Checks via regex pattern
  2. Slug: URL-friendly identifier
  3. ID: Numeric fallback for backward compatibility
- Returns 404 if company doesn't exist

### State Changes
- `is_active` boolean field set to `false`
- `deactivated_at` timestamp set when deactivated
- `deactivated_by` stores which user deactivated it

### Error Handling
- 403 Forbidden: Non-superadmin users
- 404 Not Found: Company doesn't exist
- 422 Unprocessable Entity: Company is already inactive

### Command Pattern Benefits
- Automatic idempotency key support
- Centralized audit logging
- Consistent error handling format
- Transaction safety (wrapped in database transaction)

### Reversibility
- Deactivation is temporary
- Company can be reactivated using the activate command
- Unlike deletion, no data is hidden or soft-deleted

### Effects of Deactivation
- Company still exists in database
- Users cannot switch to deactivated company
- Company features become unavailable
- Company appears in admin list but marked as inactive

### UI Considerations
- Deactivated companies should be visually distinct
- Show "Activate" button instead of "Deactivate"
- May show deactivation date and who deactivated it