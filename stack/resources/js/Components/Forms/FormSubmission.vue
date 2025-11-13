<!--
FORM SUBMISSION COMPONENT

A reusable component that provides standardized form submission patterns
with loading states, error handling, and success feedback. It wraps Inertia.js
form handling with additional UX improvements.

PROPS:
- action: The URL to submit the form to
- method: HTTP method (post, put, patch)
- initialData: Initial form data
- submitLabel: Label for submit button
- cancelLabel: Label for cancel button
- showCancel: Whether to show cancel button
- cancelUrl: URL to navigate to on cancel
- resetOnSuccess: Whether to reset form on successful submission
- preserveScroll: Whether to preserve scroll position
- confirmMessage: Confirmation message before submission
- successMessage: Success message to show on completion
- errorMessage: Custom error message for submission failures
- loadingMessage: Message to show during submission
- showProgress: Whether to show progress indicator

SLOTS:
- default: Form fields content
- actions-before: Content before action buttons
- actions-after: Content after action buttons
- success: Custom success message content
- error: Custom error message content

USAGE:
<FormSubmission
  action="/customers"
  method="post"
  :initialData="{ name: '', email: '' }"
  submit-label="Create Customer"
  success-message="Customer created successfully"
>
  <template #default="{ form, errors, loading }">
    <InputText v-model="form.name" :class="{ 'p-invalid': errors.name }" />
    <InputText v-model="form.email" :class="{ 'p-invalid': errors.email }" />
  </template>
</FormSubmission>
-->
<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import Button from 'primevue/button'
import ProgressSpinner from 'primevue/progressspinner'
import Message from 'primevue/message'
import Toast from 'primevue/toast'

// ============================================================================
// PROPS DEFINITION
// ============================================================================

interface Props {
  action: string
  method?: 'post' | 'put' | 'patch'
  initialData?: Record<string, any>
  submitLabel?: string
  cancelLabel?: string
  showCancel?: boolean
  cancelUrl?: string
  resetOnSuccess?: boolean
  preserveScroll?: boolean
  confirmMessage?: string
  successMessage?: string
  errorMessage?: string
  loadingMessage?: string
  showProgress?: boolean
  validateBeforeSubmit?: boolean
  transformData?: (data: Record<string, any>) => Record<string, any>
  onSuccess?: (response: any) => void
  onError?: (errors: Record<string, any>) => void
  onFinish?: () => void
  buttonClass?: string
  buttonIcon?: string
  buttonSeverity?: 'primary' | 'secondary' | 'danger' | 'success' | 'info' | 'warning'
  buttonSize?: 'small' | 'normal' | 'large'
  buttonLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  method: 'post',
  initialData: () => ({}),
  submitLabel: 'Submit',
  cancelLabel: 'Cancel',
  showCancel: true,
  resetOnSuccess: false,
  preserveScroll: false,
  showProgress: false,
  validateBeforeSubmit: false,
  buttonSeverity: 'primary',
  buttonSize: 'normal',
  buttonLoading: true
})

// ============================================================================
// EMITS DEFINITION
// ============================================================================

interface Emits {
  (e: 'submit', data: Record<string, any>): void
  (e: 'success', response: any): void
  (e: 'error', errors: Record<string, any>): void
  (e: 'cancel'): void
  (e: 'validate'): boolean
  (e: 'reset'): void
  (e: 'loading', isLoading: boolean): void
}

const emit = defineEmits<Emits>()

// ============================================================================
// REACTIVE STATE
// ============================================================================

const toast = ref()
const isSubmitting = ref(false)
const showSuccessMessage = ref(false)
const showErrorMessage = ref(false)
const successResponse = ref<any>(null)
const errorErrors = ref<Record<string, any>>({})
const progressPercentage = ref(0)

// Form instance
const form = useForm({
  ...props.initialData
})

// ============================================================================
// COMPUTED PROPERTIES
// ============================================================================

const canSubmit = computed((): boolean => {
  return !isSubmitting.value && !form.processing
})

const showProgressIndicator = computed((): boolean => {
  return props.showProgress && isSubmitting.value
})

const hasValidationErrors = computed((): boolean => {
  return Object.keys(form.errors).length > 0
})

const hasCustomErrors = computed((): boolean => {
  return Object.keys(errorErrors.value).length > 0
})

const displaySuccessMessage = computed((): string => {
  return props.successMessage || 'Operation completed successfully'
})

const displayErrorMessage = computed((): string => {
  return props.errorMessage || 'An error occurred. Please try again.'
})

const displayLoadingMessage = computed((): string => {
  return props.loadingMessage || 'Processing...'
})

// ============================================================================
// METHODS
// ============================================================================

