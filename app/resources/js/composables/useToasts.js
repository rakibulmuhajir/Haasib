import { ref } from 'vue'

const toasts = ref([])

/**
 * A composable to provide a consistent way to show toasts.
 * This manages a global state of toasts to be rendered by the Toasts.vue component.
 *
 * @returns {{addToast: function(message: string, type: 'info'|'success'|'warning'|'danger', duration?: int)}}
 */
export function useToasts() {
  /**
   * Removes a toast from the global list by its ID.
   * @param {number} id The unique ID of the toast to remove.
   */
  const removeToast = (id) => {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  /**
   * @param {string} message The content of the toast.
   * @param {'info'|'success'|'warning'|'danger'} [type='info'] The type of toast.
   * @param {number} [duration=5000] Duration in milliseconds.
   */
  const addToast = (message, type = 'info', duration = 5000) => {
    const id = Date.now() + Math.random()
    toasts.value.push({ id, message, type, duration, open: true })
    if (duration) {
      setTimeout(() => removeToast(id), duration)
    }
  }

  return { toasts, addToast, removeToast }
}
