<script setup lang="ts">
import { computed } from 'vue'
import PrimeCard from 'primevue/card'
import PrimeButton from 'primevue/button'
import PrimeTag from 'primevue/tag'
import PrimeBadge from 'primevue/badge'
import PrimeProgress from 'primevue/progressbar'

interface Task {
  id: string
  code: string
  title: string
  category: string
  sequence: number
  status: 'pending' | 'in_progress' | 'completed' | 'blocked' | 'waived'
  is_required: boolean
  notes?: string
}

interface PeriodCloseData {
  id: string
  status: string
  started_at?: string
  started_by?: string
  completed_at?: string
  tasks: Task[]
}

interface ChecklistActionsProps {
  periodClose?: PeriodCloseData
  permissions: {
    can_start: boolean
    can_validate: boolean
    can_lock: boolean
    can_complete: boolean
    can_reopen: boolean
    can_adjust: boolean
    can_manage_tasks: boolean
    can_view_reports: boolean
  }
  loading?: boolean
  disabled?: boolean
}

const props = withDefaults(defineProps<ChecklistActionsProps>(), {
  loading: false,
  disabled: false
})

const emit = defineEmits<{
  start: [notes?: string]
  validate: []
  lock: []
  complete: []
  reopen: [reason: string]
  adjust: [adjustmentData: any]
  extendReopenWindow: []
  manageTasks: []
  viewReports: []
  refresh: []
}>()

const availableActions = computed(() => {
  const actions = []

  if (!props.periodClose && props.permissions.can_start) {
    actions.push({
      key: 'start',
      label: 'Start Period Close',
      icon: 'pi pi-play',
      severity: 'primary' as const,
      description: 'Initiate the monthly closing process',
      primary: true
    })
  }

  if (props.periodClose) {
    switch (props.periodClose.status) {
      case 'in_review':
      case 'awaiting_approval':
        if (props.permissions.can_validate) {
          actions.push({
            key: 'validate',
            label: 'Run Validations',
            icon: 'pi pi-check',
            severity: 'info' as const,
            description: 'Check for issues and ensure readiness'
          })
        }
        
        if (props.permissions.can_lock && props.periodClose.status === 'awaiting_approval') {
          actions.push({
            key: 'lock',
            label: 'Lock Period',
            icon: 'pi pi-lock',
            severity: 'warning' as const,
            description: 'Lock the period for final processing'
          })
        }
        break

      case 'locked':
        if (props.permissions.can_complete) {
          actions.push({
            key: 'complete',
            label: 'Complete Close',
            icon: 'pi pi-check-circle',
            severity: 'success' as const,
            description: 'Finalize and complete the period close'
          })
        }
        break

      case 'closed':
        if (props.permissions.can_reopen) {
          actions.push({
            key: 'reopen',
            label: 'Reopen Period',
            icon: 'pi pi-sign-in',
            severity: 'danger' as const,
            description: 'Reopen period for modifications (audited)',
            warning: 'This action will be logged and requires approval'
          })
        }

        if (props.permissions.can_view_reports) {
          actions.push({
            key: 'viewReports',
            label: 'View Reports',
            icon: 'pi pi-chart-bar',
            severity: 'secondary' as const,
            description: 'Access period close reports and summaries'
          })
        }
        break

      case 'reopened':
        if (props.permissions.can_complete) {
          actions.push({
            key: 'complete',
            label: 'Complete Reopened Period',
            icon: 'pi pi-check-circle',
            severity: 'success' as const,
            description: 'Finalize the period again after modifications'
          })
        }

        if (props.permissions.can_adjust) {
          actions.push({
            key: 'adjust',
            label: 'Make Adjustments',
            icon: 'pi pi-pencil',
            severity: 'primary' as const,
            description: 'Record adjusting journal entries while reopened'
          })
        }

        if (props.permissions.can_reopen) {
          actions.push({
            key: 'extendReopenWindow',
            label: 'Extend Reopen Window',
            icon: 'pi pi-clock',
            severity: 'warning' as const,
            description: 'Extend the time period for making modifications'
          })
        }

        if (props.permissions.can_view_reports) {
          actions.push({
            key: 'viewReports',
            label: 'View Reports',
            icon: 'pi pi-chart-bar',
            severity: 'secondary' as const,
            description: 'Access period close reports and summaries'
          })
        }
        break
    }

    if (props.permissions.can_adjust && ['in_review', 'awaiting_approval', 'locked'].includes(props.periodClose.status)) {
      actions.push({
        key: 'adjust',
        label: 'Make Adjustments',
        icon: 'pi pi-pencil',
        severity: 'secondary' as const,
        description: 'Record adjusting journal entries'
      })
    }

    if (props.permissions.can_manage_tasks) {
      actions.push({
        key: 'manageTasks',
        label: 'Manage Tasks',
        icon: 'pi pi-list',
        severity: 'secondary' as const,
        description: 'Update task statuses and assignments'
      })
    }
  }

  // Always available
  actions.push({
    key: 'refresh',
    label: 'Refresh',
    icon: 'pi pi-refresh',
    severity: 'secondary' as const,
    description: 'Refresh the current status'
  })

  return actions
})

const completedTasksCount = computed(() => {
  if (!props.periodClose) return 0
  return props.periodClose.tasks.filter(task => task.status === 'completed').length
})

const totalTasksCount = computed(() => {
  if (!props.periodClose) return 0
  return props.periodClose.tasks.length
})

