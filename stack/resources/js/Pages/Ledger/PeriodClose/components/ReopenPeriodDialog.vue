<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import PrimeDialog from 'primevue/dialog'
import PrimeButton from 'primevue/button'
import PrimeTextarea from 'primevue/textarea'
import PrimeCalendar from 'primevue/calendar'
import PrimeMessage from 'primevue/message'
import PrimeProgressBar from 'primevue/progressbar'
import PrimeChip from 'primevue/chip'
import PrimeTag from 'primevue/tag'

interface Props {
  visible: boolean
  periodId: string
  periodStatus: string
  permissions: {
    can_reopen: boolean
  }
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false
})

const emit = defineEmits<{
  'update:visible': [visible: boolean]
  'reopen': [data: ReopenData]
  'check-can-reopen': []
}>()

// Page props
const page = usePage()
const propsValue = computed(() => page.props as any)

// Local state
const isChecking = ref(false)
const canReopenInfo = ref<any>(null)
const form = ref({
  reason: '',
  reopen_until: '',
  notes: '',
  justification: '',
})
const isSubmitting = ref(false)

// Form validation
const validationErrors = ref<Record<string, string[]>>({})
const reasonValidation = ref<any>(null)

// Computed properties
const isValid = computed(() => {
  return form.value.reason.trim() !== '' &&
         form.value.reopen_until !== '' &&
         form.value.reason.length <= 500 &&
         form.value.notes.length <= 2000 &&
         form.value.justification.length <= 1000
})

const characterCount = computed(() => ({
  reason: form.value.reason.length,
  notes: form.value.notes.length,
  justification: form.value.justification.length,
}))

const maxDays = computed(() => {
  return canReopenInfo.value?.max_reopen_days || 7
})

const userRole = computed(() => {
  return canReopenInfo.value?.user_role || 'unknown'
})

const reopenRequirements = computed(() => {
  return canReopenInfo.value?.reopen_requirements || {}
})

const warnings = computed(() => {
  return canReopenInfo.value?.warnings || []
})

const isReasonValid = computed(() => {
  return reasonValidation.value?.is_valid ?? false
})

const reasonIssues = computed(() => {
  return reasonValidation.value?.issues || []
})

const reasonSuggestions = computed(() => {
  return reasonValidation.value?.suggestions || []
})

// Methods
async function checkCanReopen() {
  if (!props.periodId || isChecking.value) return

  isChecking.value = true

  try {
    const response = await fetch(`/api/v1/ledger/periods/${props.periodId}/close/reopen/can-reopen`)
    if (!response.ok) throw new Error('Failed to check reopen status')

    const data = await response.json()
    canReopenInfo.value = data

    // Set default reopen_until to max allowed date
    const maxDate = new Date()
    maxDate.setDate(maxDate.getDate() + data.max_reopen_days)
    form.value.reopen_until = maxDate.toISOString().split('T')[0]

    emit('check-can-reopen')

  } catch (error: any) {
    console.error('Failed to check reopen status:', error)
    canReopenInfo.value = {
      can_reopen: false,
      reason: 'Failed to check reopen status',
      requirements: [],
      warnings: ['Unable to verify reopen permissions'],
    }
  } finally {
    isChecking.value = false
  }
}

async function validateReason() {
  if (!form.value.reason.trim()) {
    reasonValidation.value = null
    return
  }

  try {
    // Simulate reason validation (in real app, this would call an API)
    const reason = form.value.reason.trim()
    const issues = []
    const suggestions = []

    if (reason.length < 10) {
      issues.push('Reason is too short. Please provide more detail.')
    }

    if (!/[A-Z]/.test(reason)) {
      suggestions.push('Consider starting the reason with a capital letter for better readability.')
    }

    if (!/(audit|adjustment|correction|error|clarification)/i.test(reason)) {
      suggestions.push('Include specific details about why the period needs to be reopened (e.g., "audit adjustment", "correction of error").')
    }

    const vagueTerms = ['stuff', 'things', 'misc', 'etc']
    for (const term of vagueTerms) {
      if (reason.toLowerCase().includes(term)) {
        suggestions.push(`Replace vague terms like "${term}" with specific details.`)
        break
      }
    }

    reasonValidation.value = {
      is_valid: issues.length === 0,
      issues,
      suggestions,
      character_count: reason.length,
    }

  } catch (error) {
    reasonValidation.value = null
  }
}

