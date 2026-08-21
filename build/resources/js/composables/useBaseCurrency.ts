/**
 * The company's base (functional) currency, read from the Inertia page props
 * that `HandleInertiaRequests` already shares on every request
 * (`auth.currentCompany.base_currency`).
 *
 * Exists so `MoneyText` can decide for itself whether an amount is "the same
 * currency as everything else" without every one of its 100+ call sites
 * having to pass a `baseCurrency` prop.
 *
 * Safe to call from a component that is not inside an Inertia page (a unit
 * test, a Storybook-style harness, a component mounted standalone) — `usePage`
 * throws outside that context, and here that throw becomes `null` rather than
 * an unhandled error. Callers must treat `null` as "unknown", never as "same
 * as base": an unlabelled foreign amount is a wrong number, so the caller
 * should fall back to showing the currency, not hiding it.
 */
import { computed, type ComputedRef } from 'vue'
import { usePage } from '@inertiajs/vue3'

/**
 * `currentCompany` isn't in the shared `Auth` type (it's assembled ad hoc in
 * `HandleInertiaRequests`), so this reads it the same loosely-typed way
 * `AmountInput.vue` already does rather than widening a type other code relies on.
 */
export function useBaseCurrency(): ComputedRef<string | null> {
    let page: ReturnType<typeof usePage> | null = null
    try {
        page = usePage()
    } catch {
        page = null
    }

    return computed(() => {
        if (!page) return null
        const auth = page.props.auth as { currentCompany?: { base_currency?: string | null } } | undefined
        return auth?.currentCompany?.base_currency ?? null
    })
}
