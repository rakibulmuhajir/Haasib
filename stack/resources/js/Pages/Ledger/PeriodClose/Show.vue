<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import QuickLinks from '@/Components/QuickLinks.vue'
import { usePageActions } from '@/composables/usePageActions'
import PrimeButton from 'primevue/button'
import PrimeCard from 'primevue/card'
import PrimeTabs from 'primevue/tabs'
import PrimeTabList from 'primevue/tablist'
import PrimeTab from 'primevue/tab'
import PrimeTabPanels from 'primevue/tabpanels'
import PrimeTabPanel from 'primevue/tabpanel'
import PrimeDialog from 'primevue/dialog'
import PrimeTextarea from 'primevue/textarea'
import PrimeMessage from 'primevue/message'
import PrimeProgressbar from 'primevue/progressbar'
import PrimeTag from 'primevue/tag'
import PrimeBadge from 'primevue/badge'
import { usePeriodClose } from '@/composables/usePeriodClose'
import { usePeriodCloseForms } from '@/composables/usePeriodCloseForms'
import { useToast } from 'primevue/usetoast'
import {
  TaskList,
  ValidationSummary,
  ProgressCard,
  ChecklistActions,
  DeadlinesAlert
} from './components'
import AdjustmentDialog from './components/AdjustmentDialog.vue'
import ReportsPanel from './components/ReportsPanel.vue'
import ReopenPeriodDialog from './components/ReopenPeriodDialog.vue'

// Page props
const page = usePage()
const props = computed(() => page.props as any)

// Use composables
const periodId = computed(() => props.value.period?.id || '')
const { actions } = usePageActions()
const {
  isLoading,
  hasError,
  errorMessage,
  hasPeriodClose,
  periodCloseStatus,
  tasks,
  completedTasks,
  inProgressTasks,
  blockedTasks,
  requiredTasks,
  completedRequiredTasks,
  completionPercentage,
  requiredCompletionPercentage,
  hasValidation,
  validationScore,
  validationPassed,
  hasValidationIssues,
  canCompletePeriodClose,
  loadPeriodClose,
  validatePeriodClose,
  completeTask,
  clearError
} = usePeriodClose()

// Forms
const { useTaskUpdateForm, usePeriodCloseActionForm } = usePeriodCloseForms()
const { form: taskForm, updateTask, reset: resetTaskForm } = useTaskUpdateForm()
const { form: actionForm, executeAction, reset: resetActionForm } = usePeriodCloseActionForm()

// Define page actions
const pageActions = [
  {
    key: 'refresh',
    label: 'Refresh',
    icon: 'pi pi-refresh',
    severity: 'secondary',
    action: () => handleLoadPeriodClose()
  },
  {
    key: 'back',
    label: 'Back to Dashboard',
    icon: 'pi pi-arrow-left',
    severity: 'secondary',
    routeName: 'ledger.period-close.index'
  }
]

// Define quick links for period close details
const quickLinks = [
  {
    label: 'Period Close Dashboard',
    url: '/ledger/period-close',
    icon: 'pi pi-th-large'
  },
  {
    label: 'Bank Reconciliation',
    url: '/ledger/bank-reconciliation',
    icon: 'pi pi-bank'
  },
  {
    label: 'Journal Entries',
    url: '/accounting/journal-entries',
    icon: 'pi pi-book'
  },
  {
    label: 'Trial Balance',
    url: '/ledger/trial-balance',
    icon: 'pi pi-calculator'
  },
  {
    label: 'Generate Reports',
    url: '#',
    icon: 'pi pi-file-pdf',
    action: () => activeTabIndex.value = 3
  }
]

// Set page actions
actions.value = pageActions

// Toast notifications
const toast = useToast()

// Local state
const activeTabIndex = ref(0)
const showActionDialog = ref(false)
const currentAction = ref('')
const selectedTask = ref<any>(null)
const showTaskDialog = ref(false)
const showAdjustmentDialog = ref(false)
const showReportsDialog = ref(false)
const showReopenDialog = ref(false)
const showExtendReopenWindowDialog = ref(false)