const requiredTasksCompleted = computed(() => {
  if (!props.periodClose) return 0
  const requiredTasks = props.periodClose.tasks.filter(task => task.is_required)
  return requiredTasks.filter(task => task.status === 'completed').length
})

const totalRequiredTasks = computed(() => {
  if (!props.periodClose) return 0
  return props.periodClose.tasks.filter(task => task.is_required).length
})

const completionPercentage = computed(() => {
  if (totalTasksCount.value === 0) return 0
  return Math.round((completedTasksCount.value / totalTasksCount.value) * 100)
})

const requiredCompletionPercentage = computed(() => {
  if (totalRequiredTasks.value === 0) return 0
  return Math.round((requiredTasksCompleted.value / totalRequiredTasks.value) * 100)
})

function handleAction(actionKey: string) {
  if (props.disabled || props.loading) return

  switch (actionKey) {
    case 'start':
      emit('start')
      break
    case 'validate':
      emit('validate')
      break
    case 'lock':
      emit('lock')
      break
    case 'complete':
      emit('complete')
      break
    case 'reopen':
      emit('reopen', 'User requested reopening')
      break
    case 'adjust':
      emit('adjust', {})
      break
    case 'extendReopenWindow':
      emit('extendReopenWindow')
      break
    case 'manageTasks':
      emit('manageTasks')
      break
    case 'viewReports':
      emit('viewReports')
      break
    case 'refresh':
      emit('refresh')
      break
  }
}

function getStatusColor(status: string): string {
  switch (status) {
    case 'closed': return 'success'
    case 'locked': return 'info'
    case 'awaiting_approval': return 'warning'
    case 'in_review': return 'primary'
    case 'reopened': return 'warning'
    default: return 'secondary'
  }
}

function getStatusText(status: string): string {
  return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
}
</script>

<template>
  <PrimeCard>
    <template #header>
      <h3 class="text-lg font-semibold">Actions & Status</h3>
    </template>

    <template #content>
      <!-- Current Status -->
      <div v-if="periodClose" class="mb-6">
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Current Status</span>
          <PrimeTag 
            :value="getStatusText(periodClose.status)" 
            :severity="getStatusColor(periodClose.status)"
          />
        </div>
        
        <!-- Progress Overview -->
        <div class="space-y-2 mb-4">
          <div>
            <div class="flex justify-between items-center mb-1">
              <span class="text-xs text-gray-600 dark:text-gray-400">Overall Progress</span>
              <span class="text-xs text-gray-600 dark:text-gray-400">{{ completionPercentage }}%</span>
            </div>
            <PrimeProgress :value="completionPercentage" :showValue="false" />
          </div>
          
          <div>
            <div class="flex justify-between items-center mb-1">
              <span class="text-xs text-gray-600 dark:text-gray-400">Required Tasks</span>
              <span class="text-xs text-gray-600 dark:text-gray-400">{{ requiredCompletionPercentage }}%</span>
            </div>
            <PrimeProgress :value="requiredCompletionPercentage" :showValue="false" />
          </div>
        </div>

        <!-- Task Summary -->
        <div class="grid grid-cols-2 gap-4 text-center">
          <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded">
            <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
              {{ completedTasksCount }}/{{ totalTasksCount }}
            </div>
            <div class="text-xs text-gray-600 dark:text-gray-400">Total Tasks</div>
          </div>
          
          <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded">
            <div class="text-lg font-semibold text-green-600">
              {{ requiredTasksCompleted }}/{{ totalRequiredTasks }}
            </div>
            <div class="text-xs text-gray-600 dark:text-gray-400">Required Tasks</div>
          </div>
        </div>
      </div>

      <!-- No Period Close State -->
      <div v-else class="mb-6 text-center py-4">
        <i class="pi pi-circle text-3xl text-gray-300 dark:text-gray-600 mb-2"></i>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Period close has not been started
        </p>
      </div>

      <!-- Available Actions -->
      <div class="space-y-2">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Available Actions</h4>
        
        <div v-if="availableActions.length === 0" class="text-center py-4">
          <i class="pi pi-lock text-2xl text-gray-300 dark:text-gray-600 mb-2"></i>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            No actions available at this time
          </p>
        </div>

        <div v-else class="space-y-2">
          <PrimeButton
            v-for="action in availableActions"
            :key="action.key"
            :label="action.label"
            :icon="action.icon"
            :severity="action.severity"
            :loading="loading"
            :disabled="disabled"
            :class="{ 'w-full': !action.primary }"
            @click="handleAction(action.key)"
            v-tooltip="action.description"
          >
            <span v-if="action.primary" class="font-semibold">{{ action.label }}</span>
            <span v-else>{{ action.label }}</span>
          </PrimeButton>
        </div>
      </div>

      <!-- Warning Messages -->
      <div v-if="availableActions.some(a => a.warning)" class="mt-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
        <div class="flex items-start space-x-2">
          <i class="pi pi-exclamation-triangle text-amber-600 mt-0.5"></i>
          <div class="text-sm text-amber-800 dark:text-amber-200">
            <strong>Important:</strong> Some actions may require approval and will be logged for audit purposes.
          </div>
        </div>
      </div>
    </template>
  </PrimeCard>
</template>

<style scoped>
:deep(.p-progressbar .p-progressbar-value) {
  transition: width 0.3s ease-in-out;
}

:deep(.p-tag) {
  font-size: 0.75rem;
}

:deep(.p-button) {
  transition: all 0.2s ease;
}

:deep(.p-button:hover) {
  transform: translateY(-1px);
}
</style>