const handleSubmit = async (): Promise<void> => {
  if (isSubmitting.value || !canSubmit.value) {
    return
  }
  
  // Show confirmation dialog if message provided
  if (props.confirmMessage) {
    const confirmed = window.confirm(props.confirmMessage)
    if (!confirmed) {
      return
    }
  }
  
  // Validate before submission if required
  if (props.validateBeforeSubmit) {
    const isValid = emit('validate')
    if (isValid === false) {
      return
    }
  }
  
  isSubmitting.value = true
  showSuccessMessage.value = false
  showErrorMessage.value = false
  errorErrors.value = {}
  
  emit('loading', true)
  
  // Start progress animation
  if (props.showProgress) {
    animateProgress()
  }
  
  try {
    // Transform data if transformer provided
    let submitData = form.data()
    if (props.transformData) {
      submitData = props.transformData(submitData)
    }
    
    // Update form data with transformed data
    Object.assign(form, submitData)
    
    // Submit form
    const submitAction = () => {
      switch (props.method) {
        case 'post':
          return form.post(props.action)
        case 'put':
          return form.put(props.action)
        case 'patch':
          return form.patch(props.action)
        default:
          return form.post(props.action)
      }
    }
    
    await submitAction()
    
    // Success handling
    successResponse.value = form.data
    showSuccessMessage.value = true
    
    // Show success toast
    showToast('success', displaySuccessMessage.value)
    
    // Emit success events
    emit('success', successResponse.value)
    emit('submit', submitData)
    
    // Reset form if requested
    if (props.resetOnSuccess) {
      nextTick(() => {
        form.reset()
        form.clearErrors()
        emit('reset')
      })
    }
    
    // Call custom success handler
    if (props.onSuccess) {
      props.onSuccess(successResponse.value)
    }
    
  } catch (error: any) {
    console.error('Form submission error:', error)
    
    // Handle errors
    if (error.errors) {
      errorErrors.value = error.errors
    }
    
    showErrorMessage.value = true
    
    // Show error toast
    showToast('error', displayErrorMessage.value)
    
    // Emit error events
    emit('error', errorErrors.value)
    
    // Call custom error handler
    if (props.onError) {
      props.onError(errorErrors.value)
    }
    
  } finally {
    isSubmitting.value = false
    progressPercentage.value = 0
    emit('loading', false)
    
    // Call custom finish handler
    if (props.onFinish) {
      props.onFinish()
    }
  }
}

const handleCancel = (): void => {
  if (props.cancelUrl) {
    router.visit(props.cancelUrl, {
      preserveScroll: props.preserveScroll
    })
  } else {
    emit('cancel')
  }
}

const clearErrors = (): void => {
  form.clearErrors()
  errorErrors.value = {}
  showSuccessMessage.value = false
  showErrorMessage.value = false
}

const resetForm = (): void => {
  form.reset()
  clearErrors()
  emit('reset')
}

const retrySubmission = (): void => {
  handleSubmit()
}

// Progress animation
const animateProgress = (): void => {
  const interval = setInterval(() => {
    if (progressPercentage.value < 90) {
      progressPercentage.value += Math.random() * 10
    } else {
      clearInterval(interval)
    }
  }, 100)
  
  // Complete progress after response
  setTimeout(() => {
    progressPercentage.value = 100
  }, 500)
}

// Toast notifications
const showToast = (severity: 'success' | 'error' | 'info' | 'warning', message: string): void => {
  if (toast.value) {
    toast.value.add({
      severity,
      summary: severity.charAt(0).toUpperCase() + severity.slice(1),
      detail: message,
      life: severity === 'error' ? 5000 : 3000
    })
  }
}

// Keyboard shortcuts
const handleKeyboardSubmit = (event: KeyboardEvent): void => {
  if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
    event.preventDefault()
    handleSubmit()
  }
}

// ============================================================================
// WATCHERS
// ============================================================================

watch(() => props.initialData, (newData) => {
  if (newData && Object.keys(newData).length > 0) {
    Object.assign(form, newData)
  }
}, { deep: true })

// ============================================================================
// EXPOSE (for template refs if needed)
// ============================================================================

defineExpose({
  form,
  isSubmitting,
  hasValidationErrors,
  hasCustomErrors,
  clearErrors,
  resetForm,
  retrySubmission,
  submit: handleSubmit
})
</script>

