import { toast } from 'vue-sonner'

let lastErrorTime = 0
let lastErrorMessage = ''

export function useFormFeedback() {
  const showSuccess = (message: string) => {
    toast.success(message)
  }

  const showError = (message: string | Record<string, string[]> | Record<string, string>) => {
    // Determine the error message
    let errorMessage = ''

    if (typeof message === 'string') {
      errorMessage = message
    } else {
      // Handle both string[] and string values
      const errors = Object.values(message)
      errorMessage = Array.isArray(errors[0]) ? errors[0][0] : errors[0]
    }

    // Prevent duplicate toasts within 100ms
    const now = Date.now()
    if (errorMessage === lastErrorMessage && now - lastErrorTime < 100) {
      return
    }

    lastErrorTime = now
    lastErrorMessage = errorMessage

    toast.error(errorMessage)
  }

  return { showSuccess, showError }
}