async function handleSubmit() {
  if (!isValid.value || isSubmitting.value) return

  // Clear previous validation errors
  validationErrors.value = {}

  // Client-side validation
  if (!form.value.reason.trim()) {
    validationErrors.value.reason = ['Reopen reason is required']
    return
  }

  if (!form.value.reopen_until) {
    validationErrors.value.reopen_until = ['Reopen until date is required']
    return
  }

  if (!isReasonValid.value) {
    validationErrors.value.reason = ['Please address the validation issues with the reason']
    return
  }

  isSubmitting.value = true

  try {
    const reopenData: ReopenData = {
      reason: form.value.reason.trim(),
      reopen_until: form.value.reopen_until,
      notes: form.value.notes.trim() || undefined,
      justification: form.value.justification.trim() || undefined,
    }

    emit('reopen', reopenData)

  } catch (error: any) {
    console.error('Failed to reopen period:', error)
    // Show error to user
    validationErrors.value.general = ['Failed to reopen period. Please try again.']
  } finally {
    isSubmitting.value = false
  }
}

function handleCancel() {
  emit('update:visible', false)
  resetForm()
}

function resetForm() {
  form.value = {
    reason: '',
    reopen_until: '',
    notes: '',
    justification: '',
  }
  validationErrors.value = {}
  reasonValidation.value = null
}

function getMinDate(): string {
  return new Date().toISOString().split('T')[0]
}

function getMaxDate(): string {
  const date = new Date()
  date.setDate(date.getDate() + maxDays.value)
  return date.toISOString().split('T')[0]
}

function getRoleLimitColor(): string {
  const roleLimits: Record<string, string> = {
    'cfo': 'text-blue-600',
    'controller': 'text-green-600',
    'accountant': 'text-amber-600',
  }
  return roleLimits[userRole.value] || 'text-gray-600'
}

function getRoleIcon(): string {
  const roleIcons: Record<string, string> = {
    'cfo': 'pi pi-crown',
    'controller': 'pi pi-briefcase',
    'accountant': 'pi pi-calculator',
  }
  return roleIcons[userRole.value] || 'pi pi-user'
}

// Watch for reason changes
watch(() => form.value.reason, () => {
  validateReason()
})

// Watch for dialog visibility
watch(() => props.visible, (visible) => {
  if (visible && props.periodId) {
    checkCanReopen()
  }
})

// Initialize when component mounts
onMounted(() => {
  if (props.visible && props.periodId) {
    checkCanReopen()
  }
})
</script>

