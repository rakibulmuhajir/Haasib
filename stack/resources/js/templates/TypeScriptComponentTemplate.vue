<!--
EXAMPLE TEMPLATE: TypeScript Vue Component with PrimeVue and Inertia.js

This template demonstrates best practices for creating TypeScript components
with proper type safety, PrimeVue integration, and Inertia.js patterns.

USAGE:
1. Copy this template to your components directory
2. Update the component name and file path
3. Customize the props, emits, and functionality for your specific use case
4. Import and use appropriate types from @/types

FEATURES:
✅ Full TypeScript support with proper typing
✅ Composition API with <script setup>
✅ PrimeVue component integration
✅ Inertia.js navigation patterns
✅ Form handling with validation
✅ Error handling and loading states
✅ Responsive design with Tailwind CSS
✅ Dark mode support
✅ Accessibility considerations
-->
<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { useForm } from '@inertiajs/vue3'
import { useTypes } from '@/composables/useTypes'
import type { Company, User, PaginatedData, FormErrors } from '@/types'

// ============================================================================
// PROPS DEFINITION
// ============================================================================

interface Props {
  // Required props
  company: Company
  
  // Optional props with defaults
  editable?: boolean
  showActions?: boolean
  maxHeight?: string
  
  // Complex props with proper typing
  users?: PaginatedData<User>
  initialData?: Partial<Company>
}

const props = withDefaults(defineProps<Props>(), {
  editable: false,
  showActions: true,
  maxHeight: '400px',
  users: undefined,
  initialData: () => ({})
})

// ============================================================================
// EMITS DEFINITION
// ============================================================================

interface Emits {
  // Update events
  (e: 'update', company: Company): void
  (e: 'update:field', field: keyof Company, value: any): void
  
  // Action events
  (e: 'edit', company: Company): void
  (e: 'delete', id: string): void
  (e: 'save', company: Company): void
  (e: 'cancel'): void
  
  // Status events
  (e: 'loading', isLoading: boolean): void
  (e: 'error', error: string): void
  (e: 'success', message: string): void
}

const emit = defineEmits<Emits>()

// ============================================================================
// COMPOSABLES
// ============================================================================

const {
  formatCurrency,
  formatDate,
  getStatusClasses,
  getCompanyDisplayName,
  hasFormErrors,
  getFormErrorMessage
} = useTypes()

// ============================================================================
// REACTIVE STATE
// ============================================================================

// Loading states
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)

// Local state
const localCompany = ref<Company>({ ...props.company })
const isEditing = ref(false)
const showDeleteModal = ref(false)
const errorMessage = ref('')

// ============================================================================
// FORM HANDLING
// ============================================================================

const form = useForm({
  name: localCompany.value.name,
  legal_name: localCompany.value.legal_name || '',
  email: localCompany.value.email,
  phone: localCompany.value.phone || '',
  website: localCompany.value.website || '',
  industry: localCompany.value.industry,
  base_currency: localCompany.value.base_currency,
  is_active: localCompany.value.is_active,
})

// Form validation
const validationErrors = ref<FormErrors>({})

const validateForm = (): boolean => {
  const errors: FormErrors = {}
  
  // Required field validation
  if (!form.name.trim()) {
    errors.name = 'Company name is required'
  }
  
  if (!form.email.trim()) {
    errors.email = 'Email is required'
  } else if (!isValidEmail(form.email)) {
    errors.email = 'Please enter a valid email address'
  }
  
  if (!form.industry) {
    errors.industry = 'Industry is required'
  }
  
  // Phone validation (optional but if provided, must be valid)
  if (form.phone && !isValidPhone(form.phone)) {
    errors.phone = 'Please enter a valid phone number'
  }
  
  // Website validation (optional but if provided, must be valid)
  if (form.website && !isValidUrl(form.website)) {
    errors.website = 'Please enter a valid website URL'
  }
  
  validationErrors.value = errors
  return !hasFormErrors(errors)
}

// ============================================================================
// COMPUTED PROPERTIES
// ============================================================================

const isDirty = computed(() => {
  return JSON.stringify(localCompany.value) !== JSON.stringify(props.company)
})

const canSave = computed(() => {
  return isDirty.value && !saving.value && validateForm()
})

const statusClass = computed(() => {
  return getStatusClasses(localCompany.value.is_active ? 'active' : 'inactive')
})

const formattedCreatedAt = computed(() => {
  return formatDate(localCompany.value.created_at, 'long')
})

const userOptions = computed(() => {
  if (!props.users?.data) return []
  return props.users.data.map(user => ({
    label: user.name,
    value: user.id,
    email: user.email
  }))
})

// ============================================================================
// METHODS
// ============================================================================

