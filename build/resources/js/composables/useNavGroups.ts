import { useLexicon } from '@/composables/useLexicon'
import { getSidebarGroups } from '@/navigation/registry'
import type { NavGroup } from '@/types'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

/**
 * Which company we are looking at, what it has switched on, and the nav that
 * falls out of those answers.
 *
 * All of this used to live inside AppSidebar's setup block, which was fine
 * while the sidebar was the only thing that rendered navigation. The header
 * needs the same groups, and a second copy of the module-detection rules is a
 * second place for them to drift — the fuel-station check alone reads three
 * differently-named fields for one fact. One composable, both shells.
 */
export function useNavGroups() {
    const page = usePage()
    const { t } = useLexicon()

    const authProps = computed(() => (page.props.auth as any) || {})
    const userCompanies = computed(() => authProps.value.companies || [])

    /**
     * The URL is the authority on which company is on screen. `currentCompany`
     * from the server lags a switch by one request, and the first path segment
     * does not — but only when it actually names a company the user belongs to,
     * hence the lookup rather than a bare slug.
     */
    const companyFromUrl = computed(() => {
        const match = page.url.match(/^\/([^/]+)/)
        const possibleSlug = match ? match[1] : null

        return possibleSlug
            ? userCompanies.value.find((company: any) => company.slug === possibleSlug) || null
            : null
    })

    const currentCompany = computed(
        () => companyFromUrl.value || authProps.value.currentCompany || null,
    )

    const modules = computed(() => currentCompany.value?.settings?.modules ?? {})

    // Industry arrives under three spellings depending on how old the row is.
    const industry = computed(
        () =>
            currentCompany.value?.industry_code ??
            currentCompany.value?.industryCode ??
            currentCompany.value?.industry ??
            null,
    )

    const isFuelStationCompany = computed(
        () => modules.value?.fuel_station === true || industry.value === 'fuel_station',
    )

    const isUmrahCompany = computed(
        () => modules.value?.umrah === true || ['umrah', 'travel'].includes(industry.value),
    )

    // Absent means on: inventory and payroll are only off when a company has
    // explicitly turned them off.
    const isInventoryEnabled = computed(() => modules.value?.inventory !== false)
    const isPayrollEnabled = computed(() => modules.value?.payroll !== false)

    const navGroups = computed<NavGroup[]>(() =>
        getSidebarGroups({
            slug: currentCompany.value?.slug ?? null,
            isFuelStationCompany: isFuelStationCompany.value,
            isUmrahCompany: isUmrahCompany.value,
            isInventoryEnabled: isInventoryEnabled.value,
            isPayrollEnabled: isPayrollEnabled.value,
            currentCompanyRole: authProps.value.currentCompanyRole || null,
            t,
        }),
    )

    return {
        currentCompany,
        userCompanies,
        isFuelStationCompany,
        isUmrahCompany,
        isInventoryEnabled,
        isPayrollEnabled,
        navGroups,
    }
}
