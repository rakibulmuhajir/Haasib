import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import PeriodCloseApiService, { 
  type PeriodCloseSnapshot, 
  type ValidationResponse,
  type StartPeriodCloseRequest,
  type UpdateTaskRequest
} from '@/services/periodCloseApi'

// Types for state management
export interface PeriodCloseState {
  currentPeriod: any | null
  periodClose: PeriodCloseSnapshot['period_close'] | null
  tasks: PeriodCloseSnapshot['period_close']['tasks'] | []
  validation: ValidationResponse | null
  loading: boolean
  error: string | null
  lastUpdated: Date | null
}

export interface PeriodCloseActions {
  loadPeriodClose: (periodId: string) => Promise<void>
  startPeriodClose: (periodId: string, notes?: string) => Promise<void>
  validatePeriodClose: (periodId: string) => Promise<void>
  updateTask: (periodId: string, taskId: string, data: UpdateTaskRequest) => Promise<void>
  completeTask: (periodId: string, taskId: string, notes?: string) => Promise<void>
  refreshPeriodClose: (periodId: string) => Promise<void>
  clearError: () => void
  reset: () => void
}

// Composable for period close state management
export function usePeriodClose() {
  // Reactive state
  const state = ref<PeriodCloseState>({
    currentPeriod: null,
    periodClose: null,
    tasks: [],
    validation: null,
    loading: false,
    error: null,
    lastUpdated: null
  })

  // Computed properties
  const isLoading = computed(() => state.value.loading)
  const hasError = computed(() => !!state.value.error)
  const errorMessage = computed(() => state.value.error)
  
  const hasPeriodClose = computed(() => !!state.value.periodClose)
  const periodCloseStatus = computed(() => state.value.periodClose?.status)
  const periodCloseId = computed(() => state.value.periodClose?.id)
  
  const tasks = computed(() => state.value.tasks || [])
  const completedTasks = computed(() => tasks.value.filter(task => task.status === 'completed'))
  const inProgressTasks = computed(() => tasks.value.filter(task => task.status === 'in_progress'))
  const blockedTasks = computed(() => tasks.value.filter(task => task.status === 'blocked'))
  const requiredTasks = computed(() => tasks.value.filter(task => task.is_required))
  const completedRequiredTasks = computed(() => requiredTasks.value.filter(task => task.status === 'completed'))
  
  const completionPercentage = computed(() => {
    if (tasks.value.length === 0) return 0
    return Math.round((completedTasks.value.length / tasks.value.length) * 100)
  })
  
  const requiredCompletionPercentage = computed(() => {
    if (requiredTasks.value.length === 0) return 0
    return Math.round((completedRequiredTasks.value.length / requiredTasks.value.length) * 100)
  })

  const hasValidation = computed(() => !!state.value.validation)
  const validationScore = computed(() => state.value.validation?.score || 0)
  const validationPassed = computed(() => state.value.validation?.status === 'passed')
  const hasValidationIssues = computed(() => {
    return state.value.validation?.issues?.some(issue => issue.type === 'error' || issue.priority === 'high') || false
  })

  const canCompletePeriodClose = computed(() => {
    return hasPeriodClose.value && 
           requiredCompletionPercentage.value === 100 && 
           !hasValidationIssues.value &&
           ['in_review', 'awaiting_approval'].includes(periodCloseStatus.value || '')
  })

  // Actions
  const actions: PeriodCloseActions = {
    /**
     * Load period close data for a specific period
     */
    async loadPeriodClose(periodId: string) {
      state.value.loading = true
      state.value.error = null
      
      try {
        const snapshot = await PeriodCloseApiService.getPeriodCloseSnapshot(periodId)
        
        state.value.currentPeriod = snapshot.period
        state.value.periodClose = snapshot.period_close
        state.value.tasks = snapshot.period_close?.tasks || []
        state.value.lastUpdated = new Date()
        
        // Clear previous validation when loading new period
        state.value.validation = null
        
      } catch (error: any) {
        const errorInfo = PeriodCloseApiService.formatApiError(error)
        state.value.error = errorInfo.message
        console.error('Failed to load period close:', error)
      } finally {
        state.value.loading = false
      }
    },

    /**
     * Start a period close workflow
     */
    async startPeriodClose(periodId: string, notes?: string) {
      state.value.loading = true
      state.value.error = null
      
      try {
        const startData: StartPeriodCloseRequest = { notes }
        await PeriodCloseApiService.startPeriodClose(periodId, startData)
        
        // Reload the period close data after starting
        await actions.loadPeriodClose(periodId)
        
        // Show success message
        this.$toast?.add({
          severity: 'success',
          summary: 'Success',
          detail: 'Period close started successfully',
          life: 3000
        })
        
      } catch (error: any) {
        const errorInfo = PeriodCloseApiService.formatApiError(error)
        state.value.error = errorInfo.message
        
        // Show error message
        this.$toast?.add({
          severity: 'error',
          summary: 'Error',
          detail: errorInfo.message,
          life: 5000
        })
        
        throw error
      } finally {
        state.value.loading = false
      }
    },

    /**
     * Run period close validations
     */
    async validatePeriodClose(periodId: string) {
      state.value.loading = true
      state.value.error = null
      
      try {
        const validation = await PeriodCloseApiService.validatePeriodClose(periodId)
        state.value.validation = validation
        
        // Show validation result message
        const severity = validation.status === 'passed' ? 'success' : 
                        validation.status === 'failed' ? 'error' : 'warn'
        
        this.$toast?.add({
          severity,
          summary: 'Validation Complete',
          detail: `Validation ${validation.status} with score ${validation.score}/100`,
          life: 4000
        })
        
      } catch (error: any) {
        const errorInfo = PeriodCloseApiService.formatApiError(error)
        state.value.error = errorInfo.message
        
        this.$toast?.add({
          severity: 'error',
          summary: 'Validation Failed',
          detail: errorInfo.message,
          life: 5000
        })
        
        throw error
      } finally {
        state.value.loading = false
      }
    },

    /**
     * Update a task status
     */
    async updateTask(periodId: string, taskId: string, data: UpdateTaskRequest) {
      state.value.loading = true
      state.value.error = null
      
      try {
        await PeriodCloseApiService.updateTask(periodId, taskId, data)
        
        // Reload the period close data to get updated task status
        await actions.loadPeriodClose(periodId)
        
        // Show success message
        this.$toast?.add({
          severity: 'success',
          summary: 'Task Updated',
          detail: `Task status updated to ${data.status}`,
          life: 3000
        })
        
      } catch (error: any) {
        const errorInfo = PeriodCloseApiService.formatApiError(error)
        state.value.error = errorInfo.message
        
        this.$toast?.add({
          severity: 'error',
          summary: 'Task Update Failed',
          detail: errorInfo.message,
          life: 5000
        })
        
        throw error
      } finally {
        state.value.loading = false
      }
    },

    /**
     * Complete a task (shortcut method)
     */
    async completeTask(periodId: string, taskId: string, notes?: string) {
      return actions.updateTask(periodId, taskId, {
        status: 'completed',
        notes
      })
    },

    /**
     * Refresh period close data
     */
    async refreshPeriodClose(periodId: string) {
      try {
        await actions.loadPeriodClose(periodId)
        
        this.$toast?.add({
          severity: 'info',
          summary: 'Refreshed',
          detail: 'Period close data refreshed',
          life: 2000
        })
        
      } catch (error: any) {
        console.error('Failed to refresh period close:', error)
      }
    },

    /**
     * Clear the current error
     */
    clearError() {
      state.value.error = null
    },

    /**
     * Reset all state
     */
    reset() {
      state.value = {
        currentPeriod: null,
        periodClose: null,
        tasks: [],
        validation: null,
        loading: false,
        error: null,
        lastUpdated: null
      }
    }
  }

  // Utility methods
  const getTaskById = (taskId: string) => {
    return tasks.value.find(task => task.id === taskId)
  }

  const getTasksByCategory = (category: string) => {
    return tasks.value.filter(task => task.category === category)
  }

  const getTasksByStatus = (status: string) => {
    return tasks.value.filter(task => task.status === status)
  }

  // Return reactive state and actions
  return {
    // State
    state: state.value,
    
    // Computed
    isLoading,
    hasError,
    errorMessage,
    hasPeriodClose,
    periodCloseStatus,
    periodCloseId,
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
    
    // Actions
    ...actions,
    
    // Utilities
    getTaskById,
    getTasksByCategory,
    getTasksByStatus
  }
}

export default usePeriodClose