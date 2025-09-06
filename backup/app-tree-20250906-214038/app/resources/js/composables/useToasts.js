// PrimeVue-backed toasts composable
// Maintains the same external API shape used across the app while routing
// notifications through PrimeVue's ToastService.
import { useToast as usePrimeToast } from 'primevue/usetoast'

/**
 * @returns {{ addToast: (message: string, type?: 'info'|'success'|'warning'|'danger', duration?: number, summary?: string) => void,
 *             success: (message: string, duration?: number, summary?: string) => void,
 *             info: (message: string, duration?: number, summary?: string) => void,
 *             warning: (message: string, duration?: number, summary?: string) => void,
 *             danger: (message: string, duration?: number, summary?: string) => void,
 *             removeToast: (id: number) => void }}
 */
export function useToasts() {
  const toast = usePrimeToast()

  const mapSeverity = (type) => {
    switch (type) {
      case 'success': return 'success'
      case 'warning': return 'warn'
      case 'danger': return 'error'
      case 'info':
      default: return 'info'
    }
  }

  const addToast = (message, type = 'info', duration = 5000, summary) => {
    const severity = mapSeverity(type)
    toast.add({ severity, summary: summary ?? (type?.toString?.() ?? 'Info'), detail: message, life: duration })
  }

  // Backward-compat convenience helpers
  const success = (message, duration, summary) => addToast(message, 'success', duration, summary)
  const info = (message, duration, summary) => addToast(message, 'info', duration, summary)
  const warning = (message, duration, summary) => addToast(message, 'warning', duration, summary)
  const danger = (message, duration, summary) => addToast(message, 'danger', duration, summary)

  // No-op kept for API compatibility with old Reka-based implementation
  const removeToast = () => {}

  return { addToast, success, info, warning, danger, removeToast }
}
