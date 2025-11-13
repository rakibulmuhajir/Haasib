# PrimeVue Component Standards & Best Practices

This guide documents the consistent usage patterns, conventions, and best practices for PrimeVue v4 components in the Haasib application.

## Table of Contents
1. [Core Principles](#core-principles)
2. [Component Standards](#component-standards)
3. [Form Patterns](#form-patterns)
4. [Validation Integration](#validation-integration)
5. [Theming & Styling](#theming--styling)
6. [Accessibility Standards](#accessibility-standards)
7. [Common Patterns](#common-patterns)
8. [Performance Guidelines](#performance-guidelines)

## Core Principles

### 1. Consistency First
- All components should follow the same prop naming conventions
- Use consistent sizing and spacing across the application
- Maintain uniform color schemes and styling patterns

### 2. Accessibility by Default
- All interactive elements must have proper ARIA labels
- Keyboard navigation should work consistently
- Focus states must be clearly visible

### 3. Mobile-First Responsive Design
- Components should work seamlessly on all screen sizes
- Touch targets should be appropriately sized (minimum 44px)
- Responsive behavior should be tested across devices

### 4. Performance Awareness
- Use component lazy loading for heavy components
- Implement proper debouncing for search inputs
- Avoid unnecessary re-renders with proper key management

## Component Standards

### Button Components

#### Standard Button Usage
```vue
<template>
  <!-- Primary action -->
  <Button
    label="Save"
    icon="pi pi-save"
    severity="primary"
    @click="handleSave"
  />
  
  <!-- Secondary action -->
  <Button
    label="Cancel"
    severity="secondary"
    @click="handleCancel"
  />
  
  <!-- Danger action -->
  <Button
    label="Delete"
    icon="pi pi-trash"
    severity="danger"
    @click="handleDelete"
  />
  
  <!-- Text button -->
  <Button
    label="Edit"
    icon="pi pi-pencil"
    severity="secondary"
    text
    @click="handleEdit"
  />
</template>
```

#### Button Best Practices
- Always use `severity` prop for semantic styling
- Include icons for better visual hierarchy
- Use `loading` prop for async operations
- Apply `disabled` prop when actions are not available

```vue
<template>
  <!-- Async operation with loading state -->
  <Button
    label="Processing"
    :loading="isProcessing"
    severity="primary"
    @click="handleProcess"
  />
  
  <!-- Disabled state -->
  <Button
    label="Save"
    :disabled="!isFormValid"
    severity="primary"
    @click="handleSave"
  />
</template>
```

### Input Components

#### Text Input Standards
```vue
<template>
  <!-- Standard text input -->
  <div class="field">
    <label for="name">Name <span class="text-red-500">*</span></label>
    <InputText
      id="name"
      v-model="form.name"
      class="w-full"
      :class="{ 'p-invalid': errors.name }"
      placeholder="Enter your name"
    />
    <small class="text-red-500">{{ errors.name }}</small>
  </div>
  
  <!-- Required field with validation -->
  <div class="field">
    <label for="email">Email Address <span class="text-red-500">*</span></label>
    <InputText
      id="email"
      v-model="form.email"
      type="email"
      class="w-full"
      :class="{ 'p-invalid': errors.email }"
      placeholder="you@example.com"
    />
    <small class="text-red-500">{{ errors.email }}</small>
  </div>
</template>
```

#### Dropdown Standards
```vue
<template>
  <!-- Standard dropdown -->
  <div class="field">
    <label for="status">Status</label>
    <Dropdown
      id="status"
      v-model="form.status"
      :options="statusOptions"
      option-label="label"
      option-value="value"
      class="w-full"
      placeholder="Select status"
      :class="{ 'p-invalid': errors.status }"
    />
    <small class="text-red-500">{{ errors.status }}</small>
  </div>
  
  <!-- With clear button -->
  <Dropdown
    v-model="form.category"
    :options="categories"
    option-label="name"
    option-value="id"
    class="w-full"
    placeholder="Select category"
    showClear
  />
</template>
```

### Form Layout Standards

#### Responsive Form Grid
```vue
<template>
  <form @submit.prevent="handleSubmit" class="space-y-6">
    <!-- Two-column grid on desktop, single on mobile -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="field">
        <label for="firstName">First Name</label>
        <InputText
          id="firstName"
          v-model="form.firstName"
          class="w-full"
        />
      </div>
      
      <div class="field">
        <label for="lastName">Last Name</label>
        <InputText
          id="lastName"
          v-model="form.lastName"
          class="w-full"
        />
      </div>
    </div>
    
    <!-- Three-column grid for less critical fields -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="field">
        <label for="city">City</label>
        <InputText
          id="city"
          v-model="form.city"
          class="w-full"
        />
      </div>
      
      <div class="field">
        <label for="state">State</label>
        <Dropdown
          id="state"
          v-model="form.state"
          :options="states"
          option-label="name"
          option-value="code"
          class="w-full"
        />
      </div>
      
      <div class="field">
        <label for="zipCode">ZIP Code</label>
        <InputText
          id="zipCode"
          v-model="form.zipCode"
          class="w-full"
        />
      </div>
    </div>
  </form>
</template>
```

### Data Display Components

#### DataTable Standards
```vue
<template>
  <DataTable
    :value="customers"
    :loading="loading"
    :paginator="true"
    :rows="10"
    :rowsPerPageOptions="[10, 20, 50]"
    :filters="filters"
    :globalFilterFields="['name', 'email', 'status']"
    dataKey="id"
    class="p-datatable-sm"
    responsiveLayout="scroll"
  >
    <!-- Header -->
    <template #header>
      <div class="flex justify-between items-center">
        <h5 class="m-0">Customer Management</h5>
        <div class="flex gap-2">
          <Button
            label="Export"
            icon="pi pi-download"
            severity="secondary"
            size="small"
            @click="exportData"
          />
          <Button
            label="Add Customer"
            icon="pi pi-plus"
            severity="primary"
            size="small"
            @click="showAddDialog = true"
          />
        </div>
      </div>
    </template>
    
    <!-- Columns -->
    <Column field="name" header="Name" sortable>
      <template #body="{ data }">
        {{ data.name }}
      </template>
    </Column>
    
    <Column field="email" header="Email" sortable>
      <template #body="{ data }">
        <a :href="`mailto:${data.email}`" class="text-blue-600 hover:underline">
          {{ data.email }}
        </a>
      </template>
    </Column>
    
    <Column field="status" header="Status" sortable>
      <template #body="{ data }">
        <Tag
          :value="data.status"
          :severity="getStatusSeverity(data.status)"
        />
      </template>
    </Column>
    
    <Column header="Actions" :exportable="false">
      <template #body="{ data }">
        <div class="flex gap-2">
          <Button
            icon="pi pi-pencil"
            severity="secondary"
            size="small"
            text
            @click="editCustomer(data)"
          />
          <Button
            icon="pi pi-trash"
            severity="danger"
            size="small"
            text
            @click="deleteCustomer(data)"
          />
        </div>
      </template>
    </Column>
  </DataTable>
</template>
```

## Form Patterns

### 1. Reusable Form Component Template

```vue
<!-- components/forms/BaseForm.vue -->
<template>
  <form @submit.prevent="handleSubmit" class="space-y-6">
    <div v-if="title" class="mb-6">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
        {{ title }}
      </h2>
      <p v-if="description" class="text-sm text-gray-600 dark:text-gray-400 mt-1">
        {{ description }}
      </p>
    </div>
    
    <div class="space-y-6">
      <slot />
    </div>
    
    <!-- Form Actions -->
    <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200 dark:border-gray-700">
      <Button
        v-if="showCancel"
        type="button"
        :label="cancelLabel"
        severity="secondary"
        :disabled="processing"
        @click="$emit('cancel')"
      />
      <Button
        type="submit"
        :label="submitLabel"
        :loading="processing"
        :disabled="!isValid"
        :severity="submitSeverity"
        icon="pi pi-save"
      />
    </div>
  </form>
</template>

<script setup lang="ts">
interface Props {
  title?: string
  description?: string
  submitLabel?: string
  cancelLabel?: string
  submitSeverity?: 'primary' | 'secondary' | 'danger'
  showCancel?: boolean
  processing?: boolean
  isValid?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  submitLabel: 'Save',
  cancelLabel: 'Cancel',
  submitSeverity: 'primary',
  showCancel: true,
  processing: false,
  isValid: true
})

interface Emits {
  (e: 'submit'): void
  (e: 'cancel'): void
}

defineEmits<Emits>()

const handleSubmit = (): void => {
  if (props.isValid && !props.processing) {
    emit('submit')
  }
}
</script>
```

### 2. Field Wrapper Component

```vue
<!-- components/forms/FormField.vue -->
<template>
  <div class="field" :class="{ 'field-error': hasError }">
    <label v-if="label" :for="fieldId" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>
    
    <slot />
    
    <div v-if="hasError || hint" class="mt-1">
      <small v-if="hasError" class="text-red-500">
        {{ errorMessage }}
      </small>
      <small v-else-if="hint" class="text-gray-500 dark:text-gray-400">
        {{ hint }}
      </small>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Props {
  label?: string
  fieldId?: string
  required?: boolean
  error?: string
  hint?: string
}

const props = withDefaults(defineProps<Props>(), {
  required: false
})

const hasError = computed((): boolean => !!props.error)
const errorMessage = computed((): string => props.error || '')
</script>
```

## Validation Integration

### 1. Form Validation Composable

```typescript
// composables/useFormValidation.ts
import { ref, computed } from 'vue'

interface ValidationRule {
  validator: (value: any) => boolean
  message: string
}

interface ValidationRules {
  [field: string]: ValidationRule[]
}

export function useFormValidation<T extends Record<string, any>>(
  data: T,
  rules: ValidationRules
) {
  const errors = ref<Record<string, string[]>>({})
  const touched = ref<Record<string, boolean>>({})

  const validateField = (field: keyof T): boolean => {
    touched.value[field as string] = true
    
    const fieldRules = rules[field as string]
    if (!fieldRules) return true
    
    const fieldErrors: string[] = []
    
    fieldRules.forEach(rule => {
      if (!rule.validator(data[field])) {
        fieldErrors.push(rule.message)
      }
    })
    
    if (fieldErrors.length > 0) {
      errors.value[field as string] = fieldErrors
      return false
    } else {
      delete errors.value[field as string]
      return true
    }
  }

  const validateAll = (): boolean => {
    let isValid = true
    
    Object.keys(rules).forEach(field => {
      if (!validateField(field as keyof T)) {
        isValid = false
      }
    })
    
    return isValid
  }

  const clearErrors = (): void => {
    errors.value = {}
    touched.value = {}
  }

  const hasErrors = computed((): boolean => Object.keys(errors.value).length > 0)
  
  const getFieldError = (field: keyof T): string => {
    const fieldErrors = errors.value[field as string]
    return fieldErrors ? fieldErrors[0] : ''
  }

  const isFieldTouched = (field: keyof T): boolean => {
    return touched.value[field as string] || false
  }

  const isFieldInvalid = (field: keyof T): boolean => {
    return isFieldTouched(field) && !!getFieldError(field)
  }

  return {
    errors,
    touched,
    validateField,
    validateAll,
    clearErrors,
    hasErrors,
    getFieldError,
    isFieldTouched,
    isFieldInvalid
  }
}

// Usage example
const validationRules = {
  name: [
    { validator: (value) => value.trim().length > 0, message: 'Name is required' },
    { validator: (value) => value.trim().length >= 2, message: 'Name must be at least 2 characters' }
  ],
  email: [
    { validator: (value) => value.trim().length > 0, message: 'Email is required' },
    { validator: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value), message: 'Please enter a valid email' }
  ]
}
```

### 2. Integration with PrimeVue

```vue
<template>
  <FormField
    label="Email Address"
    field-id="email"
    :required="true"
    :error="getFieldError('email')"
    hint="We'll never share your email with anyone else"
  >
    <InputText
      id="email"
      v-model="form.email"
      class="w-full"
      :class="{ 'p-invalid': isFieldInvalid('email') }"
      @blur="validateField('email')"
    />
  </FormField>
</template>

<script setup lang="ts">
import { useFormValidation } from '@/composables/useFormValidation'

const form = ref({
  email: '',
  name: ''
})

const validationRules = {
  email: [
    { validator: (value) => value.trim().length > 0, message: 'Email is required' },
    { validator: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value), message: 'Please enter a valid email' }
  ]
}

const {
  validateField,
  getFieldError,
  isFieldInvalid
} = useFormValidation(form.value, validationRules)
</script>
```

## Theming & Styling

### 1. Color Scheme Standards

```css
/* Primary color scheme */
:root {
  --primary-50: #eff6ff;
  --primary-500: #3b82f6;
  --primary-600: #2563eb;
  --primary-700: #1d4ed8;
}

/* Semantic colors */
:root {
  --success-500: #10b981;
  --warning-500: #f59e0b;
  --danger-500: #ef4444;
  --info-500: #06b6d4;
}

/* Dark mode support */
.dark {
  --primary-500: #60a5fa;
  --primary-600: #3b82f6;
  --primary-700: #2563eb;
}
```

### 2. Spacing Standards

```css
/* Consistent spacing scale */
.space-y-1 > * + * { margin-top: 0.25rem; }
.space-y-2 > * + * { margin-top: 0.5rem; }
.space-y-3 > * + * { margin-top: 0.75rem; }
.space-y-4 > * + * { margin-top: 1rem; }
.space-y-6 > * + * { margin-top: 1.5rem; }

/* Gap utilities for flexbox and grid */
.gap-2 { gap: 0.5rem; }
.gap-3 { gap: 0.75rem; }
.gap-4 { gap: 1rem; }
.gap-6 { gap: 1.5rem; }
```

### 3. Component-Specific Styling

```vue
<template>
  <div class="custom-card">
    <!-- Card content -->
  </div>
</template>

<style scoped>
.custom-card {
  @apply bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700;
  transition: all 0.2s ease-in-out;
}

.custom-card:hover {
  @apply shadow-md;
}

/* Dark mode adjustments */
@media (prefers-color-scheme: dark) {
  .custom-card:hover {
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
  }
}
</style>
```

## Accessibility Standards

### 1. Semantic HTML Structure

```vue
<template>
  <main role="main" aria-labelledby="page-title">
    <header>
      <h1 id="page-title">Customer Management</h1>
    </header>
    
    <section aria-labelledby="customer-list-heading">
      <h2 id="customer-list-heading" class="sr-only">Customer List</h2>
      <DataTable
        :value="customers"
        aria-label="Customer data table"
      >
        <!-- Table content -->
      </DataTable>
    </section>
  </main>
</template>
```

### 2. Screen Reader Support

```vue
<template>
  <!-- Status indicators with aria-labels -->
  <div
    :aria-label="`Status: ${customer.status}`"
    :class="getStatusClass(customer.status)"
    role="status"
  >
    {{ customer.status }}
  </div>
  
  <!-- Action buttons with descriptive labels -->
  <Button
    :aria-label="`Edit customer ${customer.name}`"
    icon="pi pi-pencil"
    severity="secondary"
    text
    @click="editCustomer(customer)"
  />
  
  <!-- Form validation with aria-describedby -->
  <InputText
    id="email"
    v-model="form.email"
    class="w-full"
    :class="{ 'p-invalid': errors.email }"
    aria-describedby="email-error email-help"
    aria-invalid="!!errors.email"
  />
  <small id="email-error" class="text-red-500">
    {{ errors.email }}
  </small>
  <small id="email-help" class="text-gray-500">
    We'll never share your email with anyone else
  </small>
</template>
```

### 3. Keyboard Navigation

```vue
<template>
  <!-- Focus management for modals -->
  <Dialog
    v-model:visible="showModal"
    :modal="true"
    :dismissableMask="false"
    @show="focusModal"
    @hide="focusTrigger"
  >
    <template #header>
      <h3>Edit Customer</h3>
    </template>
    
    <!-- Modal content with proper tab order -->
    <div class="space-y-4">
      <div class="field">
        <label for="modal-name">Name</label>
        <InputText
          id="modal-name"
          ref="firstInput"
          v-model="form.name"
          class="w-full"
        />
      </div>
      
      <div class="field">
        <label for="modal-email">Email</label>
        <InputText
          id="modal-email"
          v-model="form.email"
          class="w-full"
        />
      </div>
    </div>
    
    <template #footer>
      <Button
        ref="cancelButton"
        label="Cancel"
        severity="secondary"
        @click="showModal = false"
      />
      <Button
        label="Save"
        severity="primary"
        @click="saveCustomer"
      />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
const firstInput = ref()
const cancelButton = ref()

const focusModal = (): void => {
  nextTick(() => {
    firstInput.value?.$el.focus()
  })
}

const focusTrigger = (): void => {
  nextTick(() => {
    cancelButton.value?.$el.focus()
  })
}
</script>
```

## Common Patterns

### 1. Loading States

```vue
<template>
  <!-- Skeleton loading for data tables -->
  <DataTable
    :value="customers"
    :loading="loading"
    loadingIcon="pi pi-spinner"
  >
    <!-- Table columns -->
  </DataTable>
  
  <!-- Loading overlay for forms -->
  <div class="relative">
    <form @submit.prevent="handleSubmit">
      <!-- Form fields -->
    </form>
    
    <div
      v-if="processing"
      class="absolute inset-0 bg-white/50 dark:bg-gray-900/50 flex items-center justify-center"
    >
      <ProgressSpinner
        strokeWidth="4"
        animationDuration="1s"
      />
    </div>
  </div>
  
  <!-- Inline loading for buttons -->
  <Button
    label="Save"
    :loading="saving"
    severity="primary"
    @click="handleSave"
  />
</template>
```

### 2. Empty States

```vue
<template>
  <!-- Empty state for data tables -->
  <DataTable
    :value="customers"
    :paginator="false"
    class="p-datatable-sm"
  >
    <template #empty>
      <div class="text-center py-8">
        <i class="pi pi-users text-4xl text-gray-400 mb-3"></i>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
          No customers found
        </h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">
          Get started by creating your first customer.
        </p>
        <Button
          label="Add Customer"
          icon="pi pi-plus"
          severity="primary"
          @click="showAddDialog = true"
        />
      </div>
    </template>
  </DataTable>
</template>
```

### 3. Confirmation Dialogs

```vue
<template>
  <Dialog
    v-model:visible="showConfirmDialog"
    header="Confirm Action"
    :style="{ width: '450px' }"
    :modal="true"
  >
    <div class="flex items-center">
      <i class="pi pi-exclamation-triangle mr-3" style="font-size: 2rem; color: var(--orange-500)" />
      <span>
        Are you sure you want to {{ confirmAction }}?
        This action cannot be undone.
      </span>
    </div>
    
    <template #footer>
      <Button
        label="Cancel"
        severity="secondary"
        @click="showConfirmDialog = false"
      />
      <Button
        :label="confirmLabel"
        severity="danger"
        :loading="processing"
        @click="handleConfirm"
      />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
interface Props {
  showConfirmDialog: boolean
  confirmAction: string
  confirmLabel: string
  processing: boolean
}

const props = defineProps<Props>()

interface Emits {
  (e: 'confirm'): void
  (e: 'cancel'): void
}

const emit = defineEmits<Emits>()

const handleConfirm = (): void => {
  emit('confirm')
}
</script>
```

## Performance Guidelines

### 1. Component Lazy Loading

```vue
<template>
  <!-- Lazy load heavy components -->
  <Suspense>
    <template #default>
      <LazyHeavyComponent :data="complexData" />
    </template>
    <template #fallback>
      <div class="flex items-center justify-center p-8">
        <ProgressSpinner strokeWidth="4" />
      </div>
    </template>
  </Suspense>
</template>

<script setup lang="ts">
import { defineAsyncComponent } from 'vue'

const LazyHeavyComponent = defineAsyncComponent(() =>
  import('./HeavyComponent.vue')
)
</script>
```

### 2. Debounced Search

```vue
<template>
  <div class="relative">
    <i class="pi pi-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
    <InputText
      v-model="searchQuery"
      placeholder="Search customers..."
      class="w-full pl-10"
      @input="handleSearch"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { debounce } from 'lodash-es'

const searchQuery = ref('')
const emit = defineEmits<{
  (e: 'search', query: string): void
}>()

// Debounce search input
const debouncedSearch = debounce((query: string) => {
  emit('search', query)
}, 300)

const handleSearch = (event: Event): void => {
  const target = event.target as HTMLInputElement
  debouncedSearch(target.value)
}
</script>
```

### 3. Virtual Scrolling

```vue
<template>
  <!-- For large datasets -->
  <DataView
    :value="largeDataset"
    :layout="layout"
    :paginator="true"
    :rows="20"
    :lazy="true"
    @page="onPage"
  >
    <template #list="{ data }">
      <!-- Render only visible items -->
    </template>
  </DataView>
</template>
```

## Validation Checklist

### Component Standards
- [ ] Components use consistent prop naming
- [ ] Proper TypeScript interfaces are defined
- [ ] Loading states are implemented
- [ ] Error states are handled
- [ ] Empty states are provided

### Form Patterns
- [ ] Form validation is type-safe
- [ ] Error messages are clear and helpful
- [ ] Submit button shows loading state
- [ ] Form has proper accessibility labels

### PrimeVue Integration
- [ ] PrimeVue components are used consistently
- [ ] Custom styling follows theme standards
- [ ] Component props are properly typed
- [ ] Event handlers follow conventions

### Accessibility
- [ ] Semantic HTML is used correctly
- [ ] ARIA labels are provided where needed
- [ ] Keyboard navigation works properly
- [ ] Focus management is implemented

### Performance
- [ ] Heavy components are lazy loaded
- [ ] Search inputs are debounced
- [ ] Virtual scrolling is used for large datasets
- [ ] Unnecessary re-renders are avoided

## Conclusion

These standards ensure consistency, maintainability, and accessibility across the Haasib application. All developers should follow these guidelines when creating or modifying components that use PrimeVue.

Regular code reviews should check compliance with these standards to maintain code quality and user experience consistency.