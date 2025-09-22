# Request Journey: Company Deletion

This document outlines the step-by-step process for deleting a company. This is a destructive action that uses Laravel's soft-delete mechanism.

## Flow Diagram

```
User (SuperAdmin) -> Admin/Companies/Index.vue -> DELETE /web/companies/{id} -> CompanyController@destroy -> Company Model (Soft Delete) -> Database
```

---

### 1. User Interaction (Frontend)

-   **Files:** 
    -   Admin Companies List: `resources/js/Pages/Admin/Companies/Index.vue`
    -   Company Show Page: `resources/js/Pages/Admin/Companies/Show.vue`
-   **Action:** A SuperAdmin clicks the "Delete" button for a company.

### 2. Client-Side Logic (Frontend)

-   **Action:** A confirmation modal appears to confirm the deletion. Upon confirmation, an HTTP request is sent.
-   **HTTP Request:** It sends a `DELETE` request to `/web/companies/{company_id}`.
    -   **Payload:** `{ }` (empty body - company ID in URL)
    -   **Headers:**
        -   `Accept: application/json`
        -   `X-CSRF-TOKEN`: Laravel CSRF token
        -   `Authorization: Bearer {token}` (if using API auth)

### 3. API Routing & Authorization (Backend)

-   **File:** `routes/web.php`
-   **Route:** `Route::delete('/web/companies/{company}', [\App\Http\Controllers\CompanyController::class, 'destroy']);`
-   **Middleware:** `auth` (web authentication)
-   **Authorization:** The controller method checks `$user->isSuperAdmin()` directly.
-   **No additional validation** beyond the SuperAdmin check

### 4. Business Logic (Backend)

-   **File:** `app/Http/Controllers/CompanyController.php`
-   **Method:** `destroy(string $company)`
-   **Action:**
    1.  Verifies the authenticated user is a SuperAdmin (`abort_unless($user->isSuperAdmin(), 403)`)
    2.  Finds the company by slug or UUID
    3.  Calls `$companyModel->delete()` (Laravel's soft delete)
    4.  Returns JSON success response

### 5. Model Behavior

-   **File:** `app/Models/Company.php`
-   **Traits:** Uses `Illuminate\Database\Eloquent\SoftDeletes`
-   **Action:**
    1.  Sets `deleted_at` timestamp in the database
    2.  Company is excluded from default queries
    3.  Can be restored with `restore()` method
    4.  Relationships are preserved but soft-deleted records won't appear

### 6. HTTP Response & Frontend Handling

-   **Backend:** Returns a `200 OK` response:
    ```json
    {
        "message": "Company deleted successfully"
    }
    ```
-   **Frontend:**
    -   **On Success:** Shows a success toast and removes the company from the list or redirects to company index
    -   **On Error:** Shows error based on HTTP status code (403, 404)

---

## Key Implementation Details

### Authorization
- Only users with `system_role = 'superadmin'` can delete companies
- No role-based permissions - strict SuperAdmin-only access

### Soft Delete Behavior
- Companies are not permanently deleted from database
- `deleted_at` timestamp is set when deleted
- Use `withTrashed()` to include deleted companies in queries
- Use `onlyTrashed()` to get only deleted companies
- Call `restore()` to undo deletion

### Company Lookup
- Controller first tries to find company by slug
- Falls back to UUID if slug not found
- Returns 404 if company doesn't exist

### No Confirmation Name
- Unlike the previous flow described, there's no requirement to type the company name
- Simple confirmation dialog is sufficient

### Error Handling
- 403 Forbidden: Non-superadmin users
- 404 Not Found: Company doesn't exist
- No additional validation beyond authentication and existence checks
