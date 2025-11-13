# Frontend Remediation Prompt

## Task: Fix Frontend Constitutional Violations

You are a **Vue.js Expert** specialized in PrimeVue-based frontend remediation for Inertia.js applications.

## CURRENT VIOLATIONS TO FIX

### **Common Non-Compliant Patterns Found**

#### **1. Missing PrimeVue Components (CRITICAL)**
```vue
<!-- BEFORE (VIOLATION) -->
<template>
  <div>
    <input v-model="form.name" placeholder="Name"> <!-- ❌ HTML input -->
    <select v-model="form.status"> <!-- ❌ HTML select -->
      <option value="active">Active</option>
      <option value="inactive">Inactive</option>
    </select>
    <button @click="save">Save</button> <!-- ❌ HTML button -->
  </div>
</template>

<!-- AFTER (CONSTITUTIONAL) -->
<template>
  <div class="p-4">
    <div class="field">
      <label for="name" class="block text-600 text-sm font-medium mb-2">
        Customer Name <span class="text-red-500">*</span>
      </label>
      <InputText
        id="name"
        v-model="form.name"
        placeholder="Enter customer name"
        :class="{ 'p-invalid': form.errors.name }"
        autofocus
      />
      <small v-if="form.errors.name" class="p-error">
        {{ form.errors.name }}
      </small>
    </div>

    <div class="field">
      <label for="status" class="block text-600 text-sm font-medium mb-2">
        Status
      </label>
      <Dropdown
        id="status"
        v-model="form.status"
        :options="statusOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="Select status"
        :class="{ 'p-invalid': form.errors.status }"
      />
    </div>

    <div class="flex justify-end gap-2 mt-6">
      <Button
        label="Cancel"
        severity="secondary"
        @click="$emit('cancelled')"
        :disabled="form.processing"
      />
      <Button
        type="submit"
        label="Save"
        :loading="form.processing"
        icon="pi pi-save"
      />
    </div>
  </div>
</template>
```

#### **2. Missing Composition API (CRITICAL)**
```vue
<!-- BEFORE (VIOLATION) -->
<script>
export default {
  data() {
    return {
      form: {
        name: '',
        email: '',
        status: 'active'
      }
    }
  },
  methods: {
    async save() {
      try {
        const response = await fetch('/api/customers', {
          method: 'POST',
          body: JSON.stringify(this.form)
        });
        this.$emit('saved', response.data);
      } catch (error) {
        console.error(error);
      }
    }
  }
}
</script>

<!-- AFTER (CONSTITUTIONAL) -->
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import CustomerStatus from '@/Enums/CustomerStatus'

// Props and emits
const props = defineProps({
    customer: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['saved', 'cancelled', 'error'])

// Composables
const toast = useToast()

// Form with Inertia
const form = useForm({
    name: '',
    email: '',
    tax_id: '',
    status: CustomerStatus.ACTIVE,
    credit_limit: 0
})

// Computed properties
const canSave = computed(() => {
    return form.name.trim() !== '' && !form.processing
})

const statusOptions = computed(() => [
    { label: CustomerStatus.ACTIVE.getLabel(), value: CustomerStatus.ACTIVE },
    { label: CustomerStatus.INACTIVE.getLabel(), value: CustomerStatus.INACTIVE },
    { label: CustomerStatus.SUSPENDED.getLabel(), value: CustomerStatus.SUSPENDED }
])

// Methods
const save = () => {
    if (!canSave.value) {
        showErrorToast('Please fill in required fields')
        return
    }

    const url = props.customer ? `/customers/${props.customer.id}/edit` : '/customers'
    const method = props.customer ? 'put' : 'post'

    form.transform(data => ({
        ...data,
        _method: method
    })).post(url, {
        onSuccess: (page) => {
            showSuccessToast(props.customer ? 'Customer updated successfully' : 'Customer created successfully')
            emit('saved', page.props.customer)
        },
        onError: (errors) => {
            showValidationErrors(errors)
            emit('error', errors)
        },
        preserveState: true
    })
}

const showErrorToast = (message) => {
    toast.add({
        severity: 'error',
        summary: 'Error',
        detail: message,
        life: 3000
    })
}

const showSuccessToast = (message) => {
    toast.add({
        severity: 'success',
        summary: 'Success',
        detail: message,
        life: 3000
    })
}

const showValidationErrors = (errors) => {
    Object.entries(errors).forEach(([field, messages]) => {
        toast.add({
            severity: 'warn',
            summary: 'Validation Error',
            detail: `${field}: ${Array.isArray(messages) ? messages[0] : messages}`,
            life: 5000
        })
    })
}

// Initialize form with customer data
onMounted(() => {
    if (props.customer) {
        form.defaults({
            name: props.customer.name,
            email: props.customer.email || '',
            tax_id: props.customer.tax_id || '',
            status: props.customer.status,
            credit_limit: props.customer.credit_limit
        })
        form.reset()
    }
})
</script>
```

