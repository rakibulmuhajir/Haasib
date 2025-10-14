import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

interface UseDeleteConfirmationOptions {
  deleteRouteName: string
  onSuccess?: () => void
  onError?: (error: any) => void
}

export function useDeleteConfirmation<T extends { id: any }>(options: UseDeleteConfirmationOptions) {
  const isVisible = ref(false)
  const itemToDelete = ref<T | null>(null)
  const isLoading = ref(false)

  const show = (item: T) => {
    itemToDelete.value = item
    isVisible.value = true
  }

  const hide = () => {
    isVisible.value = false
    itemToDelete.value = null
  }

  const confirm = () => {
    if (!itemToDelete.value) return

    isLoading.value = true
    router.delete(route(options.deleteRouteName, itemToDelete.value.id), {
      onSuccess: () => {
        hide()
        options.onSuccess?.()
      },
      onError: options.onError,
      onFinish: () => {
        isLoading.value = false
      },
    })
  }

  return {
    isVisible,
    itemToDelete,
    isLoading,
    show,
    hide,
    confirm,
  }
}
