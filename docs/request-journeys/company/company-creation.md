# Request Journey: Company Creation

This document outlines the step-by-step process of creating a new company, from the user's interaction in the browser to the final database record and response.

## Flow Diagram

```
User (SuperAdmin) -> Admin/Companies/Create.vue -> POST /companies -> CompanyController@store -> Company Model (with relationships) -> Database
```

---

### 1. User Interaction (Frontend)

-   **File:** `resources/js/Pages/Admin/Companies/Create.vue`
-   **Action:** A SuperAdmin fills out the "Create Company" form and clicks the "Create" button.
-   **Trigger:** The form submission method in the Vue component is called.

### 2. Client-Side Logic (Frontend)

-   **Action:** The form data is collected and sent via HTTP request.
-   **HTTP Request:** It sends a `POST` request to `/companies`.
    -   **Payload:**
        ```json
        {
            "name": "Company Name",
            "base_currency": "USD",
            "language": "en",
            "locale": "en-US",
            "settings": {}
        }
        ```
    -   **Headers:**
        -   `Accept: application/json`
        -   `X-CSRF-TOKEN`: Laravel CSRF token
        -   `Content-Type: application/json`

### 3. API Routing (Backend)

-   **File:** `routes/web.php`
-   **Route:** Implicitly handled by the CommandController at `POST /commands`
-   **Middleware:** `auth` and `verified` middleware ensure the user is authenticated and verified

### 4. Command Controller & Validation (Backend)

-   **File:** `app/Http/Controllers/CompanyController.php`
-   **Method:** `store(CompanyStoreRequest $request)`
-   **Validation:** `App\Http\Requests\CompanyStoreRequest` validates the incoming data.
    -   Required: `name`
    -   Optional: `base_currency` (defaults to 'AED'), `settings`

### 5. Authorization (Backend)

-   **File:** The controller method is protected by auth middleware
-   **Action:** Any authenticated user can create companies, but typically only SuperAdmins would have access to the creation form

### 6. Business Logic (Backend)

-   **File:** `app/Http/Controllers/CompanyController.php`
-   **Method:** `store()`
-   **Action (within DB transaction):**
    1.  Receives validated data
    2.  Creates new Company model with:
        - `name`, `base_currency`, `language`, `locale`, `settings`
        - `created_by_user_id` set to current user
    3.  Attaches creator as company owner with role 'owner'
    4.  Sets `currency_id` based on `base_currency`
    5.  Returns JSON response

### 7. Database Interaction (Backend)

-   **Files:** 
    -   `app/Models/Company.php`
    -   Database migrations for `companies` and `company_user` tables
-   **Actions:**
    1.  INSERT into `companies` table
    2.  INSERT into `company_user` pivot table
    3.  Updates `currency_id` if currency found

### 8. HTTP Response (Backend)

-   **Status Code:** `201 Created`
-   **Response Body:**
    ```json
    {
        "data": {
            "id": "uuid",
            "name": "Company Name",
            "slug": "company-name",
            "base_currency": "USD",
            "language": "en",
            "locale": "en-US"
        }
    }
    ```

### 9. Response Handling (Frontend)

-   **File:** `resources/js/Pages/Admin/Companies/Create.vue`
-   **Action:** The component handles the response.
    -   **On Success (201):** Shows success toast and redirects to company list or new company page
    -   **On Error (422):** Shows validation errors
    -   **On Other Errors:** Shows generic error message

---

## Key Implementation Details

### Transaction Safety
- All database operations are wrapped in `DB::transaction()`
- If any step fails, the entire operation rolls back

### Automatic Relationships
- Creator is automatically attached as company owner
- Company-currency relationship is established if currency exists

### Default Values
- `language`: defaults to 'en'
- `locale`: defaults to 'en-US'
- `base_currency`: defaults to 'AED'
- `settings`: defaults to empty array

### Slug Generation
- Company slug is automatically generated from the name
- Used for URL-friendly identification

### Validation Rules
- `name`: required, string, max:255
- `base_currency`: optional, string, size:3 (currency code)
- `settings`: optional, array

### Error Handling
- 422 Unprocessable Entity: Validation errors
- 500 Internal Server Error: Database or server errors
- 401 Unauthorized: Not authenticated
- 403 Forbidden: Not verified (if email verification required)

### Company Status
- New companies are created with `is_active = true` by default
- No separate activation step required for newly created companies