# Request Journey: User Creation/Registration

This document outlines the step-by-step process of user registration and creation in the system.

## Flow Diagram

```
Guest -> Register.vue -> POST /register -> RegisteredUserController@store -> User Model -> Database -> Auto-login -> Dashboard
```

---

### 1. User Interaction (Frontend)

-   **File:** `resources/js/Pages/Auth/Register.vue`
-   **Action:** A guest user navigates to the registration page and fills out the registration form.
-   **Form Fields:**
    -   Name
    -   Email
    -   Password
    -   Password Confirmation

### 2. Client-Side Logic (Frontend)

-   **Action:** Form validation and submission.
-   **HTTP Request:** Sends a `POST` request to `/register`.
    -   **Payload:**
        ```json
        {
            "name": "John Doe",
            "email": "john@example.com",
            "password": "password123",
            "password_confirmation": "password123"
        }
        ```
    -   **Headers:**
        -   `Accept: application/json`
        -   `X-CSRF-TOKEN`: Laravel CSRF token
        -   `Content-Type: application/json`

### 3. API Routing (Backend)

-   **File:** `routes/auth.php`
-   **Route:** `Route::post('register', [RegisteredUserController::class, 'store']);`
-   **Middleware:** `guest` (only accessible by unauthenticated users)

### 4. Controller & Validation (Backend)

-   **File:** `app/Http/Controllers/Auth/RegisteredUserController.php`
-   **Method:** `store()`
-   **Validation:** Uses Laravel's built-in validation or custom FormRequest
    -   `name`: required, string, max:255
    -   `email`: required, email, unique:users
    -   `password`: required, confirmed, min:8

### 5. Business Logic (Backend)

-   **File:** `app/Http/Controllers/Auth/RegisteredUserController.php`
-   **Action:**
    1.  Validates incoming data
    2.  Creates new User model
    3.  Hashes password automatically
    4.  Sets default values:
        -   `system_role`: null or default role
        -   `created_by_user_id`: null (for self-registration)
    5.  Logs the user in automatically
    6.  Sends email verification notification

### 6. Database Interaction (Backend)

-   **Model:** `app/Models/User.php`
-   **Table:** `users`
-   **Fields Created:**
    -   `id`: UUID
    -   `name`: User's full name
    -   `email`: User's email address
    -   `password`: Hashed password
    -   `system_role`: User's system role (null by default)
    -   `email_verified_at`: null (until verified)
    -   `created_at`, `updated_at`: Timestamps

### 7. Authentication Session (Backend)

-   **Action:** After successful registration:
    1.  User is logged in automatically
    2.  Session is created
    3.  Authentication cookie is set
    4.  User is redirected to intended URL or dashboard

### 8. Email Verification (Backend)

-   **Action:** Email verification notification is sent
-   **Channel:** Mail (or other configured channels)
-   **View:** `emails.verify-email`
-   **Contains:** Signed verification URL

### 9. HTTP Response (Backend)

-   **Success Response:** Redirect response (303 or 302)
    -   **Location:** `/dashboard` or intended URL
    -   **Session:** Flash message with success notification

-   **Validation Error (422):**
    ```json
    {
        "message": "The given data was invalid.",
        "errors": {
            "email": ["The email has already been taken."]
        }
    }
    ```

### 10. Response Handling (Frontend)

-   **File:** `resources/js/Pages/Auth/Register.vue`
-   **On Success:** Browser follows redirect to dashboard
-   **On Error:** Displays validation errors below form fields

---

## Key Implementation Details

### Default User State
- New users are created with `system_role = null`
- `email_verified_at` is null until email verification
- Users are logged in immediately after registration

### Security Features
- Password is automatically hashed
- CSRF protection on form submission
- Unique email validation
- Password confirmation required

### Email Verification Flow
1. User registers
2. Verification email sent automatically
3. User clicks verification link
4. Email marked as verified
5. User can access protected routes

### Database Transactions
- User creation is not wrapped in a transaction by default
- Consider wrapping if creating related records (e.g., default company)

### User Creation by Admin
Separate flow exists for SuperAdmins to create users:
- Via admin panel
- Can set `system_role`
- Can assign to companies immediately
- `created_by_user_id` is set to creating admin

### Rate Limiting
- Registration endpoint may have rate limiting
- Email verification has throttle: 6,1 (6 requests per minute)

### Events Fired
- `Registered` event (if using Laravel's auth events)
- `EmailVerificationNotification` event

### After Registration Hooks
- Send welcome email
- Create default company (if applicable)
- Log registration for analytics
- Set up user preferences