# Inertia.js v2 Best Practices Guide

This guide documents the best practices for using Inertia.js v2 in the Haasib application, ensuring optimal performance, maintainable code, and excellent user experience.

## Table of Contents
1. [Core Principles](#core-principles)
2. [Navigation Patterns](#navigation-patterns)
3. [Form Handling](#form-handling)
4. [State Management](#state-management)
5. [Error Handling](#error-handling)
6. [Loading States](#loading-states)
7. [Performance Optimization](#performance-optimization)
8. [Reusable Components](#reusable-components)
9. [Security Considerations](#security-considerations)
10. [Testing Patterns](#testing-patterns)

## Core Principles

### 1. SPA-First Approach
- Never use `window.location.href` for navigation
- Always use Inertia.js router or Link components
- Preserve application state during navigation
- Maintain client-side routing benefits

### 2. Progressive Enhancement
- Ensure graceful degradation for JavaScript failures
- Provide meaningful fallbacks
- Maintain accessibility without JavaScript

### 3. Consistent Data Flow
- Use props for data from server to client
- Use events for communication from child to parent
- Maintain predictable state management

### 4. Security by Default
- Validate all data on the server
- Use proper CSRF protection
- Implement proper authorization checks

## Navigation Patterns

### 1. When to Use `<Link>` vs `router.visit()`

#### Use `<Link>` for:
- Standard navigation between pages
- Primary navigation items
- SEO-critical links
- Menu items and breadcrumbs

```vue
<template>
  <!-- Primary navigation -->
  <nav>
    <Link href="/dashboard" class="nav-link">
      Dashboard
    </Link>
    <Link href="/customers" class="nav-link">
      Customers
    </Link>
  </nav>
  
  <!-- Breadcrumbs -->
  <nav aria-label="Breadcrumb">
    <ol class="breadcrumb">
      <li>
        <Link href="/">Home</Link>
      </li>
      <li>
        <Link href="/customers">Customers</Link>
      </li>
      <li aria-current="page">Edit Customer</li>
    </ol>
  </nav>
  
  <!-- Action links -->
  <Link 
    href="/customers/create" 
    class="btn btn-primary"
  >
    Add Customer
  </Link>
</template>
```

#### Use `router.visit()` for:
- Programmatic navigation
- Navigation after async operations
- Navigation with custom options
- Conditional navigation

```vue
<script setup lang="ts">
import { router } from '@inertiajs/vue3'

// Basic navigation
const goToDashboard = (): void => {
  router.visit('/dashboard')
}

// Navigation with options
const navigateToCustomer = (customerId: string): void => {
  router.visit(`/customers/${customerId}`, {
    method: 'get',
    preserveState: true,
    preserveScroll: true
  })
}

// Navigation after async operation
const handleSuccess = (): void => {
  router.visit('/customers', {
    onSuccess: () => {
      // Additional success handling
    }
  })
}

// Conditional navigation
const handleFormSubmit = async (): Promise<void> => {
  const success = await submitForm()
  if (success) {
    router.visit('/success-page')
  } else {
    router.visit('/error-page')
  }
}
</script>
```

### 2. Advanced Navigation Options

```vue
<script setup lang="ts">
import { router } from '@inertiajs/vue3'

// Navigation with method and data
const createCustomer = (customerData: CustomerFormData): void => {
  router.visit('/customers', {
    method: 'post',
    data: customerData,
    preserveState: false,
    onStart: () => {
      // Show loading state
    },
    onFinish: () => {
      // Hide loading state
    },
    onCancelToken: (token) => {
      // Handle cancellation
    }
  })
}

// Navigation with headers
const downloadFile = (fileId: string): void => {
  router.visit(`/files/${fileId}/download`, {
    method: 'get',
    headers: {
      'Accept': 'application/octet-stream'
    }
  })
}

// Navigation with validation
const navigateWithValidation = (): void => {
  router.visit('/dashboard', {
    onStart: (visit) => {
      // Validate before navigation
      if (!isValid) {
        visit.cancel()
        return false
      }
    }
  })
}
</script>
```

## Form Handling

### 1. Basic Form Patterns

```vue
<template>
  <form @submit.prevent="handleSubmit">
    <div class="field">
      <label for="name">Name</label>
      <InputText
        id="name"
        v-model="form.name"
        :class="{ 'p-invalid': form.errors.name }"
      />
      <small class="text-red-500">{{ form.errors.name }}</small>
    </div>
    
    <div class="field">
      <label for="email">Email</label>
      <InputText
        id="email"
        v-model="form.email"
        :class="{ 'p-invalid': form.errors.email }"
      />
      <small class="text-red-500">{{ form.errors.email }}</small>
    </div>
    
    <Button
      type="submit"
      label="Save"
      :loading="form.processing"
    />
  </form>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const form = useForm({
  name: '',
  email: ''
})

const handleSubmit = (): void => {
  form.post('/customers', {
    onSuccess: () => {
      // Handle success
    },
    onError: (errors) => {
      // Handle validation errors
    },
    onFinish: () => {
      // Always called, even on error
    }
  })
}
</script>
```

### 2. Advanced Form Handling

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const form = useForm({
  name: '',
  email: '',
  address: {
    street: '',
    city: '',
    state: '',
    zip: ''
  }
})

// Custom submission logic
const submitForm = async (): Promise<void> => {
  try {
    // Pre-validation
    if (!validateForm()) {
      return
    }
    
    // Transform data if needed
    const submitData = transformFormData(form.data())
    
    // Submit with custom options
    await form.post('/customers', {
      data: submitData,
      onStart: () => {
        showLoading.value = true
      },
      onFinish: () => {
        showLoading.value = false
      },
      preserveScroll: true,
      onSuccess: (page) => {
        // Access response data
        const customerId = page.props.customer?.id
        if (customerId) {
          router.visit(`/customers/${customerId}`)
        }
      },
      onError: (errors) => {
        // Handle specific errors
        if (errors.email) {
          showEmailError.value = true
        }
      }
    })
  } catch (error) {
    console.error('Submission error:', error)
    showErrorNotification.value = true
  }
}

// Form reset
const resetForm = (): void => {
  form.reset()
  form.clearErrors()
}

// File uploads
const handleFileUpload = (event: Event): void => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  
  if (file) {
    form.set_data('avatar', file)
  }
}

// Multi-step form
const currentStep = ref(1)
const totalSteps = 3

const nextStep = (): void => {
  if (currentStep.value < totalSteps) {
    currentStep.value++
  }
}

const previousStep = (): void => {
  if (currentStep.value > 1) {
    currentStep.value--
  }
}

const submitStep = (): void => {
  const stepData = getStepData(currentStep.value)
  
  form.post(`/customers/step/${currentStep.value}`, {
    data: stepData,
    onSuccess: () => {
      if (currentStep.value < totalSteps) {
        nextStep()
      } else {
        // Complete form submission
        submitCompleteForm()
      }
    }
  })
}
</script>
```

### 3. Form Component Template

```vue
<!-- components/forms/CustomerForm.vue -->
<template>
  <form @submit.prevent="handleSubmit">
    <slot name="fields" :form="form" :errors="form.errors" />
    
    <div class="form-actions">
      <Button
        v-if="showCancel"
        type="button"
        label="Cancel"
        severity="secondary"
        @click="handleCancel"
      />
      <Button
        type="submit"
        :label="submitLabel"
        :loading="form.processing"
        :disabled="!isValid"
      />
    </div>
  </form>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

interface Props {
  initialData?: Record<string, any>
  submitUrl: string
  submitMethod?: 'post' | 'put' | 'patch'
  submitLabel?: string
  showCancel?: boolean
  cancelUrl?: string
  validateOnSubmit?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  submitMethod: 'post',
  submitLabel: 'Save',
  showCancel: true,
  validateOnSubmit: true
})

interface Emits {
  (e: 'submit', data: Record<string, any>): void
  (e: 'cancel'): void
  (e: 'success', response: any): void
  (e: 'error', errors: Record<string, any>): void
}

const emit = defineEmits<Emits>()

const form = useForm({
  ...props.initialData
})

const isValid = computed((): boolean => {
  // Implement validation logic
  return true
})

const handleSubmit = (): void => {
  if (!isValid.value && props.validateOnSubmit) {
    return
  }
  
  const submitAction = () => {
    switch (props.submitMethod) {
      case 'post':
        return form.post(props.submitUrl)
      case 'put':
        return form.put(props.submitUrl)
      case 'patch':
        return form.patch(props.submitUrl)
      default:
        return form.post(props.submitUrl)
    }
  }
  
  submitAction()
    .then(() => {
      emit('success', form.data)
      emit('submit', form.data)
    })
    .catch((errors) => {
      emit('error', errors)
    })
}

const handleCancel = (): void => {
  if (props.cancelUrl) {
    router.visit(props.cancelUrl)
  } else {
    emit('cancel')
  }
}

// Expose form for parent access
defineExpose({
  form,
  reset: () => {
    form.reset()
    form.clearErrors()
  },
  setErrors: (errors: Record<string, any>) => {
    form.setError(errors)
  }
})
</script>
```

## State Management

### 1. Page Props Pattern

```vue
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()

// Type-safe page props access
const user = computed((): User | null => page.props.auth?.user as User | null)
const company = computed((): Company | null => page.props.current_company as Company | null)
const permissions = computed((): string[] => page.props.permissions as string[])

// Reactive computed properties
const canManageUsers = computed((): boolean => {
  return permissions.value.includes('manage-users')
})

const isCompanyAdmin = computed((): boolean => {
  return company.value?.users?.some(u => 
    u.id === user.value?.id && u.pivot?.role === 'admin'
  ) ?? false
})

// Memoized calculations
const displayName = computed((): string => {
  return user.value?.name || user.value?.email || 'Unknown User'
})
</script>
```

### 2. Shared State Composables

```typescript
// composables/useAuth.ts
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function useAuth() {
  const page = usePage()
  
  const user = computed((): User | null => page.props.auth?.user as User | null)
  const company = computed((): Company | null => page.props.current_company as Company | null)
  
  const isLoggedIn = computed((): boolean => !!user.value)
  const isGuest = computed((): boolean => !user.value)
  
  const userRole = computed((): string => user.value?.system_role || 'guest')
  const companyRole = computed((): string => {
    if (!company.value || !user.value) return 'guest'
    
    const userCompany = company.value.users?.find(u => u.id === user.value.id)
    return userCompany?.pivot?.role || 'guest'
  })
  
  const hasPermission = (permission: string): boolean => {
    const permissions = page.props.permissions as string[]
    return permissions.includes(permission)
  }
  
  const can = (action: string): boolean => {
    return hasPermission(`${action}`)
  }
  
  const switchCompany = (companyId: string): void => {
    router.patch(`/switch-company/${companyId}`)
  }
  
  return {
    user,
    company,
    isLoggedIn,
    isGuest,
    userRole,
    companyRole,
    hasPermission,
    can,
    switchCompany
  }
}
```

### 3. Local Component State

```vue
<script setup lang="ts">
import { ref, computed, watch } from 'vue'

// Local state management
const localState = ref({
  sidebarOpen: false,
  searchQuery: '',
  selectedItems: [] as string[],
  filters: {
    status: '',
    dateRange: null as [Date, Date] | null
  }
})

// Computed properties
const hasSearchQuery = computed((): boolean => localState.value.searchQuery.trim().length > 0)
const selectedCount = computed((): number => localState.value.selectedItems.length)
const hasFilters = computed((): boolean => {
  return localState.value.filters.status !== '' || 
         localState.value.filters.dateRange !== null
})

// Watchers
watch(() => localState.value.searchQuery, (newQuery) => {
  // Debounce search
  clearTimeout(searchTimeout.value)
  searchTimeout.value = setTimeout(() => {
    performSearch(newQuery)
  }, 300)
})

watch(() => localState.value.filters, (newFilters) => {
  // Apply filters
  applyFilters(newFilters)
}, { deep: true })

// Methods
const toggleSidebar = (): void => {
  localState.value.sidebarOpen = !localState.value.sidebarOpen
}

const selectItem = (id: string): void => {
  if (localState.value.selectedItems.includes(id)) {
    localState.value.selectedItems = localState.value.selectedItems.filter(item => item !== id)
  } else {
    localState.value.selectedItems.push(id)
  }
}

const clearSelection = (): void => {
  localState.value.selectedItems = []
}

const resetFilters = (): void => {
  localState.value.filters = {
    status: '',
    dateRange: null
  }
}
</script>
```

## Error Handling

### 1. Global Error Handling

```typescript
// app.ts
import { router, Head } from '@inertiajs/vue3'

// Global error handling
router.on('error', (errors) => {
  console.error('Navigation error:', errors)
  
  // Handle specific error types
  if (errors.response?.status === 401) {
    // Unauthorized - redirect to login
    router.visit('/login')
  } else if (errors.response?.status === 403) {
    // Forbidden - show access denied message
    showNotification('Access denied', 'error')
  } else if (errors.response?.status === 404) {
    // Not found - show custom 404 page
    router.visit('/404')
  }
})

// Global progress handling
router.on('start', () => {
  showProgressBar.value = true
})

router.on('finish', () => {
  showProgressBar.value = false
})
```

### 2. Component-Level Error Handling

```vue
<script setup lang="ts">
import { ref, onErrorCaptured } from 'vue'

const error = ref<string | null>(null)
const hasError = ref(false)

// Error boundary
onErrorCaptured((err, instance, info) => {
  console.error('Component error:', err, info)
  error.value = err.message
  hasError.value = true
  
  // Report error to monitoring service
  reportError(err, instance, info)
  
  // Prevent error from propagating
  return false
})

const handleError = (err: Error | string): void => {
  const errorMessage = typeof err === 'string' ? err : err.message
  error.value = errorMessage
  hasError.value = true
  
  // Show error notification
  showErrorNotification(errorMessage)
}

const clearError = (): void => {
  error.value = null
  hasError.value = false
}
</script>

<template>
  <div>
    <!-- Error state -->
    <div v-if="hasError" class="error-boundary">
      <h3>Something went wrong</h3>
      <p>{{ error }}</p>
      <Button @click="clearError">Try Again</Button>
    </div>
    
    <!-- Normal content -->
    <div v-else>
      <slot />
    </div>
  </div>
</template>
```

### 3. API Error Handling

```vue
<script setup lang="ts">
const apiErrors = ref<Record<string, string[]>>({})
const isLoading = ref(false)

const fetchData = async (url: string): Promise<any> => {
  isLoading.value = true
  apiErrors.value = {}
  
  try {
    const response = await fetch(url)
    
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      
      if (response.status === 422) {
        // Validation errors
        apiErrors.value = errorData.errors || {}
        throw new Error('Validation failed')
      } else if (response.status >= 500) {
        // Server errors
        throw new Error('Server error. Please try again later.')
      } else {
        // Other HTTP errors
        throw new Error(errorData.message || `HTTP ${response.status}`)
      }
    }
    
    return response.json()
  } catch (error) {
    handleError(error)
    throw error
  } finally {
    isLoading.value = false
  }
}

const getFieldError = (field: string): string => {
  const errors = apiErrors.value[field]
  return errors ? errors[0] : ''
}

const hasFieldError = (field: string): boolean => {
  return !!apiErrors.value[field]
}
</script>

<template>
  <form @submit.prevent="handleSubmit">
    <div class="field">
      <label for="name">Name</label>
      <InputText
        id="name"
        v-model="form.name"
        :class="{ 'p-invalid': hasFieldError('name') }"
      />
      <small class="text-red-500">{{ getFieldError('name') }}</small>
    </div>
  </form>
</template>
```

## Loading States

### 1. Page-Level Loading

```vue
<script setup lang="ts">
import { ref } from 'vue'

const isLoading = ref(false)
const progressPercentage = ref(0)

const loadData = async (): Promise<void> => {
  isLoading.value = true
  progressPercentage.value = 0
  
  try {
    progressPercentage.value = 25
    const data = await fetchPageData()
    
    progressPercentage.value = 75
    const additionalData = await fetchAdditionalData()
    
    progressPercentage.value = 100
    
    // Process data
    processData(data, additionalData)
  } catch (error) {
    handleError(error)
  } finally {
    isLoading.value = false
    setTimeout(() => {
      progressPercentage.value = 0
    }, 500)
  }
}
</script>

<template>
  <div>
    <!-- Loading overlay -->
    <div v-if="isLoading" class="loading-overlay">
      <div class="loading-content">
        <ProgressSpinner />
        <p>{{ progressPercentage }}%</p>
        <ProgressBar :value="progressPercentage" />
      </div>
    </div>
    
    <!-- Content -->
    <div v-else>
      <slot />
    </div>
  </div>
</template>

<style scoped>
.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.loading-content {
  text-align: center;
}
</style>
```

### 2. Component-Level Loading

```vue
<template>
  <div>
    <!-- Skeleton loading -->
    <div v-if="loading" class="skeleton-loader">
      <div class="skeleton-item skeleton-header"></div>
      <div class="skeleton-item skeleton-text"></div>
      <div class="skeleton-item skeleton-text"></div>
    </div>
    
    <!-- Content with inline loading -->
    <div v-else class="content">
      <div :class="{ 'opacity-50': processing }">
        <slot />
      </div>
      
      <!-- Processing overlay -->
      <div v-if="processing" class="processing-overlay">
        <ProgressSpinner strokeWidth="4" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.skeleton-loader {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.skeleton-item {
  background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: loading 1.5s infinite;
}

.skeleton-header {
  height: 32px;
  width: 60%;
  margin-bottom: 16px;
  border-radius: 4px;
}

.skeleton-text {
  height: 16px;
  margin-bottom: 12px;
  border-radius: 4px;
}

@keyframes loading {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.processing-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
```

## Performance Optimization

### 1. Preserve State Options

```vue
<script setup lang="ts">
import { router } from '@inertiajs/vue3'

// Preserve scroll position
const navigateWithScroll = (url: string): void => {
  router.visit(url, {
    preserveScroll: true
  })
}

// Preserve component state
const navigateWithState = (url: string): void => {
  router.visit(url, {
    preserveState: true
  })
}

// Combination
const navigateOptimized = (url: string): void => {
  router.visit(url, {
    preserveScroll: true,
    preserveState: true
  })
}
</script>
```

### 2. Partial Page Reloads

```vue
<script setup lang="ts">
import { router } from '@inertiajs/vue3'

// Only reload specific components
const refreshData = (): void => {
  router.reload({ only: ['users', 'companies'] })
}

// Reload data without full page navigation
const refreshPageData = (): void => {
  router.reload({
    onSuccess: () => {
      showSuccessNotification('Data refreshed')
    }
  })
}

// Partial reload with custom headers
const refreshWithAuth = (): void => {
  router.reload({
    headers: {
      'X-Custom-Header': 'value'
    }
  })
}
</script>
```

### 3. Caching Strategies

```vue
<script setup lang="ts">
import { ref, computed } from 'vue'

// Client-side caching
const cache = ref<Record<string, any>>({})

const fetchWithCache = async (url: string, cacheKey?: string): Promise<any> => {
  const key = cacheKey || url
  
  if (cache.value[key]) {
    return cache.value[key]
  }
  
  const data = await fetch(url).then(res => res.json())
  cache.value[key] = data
  
  return data
}

// Cache invalidation
const invalidateCache = (key?: string): void => {
  if (key) {
    delete cache.value[key]
  } else {
    cache.value = {}
  }
}

// Memoized computed properties
const expensiveComputation = computed((): any => {
  // Expensive calculation
  return calculateSomething(data.value)
})
</script>
```

## Reusable Components

### 1. SmartLink Component

```vue
<!-- components/SmartLink.vue -->
<template>
  <component
    :is="componentType"
    :href="href"
    :method="method"
    :data="data"
    :class="buttonClasses"
    :disabled="disabled"
    @click="handleClick"
  >
    <slot />
  </component>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import Button from 'primevue/button'

interface Props {
  href: string
  method?: 'get' | 'post' | 'put' | 'patch' | 'delete'
  data?: Record<string, any>
  as?: 'link' | 'button'
  variant?: 'primary' | 'secondary' | 'danger' | 'text'
  size?: 'small' | 'normal' | 'large'
  disabled?: boolean
  loading?: boolean
  preserveScroll?: boolean
  preserveState?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  method: 'get',
  as: 'button',
  variant: 'primary',
  size: 'normal',
  disabled: false,
  loading: false,
  preserveScroll: false,
  preserveState: false
})

const componentType = computed(() => {
  return props.as === 'link' ? Link : Button
})

const buttonClasses = computed(() => {
  const classes = []
  
  if (props.as === 'button') {
    classes.push('p-button')
    
    if (props.variant !== 'primary') {
      classes.push(`p-button-${props.variant}`)
    }
    
    if (props.size !== 'normal') {
      classes.push(`p-button-${props.size}`)
    }
  }
  
  return classes.join(' ')
})

const handleClick = (event: MouseEvent): void => {
  if (props.disabled || props.loading) {
    event.preventDefault()
    return
  }
  
  if (props.as === 'button' && props.method !== 'get') {
    event.preventDefault()
    
    router.visit(props.href, {
      method: props.method,
      data: props.data,
      preserveScroll: props.preserveScroll,
      preserveState: props.preserveState
    })
  }
}
</script>
```

### 2. FormSubmission Component

```vue
<!-- components/FormSubmission.vue -->
<template>
  <div class="form-submission">
    <slot :submit="submit" :loading="loading" :errors="errors" />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

interface Props {
  url: string
  method?: 'post' | 'put' | 'patch'
  data?: Record<string, any>
  onSuccess?: (response: any) => void
  onError?: (errors: Record<string, any>) => void
  onFinish?: () => void
}

const props = defineProps<Props>()

const loading = ref(false)
const errors = ref<Record<string, any>>({})

const submit = async (formData: Record<string, any>): Promise<void> => {
  loading.value = true
  errors.value = {}
  
  try {
    const submitData = { ...props.data, ...formData }
    
    const response = await router.visit(props.url, {
      method: props.method,
      data: submitData,
      onSuccess: (page) => {
        props.onSuccess?.(page)
      },
      onError: (pageErrors) => {
        errors.value = pageErrors
        props.onError?.(pageErrors)
      },
      onFinish: () => {
        loading.value = false
        props.onFinish?.()
      }
    })
  } catch (error) {
    loading.value = false
    console.error('Submission error:', error)
  }
}
</script>
```

## Security Considerations

### 1. CSRF Protection

```vue
<script setup lang="ts">
// Inertia.js automatically includes CSRF tokens
// Just ensure forms use proper method types

const submitForm = (): void => {
  form.post('/endpoint', {
    // CSRF token is automatically included
    // Ensure you use POST, PUT, PATCH, or DELETE methods
  })
}

// Manual CSRF token (if needed)
const getCsrfToken = (): string => {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}
</script>
```

### 2. Authorization Checks

```vue
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()

const user = computed(() => page.props.auth?.user)
const permissions = computed(() => page.props.permissions || [])

const can = (permission: string): boolean => {
  return permissions.value.includes(permission)
}

const isOwner = computed(() => {
  return user.value?.system_role === 'system_owner'
})

const canEditResource = (resourceOwnerId: string): boolean => {
  return isOwner.value || 
         user.value?.id === resourceOwnerId || 
         can('edit-all-resources')
})

// Authorization component
const AuthGuard = {
  require: (permission: string) => {
    if (!can(permission)) {
      router.visit('/unauthorized')
      return false
    }
    return true
  },
  
  requireRole: (role: string) => {
    if (user.value?.system_role !== role) {
      router.visit('/forbidden')
      return false
    }
    return true
  }
}
</script>

<template>
  <div>
    <!-- Only show if user has permission -->
    <Button 
      v-if="can('manage-users')"
      label="Manage Users"
    />
    
    <!-- Show different content based on role -->
    <div v-if="isOwner">
      <!-- Admin content -->
    </div>
    <div v-else>
      <!-- User content -->
    </div>
  </div>
</template>
```

### 3. Data Sanitization

```vue
<script setup lang="ts">
// Sanitize user input before display
const sanitizeHtml = (html: string): string => {
  // Basic HTML sanitization
  return html
    .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
    .replace(/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi, '')
}

// Escape HTML for display
const escapeHtml = (text: string): string => {
  const div = document.createElement('div')
  div.textContent = text
  return div.innerHTML
}

// Validate URLs
const isValidUrl = (url: string): boolean => {
  try {
    new URL(url)
    return true
  } catch {
    return false
  }
}

// Safe data binding
const safeContent = computed(() => {
  return sanitizeHtml(userContent.value)
})
</script>

<template>
  <!-- Safe HTML rendering -->
  <div v-html="safeContent"></div>
  
  <!-- Safe text rendering -->
  <div>{{ escapeHtml(userInput) }}</div>
  
  <!-- Safe link rendering -->
  <a 
    v-if="isValidUrl(userUrl)"
    :href="userUrl"
    target="_blank"
    rel="noopener noreferrer"
  >
    {{ userUrl }}
  </a>
  <span v-else>{{ userUrl }}</span>
</template>
```

## Testing Patterns

### 1. Component Testing

```typescript
// tests/Components/SmartLink.test.ts
import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import SmartLink from '@/components/SmartLink.vue'

describe('SmartLink', () => {
  it('renders as Link component by default', () => {
    const wrapper = mount(SmartLink, {
      props: {
        href: '/test'
      }
    })
    
    expect(wrapper.findComponent(Link).exists()).toBe(true)
  })
  
  it('renders as Button when as="button"', () => {
    const wrapper = mount(SmartLink, {
      props: {
        href: '/test',
        as: 'button'
      }
    })
    
    expect(wrapper.findComponent(Button).exists()).toBe(true)
  })
  
  it('handles POST method correctly', async () => {
    const wrapper = mount(SmartLink, {
      props: {
        href: '/test',
        method: 'post',
        as: 'button'
      }
    })
    
    await wrapper.find('button').trigger('click')
    
    // Verify router.visit was called with correct options
    expect(router.visit).toHaveBeenCalledWith('/test', {
      method: 'post'
    })
  })
})
```

### 2. Navigation Testing

```typescript
// tests/Navigation.test.ts
import { mount } from '@vue/test-utils'
import { describe, it, expect, vi } from 'vitest'
import { router } from '@inertiajs/vue3'

vi.mock('@inertiajs/vue3')

describe('Navigation', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })
  
  it('navigates to correct URL', () => {
    router.visit('/test')
    expect(router.visit).toHaveBeenCalledWith('/test')
  })
  
  it('preserves scroll position', () => {
    router.visit('/test', {
      preserveScroll: true
    })
    
    expect(router.visit).toHaveBeenCalledWith('/test', {
      preserveScroll: true
    })
  })
  
  it('handles form submission', () => {
    const form = useForm({ name: 'Test' })
    form.post('/test', {
      onSuccess: vi.fn(),
      onError: vi.fn()
    })
    
    expect(form.post).toHaveBeenCalled()
  })
})
```

## Validation Checklist

### Navigation
- [ ] No `window.location.href` used in components
- [ ] `<Link>` used for standard navigation
- [ ] `router.visit()` used for programmatic navigation
- [ ] Preserve scroll/state options used appropriately
- [ ] Loading states handled during navigation

### Forms
- [ ] `useForm` used for form handling
- [ ] Proper validation on both client and server
- [ ] CSRF protection maintained
- [ ] Error states handled gracefully
- [ ] Loading states shown during submission

### State Management
- [ ] Page props accessed through `usePage()`
- [ ] Local state managed properly
- [ ] Shared state moved to composables
- [ ] Reactive dependencies optimized

### Error Handling
- [ ] Global error handling implemented
- [ ] Component-level error boundaries
- [ ] User-friendly error messages
- [ ] Error recovery mechanisms

### Performance
- [ ] Unnecessary re-renders avoided
- [ ] State preservation used appropriately
- [ ] Partial reloads used when possible
- [ ] Client-side caching implemented

### Security
- [ ] CSRF tokens included in forms
- [ ] Authorization checks implemented
- [ ] Input sanitization performed
- [ ] XSS prevention measures in place

### Testing
- [ ] Components tested for navigation behavior
- [ ] Form submission scenarios covered
- [ ] Error conditions tested
- [ ] Loading states verified

## Conclusion

These best practices ensure that your Inertia.js v2 application is maintainable, performant, and provides an excellent user experience. Regular code reviews should check compliance with these guidelines to maintain consistency and quality across the application.

Remember that Inertia.js is a tool to enhance the user experience, not to replace good server-side practices. Always validate and authorize actions on the server, regardless of client-side validation.