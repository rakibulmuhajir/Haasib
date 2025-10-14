# Request Journey: User Deletion

This document outlines the process of permanently or temporarily deleting a user from the system.

## Overview

User deletion can be implemented in two ways:
1. **Soft Delete** (Recommended): Hide user but keep data for audit/restore
2. **Hard Delete**: Permanently remove user and all associated data

## Soft Delete Implementation (Recommended)

### Flow Diagram
```
[SuperAdmin] → [Admin/Users/Index] → [Delete User] → [Confirm] → [Soft Delete] → [User Hidden]
```

### Request Sequence

1. **Delete Request**
   - Method: `DELETE`
   - Endpoint: `/users/{user_id}`
   - Controller: `UserController@destroy`
   - Middleware: `auth`, `verified`

2. **Controller Implementation**
   ```php
   public function destroy($userId)
   {
       $user = User::findOrFail($userId);
       
       // Prevent self-deletion
       if ($user->id === auth()->id()) {
           return response()->json([
               'message' => 'Cannot delete your own account'
           ], 422);
       }
       
       // Check if user is last owner of any company
       if ($this->userIsLastOwner($user)) {
           return response()->json([
               'message' => 'User is the last owner of one or more companies'
           ], 422);
       }
       
       $user->delete();
       
       return response()->json([
           'message' => 'User deleted successfully'
       ]);
   }
   ```

### 3. Model Setup
   ```php
   // app/Models/User.php
   use Illuminate\Database\Eloquent\SoftDeletes;
   
   class User extends Authenticatable
   {
       use SoftDeletes;
       
       protected $dates = ['deleted_at'];
   }
   ```

### 4. Database Changes
   ```sql
   ALTER TABLE users
   ADD COLUMN deleted_at TIMESTAMP NULL;
   
   CREATE INDEX idx_users_deleted_at ON users(deleted_at);
   ```

### 5. HTTP Response
   - **Success (200):**
     ```json
     {
         "message": "User deleted successfully"
     }
     ```
   
   - **Validation Error (422):**
     ```json
     {
         "message": "Cannot delete last company owner"
     }
     ```

---

## Hard Delete Implementation

### Warning: This is destructive and irreversible!

### Flow Diagram
```
[SuperAdmin] → [Admin/Users/Index] → [Delete User] → [Confirm Warning] → [Hard Delete] → [Data Gone]
```

### Implementation
```php
public function forceDestroy($userId)
{
    DB::transaction(function () use ($userId) {
        $user = User::withTrashed()->findOrFail($userId);
        
        // Check permissions
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        
        // Delete related data
        $user->companies()->detach(); // Remove company relationships
        $user->tokens()->delete(); // Delete API tokens
        // Delete other related data...
        
        // Finally delete the user
        $user->forceDelete();
    });
    
    return response()->json([
        'message' => 'User permanently deleted'
    ]);
}
```

---

## Frontend Implementation

