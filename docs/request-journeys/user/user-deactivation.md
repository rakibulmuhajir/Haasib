# Request Journey: User Deactivation

This document outlines the process of deactivating a user. Unlike companies, users don't have a direct "deactivated" status. User deactivation is achieved through various methods.

## Overview

User deactivation in this system is not a single action but a combination of:
1. Removing all company assignments
2. Changing system role (optional)
3. Blocking login (if implemented)
4. Disabling email/password (extreme cases)

## Method 1: Remove All Company Assignments

### Flow Diagram
```
[SuperAdmin] → [Admin/Users/Show] → [Remove All Companies] → [User Isolated] → [Deactivated]
```

### Request Sequence

1. **Get User's Companies**
   - Method: `GET`
   - Endpoint: `/web/companies?user_id={user_id}`
   - Returns: List of companies user is assigned to

2. **Remove Each Assignment**
   - Method: `POST`
   - Endpoint: `/commands`
   - Action: `company.unassign`
   - Repeat for each company

### Result
- User cannot access any company data
- User has no current company context
- User can still log in but has no permissions

## Method 2: Change System Role

### Flow Diagram
```
[SuperAdmin] → [Admin/Users/Edit] → [Set Role to 'blocked'] → [User Blocked] → [Deactivated]
```

### Request Sequence

1. **Update User Role**
   - Method: `PUT/PATCH`
   - Endpoint: `/users/{user_id}`
   - Payload: `{"system_role": "blocked"}`
   - Authorization: SuperAdmin only

### Result
- User login blocked by middleware
- All permissions revoked
- User effectively locked out

## Method 3: Soft Delete (Recommended)

### Flow Diagram
```
[SuperAdmin] → [Admin/Users/Index] → [Delete User] → [Soft Delete] → [User Hidden]
```

### Implementation (if using soft deletes)

1. **Add Soft Deletes to User Model**
   ```php
   use Illuminate\Database\Eloquent\SoftDeletes;
   
   class User extends Authenticatable
   {
       use SoftDeletes;
       // ...
   }
   ```

2. **Deactivation Request**
   - Method: `DELETE`
   - Endpoint: `/users/{user_id}`
   - Controller: `UserController@destroy`
   - Action: `$user->delete()`

### Result
- User hidden from most queries
- Can be restored if needed
- All relationships preserved

---

## Recommended Deactivation Workflow

### Step 1: Remove Company Access
```
POST /commands
{
  "action": "company.unassign",
  "email": "user@example.com",
  "company": "*" // Special value for all companies
}
```

### Step 2: Change System Role (Optional)
```
PUT /users/{user_id}
{
  "system_role": "suspended"
}
```

### Step 3: Add Deactivation Timestamp
```php
// In User model
public function deactivate()
{
    $this->update([
        'deactivated_at' => now(),
        'deactivated_by' => auth()->id(),
        'system_role' => 'suspended'
    ]);
}
```

---

## Frontend Implementation

### Deactivation Button
```vue
<template>
  <button @click="deactivateUser" :disabled="loading">
    {{ loading ? 'Deactivating...' : 'Deactivate User' }}
  </button>
</template>

<script>
export default {
  methods: {
    async deactivateUser() {
      if (!await this.$confirm('Deactivate this user?')) return;
      
      this.loading = true;
      
      try {
        // Remove from all companies
        for (const company of this.user.companies) {
          await this.$http.post('/commands', {
            action: 'company.unassign',
            email: this.user.email,
            company: company.id
          });
        }
        
        // Update user role
        await this.$http.put(`/users/${this.user.id}`, {
          system_role: 'suspended'
        });
        
        this.$toast.success('User deactivated');
        this.$emit('deactivated');
        
      } catch (error) {
        this.$toast.error(error.response.data.message);
      } finally {
        this.loading = false;
      }
    }
  }
}
</script>
```

---

## Database Schema Considerations

### Add Deactivation Fields to Users Table
```sql
ALTER TABLE users
ADD COLUMN deactivated_at TIMESTAMP NULL,
ADD COLUMN deactivated_by VARCHAR(255) NULL,
ADD COLUMN suspension_reason TEXT NULL;
```

### Add Index for Performance
```sql
CREATE INDEX idx_users_deactivated_at ON users(deactivated_at);
CREATE INDEX idx_users_system_role ON users(system_role);
```

---

## Authorization Middleware

### Create Deactivation Check
```php
// app/Http/Middleware/CheckUserActive.php
public function handle($request, Closure $next)
{
    if ($request->user() && 
        ($request->user()->deactivated_at || 
         $request->user()->system_role === 'suspended')) {
        auth()->logout();
        return redirect('/login')->with('error', 'Account deactivated');
    }
    
    return $next($request);
}
```

### Register Middleware
```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\CheckUserActive::class,
    ]);
})
```

---

## Best Practices

### 1. Audit Trail
- Log all deactivation actions
- Record who deactivated and when
- Store reason for deactivation
- Keep history of role changes

### 2. Communication
- Notify user of deactivation
- Email company owners about user removal
- Provide reactivation instructions if applicable

### 3. Data Handling
- Don't delete user data immediately
- Archive deactivated user data
- Consider data retention policies
- Plan for reactivation process

### 4. Security
- Remove active sessions on deactivation
- Revoke API tokens
- Invalidate remember me tokens
- Block future login attempts

### 5. Performance
- Index deactivation fields
- Use soft deletes for queries
- Exclude deactivated users from default queries
- Cache user status checks

---

## Reactivation Process

### 1. Restore Company Access
```php
// Restore previous company assignments
$previousAssignments = DB::table('company_user_history')
    ->where('user_id', $userId)
    ->get();
    
foreach ($previousAssignments as $assignment) {
    $user->companies()->attach($assignment->company_id, [
        'role' => $assignment->role
    ]);
}
```

### 2. Restore User Status
```php
$user->update([
    'deactivated_at' => null,
    'deactivated_by' => null,
    'system_role' => $previousRole,
    'suspension_reason' => null
]);
```

### 3. Notify User
```php
// Send reactivation email
$user->notify(new Reactivated($reactivatedBy));
```