// Computed properties
const period = computed(() => props.value.period)
const permissions = computed(() => props.value.permissions)
const periodClose = computed(() => props.value.period_close)
const accounts = computed(() => props.value.accounts || [])

// Task filters
const taskFilters = ref(['pending', 'in_progress', 'completed', 'blocked', 'waived'])
const activeFilter = ref('all')

const filteredTasks = computed(() => {
  if (activeFilter.value === 'all') return tasks.value
  return tasks.value.filter(task => task.status === activeFilter.value)
})

// Methods
async function handleLoadPeriodClose() {
  if (periodId.value) {
    await loadPeriodClose(periodId.value)
  }
}

async function handleValidatePeriodClose() {
  if (periodId.value) {
    await validatePeriodClose(periodId.value)
  }
}

async function handleCompleteTask(task: any) {
  if (!periodId.value) return

  selectedTask.value = task
  taskForm.status = 'completed'
  taskForm.notes = ''

  const success = await updateTask(periodId.value, task.id, {
    status: 'completed',
    notes: taskForm.notes.trim() || undefined
  })

  if (success) {
    showTaskDialog.value = false
    selectedTask.value = null
    resetTaskForm()
    toast.add({
      severity: 'success',
      summary: 'Task Completed',
      detail: 'Task has been marked as completed',
      life: 3000
    })
  }
}

async function handleExecuteAction(action: string, data?: any) {
  if (!periodId.value) return

  currentAction.value = action

  const success = await executeAction(periodId.value, action, data)

  if (success) {
    showActionDialog.value = false
    resetActionForm()

    // Show success message based on action
    let successMessage = 'Action completed successfully'
    if (action === 'lock') {
      successMessage = 'Period locked successfully. The period is now ready for final completion.'
    } else if (action === 'complete') {
      successMessage = 'Period close completed successfully! The accounting period has been closed.'
    }

    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: successMessage,
      life: 5000
    })

    // Reload data after action
    await handleLoadPeriodClose()
  }
}

async function handleSaveAdjustment(adjustmentData: any) {
  if (!periodId.value) return

  try {
    const success = await executeAction(periodId.value, 'adjust', { adjustmentData })

    if (success) {
      showAdjustmentDialog.value = false

      toast.add({
        severity: 'success',
        summary: 'Adjustment Created',
        detail: 'Period close adjustment has been created successfully',
        life: 3000
      })

      // Reload data after adjustment
      await handleLoadPeriodClose()
    }
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to create adjustment: ' + error.message,
      life: 5000
    })
  }
}

function handleCancelAdjustment() {
  showAdjustmentDialog.value = false
}

async function handleReopenPeriod(reopenData: any) {
  if (!periodId.value) return

  try {
    const response = await fetch(`/api/v1/ledger/periods/${periodId.value}/close/reopen`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify(reopenData)
    })

    if (!response.ok) {
      const errorData = await response.json()
      throw new Error(errorData.error || 'Failed to reopen period')
    }

    const data = await response.json()
    showReopenDialog.value = false

    toast.add({
      severity: 'success',
      summary: 'Period Reopened',
      detail: 'Period has been reopened successfully',
      life: 3000
    })

    // Reload data to show updated status
    await handleLoadPeriodClose()

  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.message || 'Failed to reopen period',
      life: 5000
    })
  }
}

function handleCancelReopen() {
  showReopenDialog.value = false
}

async function handleExtendReopenWindow() {
  if (!periodId.value) return

  // For now, just show a message. In a real implementation,
  // you would open a dialog to collect the extension details
  toast.add({
    severity: 'info',
    summary: 'Extend Reopen Window',
    detail: 'This feature is coming soon. Please contact your administrator.',
    life: 3000
  })
}

function openTaskDialog(task: any) {
  selectedTask.value = task
  taskForm.status = task.status
  taskForm.notes = task.notes || ''
  showTaskDialog.value = true
}

