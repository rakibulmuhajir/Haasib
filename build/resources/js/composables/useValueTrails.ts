import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import type { AppPageProps } from '@/types'

export function useValueTrails() {
    const page = usePage<AppPageProps>()
    return { enabled: computed(() => page.props.auth?.preferences?.show_value_trails !== false) }
}
