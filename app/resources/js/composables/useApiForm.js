import { ref, reactive } from 'vue'
import { http } from '@/lib/http'

/**
 * A generic composable for handling API form submissions.
 * @param {Function} apiCall - A function that returns an Axios promise.
 * @param {Object} options - Configuration options.
 * @param {Object} options.initialFormState - The initial state of the form data.
 * @param {Function} options.onSuccess - Callback on successful API call.
 * @param {Function} options.onError - Callback on API call failure.
 * @param {Function} options.onFinally - Callback that runs after success or error.
 * @returns {Object} - Reactive state and the execute function.
 */
export function useApiForm(apiCall, { initialFormState = {}, onSuccess, onError, onFinally } = {}) {
  const loading = ref(false)
  const error = ref('')
  const data = ref(null)
  const form = reactive({ ...initialFormState })
  const originalFormState = JSON.parse(JSON.stringify(initialFormState))

  const execute = async (...args) => {
    loading.value = true
    error.value = ''
    data.value = null

    try {
      // Pass form data as the first argument if no other args are provided
      const callArgs = args.length > 0 ? args : [form]
      const response = await apiCall(...callArgs)
      data.value = response.data.data || response.data
      if (onSuccess) {
        onSuccess(data.value, form)
      }
    } catch (e) {
      const message = e?.response?.data?.message || e.message || 'An unknown error occurred.'
      error.value = message
      if (onError) {
        onError(e)
      }
    } finally {
      loading.value = false
      if (onFinally) {
        onFinally()
      }
    }
  }

  const reset = () => {
    Object.assign(form, originalFormState)
    error.value = ''
  }

  return { loading, error, data, form, execute, reset }
}
