# Request Journey: Company Activation

This document outlines the step-by-step process of activating a company using the command pattern. This flow is triggered by a SuperAdmin user.

## Flow Diagram

```
User (SuperAdmin) -> Admin/Companies/Index.vue -> POST /commands -> CommandController -> ActivateCompany Action -> Company Model -> Database
```

---

### 1. User Interaction (Frontend)

-   **Files:** 
    -   Admin Companies List: `resources/js/Pages/Admin/Companies/Index.vue`
    -   Or Company Details: `resources/js/Pages/Admin/Companies/Show.vue`
-   **Action:** A SuperAdmin clicks the "Activate" button for an inactive company.

### 2. Client-Side Logic (Frontend)

-   **Action:** A method is called which constructs and sends an HTTP request.
-   **HTTP Request:** It sends a `POST` request to `/commands`.
    -   **Payload:**
        ```json
        {
            "command": "company.activate",
            "payload": {
                "company": "company-uuid-or-slug"
            }
        }
        ```
    -   **Headers:**
        -   `Accept: application/json`
        -   `X-Action: company.activate`
        -   `X-CSRF-TOKEN`: Laravel CSRF token
        -   `Content-Type: application/json`

### 3. API Routing & Authorization (Backend)

-   **File:** `routes/web.php`
-   **Route:** `Route::post('/commands', [CommandController::class, 'execute']);`
-   **Middleware:** `auth` (web authentication)
-   **Authorization:** The CommandController reads `X-Action: company.activate` and dispatches to appropriate action.

### 4. Command Processing (Backend)

-   **File:** `app/Actions/Company/ActivateCompany.php`
-   **Method:** `handle(array $payload, User $actor)`
-   **Validation:** Validates the incoming payload
    -   Required: `company` (string - UUID, slug, or ID)
-   **Authorization:** Direct SuperAdmin check via `abort_unless($actor->isSuperAdmin(), 403)`
-   **Action:**
    1.  Receives validated payload
    2.  Finds company by UUID, slug, or ID
    3.  Checks if company is already active (throws validation error if true)
    4.  Calls `$company->activate()` method
    5.  Returns success response with company data

### 5. Model Logic

-   **File:** `app/Models/Company.php`
-   **Method:** `activate()`
-   **Action:**
    1.  Updates the company's `is_active` status to `true`
    2.  Sets `activated_at` timestamp
    3.  Sets `activated_by` to the current user's ID
    4.  Saves the model

### 6. HTTP Response & Frontend Handling

-   **Backend:** Returns a `200 OK` response:
    ```json
    {
        "id": "company-uuid",
        "name": "Company Name",
        "is_active": true,
        "activated_at": "2025-01-15T10:30:00Z",
        "activated_by": "user-uuid"
    }
    ```
-   **Frontend:**
    -   **On Success:** Displays a success toast/notification and updates the UI to reflect the active status
    -   **On Error:** Displays error based on HTTP status code (403, 404)

---

## Key Implementation Details

### Authorization
- Only users with `system_role = 'superadmin'` can activate companies
- Authorization is handled directly in the ActivateCompany action: `abort_unless($actor->isSuperAdmin(), 403)`
- The command pattern automatically handles the SuperAdmin check

### Company Lookup
- The action supports multiple identifier types:
  1. UUID (preferred): Checks via regex pattern
  2. Slug: URL-friendly identifier
  3. ID: Numeric fallback for backward compatibility
- Returns 404 if company doesn't exist

### State Changes
- `is_active` boolean field set to `true`
- `activated_at` timestamp set when activated
- `activated_by` stores which user activated it

### Error Handling
- 403 Forbidden: Non-superadmin users
- 404 Not Found: Company doesn't exist
- 422 Unprocessable Entity: Company is already active

### Command Pattern Benefits
- Automatic idempotency key support
- Centralized audit logging
- Consistent error handling format
- Transaction safety (wrapped in database transaction)
