import { usePage } from '@inertiajs/vue3'
import { computed, type ComputedRef } from 'vue'

/**
 * The active company's slug, and URL-building against it.
 *
 * 30 pages each re-derived this slug locally, all with the same rule: trust
 * the loaded company over the URL, because the URL segment can be stale for
 * a moment during a company switch. Centralised here so that rule only has
 * one place to drift.
 */

/** First path segment of a URL, or null if the URL is rootless. Exported so
 * useCompanySwitcher can match it against the company list without
 * duplicating the regex. */
export function firstUrlSegment(url: string): string | null {
    const match = url.match(/^\/([^/]+)/)
    return match ? match[1] : null
}

export function useCompanyRoute() {
    const page = usePage()

    const companySlug: ComputedRef<string> = computed(() => {
        const slug = (page.props as any)?.auth?.currentCompany?.slug as string | undefined
        if (slug) return slug
        return firstUrlSegment(page.url) ?? ''
    })

    const companyUrl = (path: string): string => {
        const slug = companySlug.value
        const trimmed = path.replace(/^\/+/, '')
        return trimmed ? `/${slug}/${trimmed}` : `/${slug}`
    }

    return { companySlug, companyUrl }
}
