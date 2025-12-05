import { toast } from 'sonner'

export function useFormFeedback() {
  const showSuccess = (message: string) => {
    toast.success(message)
  }

  const showError = (message: string | Record<string, string[]>) => {
    if (typeof message === 'string') {
      toast.error(message)
    } else {
      // Form validation errors - show first error as toast
      const firstError = Object.values(message).flat()[0]
      toast.error(firstError)
    }
  }

  return { showSuccess, showError }
}