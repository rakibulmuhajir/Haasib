import { addToast, dismissToast, type ToastOptions } from './toast'

export function useToast() {
  const toast = (options: ToastOptions) => addToast(options)

  return {
    toast,
    dismiss: dismissToast,
  }
}
