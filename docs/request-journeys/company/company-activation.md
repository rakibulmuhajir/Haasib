# Request Journey: Company Activation

This document outlines the step-by-step process of activating a company. This flow is triggered by a SuperAdmin user.

## Flow Diagram

```
User (SuperAdmin) -> Admin/Companies/Index.vue -> PATCH /web/companies/{id}/activate -> CompanyController@activate -> Company Model -> Database
```

---

### 1. User Interaction (Frontend)

-   **Files:** 
    -   Admin Companies List: `resources/js/Pages/Admin/Companies/Index.vue`
    -   Or Company Details: `resources/js/Pages/Admin/Companies/Show.vue`
-   **Action:** A SuperAdmin clicks the "Activate" button for an inactive company.

### 2. Client-Side Logic (Frontend)

-   **Action:** A method is called which constructs and sends an HTTP request.
-   **HTTP Request:** It sends a `PATCH` request to `/web/companies/{company_id}/activate`.
    -   **Payload:** `{ }` (empty body - company ID in URL)
    -   **Headers:**
        -   `Accept: application/json`
        -   `X-CSRF-TOKEN`: Laravel CSRF token
        -   `Authorization: Bearer {token}` (if using API auth)

### 3. API Routing & Authorization (Backend)

-   **File:** `routes/web.php`
-   **Route:** `Route::patch('/web/companies/{company}/activate', [\App\Http\Controllers\CompanyController::class, 'activate']);`
-   **Middleware:** `auth` (web authentication)
-   **Authorization:** The controller method checks `$user->isSuperAdmin()` directly.

### 4. Business Logic (Backend)

-   **File:** `app/Http/Controllers/CompanyController.php`
-   **Method:** `activate(string $company)`
-   **Action:**
    1.  Verifies the authenticated user is a SuperAdmin (`abort_unless($user->isSuperAdmin(), 403)`)
    2.  Finds the company by slug or UUID
    3.  Calls `$companyModel->activate()` method on the Company model
    4.  Returns JSON success response

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
        "message": "Company activated successfully"
    }
    ```
-   **Frontend:**
    -   **On Success:** Displays a success toast/notification and updates the UI to reflect the active status
    -   **On Error:** Displays error based on HTTP status code (403, 404)

---

## Key Implementation Details

### Authorization
- Only users with `system_role = 'superadmin'` can activate companies
- Regular users cannot access this endpoint

### Company Lookup
- Controller first tries to find company by slug
- Falls back to UUID if slug not found
- Returns 404 if company doesn't exist

### State Changes
- `is_active` boolean field in database
- `activated_at` timestamp set when activated
- `activated_by` stores which user activated it

### Error Handling
- 403 Forbidden: Non-superadmin users
- 404 Not Found: Company doesn't exist
- No specific validation for already active companies
