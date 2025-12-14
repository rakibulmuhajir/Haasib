# Inline Edit Request Journey

## Overview
This document outlines the complete request flow for the universal inline editing system, from user interaction to database persistence and UI feedback.

## Request Flow Diagram

```
User Action
    ↓
Frontend Component (Vue)
    ↓
useInlineEdit Composable
    ↓
UniversalFieldSaver Service
    ↓
Laravel API (PATCH /api/inline-edit)
    ↓
InlineEditController
    ↓
Model Service Layer
    ↓
Database
    ↓
Response
    ↓
UI Update & Feedback
```

## Detailed Request Journey

### 1. User Interaction Phase

**Trigger:**
- User clicks on an inline editable field
- Component enters edit mode
- User types new value and presses Enter or clicks away

**Example Component:**
```vue
<InlineEditable
  v-model="customerData.phone"
  v-model:editing="isEditingPhone"
  label="Phone"
  type="text"
  :saving="isSaving('phone')"
  @save="saveField('phone', $event)"
  @cancel="cancelEditing"
/>
```

### 2. Frontend Processing Phase

**useInlineEdit Composable:**
```typescript
const saveField = async (field: string, value: any) => {
  const originalValue = localData.value[field]
  const fieldPath = fieldToAddressPath(field)
  
  // Optimistic update - UI updates immediately
  UniversalFieldSaver.updateOptimistically(localData.value, field, value, originalValue)
  
  // Send to server
  const result = await UniversalFieldSaver.save({
    model: 'customer',
    id: props.customer.id,
    fieldPath,
    verify: true,
    maxRetries: 2,
    toast,
    onSuccess: (responseData) => {
      // Update local data with server response
      if (responseData.resource) {
        Object.assign(localData.value, responseData.resource)
      }
      onSuccess?.(responseData.resource || localData.value)
    },
    onError: (error) => {
      // Rollback optimistic update on error
      UniversalFieldSaver.rollbackOptimisticUpdate(localData.value, field)
      onError?.(error)
    }
  }, value, originalValue)
  
  // Close editing on success
  if (result.ok) {
    editingField.value = null
  }
  
  return result
}
```

### 3. UniversalFieldSaver Service Processing

**Key Operations:**
1. **Field Path Resolution**
2. **Request Deduplication**
3. **Optimistic UI Update**
4. **Retry Logic**
5. **Error Handling**

**Service Flow:**
```typescript
public async save(opts: SaveOpts, value: any, originalValue?: any): Promise<SaveResult> {
  const { model, id, fieldPath, verify = true, maxRetries = 2 } = opts
  
  // Generate request key for deduplication
  const requestKey = `${model}:${id}:${fieldPath}`
  
  // Check if already in flight
  if (this.inflight[requestKey]) {
    return this.inflight[requestKey]!
  }
  
  // Create save promise
  const savePromise = this.executeSave(opts, value, originalValue, requestKey, maxRetries)
  
  // Store in flight
  this.inflight[requestKey] = savePromise
  
  try {
    const result = await savePromise
    return result
  } finally {
    this.inflight[requestKey] = null
  }
}
```

### 4. API Request Phase

**HTTP Request Details:**
```
Method: PATCH
URL: /api/inline-edit
Headers: {
  "Content-Type": "application/json",
  "Accept": "application/json",
  "X-Requested-With": "XMLHttpRequest",
  "X-CSRF-TOKEN": "[csrf-token]"
}
```

**Request Payload:**
```json
{
  "model": "customer",
  "id": "bebdfd96-4a3f-4bae-9542-66d1842f01cb",
  "fields": {
    "phone": "+1 (555) 123-4567"
  }
}
```

**For Nested Fields (Address):**
```json
{
  "model": "customer",
  "id": "bebdfd96-4a3f-4bae-9542-66d1842f01cb",
  "fields": {
    "address": {
      "city": "Multan",
      "country_id": "3ab47ae2-80b3-49b4-a9b3-2ccfdc9d354b"
    }
  }
}
```

### 5. Backend Processing Phase

**InlineEditController::patch()**
```php
public function patch(Request $request)
{
    $modelType = $request->input('model');
    $id = $request->input('id');
    $fields = $request->input('fields', []);
    
    // Get model configuration
    $config = $this->modelMap[$modelType] ?? null;
    if (!$config) {
        return response()->json([
            'success' => false,
            'message' => 'Unsupported model type'
        ], 400);
    }
    
    // Find the model
    $modelClass = $config['model'];
    $model = $modelClass::findOrFail($id);
    
    // Authorization check
    $this->authorize('update', $model);
    
    // Validate fields
    $validator = Validator::make($fields, $config['validationRules']);
    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }
    
    // Apply field handlers
    $updateData = [];
    foreach ($fields as $field => $value) {
        if (isset($config['fieldHandlers'][$field])) {
            $handler = $config['fieldHandlers'][$field];
            $result = $handler($value, $model);
            $updateData = array_merge($updateData, $result);
        } else {
            $updateData[$field] = $value;
        }
    }
    
    // Update model
    DB::transaction(function () use ($model, $updateData) {
        $model->update($updateData);
        
        // Log the change
        Log::info('Inline edit', [
            'model' => get_class($model),
            'id' => $model->id,
            'fields' => array_keys($updateData),
            'user_id' => auth()->id()
        ]);
    });
    
    // Return updated model
    return response()->json([
        'success' => true,
        'message' => 'Updated successfully',
        'resource' => $model->fresh()
    ]);
}
```