// Basic validation helpers
const isValidEmail = (email: string): boolean => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(email)
}

const isValidPhone = (phone: string): boolean => {
  const phoneRegex = /^[\d\s\-\+\(\)]+$/
  return phoneRegex.test(phone) && phone.replace(/\D/g, '').length >= 10
}

const isValidUrl = (url: string): boolean => {
  try {
    new URL(url)
    return true
  } catch {
    return false
  }
}

// Form actions
const startEdit = (): void => {
  isEditing.value = true
  emit('edit', localCompany.value)
}

const cancelEdit = (): void => {
  isEditing.value = false
  localCompany.value = { ...props.company }
  form.clearErrors()
  validationErrors.value = {}
  emit('cancel')
}

const saveCompany = async (): Promise<void> => {
  if (!validateForm()) return
  
  saving.value = true
  emit('loading', true)
  
  try {
    form.put(`/companies/${localCompany.value.id}`, {
      onSuccess: () => {
        isEditing.value = false
        emit('save', localCompany.value)
        emit('success', 'Company updated successfully')
      },
      onError: (errors) => {
        validationErrors.value = errors
        emit('error', 'Failed to save company')
      }
    })
  } catch (error) {
    console.error('Save error:', error)
    emit('error', 'An unexpected error occurred')
  } finally {
    saving.value = false
    emit('loading', false)
  }
}

const deleteCompany = async (): Promise<void> => {
  deleting.value = true
  emit('loading', true)
  
  try {
    await router.delete(`/companies/${localCompany.value.id}`, {
      onSuccess: () => {
        emit('delete', localCompany.value.id)
        emit('success', 'Company deleted successfully')
        showDeleteModal.value = false
      },
      onError: () => {
        emit('error', 'Failed to delete company')
      }
    })
  } catch (error) {
    console.error('Delete error:', error)
    emit('error', 'An unexpected error occurred')
  } finally {
    deleting.value = false
    emit('loading', false)
  }
}

// Navigation
const navigateToCompany = (): void => {
  router.visit(`/companies/${localCompany.value.id}`)
}

const navigateToEdit = (): void => {
  router.visit(`/companies/${localCompany.value.id}/edit`)
}

// ============================================================================
// WATCHERS
// ============================================================================

watch(() => props.company, (newCompany) => {
  localCompany.value = { ...newCompany }
  form.reset()
  validationErrors.value = {}
}, { deep: true })

watch(() => localCompany.value.is_active, (newValue) => {
  form.is_active = newValue
})

// ============================================================================
// LIFECYCLE
// ============================================================================

onMounted(() => {
  // Initialize form with current data
  if (props.initialData && Object.keys(props.initialData).length > 0) {
    localCompany.value = { ...localCompany.value, ...props.initialData }
  }
})

// ============================================================================
// EXPOSE (for template refs if needed)
// ============================================================================

defineExpose({
  validateForm,
  saveCompany,
  deleteCompany,
  isDirty,
  localCompany,
  validationErrors
})
</script>