function openActionDialog(action: string) {
  currentAction.value = action
  actionForm.reason = ''
  actionForm.notes = ''
  showActionDialog.value = true
}

function getActionDialogTitle(action: string): string {
  switch (action) {
    case 'validate': return 'Run Validations'
    case 'lock': return 'Lock Period'
    case 'complete': return 'Complete Period Close'
    case 'reopen': return 'Reopen Period'
    default: return 'Confirm Action'
  }
}

function getActionDialogContent(action: string): string {
  switch (action) {
    case 'validate': return 'This will run all validation checks for the period close.'
    case 'lock': return 'This will lock the period for final processing. Make sure all tasks are completed.'
    case 'complete': return 'This will finalize and complete the period close process.'
    case 'reopen': return 'This will reopen the period for modifications. This action will be audited.'
    default: return 'Are you sure you want to proceed with this action?'
  }
}

function setTaskFilter(filter: string) {
  activeFilter.value = filter
}

async function handleReportsGenerated(reports: any) {
  toast.add({
    severity: 'success',
    summary: 'Reports Generated',
    detail: 'Period close reports have been generated successfully',
    life: 3000
  })

  // Reload data to show updated reports
  await handleLoadPeriodClose()
}

function handleReportsError(error: string) {
  toast.add({
    severity: 'error',
    summary: 'Reports Generation Failed',
    detail: error,
    life: 5000
  })
}

// Load data on mount
onMounted(() => {
  handleLoadPeriodClose()
})

// Handle action emissions from ChecklistActions component
function handleAction(action: string, data?: any) {
  switch (action) {
    case 'start':
      // Navigate to start page or show start dialog
      router.get(`/ledger/periods/${periodId.value}/start`)
      break
    case 'validate':
      handleValidatePeriodClose()
      break
    case 'lock':
      openActionDialog('lock')
      break
    case 'complete':
      openActionDialog('complete')
      break
    case 'reopen':
      showReopenDialog.value = true
      break
    case 'extendReopenWindow':
      handleExtendReopenWindow()
      break
    case 'adjust':
      showAdjustmentDialog.value = true
      break
    case 'manageTasks':
      activeTabIndex.value = 1 // Switch to tasks tab
      break
    case 'viewReports':
      activeTabIndex.value = 3 // Switch to reports tab
      break
    case 'refresh':
      handleLoadPeriodClose()
      break
  }
}
</script>