### 6. Database Operations Phase

**Example SQL Operations:**

**Simple Field Update:**
```sql
UPDATE customers 
SET phone = '+1 (555) 123-4567', 
    updated_at = '2025-09-24 11:56:13' 
WHERE id = 'bebdfd96-4a3f-4bae-9542-66d1842f01cb';
```

**Address Field Update (JSON):**
```sql
UPDATE customers 
SET billing_address = '{"city":"Multan","country_id":"3ab47ae2-80b3-49b4-a9b3-2ccfdc9d354b"}', 
    updated_at = '2025-09-24 11:56:13' 
WHERE id = 'bebdfd96-4a3f-4bae-9542-66d1842f01cb';
```

### 7. Response Phase

**Success Response (200 OK):**
```json
{
  "success": true,
  "message": "Updated successfully",
  "resource": {
    "id": "bebdfd96-4a3f-4bae-9542-66d1842f01cb",
    "name": "Acme Corporation",
    "phone": "+1 (555) 123-4567",
    "billing_address": "{\"city\":\"Multan\",\"country_id\":\"3ab47ae2-80b3-49b4-a9b3-2ccfdc9d354b\"}",
    "created_at": "2025-09-24T10:30:00.000000Z",
    "updated_at": "2025-09-24T11:56:13.000000Z"
  }
}
```

**Validation Error Response (422):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email field must be a valid email address."]
  }
}
```

**Authentication Error Response (401):**
```json
{
  "success": false,
  "message": "Unauthenticated"
}
```

### 8. Frontend Response Processing

**Success Handling:**
```typescript
onSuccess: (responseData) => {
  // Update local data with server response
  if (responseData.resource) {
    Object.assign(localData.value, responseData.resource)
  }
  // Show success toast
  toast.add({
    severity: 'success',
    summary: 'Success',
    detail: 'Updated successfully',
    life: 3000
  })
  // Close editing mode
  editingField.value = null
}
```

**Error Handling:**
```typescript
onError: (error) => {
  // Rollback optimistic update
  UniversalFieldSaver.rollbackOptimisticUpdate(localData.value, field)
  
  // Show error toast
  toast.add({
    severity: 'error',
    summary: 'Error',
    detail: error.response?.data?.message || 'Failed to save',
    life: 5000
  })
  
  // Log error for debugging
  console.error('Inline edit failed:', error)
}
```

## Error Scenarios and Recovery

### 1. **Network Error**
- Automatic retry with exponential backoff (300ms, 600ms, 1200ms)
- Shows loading state during retry
- Rolls back optimistic UI if all retries fail

### 2. **Validation Error**
- Shows field-specific error messages
- Keeps field in edit mode for correction
- Does not retry (user input error)

### 3. **Authentication Error**
- Redirects to login page
- Rolls back all optimistic updates
- Shows authentication required message

### 4. **Server Error (5xx)**
- Retries up to configured maximum
- Shows generic error message
- Rolls back on final failure

### 5. **Concurrency Conflict**
- Last write wins (server response overwrites local)
- User sees the final server state
- No conflict resolution UI (simplified approach)

## Performance Considerations

### 1. **Optimistic Updates**
- UI responds immediately without waiting for server
- Improves perceived performance
- Rollback on error maintains consistency

### 2. **Request Deduplication**
- Prevents duplicate requests for same field
- Maintains single loading state
- Reduces server load

### 3. **Batch Operations**
- Multiple fields can be updated in single request
- Reduces HTTP overhead
- Atomic database transaction

### 4. **Efficient Field Mapping**
- Centralized mapping reduces transformation overhead
- Address fields handled with JSON operations
- Minimal data transfer

## Security Features

### 1. **CSRF Protection**
- All requests include CSRF token
- Laravel validates token automatically

### 2. **Authorization**
- Controller checks update permissions
- Company-scoped queries prevent cross-company access

### 3. **Input Validation**
- Backend validation for all fields
- Type checking and sanitization
- Field-specific validation rules

### 4. **SQL Injection Prevention**
- Eloquent ORM parameterizes queries
- JSON fields properly escaped
- No raw SQL in update operations

## Monitoring and Logging

### 1. **Application Logs**
```php
Log::info('Inline edit', [
  'model' => get_class($model),
  'id' => $model->id,
  'fields' => array_keys($updateData),
  'user_id' => auth()->id(),
  'timestamp' => now()
]);
```

### 2. **Browser Console**
- Successful saves logged at info level
- Errors logged with full context
- Retry attempts logged for debugging

### 3. **Network Tab**
- All PATCH requests visible
- Request/response payloads inspectable
- Timing information available

## Future Enhancements

### 1. **Conflict Resolution**
- Show "Field was modified by another user" message
- Offer merge options
- Implement last-write-wins with notification

### 2. **Offline Support**
- Queue updates when offline
- Sync when connection restored
- Conflict resolution for offline changes

### 3. **Field-Level Permissions**
- Role-based edit restrictions
- Field visibility based on permissions
- Audit trail for compliance

### 4. **Real-time Collaboration**
- WebSocket notifications for edits
- Live cursor positions
- Collaborative editing sessions

---

*This document provides a complete view of the inline editing request flow from user interaction to database persistence and back.*