<template>
  <div class="company-card bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
    <!-- Header -->
    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
      <div class="flex items-start justify-between">
        <div class="flex-1">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ getCompanyDisplayName(localCompany) }}
          </h3>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            {{ localCompany.industry }} • {{ localCompany.base_currency }}
          </p>
        </div>
        
        <!-- Status Badge -->
        <span :class="statusClass" class="px-2 py-1 text-xs font-medium rounded-full">
          {{ localCompany.is_active ? 'Active' : 'Inactive' }}
        </span>
      </div>
    </div>

    <!-- Content -->
    <div class="p-6">
      <!-- View Mode -->
      <div v-if="!isEditing" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
            <p class="text-sm text-gray-900 dark:text-white">{{ localCompany.email }}</p>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
            <p class="text-sm text-gray-900 dark:text-white">
              {{ localCompany.phone || 'Not provided' }}
            </p>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Website</label>
            <p class="text-sm text-gray-900 dark:text-white">
              <Link 
                v-if="localCompany.website" 
                :href="localCompany.website" 
                target="_blank"
                class="text-blue-600 hover:text-blue-800 hover:underline"
              >
                {{ localCompany.website }}
              </Link>
              <span v-else>Not provided</span>
            </p>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Created</label>
            <p class="text-sm text-gray-900 dark:text-white">{{ formattedCreatedAt }}</p>
          </div>
        </div>
      </div>

      <!-- Edit Mode -->
      <form v-else @submit.prevent="saveCompany" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Name -->
          <div>
            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Company Name <span class="text-red-500">*</span>
            </label>
            <InputText
              id="name"
              v-model="form.name"
              class="w-full"
              :class="{ 'p-invalid': getFormErrorMessage(validationErrors, 'name') }"
              placeholder="Enter company name"
            />
            <small class="text-red-500">{{ getFormErrorMessage(validationErrors, 'name') }}</small>
          </div>

          <!-- Legal Name -->
          <div>
            <label for="legal_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Legal Name
            </label>
            <InputText
              id="legal_name"
              v-model="form.legal_name"
              class="w-full"
              placeholder="Enter legal company name"
            />
          </div>

          <!-- Email -->
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Email <span class="text-red-500">*</span>
            </label>
            <InputText
              id="email"
              v-model="form.email"
              type="email"
              class="w-full"
              :class="{ 'p-invalid': getFormErrorMessage(validationErrors, 'email') }"
              placeholder="company@example.com"
            />
            <small class="text-red-500">{{ getFormErrorMessage(validationErrors, 'email') }}</small>
          </div>

          <!-- Phone -->
          <div>
            <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Phone
            </label>
            <InputText
              id="phone"
              v-model="form.phone"
              class="w-full"
              :class="{ 'p-invalid': getFormErrorMessage(validationErrors, 'phone') }"
              placeholder="+1 (555) 123-4567"
            />
            <small class="text-red-500">{{ getFormErrorMessage(validationErrors, 'phone') }}</small>
          </div>

          <!-- Website -->
          <div>
            <label for="website" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Website
            </label>
            <InputText
              id="website"
              v-model="form.website"
              class="w-full"
              :class="{ 'p-invalid': getFormErrorMessage(validationErrors, 'website') }"
              placeholder="https://example.com"
            />
            <small class="text-red-500">{{ getFormErrorMessage(validationErrors, 'website') }}</small>
          </div>

          <!-- Industry -->
          <div>
            <label for="industry" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Industry <span class="text-red-500">*</span>
            </label>
            <Dropdown
              id="industry"
              v-model="form.industry"
              :options="[
                { label: 'Technology', value: 'technology' },
                { label: 'Hospitality', value: 'hospitality' },
                { label: 'Retail', value: 'retail' },
                { label: 'Professional Services', value: 'professional_services' },
                { label: 'Other', value: 'other' }
              ]"
              option-label="label"
              option-value="value"
              class="w-full"
              :class="{ 'p-invalid': getFormErrorMessage(validationErrors, 'industry') }"
            />
            <small class="text-red-500">{{ getFormErrorMessage(validationErrors, 'industry') }}</small>
          </div>
        </div>
      </form>
    </div>

    <!-- Actions -->
    <div v-if="showActions" class="px-6 py-4 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
      <div class="flex items-center justify-between">
        <div class="text-sm text-gray-500 dark:text-gray-400">
          Last updated: {{ formatDate(localCompany.updated_at) }}
        </div>
        
        <div class="flex items-center space-x-3">
          <!-- View Mode Actions -->
          <template v-if="!isEditing">
            <Button
              v-if="editable"
              type="button"
              label="Edit"
              icon="pi pi-pencil"
              severity="secondary"
              size="small"
              @click="startEdit"
            />
            
            <Button
              type="button"
              label="View Details"
              icon="pi pi-eye"
              severity="primary"
              size="small"
              @click="navigateToCompany"
            />
            
            <Button
              type="button"
              label="Delete"
              icon="pi pi-trash"
              severity="danger"
              size="small"
              text
              @click="showDeleteModal = true"
            />
          </template>

          <!-- Edit Mode Actions -->
          <template v-else>
            <Button
              type="button"
              label="Cancel"
              severity="secondary"
              size="small"
              @click="cancelEdit"
            />
            
            <Button
              type="submit"
              label="Save"
              icon="pi pi-save"
              severity="primary"
              size="small"
              :loading="saving"
              :disabled="!canSave"
              @click="saveCompany"
            />
          </template>
        </div>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <Dialog
    v-model:visible="showDeleteModal"
    header="Confirm Delete"
    :style="{ width: '450px' }"
    :modal="true"
  >
    <div class="flex items-center">
      <i class="pi pi-exclamation-triangle mr-3" style="font-size: 2rem; color: var(--red-500)" />
      <span>
        Are you sure you want to delete <strong>{{ getCompanyDisplayName(localCompany) }}</strong>?
        This action cannot be undone.
      </span>
    </div>
    
    <template #footer>
      <Button
        label="Cancel"
        severity="secondary"
        @click="showDeleteModal = false"
      />
      <Button
        label="Delete"
        severity="danger"
        :loading="deleting"
        @click="deleteCompany"
      />
    </template>
  </Dialog>
</template>

<style scoped>
/* Component-specific styles */
.company-card {
  transition: all 0.2s ease-in-out;
}

.company-card:hover {
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Dark mode adjustments */
@media (prefers-color-scheme: dark) {
  .company-card:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2);
  }
}
</style>