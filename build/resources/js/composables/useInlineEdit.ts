import { ref, computed, type Ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'

export interface InlineEditOptions {
  /** The URL endpoint to PATCH updates to */
  endpoint: string
  /** Callback when save succeeds */
  onSuccess?: (field: string, value: unknown) => void
  /** Callback when save fails */
  onError?: (field: string, errors: Record<string, string[]>) => void
  /** Custom success message (default: "Updated successfully") */
  successMessage?: string
  /** Custom error message (default: "Failed to update") */
  errorMessage?: string
}

export interface InlineEditField<T = unknown> {
  /** Current value */
  value: Ref<T>
  /** Original value (before editing) */
  originalValue: Ref<T>
  /** Whether this field is currently being edited */
  isEditing: Ref<boolean>
  /** Whether this field is currently saving */
  isSaving: Ref<boolean>
  /** Start editing this field */
  startEditing: () => void
  /** Cancel editing and revert to original value */
  cancelEditing: () => void
  /** Save the current value */
  save: () => Promise<void>
}

export function useInlineEdit(options: InlineEditOptions) {
  const { endpoint, onSuccess, onError, successMessage = 'Updated successfully', errorMessage = 'Failed to update' } = options

  // Track which field is currently being edited (only one at a time)
  const editingField = ref<string | null>(null)
  const savingField = ref<string | null>(null)

  // Store for field values
  const fieldValues = ref<Record<string, unknown>>({})
  const originalValues = ref<Record<string, unknown>>({})

  /**
   * Register a field for inline editing
   */
  function registerField<T>(fieldName: string, initialValue: T): InlineEditField<T> {
    // Initialize values
    fieldValues.value[fieldName] = initialValue
    originalValues.value[fieldName] = initialValue

    const value = computed({
      get: () => fieldValues.value[fieldName] as T,
      set: (val: T) => {
        fieldValues.value[fieldName] = val
      },
    })

    const originalValue = computed(() => originalValues.value[fieldName] as T)

    const isEditing = computed(() => editingField.value === fieldName)
    const isSaving = computed(() => savingField.value === fieldName)

    const startEditing = () => {
      // Store original value before editing
      originalValues.value[fieldName] = fieldValues.value[fieldName]
      editingField.value = fieldName
    }

    const cancelEditing = () => {
      // Revert to original value
      fieldValues.value[fieldName] = originalValues.value[fieldName]
      editingField.value = null
    }

    const save = async () => {
      savingField.value = fieldName
      const currentValue = fieldValues.value[fieldName]

      // @ts-expect-error - dynamic field name with useForm requires type assertion
      const form = useForm({ [fieldName]: currentValue })

      return new Promise<void>((resolve, reject) => {
        form.patch(endpoint, {
          preserveScroll: true,
          onSuccess: () => {
            // Update original value to the new saved value
            originalValues.value[fieldName] = currentValue
            editingField.value = null
            savingField.value = null
            toast.success(successMessage)
            onSuccess?.(fieldName, currentValue)
            resolve()
          },
          onError: (errors) => {
            savingField.value = null
            toast.error(errorMessage)
            const normalizedErrors: Record<string, string[]> = {}
            Object.entries(errors).forEach(([key, value]) => {
              if (value == null) {
                return
              }
              normalizedErrors[key] = Array.isArray(value) ? value : [String(value)]
            })
            onError?.(fieldName, normalizedErrors)
            reject(normalizedErrors)
          },
        })
      })
    }

    return {
      value: value as unknown as Ref<T>,
      originalValue: originalValue as unknown as Ref<T>,
      isEditing,
      isSaving,
      startEditing,
      cancelEditing,
      save,
    }
  }

  /**
   * Check if any field is currently being edited
   */
  const isAnyEditing = computed(() => editingField.value !== null)

  /**
   * Check if any field is currently saving
   */
  const isAnySaving = computed(() => savingField.value !== null)

  /**
   * Cancel all editing
   */
  const cancelAll = () => {
    if (editingField.value) {
      fieldValues.value[editingField.value] = originalValues.value[editingField.value]
    }
    editingField.value = null
  }

  return {
    registerField,
    editingField,
    savingField,
    isAnyEditing,
    isAnySaving,
    cancelAll,
  }
}
