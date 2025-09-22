# Request Journey: Company Deletion

This document outlines the step-by-step process for deleting a company using the command pattern. This is a destructive action that uses Laravel's soft-delete mechanism.

## Flow Diagram

```
User (SuperAdmin) -> Admin/Companies/Index.vue -> POST /commands -> CommandController -> CompanyDelete Action -> Company Model (Soft Delete) -> Database
```

---

### 1. User Interaction (Frontend)

-   **Files:** 
    -   Admin Companies List: `resources/js/Pages/Admin/Companies/Index.vue`
    -   Company Show Page: `resources/js/Pages/Admin/Companies/Show.vue`
-   **Action:** A SuperAdmin clicks the "Delete" button for a company.

### 2. Client-Side Logic (Frontend)

-   **Action:** A confirmation modal appears to confirm the deletion. Upon confirmation, an HTTP request is sent.
-   **HTTP Request:** It sends a `POST` request to `/commands`.
    -   **Payload:**
        ```json
        {
            "command": "company.delete",
            "payload": {
                "company": "company-uuid-or-slug"
            }
        }
        ```
    -   **Headers:**
        -   `Accept: application/json`
        -   `X-Action: company.delete`
        -   `X-CSRF-TOKEN`: Laravel CSRF token
        -   `Content-Type: application/json`

### 3. API Routing & Authorization (Backend)

-   **File:** `routes/web.php`
-   **Route:** `Route::post('/commands', [CommandController::class, 'execute']);`
-   **Middleware:** `auth` (web authentication)
-   **Authorization:** The CommandController reads `X-Action: company.delete` and dispatches to appropriate action.

### 4. Command Processing (Backend)

-   **File:** `app/Actions/DevOps/CompanyDelete.php`
-   **Method:** `handle(array $payload, User $actor)`
-   **Validation:** Validates the incoming payload
    -   Required: `company` (string - UUID, slug, or ID)
-   **Authorization:** Direct SuperAdmin check via `abort_unless($actor->isSuperAdmin(), 403)`
-   **Action:**
    1.  Receives validated payload
    2.  Finds company by UUID, slug, or ID within a database transaction
    3.  Calls `$company->delete()` (Laravel's soft delete)
    4.  Returns success response

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
        "message": "Company deleted",
        "data": {
            "company": "company-identifier"
        }
    }
    ```
-   **Frontend:**
    -   **On Success:** Shows a success toast and removes the company from the list or redirects to company index
    -   **On Error:** Shows error based on HTTP status code (403, 404)

---

## Key Implementation Details

### Authorization
- Only users with `system_role = 'superadmin'` can delete companies
- Authorization is handled directly in the CompanyDelete action: `abort_unless($actor->isSuperAdmin(), 403)`
- The command pattern automatically handles the SuperAdmin check

### Company Lookup
- The action supports multiple identifier types:
  1. UUID (preferred): Checks via `Str::isUuid()` to avoid PostgreSQL casting issues
  2. Slug: URL-friendly identifier
  3. Name: Fallback for backward compatibility
- Returns 404 if company doesn't exist

### Soft Delete Behavior
- Companies are not permanently deleted from database
- `deleted_at` timestamp is set when deleted
- Use `withTrashed()` to include deleted companies in queries
- Use `onlyTrashed()` to get only deleted companies
- Call `restore()` to undo deletion

### Database Transaction
- All operations are wrapped in `DB::transaction()` for data integrity
- If any step fails, the entire operation rolls back

### Command Pattern Benefits
- Automatic idempotency key support
- Centralized audit logging
- Consistent error handling format
- Transaction safety

### Error Handling
- 403 Forbidden: Non-superadmin users
- 404 Not Found: Company doesn't exist
- No additional validation beyond authentication and existence checks

### No Confirmation Name
- Unlike the previous flow described, there's no requirement to type the company name
- Simple confirmation dialog is sufficient
