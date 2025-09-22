# Company Operations Migration Guide

This guide explains how to migrate from direct API routes to the command pattern for all company operations.

## Overview

All company operations (create, activate, deactivate, delete) now use a unified command pattern through the `/commands` endpoint. This provides consistency, better audit logging, and idempotency support.

## Migration Changes

### Before (Direct Routes)

```javascript
// Company Creation
axios.post('/companies', { name: 'New Company' })

// Company Activation  
axios.patch(`/web/companies/${companyId}/activate`)

// Company Deactivation
axios.patch(`/web/companies/${companyId}/deactivate`)

// Company Deletion
axios.delete(`/web/companies/${companyId}`)
```

### After (Command Pattern)

```javascript
// All operations use the same endpoint
const sendCommand = async (command, payload) => {
  return axios.post('/commands', {
    command,
    payload
  }, {
    headers: {
      'X-Action': command,
      'Content-Type': 'application/json'
    }
  })
}

// Company Creation
sendCommand('company.create', { 
  name: 'New Company',
  base_currency: 'USD'
})

// Company Activation
sendCommand('company.activate', { 
  company: companyId  // Can be UUID, slug, or ID
})

// Company Deactivation
sendCommand('company.deactivate', { 
  company: companyId
})

// Company Deletion
sendCommand('company.delete', { 
  company: companyId
})
```

## Frontend Component Updates

### Vue Component Example

```vue
<template>
  <div>
    <!-- Activation Button -->
    <button 
      v-if="!company.is_active"
      @click="activateCompany"
      :disabled="processing"
    >
      Activate
    </button>
    
    <!-- Deactivation Button -->
    <button 
      v-if="company.is_active"
      @click="deactivateCompany"
      :disabled="processing"
    >
      Deactivate
    </button>
    
    <!-- Delete Button -->
    <button 
      @click="deleteCompany"
      :disabled="processing"
      class="text-red-600"
    >
      Delete
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps(['company'])
const processing = ref(false)

const activateCompany = async () => {
  processing.value = true
  try {
    await axios.post('/commands', {
      command: 'company.activate',
      payload: { company: props.company.id }
    }, {
      headers: { 'X-Action': 'company.activate' }
    })
    
    // Success handling
    router.reload() // or use Inertia visit
  } catch (error) {
    // Error handling
    console.error('Activation failed:', error)
  } finally {
    processing.value = false
  }
}

const deactivateCompany = async () => {
  if (!confirm('Are you sure you want to deactivate this company?')) return
  
  processing.value = true
  try {
    await axios.post('/commands', {
      command: 'company.deactivate',
      payload: { company: props.company.id }
    }, {
      headers: { 'X-Action': 'company.deactivate' }
    })
    
    router.reload()
  } catch (error) {
    console.error('Deactivation failed:', error)
  } finally {
    processing.value = false
  }
}

const deleteCompany = async () => {
  if (!confirm('Are you sure you want to delete this company? This action cannot be undone.')) return
  
  processing.value = true
  try {
    await axios.post('/commands', {
      command: 'company.delete',
      payload: { company: props.company.id }
    }, {
      headers: { 'X-Action': 'company.delete' }
    })
    
    // Redirect to company list
    router.visit('/admin/companies')
  } catch (error) {
    console.error('Deletion failed:', error)
  } finally {
    processing.value = false
  }
}
</script>
```

## Benefits of the Command Pattern

1. **Consistency**: All operations use the same endpoint structure
2. **Idempotency**: Automatic support for idempotency keys
3. **Audit Logging**: Centralized logging of all operations
4. **Error Handling**: Consistent error response format
5. **Transaction Safety**: All operations wrapped in database transactions
6. **Flexibility**: Supports multiple company identifier types (UUID, slug, ID)

## Error Handling

The command pattern provides consistent error responses:

```javascript
try {
  await sendCommand('company.activate', { company: companyId })
} catch (error) {
  if (error.response?.status === 403) {
    // Not authorized
    alert('You do not have permission to perform this action')
  } else if (error.response?.status === 404) {
    // Company not found
    alert('Company not found')
  } else if (error.response?.status === 422) {
    // Validation error
    const errors = error.response.data.errors
    alert(Object.values(errors).join('\n'))
  } else {
    // Other errors
    alert('An unexpected error occurred')
  }
}
```

## API Response Format

All commands return structured responses:

```json
{
  "data": {
    "id": "uuid",
    "name": "Company Name",
    "is_active": true,
    "activated_at": "2025-01-15T10:30:00Z",
    "activated_by": "user-uuid"
  }
}
```

## Migration Checklist

- [ ] Update all frontend components to use `/commands` endpoint
- [ ] Add `X-Action` header to all requests
- [ ] Update request payloads to use `command` and `payload` structure
- [ ] Update error handling to work with command pattern responses
- [ ] Remove old direct route references
- [ ] Test all operations in staging environment
- [ ] Update any API documentation or external integrations

## Backward Compatibility

The command pattern is backward compatible. Existing direct routes may still work but should be considered deprecated. All new development should use the command pattern.