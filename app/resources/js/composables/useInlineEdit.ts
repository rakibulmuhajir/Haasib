// src/composables/useInlineEdit.ts
import { ref, computed } from 'vue'
import UniversalFieldSaver from '@/services/UniversalFieldSaver'
import { fieldToAddressPath } from '@/utils/fieldMap'
import type { ToastServiceMethods } from 'primevue/toastservice'

interface UseInlineEditOptions {
  model: string
  id: string | number
  data: Record<string, any>
  toast?: ToastServiceMethods
  onSuccess?: (updatedData: any) => void
  onError?: (error: any) => void
}

export function useInlineEdit(options: UseInlineEditOptions) {
  const { model, id, data, toast, onSuccess, onError } = options
  
  const editingField = ref<string | null>(null)
  const localData = ref(data)
  
  // Create computed properties for each field's editing state
  const createEditingComputed = (field: string) => computed({
    get: () => editingField.value === field,
    set: (value: boolean) => editingField.value = value ? field : null
  })
  
  // Check if a field is currently being saved
  const isSaving = (field: string) => {
    return UniversalFieldSaver.isSaving({ model, id, fieldPath: fieldToAddressPath(field) })
  }
  
  // Save a field value
  const saveField = async (field: string, value: any) => {
    const originalValue = localData.value[field]
    const fieldPath = fieldToAddressPath(field)
    
    // Optimistic update
    UniversalFieldSaver.updateOptimistically(localData.value, field, value, originalValue)
    
    // Save to server
    const result = await UniversalFieldSaver.save({
      model,
      id,
      fieldPath,
      verify: true,
      maxRetries: 2,
      toast,
      onSuccess: (responseData) => {
        // Update local data with server response
        if (responseData.resource) {
          Object.assign(localData.value, responseData.resource)
        }
        onSuccess?.(responseData.resource || localData.value)
      },
      onError: (error) => {
        // Rollback optimistic update
        UniversalFieldSaver.rollbackOptimisticUpdate(localData.value, field)
        onError?.(error)
      }
    }, value, originalValue)
    
    // Close editing on success
    if (result.ok) {
      editingField.value = null
    }
    
    return result
  }
  
  // Cancel editing
  const cancelEditing = () => {
    editingField.value = null
  }
  
  return {
    localData,
    editingField,
    createEditingComputed,
    isSaving,
    saveField,
    cancelEditing
  }
}