import { router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { firstUrlSegment } from '@/composables/useCompanyRoute'

/**
 * The company list, which one is active, and how to change it.
 *
 * Split out of CompanySwitcher.vue so the header can mount its own trigger —
 * the sidebar's is a SidebarMenuButton and throws no error outside a sidebar,
 * but it inherits sidebar sizing and collapse behaviour that make no sense in
 * a horizontal bar. The behaviour is shared; only the trigger differs.
 */
export function useCompanySwitcher() {
    const page = usePage()

    const companies = computed(() => (page.props.auth as any)?.companies || [])

    const currentCompany = computed(() => {
        const segment = firstUrlSegment(page.url)
        const companyFromUrl = segment
            ? companies.value.find((company: any) => company.slug === segment)
            : null

        return companyFromUrl || (page.props.auth as any)?.currentCompany || null
    })

    const canCreateCompanies = computed(() =>
        Boolean((page.props.auth as any)?.canCreateCompanies),
    )

    const switchCompany = (slug: string) => {
        router.post('/companies/switch', { slug }, { preserveScroll: true })
    }

    const createCompany = () => router.visit('/companies')

    return { companies, currentCompany, canCreateCompanies, switchCompany, createCompany }
}