<template>
  <Head title="Period Close Details" />

  <LayoutShell>
    <!-- Universal Page Header -->
    <UniversalPageHeader
      title="Period Close"
      description="Manage monthly closing workflows"
      subDescription="Complete accounting period closing procedures"
      :show-search="true"
      search-placeholder="Search periods..."
    />

    <!-- Main Content Grid -->
    <div class="content-grid-5-6">
      <div class="main-content">

      <!-- Error Message -->
      <PrimeMessage v-if="hasError" severity="error" :closable="false" class="mb-4">
        {{ errorMessage }}
        <PrimeButton
          label="Dismiss"
          size="small"
          severity="secondary"
          @click="clearError"
          class="ml-2"
        />
      </PrimeMessage>

      <!-- Overview Cards -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Period Progress Card -->
        <div class="lg:col-span-1">
          <ProgressCard
            :period="{
              ...period,
              period_close: periodClose
            }"
            :detailed="true"
            size="medium"
          />
        </div>

        <!-- Actions Card -->
        <div class="lg:col-span-1">
          <ChecklistActions
            :period-close="periodClose"
            :permissions="permissions"
            :loading="isLoading"
            @start="() => handleAction('start')"
            @validate="() => handleAction('validate')"
            @lock="() => handleAction('lock')"
            @complete="() => handleAction('complete')"
            @reopen="(reason) => handleAction('reopen', { reason })"
            @adjust="(data) => handleAction('adjust', data)"
            @manage-tasks="() => handleAction('manageTasks')"
            @view-reports="() => handleAction('viewReports')"
            @refresh="handleLoadPeriodClose"
          />
        </div>

        <!-- Quick Stats -->
        <div class="lg:col-span-1">
          <PrimeCard>
            <template #header>
              <h3 class="text-lg font-semibold">Quick Stats</h3>
            </template>
            <template #content>
              <div class="space-y-4">
                <div class="flex justify-between items-center">
                  <span class="text-sm text-gray-600 dark:text-gray-400">Overall Progress</span>
                  <span class="font-semibold">{{ completionPercentage }}%</span>
                </div>
                <PrimeProgressbar :value="completionPercentage" :showValue="false" />

                <div class="flex justify-between items-center">
                  <span class="text-sm text-gray-600 dark:text-gray-400">Required Tasks</span>
                  <span class="font-semibold text-green-600">{{ requiredCompletionPercentage }}%</span>
                </div>
                <PrimeProgressbar :value="requiredCompletionPercentage" :showValue="false" />

                <div v-if="hasValidation" class="pt-4 border-t border-gray-200 dark:border-gray-700">
                  <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Validation Score</span>
                    <span class="font-semibold" :class="{
                      'text-green-600': validationPassed,
                      'text-amber-600': !validationPassed && validationScore >= 70,
                      'text-red-600': !validationPassed && validationScore < 70
                    }">{{ validationScore }}/100</span>
                  </div>
                </div>
              </div>
            </template>
          </PrimeCard>
        </div>
      </div>

      <!-- Main Content Tabs -->
      <PrimeTabs v-model:activeIndex="activeTabIndex">
        <PrimeTabList>
          <PrimeTab value="0">Tasks</PrimeTab>
          <PrimeTab value="1">Validation</PrimeTab>
          <PrimeTab value="2">Activity</PrimeTab>
          <PrimeTab value="3" v-if="permissions.can_view_reports">Reports</PrimeTab>
        </PrimeTabList>

        <PrimeTabPanels>
          <!-- Tasks Tab -->
          <PrimeTabPanel value="0">
            <TaskList
              :tasks="filteredTasks"
              :show-progress="true"
              :compact="false"
            />
          </PrimeTabPanel>

          <!-- Validation Tab -->
          <PrimeTabPanel value="1">
            <div v-if="!hasValidation" class="text-center py-8">
              <i class="pi pi-shield text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
              <p class="text-gray-500 dark:text-gray-400 mb-4">
                No validation results available
              </p>
              <PrimeButton
                label="Run Validations"
                icon="pi pi-check"
                @click="handleValidatePeriodClose"
                :loading="isLoading"
                :disabled="!permissions.can_validate"
              />
            </div>

            <ValidationSummary
              v-else-if="periodClose && hasValidation"
              :results="{
                status: validationPassed ? 'passed' : 'failed',
                score: validationScore,
                issues: [], // Would come from validation data
                trial_balance: {
                  is_balanced: true,
                  total_debits: 0,
                  total_credits: 0,
                  difference: 0
                },
                unposted_documents: {
                  count: 0,
                  total_amount: 0,
                  document_types: {}
                },
                recommendations: [],
                validation_metadata: {
                  validation_timestamp: new Date().toISOString(),
                  validated_by: 'Current User',
                  validation_version: '1.0',
                  checks_performed: ['trial_balance', 'unposted_documents']
                }
              }"
              :detailed="true"
              :show-details="true"
            />
          </PrimeTabPanel>

          <!-- Activity Tab -->
          <PrimeTabPanel value="2">
            <PrimeCard>
              <template #header>
                <h3 class="text-lg font-semibold">Recent Activity</h3>
              </template>
              <template #content>
                <div class="text-center py-8">
                  <i class="pi pi-history text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
                  <p class="text-gray-500 dark:text-gray-400">
                    Activity tracking coming soon
                  </p>
                </div>
              </template>
            </PrimeCard>
          </PrimeTabPanel>

          <!-- Reports Tab -->
          <PrimeTabPanel value="3" v-if="permissions.can_view_reports">
            <ReportsPanel
              :period-id="periodId"
              :period-close="periodClose"
              :permissions="permissions"
              :loading="isLoading"
              @reports-generated="handleReportsGenerated"
              @reports-error="handleReportsError"
            />
          </PrimeTabPanel>
        </PrimeTabPanels>
      </PrimeTabs>

      <!-- Task Update Dialog -->
      <PrimeDialog
        v-model:visible="showTaskDialog"
        modal
        :header="`Update Task - ${selectedTask?.title}`"
        style="width: 600px"
      >
        <div class="space-y-4">
          <div>
            <label for="taskStatus" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Status
            </label>
            <select
              id="taskStatus"
              v-model="taskForm.status"
              class="w-full p-2 border rounded-md"
            >
              <option value="pending">Pending</option>
              <option value="in_progress">In Progress</option>
              <option value="completed">Completed</option>
              <option value="blocked">Blocked</option>
              <option value="waived">Waived</option>
            </select>
          </div>

          <div>
            <label for="taskNotes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Notes
            </label>
            <PrimeTextarea
              id="taskNotes"
              v-model="taskForm.notes"
              rows="4"
              placeholder="Add notes about this task..."
              class="w-full"
            />
          </div>
        </div>

        <template #footer>
          <PrimeButton
            label="Cancel"
            @click="showTaskDialog = false"
            severity="secondary"
          />
          <PrimeButton
            label="Update Task"
            @click="handleCompleteTask(selectedTask)"
            :loading="taskForm.processing"
            severity="primary"
          />
        </template>
      </PrimeDialog>

      <!-- Action Confirmation Dialog -->
      <PrimeDialog
        v-model:visible="showActionDialog"
        modal
        :header="getActionDialogTitle(currentAction)"
        style="width: 500px"
      >
        <div class="space-y-4">
          <p class="text-gray-700 dark:text-gray-300">
            {{ getActionDialogContent(currentAction) }}
          </p>

          <div v-if="currentAction === 'reopen' || currentAction === 'lock'">
            <label for="actionReason" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Reason (Required)
            </label>
            <PrimeTextarea
              id="actionReason"
              v-model="actionForm.reason"
              rows="3"
              placeholder="Please provide a reason..."
              class="w-full"
            />
          </div>

          <div v-if="currentAction === 'complete'">
            <label for="actionNotes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Notes (Optional)
            </label>
            <PrimeTextarea
              id="actionNotes"
              v-model="actionForm.notes"
              rows="3"
              placeholder="Add any additional notes..."
              class="w-full"
            />
          </div>
        </div>

        <template #footer>
          <PrimeButton
            label="Cancel"
            @click="showActionDialog = false"
            severity="secondary"
          />
          <PrimeButton
            label="Confirm"
            @click="handleExecuteAction(currentAction)"
            :loading="actionForm.processing"
            severity="primary"
          />
        </template>
      </PrimeDialog>

      <!-- Adjustment Dialog -->
      <AdjustmentDialog
        :visible="showAdjustmentDialog"
        @update:visible="showAdjustmentDialog = $event"
        :accounts="accounts"
        :loading="actionForm.processing"
        @save="handleSaveAdjustment"
        @cancel="handleCancelAdjustment"
      />

      <!-- Reopen Period Dialog -->
      <ReopenPeriodDialog
        :visible="showReopenDialog"
        @update:visible="showReopenDialog = $event"
        :period-id="periodId"
        :period-status="period?.status"
        :permissions="permissions"
        :loading="isLoading"
        @reopen="handleReopenPeriod"
      />
    </div>

    <!-- Right Column - Quick Links -->
    <div class="sidebar-content">
      <QuickLinks 
        :links="quickLinks" 
        title="Period Actions"
      />
    </div>
  </div>
  </LayoutShell>
</template>