### Delete Confirmation Modal
```vue
<template>
  <div>
    <button @click="showConfirm = true" class="btn btn-danger">
      Delete User
    </button>
    
    <Modal v-model="showConfirm" title="Delete User">
      <div class="space-y-4">
        <p>Are you sure you want to delete this user?</p>
        
        <div v-if="user.isLastOwner" class="bg-red-50 p-4 rounded">
          <p class="text-red-800 font-semibold">
            ⚠️ This user is the last owner of one or more companies.
          </p>
          <p class="text-red-700">
            Deleting this user may leave companies without an owner.
          </p>
        </div>
        
        <div class="bg-yellow-50 p-4 rounded">
          <p class="text-yellow-800">
            This action can be undone by a SuperAdmin.
          </p>
        </div>
        
        <div class="flex justify-end space-x-2">
          <button @click="showConfirm = false" class="btn">
            Cancel
          </button>
          <button 
            @click="deleteUser" 
            class="btn btn-danger"
            :disabled="loading"
          >
            {{ loading ? 'Deleting...' : 'Delete User' }}
          </button>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script>
export default {
  props: ['user'],
  data() {
    return {
      showConfirm: false,
      loading: false
    }
  },
  computed: {
    userIsLastOwner() {
      return this.user.companies.some(company => 
        company.pivot.role === 'owner' && 
        company.users_count === 1
      );
    }
  },
  methods: {
    async deleteUser() {
      this.loading = true;
      
      try {
        await this.$http.delete(`/users/${this.user.id}`);
        this.$toast.success('User deleted');
        this.$emit('deleted');
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

## Data Cleanup Options

### Option 1: Keep All Data (Recommended)
- Soft delete user
- Keep all user's data
- Data inaccessible but preserved for audit
- Can restore if needed

### Option 2: Anonymize Data
- Soft delete user
- Replace PII with anonymized data
- Keep statistical/aggregate data
- Maintain referential integrity

### Option 3: Cascade Delete
```php
// In User model
protected static function boot()
{
    parent::boot();
    
    static::deleting(function ($user) {
        // Delete related data
        $user->companies()->detach();
        $user->notifications()->delete();
        $user->tokens()->delete();
        // Add other related models
    });
}
```

---

## Authorization Rules

### Who Can Delete Users
1. **SuperAdmins**: Can delete any user
2. **Company Owners**: Can delete users from their companies (unassign only)
3. **Users**: Cannot delete themselves or others

### Prevention Rules
1. **Self-deletion**: Users cannot delete themselves
2. **Last Owner**: Cannot delete last owner of a company
3. **Active Sessions**: Consider terminating active sessions
4. **Critical Data**: Additional checks for users with critical data

---

## Audit Trail

### Log Deletion Actions
```php
// Create audit log entry
AuditLog::create([
    'user_id' => auth()->id(),
    'action' => 'user_deleted',
    'model_type' => 'User',
    'model_id' => $user->id,
    'old_values' => $user->toArray(),
    'new_values' => null,
    'ip_address' => request()->ip(),
    'user_agent' => request()->userAgent()
]);
```

### Store Deletion Metadata
```sql
ALTER TABLE users
ADD COLUMN deleted_by VARCHAR(255) NULL,
ADD COLUMN deletion_reason TEXT NULL,
ADD COLUMN deleted_at TIMESTAMP NULL;
```

---

## Restoration Process

### Restore Soft Deleted User
```php
public function restore($userId)
{
    $user = User::withTrashed()->findOrFail($userId);
    
    // Check permissions
    abort_unless(auth()->user()->isSuperAdmin(), 403);
    
    // Restore user
    $user->restore();
    
    // Optionally restore company assignments
    // This would require storing assignment history
    
    return response()->json([
        'message' => 'User restored successfully'
    ]);
}
```

### Restore Related Data
- Company assignments (if stored)
- User preferences
- API tokens (consider security implications)
- Other related data as needed

---

## Best Practices

### 1. Always Use Soft Deletes
- Provides safety net
- Allows for restoration
- Maintains audit trail
- Complies with data retention laws

### 2. Implement Proper Authorization
- Multiple permission checks
- Prevent self-deletion
- Protect critical system users
- Consider company ownership

### 3. Handle Related Data Carefully
- Don't cascade delete automatically
- Consider data privacy laws
- Maintain referential integrity
- Archive important data

### 4. Provide Clear Feedback
- Explain what will happen
- Show warning for critical users
- Confirm success/failure
- Provide restoration path

### 5. Consider Legal Requirements
- GDPR right to erasure
- Data retention policies
- Audit requirements
- Privacy regulations

---

## API Endpoints Summary

### User Deletion
- `DELETE /api/users/{id}` - Soft delete user
- `DELETE /api/users/{id}/force` - Hard delete (SuperAdmin only)
- `POST /api/users/{id}/restore` - Restore soft deleted user

### User Status Checks
- `GET /api/users/{id}/status` - Check if user is deleted
- `GET /api/users/trashed` - List soft deleted users
- `GET /api/users/{id}/can-delete` - Check if user can be deleted