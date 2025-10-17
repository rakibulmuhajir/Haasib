import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import PeriodCloseApiService from '@/services/periodCloseApi'

// Types for forms
export interface StartPeriodCloseForm {
  notes: string
  errors: Record<string, string>
  processing: boolean
  wasSuccessful: boolean
  recentlySuccessful: boolean
}

export interface TaskUpdateForm {
  status: 'pending' | 'in_progress' | 'completed' | 'blocked' | 'waived'
  notes: string
  attachments: string[]
  errors: Record<string, string>
  processing: boolean
  wasSuccessful: boolean
  recentlySuccessful: boolean
}

export interface PeriodCloseActionForm {
  reason?: string
  notes?: string
  errors: Record<string, string>
  processing: boolean
  wasSuccessful: boolean
  recentlySuccessful: boolean
}

// Composable for period close forms
export function usePeriodCloseForms() {
  
  /**
   * Form for starting period close
   */
  function useStartPeriodCloseForm() {
    const form = ref<StartPeriodCloseForm>({
      notes: '',
      errors: {},
      processing: false,
      wasSuccessful: false,
      recentlySuccessful: false
    })

    const startPeriodClose = async (periodId: string) => {
      form.value.processing = true
      form.value.errors = {}
      form.value.wasSuccessful = false
      form.value.recentlySuccessful = false

      try {
        await PeriodCloseApiService.startPeriodClose(periodId, {
          notes: form.value.notes.trim() || undefined
        })

        form.value.wasSuccessful = true
        form.value.recentlySuccessful = true
        form.value.notes = '' // Clear form

        // Reset recently successful after 3 seconds
        setTimeout(() => {
          form.value.recentlySuccessful = false
        }, 3000)

        return true

      } catch (error: any) {
        const errorMessage = PeriodCloseApiService.handleApiError(error, 'Failed to start period close')
        
        // Handle validation errors
        if (error.response?.status === 422 && error.response?.data?.errors) {
          form.value.errors = error.response.data.errors
        } else {
          form.value.errors = { 
            general: errorMessage 
          }
        }

        return false

      } finally {
        form.value.processing = false
      }
    }

    const reset = () => {
      form.value.notes = ''
      form.value.errors = {}
      form.value.processing = false
      form.value.wasSuccessful = false
      form.value.recentlySuccessful = false
    }

    const setErrors = (errors: Record<string, string>) => {
      form.value.errors = errors
    }

    const clearErrors = (field?: string) => {
      if (field) {
        delete form.value.errors[field]
      } else {
        form.value.errors = {}
      }
    }

    return {
      form,
      startPeriodClose,
      reset,
      setErrors,
      clearErrors
    }
  }

  /**
   * Form for updating task status
   */
  function useTaskUpdateForm() {
    const form = ref<TaskUpdateForm>({
      status: 'pending',
      notes: '',
      attachments: [],
      errors: {},
      processing: false,
      wasSuccessful: false,
      recentlySuccessful: false
    })

    const updateTask = async (periodId: string, taskId: string) => {
      form.value.processing = true
      form.value.errors = {}
      form.value.wasSuccessful = false
      form.value.recentlySuccessful = false

      try {
        await PeriodCloseApiService.updateTask(periodId, taskId, {
          status: form.value.status,
          notes: form.value.notes.trim() || undefined,
          attachments: form.value.attachments.length > 0 ? form.value.attachments : undefined
        })

        form.value.wasSuccessful = true
        form.value.recentlySuccessful = true

        // Reset form after successful update
        form.value.status = 'pending'
        form.value.notes = ''
        form.value.attachments = []

        // Reset recently successful after 3 seconds
        setTimeout(() => {
          form.value.recentlySuccessful = false
        }, 3000)

        return true

      } catch (error: any) {
        const errorMessage = PeriodCloseApiService.handleApiError(error, 'Failed to update task')
        
        // Handle validation errors
        if (error.response?.status === 422 && error.response?.data?.errors) {
          form.value.errors = error.response.data.errors
        } else {
          form.value.errors = { 
            general: errorMessage 
          }
        }

        return false

      } finally {
        form.value.processing = false
      }
    }

    const completeTask = async (periodId: string, taskId: string) => {
      form.value.status = 'completed'
      return await updateTask(periodId, taskId)
    }

    const reset = () => {
      form.value.status = 'pending'
      form.value.notes = ''
      form.value.attachments = []
      form.value.errors = {}
      form.value.processing = false
      form.value.wasSuccessful = false
      form.value.recentlySuccessful = false
    }

    const setErrors = (errors: Record<string, string>) => {
      form.value.errors = errors
    }

    const clearErrors = (field?: string) => {
      if (field) {
        delete form.value.errors[field]
      } else {
        form.value.errors = {}
      }
    }

    const addAttachment = (attachment: string) => {
      if (!form.value.attachments.includes(attachment)) {
        form.value.attachments.push(attachment)
      }
    }

    const removeAttachment = (attachment: string) => {
      const index = form.value.attachments.indexOf(attachment)
      if (index > -1) {
        form.value.attachments.splice(index, 1)
      }
    }

    return {
      form,
      updateTask,
      completeTask,
      reset,
      setErrors,
      clearErrors,
      addAttachment,
      removeAttachment
    }
  }

  /**
   * Form for period close actions (lock, complete, reopen, etc.)
   */
  function usePeriodCloseActionForm() {
    const form = ref<PeriodCloseActionForm>({
      reason: '',
      notes: '',
      errors: {},
      processing: false,
      wasSuccessful: false,
      recentlySuccessful: false
    })

    const executeAction = async (periodId: string, action: string, data?: any) => {
      form.value.processing = true
      form.value.errors = {}
      form.value.wasSuccessful = false
      form.value.recentlySuccessful = false

      try {
        let endpoint = `/api/v1/ledger/periods/${periodId}/close`
        let payload: any = {}

        switch (action) {
          case 'validate':
            endpoint += '/validate'
            break
            
          case 'lock':
            if (form.value.reason?.trim()) {
              return await PeriodCloseApiService.lockPeriodClose(
                periodId, 
                form.value.reason.trim()
              )
            } else {
              throw new Error('Lock reason is required')
            }
            break
            
          case 'complete':
            return await PeriodCloseApiService.completePeriodClose(
              periodId, 
              form.value.notes?.trim()
            )
            break
            
          case 'reopen':
            endpoint += '/reopen'
            if (form.value.reason?.trim()) {
              payload.reason = form.value.reason.trim()
            }
            break
            
          case 'adjust':
            // For adjustments, we need specific data structure
            if (!data || !data.adjustmentData) {
              throw new Error('Adjustment data is required for adjust action')
            }
            return await PeriodCloseApiService.createAdjustment(
              periodId, 
              {
                ...data.adjustmentData,
                notes: data.adjustmentData.notes || form.value.notes?.trim()
              }
            )
            
          default:
            throw new Error(`Unknown action: ${action}`)
        }

        // Make the API call using axios directly since these actions may not be fully implemented
        await PeriodCloseApiService['axiosInstance']?.post(endpoint, payload)

        form.value.wasSuccessful = true
        form.value.recentlySuccessful = true

        // Clear form after successful action
        form.value.reason = ''
        form.value.notes = ''

        // Reset recently successful after 3 seconds
        setTimeout(() => {
          form.value.recentlySuccessful = false
        }, 3000)

        return true

      } catch (error: any) {
        const errorMessage = PeriodCloseApiService.handleApiError(error, `Failed to ${action} period close`)
        
        // Handle validation errors
        if (error.response?.status === 422 && error.response?.data?.errors) {
          form.value.errors = error.response.data.errors
        } else {
          form.value.errors = { 
            general: errorMessage 
          }
        }

        return false

      } finally {
        form.value.processing = false
      }
    }

    const reset = () => {
      form.value.reason = ''
      form.value.notes = ''
      form.value.errors = {}
      form.value.processing = false
      form.value.wasSuccessful = false
      form.value.recentlySuccessful = false
    }

    const setErrors = (errors: Record<string, string>) => {
      form.value.errors = errors
    }

    const clearErrors = (field?: string) => {
      if (field) {
        delete form.value.errors[field]
      } else {
        form.value.errors = {}
      }
    }

    return {
      form,
      executeAction,
      reset,
      setErrors,
      clearErrors
    }
  }

  return {
    useStartPeriodCloseForm,
    useTaskUpdateForm,
    usePeriodCloseActionForm
  }
}

export default usePeriodCloseForms