<template>
  <div class="form-submission">
    <!-- Toast for notifications -->
    <Toast ref="toast" />
    
    <!-- Loading overlay -->
    <div
      v-if="showProgressIndicator"
      class="loading-overlay"
    >
      <div class="loading-content">
        <ProgressSpinner strokeWidth="4" />
        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
          {{ displayLoadingMessage }}
        </p>
        
        <!-- Progress bar -->
        <ProgressBar
          v-if="showProgress"
          :value="progressPercentage"
          class="w-64 mt-3"
        />
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
          {{ Math.round(progressPercentage) }}%
        </p>
      </div>
    </div>
    
    <!-- Success message -->
    <Message
      v-if="showSuccessMessage"
      severity="success"
      :closable="false"
      class="mb-4"
    >
      {{ displaySuccessMessage }}
    </Message>
    
    <!-- Error message -->
    <Message
      v-if="showErrorMessage"
      severity="error"
      :closable="false"
      class="mb-4"
    >
      {{ displayErrorMessage }}
      
      <!-- Retry button -->
      <template v-if="!buttonLoading">
        <Button
          label="Retry"
          severity="danger"
          size="small"
          text
          @click="retrySubmission"
        />
      </template>
    </Message>
    
    <!-- Form content -->
    <form @submit.prevent="handleSubmit" @keydown="handleKeyboardSubmit">
      <!-- Default slot for form fields -->
      <slot
        :form="form"
        :errors="{ ...form.errors, ...errorErrors }"
        :loading="isSubmitting"
        :canSubmit="canSubmit"
        :clearErrors="clearErrors"
        :resetForm="resetForm"
      />
      
      <!-- Actions before buttons -->
      <div v-if="$slots['actions-before']" class="actions-before mb-4">
        <slot name="actions-before" :form="form" :submitting="isSubmitting" />
      </div>
      
      <!-- Action buttons -->
      <div class="actions flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
        <div class="flex items-center space-x-2">
          <!-- Custom actions -->
          <slot name="actions-before-buttons" :form="form" :submitting="isSubmitting" />
          
          <!-- Cancel button -->
          <Button
            v-if="showCancel"
            type="button"
            :label="cancelLabel"
            severity="secondary"
            :size="buttonSize"
            :disabled="isSubmitting"
            @click="handleCancel"
          />
        </div>
        
        <div class="flex items-center space-x-2">
          <!-- Custom actions after buttons -->
          <slot name="actions-after-buttons" :form="form" :submitting="isSubmitting" />
          
          <!-- Submit button -->
          <Button
            type="submit"
            :label="submitLabel"
            :severity="buttonSeverity"
            :size="buttonSize"
            :loading="buttonLoading && isSubmitting"
            :disabled="!canSubmit"
            :icon="buttonIcon"
            :class="buttonClass"
          />
        </div>
      </div>
      
      <!-- Actions after buttons -->
      <div v-if="$slots['actions-after']" class="actions-after mt-4">
        <slot name="actions-after" :form="form" :submitting="isSubmitting" />
      </div>
      
      <!-- Custom success content -->
      <div v-if="$slots.success && showSuccessMessage" class="success-content mt-4">
        <slot name="success" :response="successResponse" />
      </div>
      
      <!-- Custom error content -->
      <div v-if="$slots.error && showErrorMessage" class="error-content mt-4">
        <slot name="error" :errors="{ ...form.errors, ...errorErrors }" />
      </div>
    </form>
  </div>
</template>

<style scoped>
.form-submission {
  position: relative;
}

.loading-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
}

.dark .loading-overlay {
  background: rgba(0, 0, 0, 0.8);
}

.loading-content {
  text-align: center;
  padding: 2rem;
  border-radius: 0.5rem;
  background: white;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.dark .loading-content {
  background: rgba(31, 41, 55, 0.95);
  border: 1px solid rgba(75, 85, 99, 0.3);
}

.actions {
  transition: opacity 0.2s ease-in-out;
}

.actions:has(button:disabled) {
  opacity: 0.7;
}

/* Button focus states */
button:focus {
  outline: 2px solid var(--primary-color);
  outline-offset: 2px;
}

/* Form validation states */
.form-submission:has(.p-invalid) button[type="submit"] {
  animation: pulse 1s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

/* Success and error message animations */
.message-enter-active,
.message-leave-active {
  transition: all 0.3s ease-in-out;
}

.message-enter-from {
  opacity: 0;
  transform: translateY(-10px);
}

.message-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

/* Progress bar customization */
.progress-content .p-progressbar {
  background: linear-gradient(90deg, var(--primary-500) var(--progress), #e5e7eb var(--progress));
}

.dark .progress-content .p-progressbar {
  background: linear-gradient(90deg, var(--primary-600) var(--progress), #374151 var(--progress));
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .actions {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .actions .flex {
    justify-content: center;
  }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
  .loading-overlay {
    background: rgba(0, 0, 0, 0.95);
  }
  
  .loading-content {
    border: 2px solid white;
  }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  .message-enter-active,
  .message-leave-active {
    transition: none;
  }
  
  .actions:has(button:disabled) {
    animation: none;
  }
}
</style>