#### **3. Missing Error Handling (CRITICAL)**
```vue
<!-- BEFORE (VIOLATION) -->
<script setup>
import { ref } from 'vue'

const loading = ref(false)

const save = async () => {
    loading.value = true
    try {
        const response = await fetch('/api/customers', {
            method: 'POST',
            body: JSON.stringify(form.value)
        })
        // ❌ No error handling, no user feedback
    } catch (error) {
        console.error(error) // ❌ Only console error
    }
    loading.value = false
}
</script>

<!-- AFTER (CONSTITUTIONAL) -->
<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'

const toast = useToast()

const form = useForm({
    name: '',
    email: '',
    status: 'active'
})

const canSave = computed(() => {
    return form.name.trim() !== '' && !form.processing
})

const save = () => {
    if (!canSave.value) {
        showErrorToast('Please fill in required fields')
        return
    }

    form.post('/customers', {
        onSuccess: () => {
            showSuccessToast('Customer created successfully')
        },
        onError: (errors) => {
            showValidationErrors(errors)
        },
        onError: (error) => {
            if (error.response?.status === 422) {
                handleValidationError(error.response.data.errors)
            } else if (error.response?.status === 403) {
                handlePermissionError()
            } else {
                handleGenericError(error)
            }
        },
        onFinish: () => {
            // Loading state automatically handled by Inertia form
        }
    })
}

const handleValidationError = (errors) => {
    Object.entries(errors).forEach(([field, messages]) => {
        toast.add({
            severity: 'warn',
            summary: 'Validation Error',
            detail: `${field}: ${Array.isArray(messages) ? messages[0] : messages}`,
            life: 5000
        })
    })
}

const handlePermissionError = () => {
    toast.add({
        severity: 'error',
        summary: 'Access Denied',
        detail: 'You do not have permission to perform this action',
        life: 5000
    })
}

const handleGenericError = (error) => {
    console.error('Unexpected error:', error)
    toast.add({
        severity: 'error',
        summary: 'Error',
        detail: 'An unexpected error occurred. Please try again.',
        life: 3000
    })
}

const showErrorToast = (message) => {
    toast.add({
        severity: 'error',
        summary: 'Error',
        detail: message,
        life: 3000
    })
}

const showSuccessToast = (message) => {
    toast.add({
        severity: 'success',
        summary: 'Success',
        detail: message,
        life: 3000
    })
}
</script>
```

#### **4. Missing Responsive Design (CRITICAL)**
```vue
<!-- BEFORE (VIOLATION) -->
<template>
  <div>
    <div style="display: flex;">
      <div>
        <label>Name</label>
        <input v-model="form.name">
      </div>
      <div>
        <label>Email</label>
        <input v-model="form.email">
      </div>
    </div>
    </div>
</template>

<!-- AFTER (CONSTITUTIONAL) -->
<template>
  <div class="customer-form p-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- Customer Name -->
      <div class="field">
        <label for="name" class="block text-600 text-sm font-medium mb-2">
          Customer Name <span class="text-red-500">*</span>
        </label>
        <InputText
          id="name"
          v-model="form.name"
          placeholder="Enter customer name"
          class="w-full"
          :class="{ 'p-invalid': form.errors.name }"
        />
        <small v-if="form.errors.name" class="p-error">
          {{ form.errors.name }}
        </small>
      </div>

      <!-- Email -->
      <div class="field">
        <label for="email" class="block text-600 text-sm font-medium mb-2">
          Email Address
        </label>
        <InputText
          id="email"
          v-model="form.email"
          type="email"
          placeholder="customer@example.com"
          class="w-full"
          :class="{ 'p-invalid': form.errors.email }"
        />
        <small v-if="form.errors.email" class="p-error">
          {{ form.errors.email }}
        </small>
      </div>
    </div>

    <!-- Actions - Responsive -->
    <div class="flex flex-col sm:flex-row justify-end gap-2 mt-6">
      <Button
        type="button"
        label="Cancel"
        severity="secondary"
        @click="$emit('cancelled')"
        class="w-full sm:w-auto order-2 sm:order-1"
        :disabled="form.processing"
      />
      <Button
        type="submit"
        label="Save Customer"
        class="w-full sm:w-auto order-1 sm:order-2"
        :loading="form.processing"
        :disabled="!canSave"
        icon="pi pi-save"
      />
    </div>
  </div>
</template>

<style scoped>
.customer-form {
    max-width: 800px;
    margin: 0 auto;
}

.field {
    margin-bottom: 1rem;
}

.field label {
    margin-bottom: 0.5rem;
    display: block;
    font-weight: 500;
}

/* Responsive grid system */
.grid {
    display: grid;
    gap: 1rem;
}

.grid-cols-1 {
    grid-template-columns: repeat(1, minmax(0, 1fr));
}

@media (min-width: 768px) {
    .md\:grid-cols-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

/* Responsive button layout */
@media (max-width: 640px) {
    .flex-col {
        flex-direction: column;
    }
}

@media (min-width: 640px) {
    .sm\:flex-row {
        flex-direction: row;
    }

    .sm\:w-auto {
        width: auto;
    }
}
</style>
```

