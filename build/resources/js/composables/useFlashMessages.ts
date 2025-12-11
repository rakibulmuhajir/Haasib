import { usePage } from '@inertiajs/vue3'
import { watch } from 'vue'
import { toast } from 'vue-sonner'

export function useFlashMessages() {
  const page = usePage()

  const handleFlashMessages = () => {
    const flash = page.props?.flash as any

    if (flash?.success) {
      toast.success(flash.success)
    }

    if (flash?.error) {
      toast.error(flash.error)
    }

    if (flash?.warning) {
      toast.warning(flash.warning)
    }

    if (flash?.info) {
      toast.info(flash.info)
    }
  }

  // Watch for flash changes and show toast notifications
  watch(
    () => page.props?.flash,
    () => {
      handleFlashMessages()
    },
    { immediate: true }
  )

  return {
    handleFlashMessages
  }
}
