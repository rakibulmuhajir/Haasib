# TypeScript Migration Guide for Haasib Frontend

This guide provides a step-by-step process for converting JavaScript Vue components to TypeScript, ensuring type safety and better development experience.

## Table of Contents
1. [Prerequisites](#prerequisites)
2. [Migration Process](#migration-process)
3. [Common Patterns](#common-patterns)
4. [Pitfalls & Solutions](#pitfalls--solutions)
5. [Best Practices](#best-practices)
6. [Validation Checklist](#validation-checklist)

## Prerequisites

### Required Knowledge
- Basic TypeScript syntax and types
- Vue 3 Composition API
- Understanding of interfaces and generics
- Familiarity with PrimeVue components

### Available Resources
- **Type Definitions**: `/resources/js/types/index.ts`
- **Utility Composable**: `/resources/js/composables/useTypes.ts`
- **Component Template**: `/resources/js/templates/TypeScriptComponentTemplate.vue`

## Migration Process

### Step 1: Prepare the Component

1. **Backup the original file**
   ```bash
   cp YourComponent.vue YourComponent.vue.backup
   ```

2. **Add TypeScript support**
   ```vue
   <!-- Change from -->
   <script setup>
   <!-- To -->
   <script setup lang="ts">
   ```

### Step 2: Add Type Imports

```vue
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { useForm } from '@inertiajs/vue3'
import { useTypes } from '@/composables/useTypes'
import type { Company, User, FormErrors } from '@/types'
```

### Step 3: Define Props Interface

```vue
<script setup lang="ts">
// Define props with proper typing
interface Props {
  company: Company
  editable?: boolean
  users?: User[]
}

const props = withDefaults(defineProps<Props>(), {
  editable: false,
  users: () => []
})
```

### Step 4: Define Emits Interface

```vue
<script setup lang="ts">
// Define emits with type safety
interface Emits {
  (e: 'update', company: Company): void
  (e: 'delete', id: string): void
  (e: 'save', data: FormData): void
}

const emit = defineEmits<Emits>()
```

### Step 5: Type Reactive State

```vue
<script setup lang="ts">
// Before (JavaScript)
const loading = ref(false)
const company = ref(null)

// After (TypeScript)
const loading = ref<boolean>(false)
const company = ref<Company | null>(null)

// Or with inferred types
const loading = ref(false)
const company = ref<Company | null>(null)
```

### Step 6: Type Form Data

```vue
<script setup lang="ts">
// Use useForm with typed interface
interface CompanyFormData {
  name: string
  email: string
  industry: string
  is_active: boolean
}

const form = useForm<CompanyFormData>({
  name: '',
  email: '',
  industry: '',
  is_active: true
})
```

### Step 7: Type Methods and Functions

```vue
<script setup lang="ts">
// Before
const saveCompany = async () => {
  // implementation
}

// After
const saveCompany = async (): Promise<void> => {
  // implementation
}

// With parameters and return type
const formatCurrency = (amount: number, currency: string = 'USD'): string => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency
  }).format(amount)
}
```

### Step 8: Update Event Handlers

```vue
<template>
  <!-- Before -->
  <button @click="handleClick(item.id)">
  
  <!-- After (with typed parameter) -->
  <button @click="handleClick(item.id)">
</template>

<script setup lang="ts">
const handleClick = (id: string): void => {
  console.log('Clicked item:', id)
}
</script>
```

## Common Patterns

### 1. API Response Handling

```typescript
// Typed API response
interface ApiResponse<T> {
  success: boolean
  data?: T
  message?: string
  errors?: FormErrors
}

const fetchData = async (): Promise<ApiResponse<Company[]>> => {
  const response = await fetch('/api/companies')
  return response.json()
}
```

### 2. Form Validation

```typescript
const validateForm = (): boolean => {
  const errors: FormErrors = {}
  
  if (!form.name.trim()) {
    errors.name = 'Name is required'
  }
  
  if (!form.email.includes('@')) {
    errors.email = 'Invalid email format'
  }
  
  return Object.keys(errors).length === 0
}
```

### 3. Computed Properties

```typescript
const isFormValid = computed((): boolean => {
  return validateForm() && !form.processing
})

const fullName = computed((): string => {
  return `${user.value.first_name} ${user.value.last_name}`
})
```

### 4. Event Emitters

```typescript
// Parent component
interface ChildEmits {
  (e: 'save', data: Company): void
  (e: 'cancel'): void
}

const emit = defineEmits<ChildEmits>()

// Child component emit
const save = (): void => {
  emit('save', company.value)
}

// Parent component usage
<ChildComponent @save="handleSave" />
```

## Pitfalls & Solutions

### 1. `any` Type Overuse

**Problem**: Using `any` defeats the purpose of TypeScript

```typescript
// ❌ Wrong
const data = ref<any>(null)

// ✅ Correct
const data = ref<Company | null>(null)
```

### 2. Missing Type Assertions

**Problem**: TypeScript can't infer types in complex situations

```typescript
// ❌ Wrong
const user = page.props.user

// ✅ Correct
const user = computed((): User | null => page.props.auth?.user as User | null)
```

### 3. Optional Chaining Issues

**Problem**: Null/undefined errors in templates

```typescript
// ❌ Wrong - can cause runtime errors
{{ company.name }}

// ✅ Correct - safe optional chaining
{{ company?.name || 'Unknown Company' }}
```

### 4. PrimeVue Component Typing

**Problem**: Untyped PrimeVue component props

```typescript
// ❌ Wrong
const options = ref([])

// ✅ Correct
const options = ref<{ label: string; value: string }[]>([])
```

### 5. Inertia.js Page Props

**Problem**: Untyped page props from backend

```typescript
// ❌ Wrong
const companies = page.props.companies

// ✅ Correct
const companies = computed<Company[]>(() => page.props.companies as Company[])
```

## Best Practices

### 1. Create Specific Interfaces

```typescript
// Instead of generic objects
interface CompanySettings {
  notifications: {
    email: boolean
    sms: boolean
  }
  privacy: {
    profile_visible: boolean
    contact_visible: boolean
  }
}
```

### 2. Use Type Guards

```typescript
const isCompany = (value: any): value is Company => {
  return value && 
         typeof value.id === 'string' &&
         typeof value.name === 'string' &&
         typeof value.email === 'string'
}

const handleEntity = (entity: Company | User): void => {
  if (isCompany(entity)) {
    // TypeScript knows this is Company
    console.log(entity.industry)
  } else {
    // TypeScript knows this is User
    console.log(entity.username)
  }
}
```

### 3. Leverage Utility Types

```typescript
// Make all properties optional
type PartialCompany = Partial<Company>

// Make specific properties required
type RequiredCompanyFields = Required<Pick<Company, 'name' | 'email'>>

// Omit certain properties
type CreateCompanyData = Omit<Company, 'id' | 'created_at' | 'updated_at'>
```

### 4. Use Generics for Reusable Components

```typescript
// Generic component props
interface DataTableProps<T> {
  data: T[]
  columns: TableColumn<T>[]
  loading?: boolean
}

// Generic composable
function useAsyncData<T>(url: string) {
  const data = ref<T | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  
  const fetch = async (): Promise<void> => {
    loading.value = true
    try {
      const response = await fetch(url)
      data.value = await response.json()
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Unknown error'
    } finally {
      loading.value = false
    }
  }
  
  return { data, loading, error, fetch }
}
```

### 5. Type Event Handlers Properly

```typescript
// Form events
const handleSubmit = (event: Event): void => {
  event.preventDefault()
  // handle form submission
}

// Input events
const handleInput = (event: Event): void => {
  const target = event.target as HTMLInputElement
  form.value.name = target.value
}

// Custom events
const handleCustomEvent = (payload: { id: string; action: string }): void => {
  console.log(`Action ${payload.action} on item ${payload.id}`)
}
```

## Validation Checklist

### Pre-Migration
- [ ] Component has been backed up
- [ ] Required types are available in `/types/index.ts`
- [ ] Component uses Composition API (`<script setup>`)

### Post-Migration
- [ ] No `any` types used (unless absolutely necessary)
- [ ] All props are properly typed with interfaces
- [ ] All emits are properly typed
- [ ] Reactive state has proper typing
- [ ] Form validation is type-safe
- [ ] Event handlers have proper parameter types
- [ ] API responses are typed
- [ ] Component compiles without TypeScript errors
- [ ] Template bindings work correctly
- [ ] PrimeVue components receive correct prop types

### Testing
- [ ] Component renders without runtime errors
- [ ] Form validation works as expected
- [ ] Event handlers trigger correctly
- [ ] Data flow works in both directions
- [ ] Error handling works properly
- [ ] Dark mode styles apply correctly

## Migration Example

### Before (JavaScript)
```vue
<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  company: Object,
  editable: Boolean
})

const form = useForm({
  name: props.company.name,
  email: props.company.email
})

const isDirty = computed(() => {
  return form.name !== props.company.name || 
         form.email !== props.company.email
})

const save = () => {
  form.put(`/companies/${props.company.id}`)
}
</script>
```

### After (TypeScript)
```vue
<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import type { Company } from '@/types'

interface Props {
  company: Company
  editable?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  editable: false
})

interface CompanyFormData {
  name: string
  email: string
}

const form = useForm<CompanyFormData>({
  name: props.company.name,
  email: props.company.email
})

const isDirty = computed((): boolean => {
  return form.name !== props.company.name || 
         form.email !== props.company.email
})

const save = async (): Promise<void> => {
  await form.put(`/companies/${props.company.id}`)
}
</script>
```

## Resources

- [TypeScript Handbook](https://www.typescriptlang.org/docs/handbook/intro.html)
- [Vue with TypeScript Guide](https://vuejs.org/guide/typescript/overview.html)
- [PrimeVue TypeScript Support](https://www.primefaces.org/primevue/showcase/#/typescript/)
- [Inertia.js TypeScript Guide](https://inertiajs.com/guide/type-script-integration)

## Support

For questions or issues during migration:
1. Check the existing TypeScript components for examples
2. Review the type definitions in `/resources/js/types/index.ts`
3. Use the TypeScript component template as a reference
4. Run `npm run type-check` to validate your changes