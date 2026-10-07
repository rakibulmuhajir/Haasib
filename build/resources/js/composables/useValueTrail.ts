import { onUnmounted, provide, ref, shallowRef, watch, type InjectionKey } from 'vue'
import { router } from '@inertiajs/vue3'
import { useValueTrails } from '@/composables/useValueTrails'
import { useLexicon } from '@/composables/useLexicon'
import { useFormFeedback } from '@/composables/useFormFeedback'
import type { ValueTrail } from '@/types/valueTrail'

export const valueTrailKey: InjectionKey<{ explore: (root: string) => void }> = Symbol('valueTrail')
export const valueTrailBatchKey: InjectionKey<{ load: (node: string, offset?: number) => void }> = Symbol('valueTrailBatch')

interface Options {
    /** Props refreshed with the evidence to keep the displayed values consistent. */
    refresh: string[]
    context: () => unknown
    snapshot: () => unknown
    snapshotFromPage: (props: Record<string, unknown>) => unknown
    prop?: string
}

/** Modules supply filters and report props; core owns the request lifecycle. */
export function useValueTrail(options: Options) {
    const { enabled } = useValueTrails()
    const { t } = useLexicon()
    const { showError } = useFormFeedback()
    const open = ref(false)
    const loading = ref(false)
    const error = ref('')
    const root = ref('')
    const trail = shallowRef<ValueTrail | null>(null)
    const prop = options.prop ?? 'valueTrail'
    let cancel: (() => void) | undefined
    let request = 0
    let fingerprint = ''
    let retryNode = ''
    let retryOffset = 0

    function reset() {
        request++
        cancel?.()
        cancel = undefined
        trail.value = null
        fingerprint = ''
        loading.value = false
        error.value = ''
        open.value = false
        retryNode = ''
        retryOffset = 0
    }

    function fail(message = t('valueTrailError')) {
        error.value = message
        showError(message)
    }

    function load(node = retryNode || root.value, offset = retryOffset) {
        if (!enabled.value || loading.value) return
        retryNode = node
        retryOffset = offset
        const id = ++request
        loading.value = true
        error.value = ''
        router.reload({
            headers: {
                'X-Value-Trail-Node': node,
                'X-Value-Trail-Offset': String(offset),
                ...(trail.value?.batch ? { 'X-Value-Trail-Version': trail.value.batch.version } : {}),
            },
            only: [...new Set([prop, ...options.refresh, 'auth'])],
            onCancelToken: (token) => { cancel = () => token.cancel() },
            onSuccess: (page) => {
                if (id !== request) return
                if (!enabled.value) { reset(); return }
                const result = page.props[prop] as ValueTrail | null | undefined
                if (!result || result.error) {
                    trail.value = null
                    fail(result?.error)
                    return
                }
                fingerprint = JSON.stringify(options.snapshotFromPage(page.props))
                if (result.batch && trail.value?.batch) {
                    const nodes = { ...trail.value.nodes }
                    for (const [key, incoming] of Object.entries(result.nodes)) {
                        if (incoming.summary && nodes[key] && !nodes[key].summary) continue
                        nodes[key] = incoming
                    }
                    if (result.batch.offset > 0) {
                        const parent = result.batch.parent
                        nodes[parent] = { ...nodes[parent], children: [...new Set([
                            ...(trail.value.nodes[parent]?.children ?? []), ...nodes[parent].children,
                        ])] }
                    }
                    trail.value = { ...result, nodes, roots: { ...trail.value.roots, ...result.roots } }
                } else {
                    trail.value = result
                }
            },
            onError: () => { if (id === request) fail() },
            onFinish: () => {
                if (id !== request) return
                loading.value = false
                cancel = undefined
            },
        })
    }

    function explore(key: string) {
        if (!enabled.value) return
        root.value = key
        open.value = true
        const node = trail.value?.nodes[trail.value.roots[key] ?? key]
        if (!trail.value || (trail.value.batch && (!node || node.summary))) load(key, 0)
    }

    const stopException = router.on('exception', (event) => {
        if (!loading.value) return
        event.preventDefault()
        fail()
        loading.value = false
    })
    const stopInvalid = router.on('invalid', (event) => {
        if (!loading.value) return
        event.preventDefault()
        fail()
        loading.value = false
    })
    watch(() => JSON.stringify(options.context()), reset)
    watch(() => JSON.stringify(options.snapshot()), (value) => {
        if (trail.value && !loading.value && value !== fingerprint) reset()
    })
    watch(enabled, (value) => { if (!value) reset() })
    onUnmounted(() => { reset(); stopException(); stopInvalid() })
    provide(valueTrailKey, { explore })
    provide(valueTrailBatchKey, { load })
    return { open, loading, error, root, trail, explore, load, reset }
}
