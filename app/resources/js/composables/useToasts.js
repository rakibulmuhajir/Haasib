import { ref } from 'vue'

const toasts = ref([])

export function useToasts() {
  const removeToast = (id) => {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  const addToast = (message, type = 'info', duration = 5000) => {
    const id = Date.now() + Math.random()
    toasts.value.push({ id, message, type })
    if (duration) {
      setTimeout(() => removeToast(id), duration)
    }
  }

  return { toasts, addToast, removeToast }
}