## COMPLETE COMPONENT TEMPLATE

```vue
<template>
  <div class="customer-form p-4">
    <!-- Form Header -->
    <div class="mb-6">
      <h2 class="text-xl font-semibold text-900 mb-2">
        {{ customer ? 'Edit Customer' : 'New Customer' }}
      </h2>
      <p class="text-600 text-sm">
        {{ customer ? 'Update customer information' : 'Create a new customer record' }}
      </p>
    </div>

    <!-- Main Form -->
    <form @submit.prevent="save">
      <!-- Basic Information Section -->
      <div class="surface-card p-4 mb-4 border-round">
        <h3 class="text-lg font-medium text-900 mb-4 flex items-center gap-2">
          <i class="pi pi-user text-blue-500"></i>
          Basic Information
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Customer Name -->
          <div class="field">
            <label for="name" class="block text-600 text-sm font-medium mb-2">
              Customer Name <span class="text-red-500">*</span>
            </label>
            <InputText
              id="name"
              v-model="form.name"
              placeholder="Enter customer name"
              class="w-full"
              :class="{ 'p-invalid': form.errors.name }"
              autofocus
            />
            <small v-if="form.errors.name" class="p-error">
              {{ form.errors.name }}
            </small>
          </div>

          <!-- Email -->
          <div class="field">
            <label for="email" class="block text-600 text-sm font-medium mb-2">
              Email Address
            </label>
            <InputText
              id="email"
              v-model="form.email"
              type="email"
              placeholder="customer@example.com"
              class="w-full"
              :class="{ 'p-invalid': form.errors.email }"
            />
            <small v-if="form.errors.email" class="p-error">
              {{ form.errors.email }}
            </small>
          </div>

          <!-- Tax ID -->
          <div class="field">
            <label for="tax_id" class="block text-600 text-sm font-medium mb-2">
              Tax ID / VAT Number
            </label>
            <InputText
              id="tax_id"
              v-model="form.tax_id"
              placeholder="Tax identification number"
              class="w-full"
              :class="{ 'p-invalid': form.errors.tax_id }"
            />
            <small v-if="form.errors.tax_id" class="p-error">
              {{ form.errors.tax_id }}
            </small>
          </div>

          <!-- Status -->
          <div class="field">
            <label for="status" class="block text-600 text-sm font-medium mb-2">
              Status
            </label>
            <Dropdown
              id="status"
              v-model="form.status"
              :options="statusOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Select status"
              class="w-full"
              :class="{ 'p-invalid': form.errors.status }"
            />
            <small v-if="form.errors.status" class="p-error">
              {{ form.errors.status }}
            </small>
          </div>
        </div>
      </div>

      <!-- Financial Information Section -->
      <div class="surface-card p-4 mb-4 border-round">
        <h3 class="text-lg font-medium text-900 mb-4 flex items-center gap-2">
          <i class="pi pi-dollar text-green-500"></i>
          Financial Information
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Credit Limit -->
          <div class="field md:col-span-2">
            <label for="credit_limit" class="block text-600 text-sm font-medium mb-2">
              Credit Limit
            </label>
            <InputNumber
              id="credit_limit"
              v-model="form.credit_limit"
              mode="currency"
              currency="USD"
              :min="0"
              :maxFractionDigits="2"
              placeholder="0.00"
              class="w-full"
              :class="{ 'p-invalid': form.errors.credit_limit }"
            />
            <small v-if="form.errors.credit_limit" class="p-error">
              {{ form.errors.credit_limit }}
            </small>
            <small class="text-500 text-xs">
              Set to 0 for no credit limit
            </small>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex flex-col sm:flex-row justify-end gap-2 mt-6">
        <Button
          type="button"
          label="Cancel"
          severity="secondary"
          @click="handleCancel"
          class="w-full sm:w-auto order-2 sm:order-1"
          :disabled="form.processing"
          icon="pi pi-times"
        />
        <Button
          type="submit"
          :label="customer ? 'Update Customer' : 'Create Customer'"
          class="w-full sm:w-auto order-1 sm:order-2"
          :loading="form.processing"
          :disabled="!canSave"
          icon="pi pi-save"
        />
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { usePage } from '@inertiajs/vue3'
import CustomerStatus from '@/Enums/CustomerStatus'

// Props and emits
const props = defineProps({
    customer: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['saved', 'cancelled', 'error'])

// Composables
const page = usePage()
const toast = useToast()

// Form with Inertia
const form = useForm({
    name: '',
    email: '',
    tax_id: '',
    status: CustomerStatus.ACTIVE,
    credit_limit: 0
})

// Computed properties
const canSave = computed(() => {
    return form.name.trim() !== '' && !form.processing
})

const statusOptions = computed(() => [
    {
        label: CustomerStatus.ACTIVE.getLabel(),
        value: CustomerStatus.ACTIVE,
        icon: CustomerStatus.ACTIVE.getIcon()
    },
    {
        label: CustomerStatus.INACTIVE.getLabel(),
        value: CustomerStatus.INACTIVE,
        icon: CustomerStatus.INACTIVE.getIcon()
    },
    {
        label: CustomerStatus.SUSPENDED.getLabel(),
        value: CustomerStatus.SUSPENDED,
        icon: CustomerStatus.SUSPENDED.getIcon()
    }
])

const isEditing = computed(() => !!props.customer)

// Methods
const save = () => {
    if (!canSave.value) {
        showErrorToast('Please fill in all required fields')
        return
    }

    const url = props.customer ? route('customers.update', props.customer.id) : route('customers.store')
    const method = props.customer ? 'put' : 'post'

    form.transform(data => ({
        ...data,
        _method: method
    })).post(url, {
        onSuccess: (page) => {
            showSuccessToast(
                props.customer ? 'Customer updated successfully' : 'Customer created successfully'
            )
            emit('saved', page.props.customer)
        },
        onError: (errors) => {
            showValidationErrors(errors)
            emit('error', errors)
        },
        preserveState: true
    })
}

const handleCancel = () => {
    if (form.isDirty) {
        // Show confirmation dialog
        confirm.require({
            message: 'You have unsaved changes. Are you sure you want to cancel?',
            header: 'Confirm Cancel',
            icon: 'pi pi-exclamation-triangle',
            accept: () => {
                form.reset()
                emit('cancelled')
            },
            reject: () => {
                // Do nothing, stay on form
            }
        })
    } else {
        emit('cancelled')
    }
}

const showErrorToast = (message) => {
    toast.add({
        severity: 'error',
        summary: 'Error',
        detail: message,
        life: 3000
    })
}

const showSuccessToast = (message) => {
    toast.add({
        severity: 'success',
        summary: 'Success',
        detail: message,
        life: 3000
    })
}

const showValidationErrors = (errors) => {
    // Show individual field errors
    Object.entries(errors).forEach(([field, messages]) => {
        toast.add({
            severity: 'warn',
            summary: 'Validation Error',
            detail: `${field}: ${Array.isArray(messages) ? messages[0] : messages}`,
            life: 5000
        })
    })

    // Show general error message
    toast.add({
        severity: 'error',
        summary: 'Validation Failed',
        detail: 'Please correct the highlighted errors and try again.',
        life: 3000
    })
}

// Initialize form with customer data
onMounted(() => {
    if (props.customer) {
        form.defaults({
            name: props.customer.name,
            email: props.customer.email || '',
            tax_id: props.customer.tax_id || '',
            status: props.customer.status,
            credit_limit: props.customer.credit_limit || 0
        })
        form.reset()
    }
})
</script>

<style scoped>
.customer-form {
    max-width: 800px;
    margin: 0 auto;
}

.field {
    margin-bottom: 1rem;
}

.field label {
    margin-bottom: 0.5rem;
    display: block;
    font-weight: 500;
}

/* Card styling */
.surface-card {
    background-color: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
}

/* Grid system */
.grid {
    display: grid;
    gap: 1rem;
}

.grid-cols-1 {
    grid-template-columns: repeat(1, minmax(0, 1fr));
}

@media (min-width: 768px) {
    .md\:grid-cols-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .md\:col-span-2 {
        grid-column: span 2 / span 2;
    }
}

/* Responsive button layout */
@media (max-width: 640px) {
    .flex-col {
        flex-direction: column;
    }
}

@media (min-width: 640px) {
    .sm\:flex-row {
        flex-direction: row;
    }

    .sm\:w-auto {
        width: auto;
    }
}

/* Section headers */
.text-900 {
    color: #111827;
}

.text-600 {
    color: #4b5563;
}

.text-sm {
    font-size: 0.875rem;
}

/* Error states */
.text-red-500 {
    color: #ef4444;
}
</style>
```

