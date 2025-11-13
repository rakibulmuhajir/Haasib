<!--
REUSABLE FORM COMPONENT TEMPLATE

This template provides a standardized form structure with validation integration,
loading states, and consistent PrimeVue component usage. It serves as a foundation
for creating forms throughout the Haasib application.

FEATURES:
✅ TypeScript support with proper typing
✅ PrimeVue component integration
✅ Form validation with error handling
✅ Loading states and progress indicators
✅ Responsive design with mobile-first approach
✅ Accessibility support with ARIA labels
✅ Dark mode compatibility
✅ Consistent styling patterns

USAGE:
1. Copy this template to your components directory
2. Customize the props interface for your specific form data
3. Update validation rules as needed
4. Add form fields in the template section
5. Customize form actions and handlers
-->
<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useForm } from '@inertiajs/vue3'
import { useTypes } from '@/composables/useTypes'
import { useFormValidation } from '@/composables/useFormValidation'
import type { FormErrors } from '@/types'

// ============================================================================
// PROPS DEFINITION
// ============================================================================

interface Props {
  // Required props
  title: string
  submitUrl: string
  
  // Optional props with defaults
  description?: string
  submitLabel?: string
  cancelLabel?: string
  cancelUrl?: string
  submitMethod?: 'post' | 'put' | 'patch'
  submitSeverity?: 'primary' | 'secondary' | 'danger'
  showCancel?: boolean
  disabled?: boolean
  initialData?: Record<string, any>
  
  // Configuration props
  showProgress?: boolean
  validateOnSubmit?: boolean
  resetOnSuccess?: boolean
  
  // Styling props
  cardClass?: string
  fieldClass?: string
  labelClass?: string
  inputClass?: string
  buttonClass?: string
}

const props = withDefaults(defineProps<Props>(), {
  description: '',
  submitLabel: 'Save',
  cancelLabel: 'Cancel',
  cancelUrl: '',
  submitMethod: 'post',
  submitSeverity: 'primary',
  showCancel: true,
  disabled: false,
  initialData: () => ({}),
  showProgress: true,
  validateOnSubmit: true,
  resetOnSuccess: false,
  cardClass: 'bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700',
  fieldClass: 'mb-4',
  labelClass: 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2',
  inputClass: 'w-full',
  buttonClass: ''
})

// ============================================================================
// EMITS DEFINITION
// ============================================================================

interface Emits {
  (e: 'submit', data: Record<string, any>): void
  (e: 'cancel'): void
  (e: 'success', response: any): void
  (e: 'error', errors: FormErrors): void
  (e: 'loading', isLoading: boolean): void
  (e: 'field-change', field: string, value: any): void
}

const emit = defineEmits<Emits>()

// ============================================================================
// COMPOSABLES
// ============================================================================

const {
  formatCurrency,
  formatDate,
  getStatusClasses,
  hasFormErrors,
  getFormErrorMessage
} = useTypes()

// ============================================================================
// FORM DATA & VALIDATION
// ============================================================================

// Form data (customize this for your specific form)
const defaultFormData = {
  name: '',
  email: '',
  phone: '',
  address: '',
  city: '',
  state: '',
  zip_code: '',
  country: '',
  notes: '',
  is_active: true,
  ...props.initialData
}

const formData = ref<Record<string, any>>(defaultFormData)

// Validation rules (customize this for your form)
const validationRules = {
  name: [
    { 
      validator: (value: string) => value.trim().length > 0, 
      message: 'Name is required' 
    },
    { 
      validator: (value: string) => value.trim().length >= 2, 
      message: 'Name must be at least 2 characters' 
    }
  ],
  email: [
    { 
      validator: (value: string) => value.trim().length > 0, 
      message: 'Email is required' 
    },
    { 
      validator: (value: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value), 
      message: 'Please enter a valid email address' 
    }
  ],
  phone: [
    { 
      validator: (value: string) => !value || /^[\d\s\-\+\(\)]+$/.test(value), 
      message: 'Please enter a valid phone number' 
    }
  ]
}

const {
  validateField,
  validateAll,
  clearErrors,
  hasErrors,
  getFieldError,
  isFieldInvalid
} = useFormValidation(formData.value, validationRules)

// ============================================================================
// FORM HANDLING
// ============================================================================

const form = useForm({
  ...formData.value,
})

// Reactive states
const isDirty = computed(() => {
  return JSON.stringify(formData.value) !== JSON.stringify(defaultFormData)
})

const isValid = computed(() => {
  return !hasErrors.value && isDirty.value
})

