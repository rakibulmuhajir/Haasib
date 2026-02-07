# Frontend Development Guide

**Last Updated**: 2025-02-01  
**Purpose**: Vue 3, Inertia, and UI component patterns  
**Audience**: Frontend developers

---

## Table of Contents

1. [Technology Stack](#1-technology-stack)
2. [Project Structure](#2-project-structure)
3. [Component Standards](#3-component-standards)
4. [Form Patterns](#4-form-patterns)
5. [Inline Editing](#5-inline-editing)
6. [Toast Notifications](#6-toast-notifications)
7. [User Modes & Terminology](#7-user-modes--terminology)
8. [UI Components](#8-ui-components)
9. [Error Handling](#9-error-handling)

---

## 1. Technology Stack

- **Framework**: Vue 3 with Composition API
- **Language**: TypeScript
- **SSR**: Inertia.js v2
- **Styling**: Tailwind CSS
- **Components**: Shadcn-Vue (Radix Vue + Tailwind)
- **Icons**: Lucide Vue
- **Notifications**: Sonner (toast)
- **State**: Vue reactivity (no Pinia/Vuex needed with Inertia)

---

## 2. Project Structure

```
resources/js/
├── app.ts                    # Vue app entry
├── layouts/
│   └── AppLayout.vue         # Main layout wrapper
├── pages/                    # Page components
│   ├── auth/
│   ├── companies/
│   ├── dashboard/
│   └── ...
├── routes/                   # Feature modules
│   ├── invoices/
│   ├── customers/
│   └── ...
├── components/
│   ├── ui/                   # Shadcn-Vue components
│   │   ├── Button.vue
│   │   ├── Input.vue
│   │   ├── Table.vue
│   │   └── ...
│   ├── forms/                # Form components
│   └── palette/              # Command palette
├── composables/              # Reusable logic
│   ├── useInlineEdit.ts
│   ├── useFormFeedback.ts
│   └── useLexicon.ts
├── lib/                      # Utilities
│   ├── utils.ts
│   └── validators.ts
└── types/                    # TypeScript types
    ├── models.ts
    └── index.ts
```

---

## 3. Component Standards

### 3.1 Script Setup Pattern (REQUIRED)

```vue
<script setup lang="ts">
// ✅ CORRECT - Always use <script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

// Props with TypeScript
interface Props {
  invoices: Invoice[]
  company: Company
}

const props = defineProps<Props>()

// Emits
defineEmits<{
  saved: [invoice: Invoice]
  error: [errors: ValidationErrors]
}>()

// Reactive state
const searchQuery = ref('')

// Computed
const filteredInvoices = computed(() => {
  return props.invoices.filter(i => 
    i.number.includes(searchQuery.value)
  )
})

// Methods
const formatDate = (date: string): string => {
  return new Date(date).toLocaleDateString()
}
</script>

<template>
  <!-- Template here -->
</template>
```

### 3.2 Component File Structure

```vue
<script setup lang="ts">
// 1. Imports (external first, then internal)
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

// 2. Types/Interfaces
interface Props {
  // ...
}

// 3. Props & Emits
defineProps<Props>()
defineEmits<...>()

// 4. Composables
const form = useForm({...})

// 5. Reactive State
const isOpen = ref(false)

// 6. Computed
const isValid = computed(() => ...)

// 7. Methods
const submit = () => { ... }
</script>

<template>
  <!-- Template -->
</template>

<style scoped>
/* Scoped styles if needed */
</style>
```

### 3.3 Props Definition

```vue
<script setup lang="ts">
// ✅ Good - Interface with defineProps
interface Props {
  invoice: Invoice
  customers: Customer[]
  readonly?: boolean
}

const props = defineProps<Props>()

// Access with props.invoice
</script>
```

### 3.4 Event Emission

```vue
<script setup lang="ts">
// ✅ Good - Typed emits
const emit = defineEmits<{
  saved: [invoice: Invoice]
  cancel: []
  'update:modelValue': [value: string]
}>()

// Usage
emit('saved', invoice)
emit('cancel')
</script>
```

---

## 4. Form Patterns

### 4.1 Inertia Form Pattern

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

interface Props {
  company: Company
  customers: Customer[]
}

const props = defineProps<Props>()

const form = useForm({
  customer_id: '',
  invoice_date: new Date().toISOString().split('T')[0],
  due_date: '',
  line_items: [] as LineItem[],
})

const submit = () => {
  form.post(route('invoices.store', { 
    company: props.company.slug 
  }), {
    onSuccess: () => {
      // Reset form or redirect
      form.reset()
    },
    onError: (errors) => {
      // Errors automatically available in form.errors
      console.error(errors)
    },
  })
}
</script>

<template>
  <form @submit.prevent="submit" class="space-y-4">
    <div>
      <Label for="customer">Customer</Label>
      <select 
        id="customer" 
        v-model="form.customer_id"
        :class="{ 'border-red-500': form.errors.customer_id }"
      >
        <option value="">Select customer</option>
        <option 
          v-for="customer in customers" 
          :key="customer.id" 
          :value="customer.id"
        >
          {{ customer.name }}
        </option>
      </select>
      <p v-if="form.errors.customer_id" class="text-red-500 text-sm">
        {{ form.errors.customer_id }}
      </p>
    </div>
    
    <Button 
      type="submit" 
      :disabled="form.processing"
    >
      <span v-if="form.processing">Creating...</span>
      <span v-else>Create Invoice</span>
    </Button>
  </form>
</template>
```

### 4.2 Form with Validation Display

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const form = useForm({
  email: '',
  password: '',
})
</script>

<template>
  <form @submit.prevent="form.post('/login')">
    <div class="space-y-4">
      <div>
        <Input 
          v-model="form.email" 
          type="email"
          :class="{ 'border-red-500': form.errors.email }"
        />
        <p v-if="form.errors.email" class="text-red-500 text-sm mt-1">
          {{ form.errors.email }}
        </p>
      </div>
      
      <div>
        <Input 
          v-model="form.password" 
          type="password"
          :class="{ 'border-red-500': form.errors.password }"
        />
        <p v-if="form.errors.password" class="text-red-500 text-sm mt-1">
          {{ form.errors.password }}
        </p>
      </div>
      
      <Button :disabled="form.processing">
        Login
      </Button>
    </div>
  </form>
</template>
```

---

## 5. Inline Editing

### 5.1 Using useInlineEdit Composable

```vue
<script setup lang="ts">
import { useInlineEdit } from '@/composables/useInlineEdit'
import InlineEditable from '@/components/InlineEditable.vue'

interface Props {
  company: Company
}

const props = defineProps<Props>()

// Setup inline editing
const inlineEdit = useInlineEdit({
  endpoint: `/${props.company.slug}/settings`,
  successMessage: 'Updated successfully',
  errorMessage: 'Failed to update',
})

// Register fields
const nameField = inlineEdit.registerField('name', props.company.name)
const statusField = inlineEdit.registerField('status', props.company.status)
</script>

<template>
  <div class="space-y-4">
    <!-- Text field -->
    <InlineEditable
      v-model="nameField.value.value"
      label="Company Name"
      :editing="nameField.isEditing.value"
      :saving="nameField.isSaving.value"
      type="text"
      @start-edit="nameField.startEditing()"
      @save="nameField.save()"
      @cancel="nameField.cancelEditing()"
    />
    
    <!-- Select field -->
    <InlineEditable
      v-model="statusField.value.value"
      label="Status"
      :editing="statusField.isEditing.value"
      :saving="statusField.isSaving.value"
      type="select"
      :options="[
        { value: 'active', label: 'Active' },
        { value: 'inactive', label: 'Inactive' }
      ]"
      @start-edit="statusField.startEditing()"
      @save="statusField.save()"
      @cancel="statusField.cancelEditing()"
    />
  </div>
</template>
```

### 5.2 When to Use Inline Editing

| Field Type | Inline? | Reason |
|------------|---------|--------|
| `name`, `email`, `status` | ✅ | Simple, atomic, no side effects |
| `total_amount`, `balance` | ❌ | Calculated fields |
| `address`, `line_items` | ❌ | Complex/nested data |
| `currency` | ❌ | Affects other calculations |

**Rule**: If changing the field triggers recalculations or affects other fields, use a form.

---

## 6. Toast Notifications

### 6.1 Using Sonner

```vue
<script setup lang="ts">
import { toast } from 'vue-sonner'

const handleAction = () => {
  // Success toast
  toast.success('Invoice created successfully', {
    description: 'Invoice #123 has been saved.',
  })
  
  // Error toast
  toast.error('Failed to create invoice', {
    description: 'Please check your input and try again.',
  })
  
  // Info toast
  toast.info('New update available', {
    action: {
      label: 'Update',
      onClick: () => { /* ... */ }
    },
  })
}
</script>
```

### 6.2 useFormFeedback Composable

```vue
<script setup lang="ts">
import { useFormFeedback } from '@/composables/useFormFeedback'

const { showSuccess, showError, showWarning } = useFormFeedback()

const submit = () => {
  form.post('/endpoint', {
    onSuccess: () => {
      showSuccess('Operation completed successfully')
    },
    onError: () => {
      showError('Failed to complete operation')
    },
  })
}
</script>
```

---

## 7. User Modes & Terminology

### 7.1 useLexicon Composable

Haasib supports two user modes with different terminology:

```vue
<script setup lang="ts">
import { useLexicon } from '@/composables/useLexicon'

const { t, mode, isOwnerMode, isAccountantMode } = useLexicon()

// In Owner mode: "Money In" / "Money Out"
// In Accountant mode: "Revenue" / "Expenses"
console.log(t('moneyIn'))  // "Money In" or "Revenue"
console.log(t('moneyOut')) // "Money Out" or "Expenses"
</script>

<template>
  <h1>{{ t('moneyIn') }}</h1>
  <p>Current mode: {{ mode }}</p>
</template>
```

### 7.2 Terminology Mapping

| Owner Mode | Accountant Mode | Description |
|------------|----------------|-------------|
| Money In | Revenue | Income/revenue tracking |
| Money Out | Expenses | Spending/expense tracking |
| People Owes Me | Accounts Receivable | Money customers owe |
| I Owe People | Accounts Payable | Money owed to vendors |
| Things I Sell | Products/Services | Items for sale |

**Never hardcode mode checks**:

```vue
<!-- ❌ WRONG -->
<span>{{ isAccountantMode ? 'Revenue' : 'Money In' }}</span>

<!-- ✅ CORRECT -->
<span>{{ t('moneyIn') }}</span>
```

---

## 8. UI Components

### 8.1 Shadcn-Vue Components (REQUIRED)

Always use Shadcn-Vue components. Never use raw HTML inputs.

```vue
<script setup lang="ts">
// ✅ CORRECT
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card } from '@/components/ui/card'
import { Table } from '@/components/ui/table'
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle>Title</CardTitle>
    </CardHeader>
    <CardContent>
      <div class="space-y-2">
        <Label for="email">Email</Label>
        <Input id="email" v-model="email" />
      </div>
      <Button @click="submit">Submit</Button>
    </CardContent>
  </Card>
</template>
```

### 8.2 Available Components

| Component | Import | Usage |
|-----------|--------|-------|
| Button | `@/components/ui/button` | Actions, submissions |
| Input | `@/components/ui/input` | Text inputs |
| Label | `@/components/ui/label` | Form labels |
| Card | `@/components/ui/card` | Content containers |
| Table | `@/components/ui/table` | Data tables |
| Dialog | `@/components/ui/dialog` | Modals |
| Select | `@/components/ui/select` | Dropdowns |
| Checkbox | `@/components/ui/checkbox` | Checkboxes |
| Radio | `@/components/ui/radio-group` | Radio buttons |
| Tabs | `@/components/ui/tabs` | Tabbed content |
| Toast | `vue-sonner` | Notifications |

### 8.3 Component Props

**Button**:
```vue
<Button 
  variant="default"     <!-- default, secondary, destructive, outline, ghost, link -->
  size="default"        <!-- default, sm, lg, icon -->
  :disabled="isLoading"
  @click="handleClick"
>
  Click Me
</Button>
```

**Input**:
```vue
<Input 
  v-model="value"
  type="text"           <!-- text, email, password, number, date -->
  placeholder="Enter..."
  :class="{ 'border-red-500': hasError }"
/>
```

---

## 9. Error Handling

### 9.1 Form Errors

Form errors are automatically handled by Inertia's `useForm`:

```vue
<script setup lang="ts">
const form = useForm({
  email: '',
})
</script>

<template>
  <div>
    <Input 
      v-model="form.email"
      :class="{ 'border-red-500': form.errors.email }"
    />
    <p v-if="form.errors.email" class="text-red-500 text-sm">
      {{ form.errors.email }}
    </p>
  </div>
</template>
```

### 9.2 Server Errors

Always show server errors via toast, never expose raw errors:

```vue
<script setup lang="ts">
import { useFormFeedback } from '@/composables/useFormFeedback'

const { showError } = useFormFeedback()

const submit = () => {
  form.post('/endpoint', {
    onError: (errors) => {
      // Show generic error message
      showError('Failed to save. Please try again.')
      
      // Or show specific error
      if (errors.message) {
        showError(errors.message)
      }
    },
  })
}
</script>
```

### 9.3 Error Handling Requirements

Every user-facing action must handle:

1. **Success path**: Response handled, user sees feedback (toast), UI updates
2. **Error path**: Validation errors shown inline, server errors shown as toast  
3. **Loading state**: Button disabled, spinner if >300ms expected

```vue
<Button :disabled="form.processing">
  <span v-if="form.processing">
    <LoadingSpinner class="mr-2" />
    Saving...
  </span>
  <span v-else>Save</span>
</Button>
```

---

## Common Mistakes

### ❌ DON'T

```vue
<!-- Wrong API style -->
<script>
export default {
  data() {
    return { count: 0 }
  }
}
</script>

<!-- Raw HTML inputs -->
<input v-model="email">
<button @click="submit">Submit</button>

<!-- Direct fetch calls -->
const response = await fetch('/api/endpoint')

<!-- Hardcoded mode checks -->
<span>{{ isAccountantMode ? 'Revenue' : 'Money In' }}</span>
```

### ✅ DO

```vue
<!-- Script setup -->
<script setup lang="ts">
const count = ref(0)
</script>

<!-- Shadcn components -->
<Input v-model="email" />
<Button @click="submit">Submit</Button>

<!-- Inertia forms -->
const form = useForm({ email: '' })
form.post('/endpoint')

<!-- useLexicon -->
<span>{{ t('moneyIn') }}</span>
```

---

## Related Documentation

- [01-ARCHITECTURE.md](01-ARCHITECTURE.md) - System architecture
- [02-DEVELOPMENT-STANDARDS.md](02-DEVELOPMENT-STANDARDS.md) - Backend standards
- [03-RBAC-GUIDE.md](03-RBAC-GUIDE.md) - Permissions
- `docs/ui-screen-specifications.md` - Screen specifications