<template>
  <PrimeDialog
    :visible="visible"
    @update:visible="handleCancel"
    modal
    header="Reopen Period"
    :style="{ width: '700px' }"
    :draggable="false"
    :closable="!isSubmitting"
  >
    <div v-if="isChecking" class="text-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl text-blue-600"></i>
      <p class="mt-3 text-gray-600 dark:text-gray-400">Checking reopen permissions...</p>
    </div>

    <div v-else-if="canReopenInfo && !canReopenInfo.can_reopen" class="space-y-4">
      <PrimeMessage severity="error" :closable="false">
        <span class="font-semibold">Cannot Reopen Period</span>
        <p class="mt-1">{{ canReopenInfo.reason }}</p>
      </PrimeMessage>

      <div v-if="canReopenInfo.requirements.length > 0" class="space-y-2">
        <h4 class="text-sm font-medium text-gray-900 dark:text-white">Requirements:</h4>
        <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1">
          <li v-for="requirement in canReopenInfo.requirements" :key="requirement">
            {{ requirement }}
          </li>
        </ul>
      </div>

      <div class="flex justify-end">
        <PrimeButton label="Close" @click="handleCancel" severity="secondary" />
      </div>
    </div>

    <div v-else-if="canReopenInfo && canReopenInfo.can_reopen" class="space-y-6">
      <!-- Warnings -->
      <div v-if="warnings.length > 0" class="space-y-2">
        <PrimeMessage severity="warn" :closable="false">
          <div class="space-y-1">
            <span class="font-semibold">Important Considerations:</span>
            <ul class="list-disc list-inside text-sm">
              <li v-for="warning in warnings" :key="warning">{{ warning }}</li>
            </ul>
          </div>
        </PrimeMessage>
      </div>

      <!-- Role Information -->
      <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <div class="flex items-center space-x-3">
          <i :class="[getRoleIcon(), getRoleLimitColor()]" class="text-2xl"></i>
          <div>
            <h4 class="text-sm font-medium text-gray-900 dark:text-white">
              Your Role: <span class="capitalize">{{ userRole }}</span>
            </h4>
            <p class="text-sm text-gray-600 dark:text-gray-400">
              You can reopen periods for up to {{ maxDays }} days
            </p>
          </div>
        </div>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" class="space-y-4">
        <!-- Reason -->
        <div>
          <label for="reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Reopen Reason <span class="text-red-500">*</span>
          </label>
          <PrimeTextarea
            id="reason"
            v-model="form.reason"
            rows="3"
            placeholder="Provide a clear and specific reason for reopening this period..."
            :class="{ 'border-red-500': validationErrors.reason }"
            class="w-full"
            maxlength="500"
          />
          <div class="flex justify-between items-center mt-1">
            <span class="text-xs text-gray-500">
              {{ characterCount.reason }}/500 characters
            </span>
            <PrimeProgressBar
              v-if="characterCount.reason > 450"
              :value="(characterCount.reason / 500) * 100"
              class="w-24"
              :showValue="false"
              :pt="{
                value: { class: characterCount.reason >= 500 ? 'bg-red-500' : 'bg-amber-500' }
              }"
            />
          </div>

          <!-- Reason validation feedback -->
          <div v-if="reasonValidation" class="mt-2">
            <div v-if="reasonIssues.length > 0" class="text-red-600 text-sm space-y-1">
              <p v-for="issue in reasonIssues" :key="issue" class="flex items-start">
                <i class="pi pi-exclamation-circle mt-0.5 mr-1"></i>
                {{ issue }}
              </p>
            </div>

            <div v-if="reasonSuggestions.length > 0" class="text-amber-600 text-sm space-y-1">
              <p v-for="suggestion in reasonSuggestions" :key="suggestion" class="flex items-start">
                <i class="pi pi-info-circle mt-0.5 mr-1"></i>
                {{ suggestion }}
              </p>
            </div>
          </div>

          <div v-if="validationErrors.reason" class="text-red-600 text-sm mt-1">
            {{ validationErrors.reason[0] }}
          </div>
        </div>

        <!-- Reopen Until -->
        <div>
          <label for="reopen_until" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Reopen Until <span class="text-red-500">*</span>
          </label>
          <PrimeCalendar
            id="reopen_until"
            v-model="form.reopen_until"
            :minDate="getMinDate()"
            :maxDate="getMaxDate()"
            dateFormat="yy-mm-dd"
            :class="{ 'border-red-500': validationErrors.reopen_until }"
            class="w-full"
            placeholder="Select date"
          />
          <div class="flex items-center justify-between mt-1">
            <span class="text-xs text-gray-500">
              Maximum: {{ maxDays }} days from today
            </span>
            <PrimeChip :label="`Max: ${maxDays} days`" class="text-xs" />
          </div>

          <div v-if="validationErrors.reopen_until" class="text-red-600 text-sm mt-1">
            {{ validationErrors.reopen_until[0] }}
          </div>
        </div>

        <!-- Notes -->
        <div>
          <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Additional Notes
          </label>
          <PrimeTextarea
            id="notes"
            v-model="form.notes"
            rows="3"
            placeholder="Optional additional context or details..."
            class="w-full"
            maxlength="2000"
          />
          <div class="flex justify-between items-center mt-1">
            <span class="text-xs text-gray-500">
              {{ characterCount.notes }}/2000 characters
            </span>
          </div>
        </div>

        <!-- Justification -->
        <div>
          <label for="justification" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Business Justification
          </label>
          <PrimeTextarea
            id="justification"
            v-model="form.justification"
            rows="2"
            placeholder="Business case for reopening (if required)..."
            class="w-full"
            maxlength="1000"
          />
          <div class="flex justify-between items-center mt-1">
            <span class="text-xs text-gray-500">
              {{ characterCount.justification }}/1000 characters
            </span>
          </div>
        </div>

        <!-- General Error -->
        <div v-if="validationErrors.general" class="text-red-600 text-sm">
          {{ validationErrors.general[0] }}
        </div>
      </form>
    </div>

    <template #footer>
      <div v-if="isChecking" class="flex justify-end">
        <PrimeButton label="Cancel" @click="handleCancel" severity="secondary" />
      </div>

      <div v-else-if="canReopenInfo && !canReopenInfo.can_reopen" class="flex justify-end">
        <PrimeButton label="Close" @click="handleCancel" severity="secondary" />
      </div>

      <div v-else class="flex justify-between">
        <div class="text-sm text-gray-600 dark:text-gray-400">
          <i class="pi pi-info-circle mr-1"></i>
          This action will be logged and requires audit approval
        </div>
        <div class="flex space-x-2">
          <PrimeButton
            label="Cancel"
            @click="handleCancel"
            severity="secondary"
            :disabled="isSubmitting"
          />
          <PrimeButton
            label="Reopen Period"
            icon="pi pi-sign-in"
            @click="handleSubmit"
            :loading="isSubmitting"
            :disabled="!isValid || isSubmitting"
            severity="danger"
          />
        </div>
      </div>
    </template>
  </PrimeDialog>
</template>

<style scoped>
:deep(.p-progressbar) {
  height: 4px;
}

:deep(.p-calendar) {
  width: 100%;
}
</style>
