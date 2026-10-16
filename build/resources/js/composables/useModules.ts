import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

/** Which industry modules are on for the current company (server-resolved, industry fallback included). */
export function useModules() {
    const page = usePage()
    const enabled = computed<string[]>(
        () => ((page.props.auth as any)?.enabledModules as string[] | undefined) ?? [],
    )

    return { enabled, has: (key: string) => enabled.value.includes(key) }
}