## REQUIRED COMPOSABLES

### **useCustomerForm.js**
```javascript
// composables/useCustomerForm.js
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import CustomerStatus from '@/Enums/CustomerStatus'

export function useCustomerForm(customer = null) {
    const toast = useToast()

    const form = useForm({
        name: customer?.name || '',
        email: customer?.email || '',
        tax_id: customer?.tax_id || '',
        status: customer?.status || CustomerStatus.ACTIVE,
        credit_limit: customer?.credit_limit || 0
    })

    const canSave = computed(() => {
        return form.name.trim() !== '' && !form.processing
    })

    const isEditing = computed(() => !!customer)

    const save = (url, options = {}) => {
        if (!canSave.value) {
            showErrorToast('Please fill in all required fields')
            return Promise.reject(new Error('Validation failed'))
        }

        return form.post(url, {
            onSuccess: options.onSuccess || (() => {
                showSuccessToast(
                    customer ? 'Customer updated successfully' : 'Customer created successfully'
                )
            }),
            onError: options.onError || showValidationErrors,
            preserveState: true
        })
    }

    const reset = () => {
        if (customer) {
            form.defaults({
                name: customer.name,
                email: customer.email || '',
                tax_id: customer.tax_id || '',
                status: customer.status,
                credit_limit: customer.credit_limit || 0
            })
        }
        form.reset()
    }

    const showErrorToast = (message) => {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: message,
            life: 3000
        })
    }

    const showSuccessToast = (message) => {
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: message,
            life: 3000
        })
    }

    const showValidationErrors = (errors) => {
        Object.entries(errors).forEach(([field, messages]) => {
            toast.add({
                severity: 'warn',
                summary: 'Validation Error',
                detail: `${field}: ${Array.isArray(messages) ? messages[0] : messages}`,
                life: 5000
            })
        })
    }

    return {
        form,
        canSave,
        isEditing,
        save,
        reset,
        showErrorToast,
        showSuccessToast,
        showValidationErrors
    }
}
```

