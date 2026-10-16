import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

/** Does the current company charge tax? One switch (Settings > Tax) behind every tax field. */
export function useTaxEnabled() {
    const page = usePage()
    return computed<boolean>(() => Boolean((page.props.auth as any)?.taxEnabled))
}