const progressPercentage = computed(() => {
  const totalFields = Object.keys(validationRules).length
  const validFields = Object.keys(validationRules).filter(field => 
    validateField(field)
  ).length
  return totalFields > 0 ? Math.round((validFields / totalFields) * 100) : 0
})

// ============================================================================
// METHODS
// ============================================================================

// Field change handler
const handleFieldChange = (field: string, value: any): void => {
  formData.value[field] = value
  form[field] = value
  
  // Validate field immediately
  if (getFieldError(field)) {
    validateField(field)
  }
  
  emit('field-change', field, value)
}

// Form validation
const validateFormData = (): boolean => {
  let isValid = true
  
  Object.keys(validationRules).forEach(field => {
    if (!validateField(field)) {
      isValid = false
    }
  })
  
  return isValid
}

// Form submission
const handleSubmit = (): void => {
  if (props.disabled) return
  
  emit('loading', true)
  
  // Validate form if required
  if (props.validateOnSubmit && !validateFormData()) {
    emit('error', hasErrors.value)
    emit('loading', false)
    return
  }
  
  // Update form data
  Object.assign(form, formData.value)
  
  // Submit form based on method
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
      emit('submit', formData.value)
      
      if (props.resetOnSuccess) {
        resetForm()
      }
    })
    .catch((errors) => {
      emit('error', errors)
      if (errors.errors) {
        // Map Laravel validation errors to our form
        Object.entries(errors.errors).forEach(([field, messages]) => {
          validationRules[field] = [
            { validator: () => false, message: Array.isArray(messages) ? messages[0] : messages }
          ]
        })
      }
    })
    .finally(() => {
      emit('loading', false)
    })
}

// Form cancellation
const handleCancel = (): void => {
  if (props.cancelUrl) {
    router.visit(props.cancelUrl)
  } else {
    emit('cancel')
  }
}

// Form reset
const resetForm = (): void => {
  Object.assign(formData.value, defaultFormData)
  Object.assign(form, defaultFormData)
  clearErrors()
  form.clearErrors()
}

// Form clear
const clearForm = (): void => {
  Object.keys(formData.value).forEach(key => {
    if (typeof defaultFormData[key] === 'boolean') {
      formData.value[key] = false
    } else if (typeof defaultFormData[key] === 'number') {
      formData.value[key] = 0
    } else {
      formData.value[key] = ''
    }
  })
  clearErrors()
}

// ============================================================================
// WATCHERS
// ============================================================================

watch(() => props.initialData, (newData) => {
  if (newData && Object.keys(newData).length > 0) {
    Object.assign(formData.value, newData)
    Object.assign(form, newData)
  }
}, { immediate: true, deep: true })

// ============================================================================
// LIFECYCLE
// ============================================================================

onMounted(() => {
  // Initialize form with initial data
  if (props.initialData && Object.keys(props.initialData).length > 0) {
    Object.assign(formData.value, props.initialData)
    Object.assign(form, props.initialData)
  }
})

// ============================================================================
// EXPOSE (for template refs if needed)
// ============================================================================

defineExpose({
  validateForm: validateFormData,
  resetForm,
  clearForm,
  isDirty,
  isValid,
  formData,
  hasErrors
})
</script>

