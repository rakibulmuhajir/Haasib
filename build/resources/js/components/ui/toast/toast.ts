import { reactive } from 'vue'

export type ToastVariant = 'default' | 'destructive'

export interface ToastOptions {
  id?: string
  title?: string
  description?: string
  variant?: ToastVariant
  duration?: number
}

interface ToastRecord extends ToastOptions {
  id: string
  dismissAt?: number
}

const store = reactive<{ toasts: ToastRecord[] }>({
  toasts: [],
})

let toastId = 0

export const toastStore = store

export function addToast(options: ToastOptions): ToastRecord {
  const id = options.id ?? `toast-${++toastId}`
  const record: ToastRecord = {
    id,
    title: options.title,
    description: options.description,
    variant: options.variant ?? 'default',
    duration: options.duration ?? 4000,
  }

  store.toasts.push(record)

  if (record.duration && record.duration > 0) {
    record.dismissAt = window.setTimeout(() => dismissToast(id), record.duration)
  }

  return record
}

export function dismissToast(id: string): void {
  const index = store.toasts.findIndex((toast) => toast.id === id)
  if (index !== -1) {
    const toast = store.toasts[index]
    if (toast.dismissAt) {
      window.clearTimeout(toast.dismissAt)
    }
    store.toasts.splice(index, 1)
  }
}