### **CustomerStatus Enum**
```javascript
// Enums/CustomerStatus.js
export default {
    ACTIVE: {
        value: 'active',
        label: 'Active',
        icon: 'pi pi-check-circle',
        color: 'success'
    },
    INACTIVE: {
        value: 'inactive',
        label: 'Inactive',
        icon: 'pi pi-pause-circle',
        color: 'secondary'
    },
    SUSPENDED: {
        value: 'suspended',
        label: 'Suspended',
        icon: 'pi pi-times-circle',
        color: 'danger'
    },

    // Helper methods
    getAll() {
        return Object.values(this).map(status => ({
            label: status.label,
            value: status.value,
            icon: status.icon
        }))
    },

    findByValue(value) {
        return Object.values(this).find(status => status.value === value)
    }
}
```

## CHECKLIST FOR EVERY COMPONENT

### **✅ Must Include:**
- [ ] `<script setup>` with Composition API
- [ ] Proper props and emits definitions
- [ ] useForm for Inertia.js form handling
- [ ] PrimeVue components exclusively
- [ ] Toast notifications for user feedback
- [ ] Comprehensive error handling
- [ ] Responsive design with Tailwind CSS
- [ ] Loading states and disabled states
- [ ] Form validation and error display
- [ ] Accessibility (ARIA labels, keyboard navigation)
- [ ] TypeScript where critical (optional but recommended)
- [ ] Proper component organization and styling

### **❌ Must NOT Include:**
- [ ] Options API (data, methods, computed)
- [ ] HTML form elements (input, select, button)
- [ ] Custom UI libraries (Bootstrap, Material, etc.)
- [ ] Console-only error handling
- [ ] Missing loading states
- [ ] Non-responsive layouts
- [ ] Hardcoded strings without i18n support
- [ ] Missing accessibility features

## VALIDATION COMMANDS

```bash
# Build frontend to check for errors
npm run build

# Start development server
npm run dev

# Test component in browser
# Navigate to customer form page and test:
# - Form submission
# - Validation errors
# - Loading states
# - Responsive design on mobile/desktop
# - Accessibility with keyboard navigation
```

Apply this template to ALL Vue components in your codebase.