<template>
  <div :class="props.cardClass">
    <!-- Header -->
    <div v-if="title" class="p-6 border-b border-gray-200 dark:border-gray-700">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
        {{ title }}
      </h2>
      <p v-if="description" class="text-sm text-gray-600 dark:text-gray-400 mt-1">
        {{ description }}
      </p>
      
      <!-- Progress Bar -->
      <div v-if="showProgress && validateOnSubmit" class="mt-4">
        <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
          <span>Form Completion</span>
          <span>{{ progressPercentage }}%</span>
        </div>
        <ProgressBar 
          :value="progressPercentage" 
          class="h-2"
          :show-value="false"
        />
      </div>
    </div>

    <!-- Form Content -->
    <form @submit.prevent="handleSubmit" class="p-6 space-y-6">
      <!-- Name Field -->
      <div :class="fieldClass">
        <label :class="labelClass" for="name">
          Name <span class="text-red-500">*</span>
        </label>
        <InputText
          id="name"
          v-model="formData.name"
          :class="[inputClass, { 'p-invalid': isFieldInvalid('name') }]"
          :disabled="disabled"
          placeholder="Enter name"
          @blur="validateField('name')"
          @input="handleFieldChange('name', $event.target.value)"
        />
        <small class="text-red-500">
          {{ getFieldError('name') }}
        </small>
      </div>

      <!-- Email Field -->
      <div :class="fieldClass">
        <label :class="labelClass" for="email">
          Email Address <span class="text-red-500">*</span>
        </label>
        <InputText
          id="email"
          v-model="formData.email"
          type="email"
          :class="[inputClass, { 'p-invalid': isFieldInvalid('email') }]"
          :disabled="disabled"
          placeholder="Enter email address"
          @blur="validateField('email')"
          @input="handleFieldChange('email', $event.target.value)"
        />
        <small class="text-red-500">
          {{ getFieldError('email') }}
        </small>
      </div>

      <!-- Phone Field -->
      <div :class="fieldClass">
        <label :class="labelClass" for="phone">
          Phone Number
        </label>
        <InputText
          id="phone"
          v-model="formData.phone"
          :class="[inputClass, { 'p-invalid': isFieldInvalid('phone') }]"
          :disabled="disabled"
          placeholder="Enter phone number"
          @blur="validateField('phone')"
          @input="handleFieldChange('phone', $event.target.value)"
        />
        <small class="text-red-500">
          {{ getFieldError('phone') }}
        </small>
      </div>

      <!-- Address Fields Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div :class="fieldClass">
          <label :class="labelClass" for="city">
            City
          </label>
          <InputText
            id="city"
            v-model="formData.city"
            :class="inputClass"
            :disabled="disabled"
            placeholder="Enter city"
            @input="handleFieldChange('city', $event.target.value)"
          />
        </div>

        <div :class="fieldClass">
          <label :class="labelClass" for="state">
            State
          </label>
          <InputText
            id="state"
            v-model="formData.state"
            :class="inputClass"
            :disabled="disabled"
            placeholder="Enter state"
            @input="handleFieldChange('state', $event.target.value)"
          />
        </div>
      </div>

      <!-- Notes Field -->
      <div :class="fieldClass">
        <label :class="labelClass" for="notes">
          Notes
        </label>
        <Textarea
          id="notes"
          v-model="formData.notes"
          :class="inputClass"
          :disabled="disabled"
          rows="4"
          placeholder="Enter any additional notes..."
          @input="handleFieldChange('notes', $event.target.value)"
        />
      </div>

      <!-- Active Status -->
      <div :class="fieldClass">
        <div class="flex items-center">
          <Checkbox
            id="is_active"
            v-model="formData.is_active"
            :disabled="disabled"
            input-id="is_active"
            binary
          />
          <label for="is_active" class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">
            Active
          </label>
        </div>
      </div>

      <!-- Form Actions -->
      <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
        <div class="text-sm text-gray-500 dark:text-gray-400">
          <span v-if="isDirty" class="text-orange-600">
            You have unsaved changes
          </span>
          <span v-else>
            All changes saved
          </span>
        </div>
        
        <div class="flex items-center space-x-3">
          <!-- Clear Button -->
          <Button
            v-if="isDirty"
            type="button"
            label="Clear"
            severity="secondary"
            size="small"
            :disabled="disabled"
            @click="clearForm"
          />
          
          <!-- Cancel Button -->
          <Button
            v-if="showCancel"
            type="button"
            :label="cancelLabel"
            severity="secondary"
            size="small"
            :disabled="disabled || form.processing"
            @click="handleCancel"
          />
          
          <!-- Submit Button -->
          <Button
            type="submit"
            :label="submitLabel"
            :severity="submitSeverity"
            :loading="form.processing"
            :disabled="disabled || !isValid"
            :class="buttonClass"
            icon="pi pi-save"
          />
        </div>
      </div>
    </form>
  </div>
</template>

<style scoped>
/* Custom form styles */
.form-progress {
  background: linear-gradient(90deg, var(--primary-500) var(--progress), #e5e7eb var(--progress));
}

/* Dark mode adjustments */
@media (prefers-color-scheme: dark) {
  .form-progress {
    background: linear-gradient(90deg, var(--primary-600) var(--progress), #374151 var(--progress));
  }
}

/* Validation states */
.field-error input,
.field-error textarea {
  border-color: var(--red-500);
}

.field-error input:focus,
.field-error textarea:focus {
  box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
}

/* Loading states */
.opacity-50 {
  opacity: 0.5;
  pointer-events: none;
}

/* Animation for validation errors */
.animate-shake {
  animation: shake 0.5s ease-in-out;
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  10%, 30%, 50%, 70%, 90% { transform: translateX(-2px); }
  20%, 40%, 60%, 80% { transform: translateX(2px); }
}

/* Focus states */
input:focus,
textarea:focus,
select:focus {
  outline: none;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}

/* Disabled states */
input:disabled,
textarea:disabled,
select:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>