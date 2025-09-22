# Request Journey: User Activation (Email Verification)

This document outlines the step-by-step process of verifying a user's email address after registration.

## Flow Diagram

```
[User] → [Registration] → [Email Sent] → [User Clicks Link] → [Verify Email] → [Mark Verified] → [Success]
```

---

### 1. Initial Registration

-   **Endpoint:** `POST /register`
-   **Controller:** `RegisteredUserController@store`
-   **Action:**
    1.  Creates user account
    2.  Sets `email_verified_at = null`
    3.  Sends verification email automatically
    4.  Logs user in

### 2. Email Sending (Backend)

-   **Notification:** `Illuminate\Auth\Notifications\VerifyEmail`
-   **Method:** `EmailVerificationNotificationController@store`
-   **Action:**
    1.  Generates signed URL for verification
    2.  Sends email with verification link
    3.  Rate limits to 6 requests per minute

### 3. Email Content

-   **Subject:** "Verify Email Address"
-   **Body:** Contains verification link
-   **Link Format:** `/verify-email/{id}/{hash}`
-   **Expiration:** Link expires based on configuration

### 4. User Clicks Verification Link

-   **Method:** `GET`
-   **Endpoint:** `/verify-email/{id}/{hash}`
-   **Middleware:** `signed`, `throttle:6,1`
-   **Controller:** `VerifyEmailController`

### 5. URL Verification (Backend)

-   **Signed Middleware:** Validates URL signature
-   **Throttle Middleware:** Limits verification attempts
-   **Controller Action:** `__invoke()`

### 6. Email Verification Process

-   **File:** `app/Http/Controllers/Auth/VerifyEmailController.php`
-   **Action:**
    1.  Verify URL signature is valid
    2.  Find user by ID
    3.  Check if email is already verified
    4.  Mark email as verified
    5.  Redirect with success message

### 7. Database Update

-   **Model:** `App\Models\User`
-   **Field:** `email_verified_at`
-   **Value:** Current timestamp
-   **Operation:** `update(['email_verified_at' => now()])`

### 8. Response Handling (Backend)

-   **Success:** Redirect to intended URL with success message
-   **Already Verified:** Redirect without changes
-   **Invalid Link:** Show error message

### 9. Frontend Handling

-   **Success State:** Show "Email verified successfully" message
-   **Error State:** Show "Invalid verification link" message
-   **UI Updates:** Remove verification notices, enable verified-only features

### 10. Resend Verification Email

-   **Method:** `POST`
-   **Endpoint:** `/email/verification-notification`
-   **Controller:** `EmailVerificationNotificationController@store`
-   **Middleware:** `auth`, `throttle:6,1`
-   **Action:** Sends another verification email

---

## Key Implementation Details

### Signed URLs
- Verification links are signed to prevent tampering
- Uses Laravel's URL signing functionality
- Expires after configurable time (default 60 minutes)

### Rate Limiting
- Email sending limited to 6 per minute
- Verification attempts limited to 6 per minute
- Prevents abuse of verification system

### Middleware Stack
- `guest` middleware for registration
- `auth` middleware for resend
- `signed` middleware for verification
- `throttle` middleware for protection

### User State During Verification
- User can be logged in or not when verifying
- Verification works regardless of login state
- Email marked verified for all sessions

### Verification Workflow
1. User registers → `email_verified_at = null`
2. Email sent with signed URL
3. User clicks URL → signature validated
4. `email_verified_at` set to current timestamp
5. User gains full access to application

### Email Verification Notice
- Laravel provides `Illuminate\Auth\Middleware\EnsureEmailIsVerified`
- Redirects unverified users to verification notice
- Can be applied to routes that require verification

### Events Fired
- `Verified` event when email verified
- `EmailVerificationNotification` when sending
- Can listen for these events for custom logic

### Database Considerations
- `email_verified_at` is nullable timestamp
- `null` means not verified
- Timestamp value means verified at that time
- Index on this field for performance

### Security Features
- Signed URLs prevent forgery
- Rate limiting prevents abuse
- Expiration prevents old links from working
- Users can only verify their own email

### Error Scenarios
- Invalid signature → show error
- Expired link → show error
- Already verified → ignore
- User not found → show error
- Network issues during send → retry option

### Best Practices
- Send verification immediately after registration
- Allow users to resend verification email
- Show clear verification status in UI
- Provide helpful error messages
- Consider backup verification methods

### Customization Options
- Custom email templates
- Custom verification routes
- Custom redirect after verification
- Custom expiration times
- Custom notification channels