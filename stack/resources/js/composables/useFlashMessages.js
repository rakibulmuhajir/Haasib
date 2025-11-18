import { watch, nextTick } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'

export function useFlashMessages() {
    const page = usePage()
    const toast = useToast()

    const showFlashMessage = (severity, message) => {
        if (!message) return

        const severityConfig = {
            success: { severity: 'success', summary: 'Success', life: 3000 },
            error: { severity: 'error', summary: 'Error', life: 5000 },
            warning: { severity: 'warn', summary: 'Warning', life: 4000 },
            info: { severity: 'info', summary: 'Info', life: 3000 }
        }

        const config = severityConfig[severity] || severityConfig.info

        toast.add({
            severity: config.severity,
            summary: config.summary,
            detail: message,
            life: config.life
        })
    }

    // Watch for flash messages and show them as toasts
    watch(
        () => page.props.flash,
        (flash) => {
            if (!flash) return

            // Show messages in order of priority: error, warning, success, info
            if (flash.error) {
                showFlashMessage('error', flash.error)
            } else if (flash.warning) {
                showFlashMessage('warning', flash.warning)
            } else if (flash.success) {
                showFlashMessage('success', flash.success)
            } else if (flash.info) {
                showFlashMessage('info', flash.info)
            }
        },
        { immediate: true, deep: true }
    )

    return {
        showFlashMessage
    }
}