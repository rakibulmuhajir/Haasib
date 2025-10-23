<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { http } from '@/lib/http'
import { usePageActions } from '@/composables/usePageActions.js'
import CompanyHeader from '@/Components/Company/CompanyHeader.vue'
import QuickLinks from '@/Components/QuickLinks.vue'
import TeamMembers from '@/Components/Company/TeamMembers.vue'
import ActivityFeed from '@/Components/Company/ActivityFeed.vue'

type CompanyRole = {
    role: string
    is_active: boolean
    joined_at: string
} | null

type FiscalYear = {
    id: string
    name: string
    start_date: string
    end_date: string
    is_current?: boolean
}

type CompanyUser = {
    id: number
    name: string
    email: string
    role: string
    is_active: boolean
    joined_at: string
}

type CompanyInvitation = {
    id: number
    email: string
    role: string
    status: string
    expires_at?: string | null
    created_at: string
}

type AuditEntry = {
    id: number
    action: string
    description?: string | null
    created_at: string
    actor_name?: string | null
    metadata?: Record<string, unknown>
}

type CompanyPayload = {
    id: number
    name: string
    slug: string
    industry?: string | null
    currency?: string | null
    base_currency?: string | null
    timezone?: string | null
    country?: string | null
    language?: string | null
    locale?: string | null
    is_active: boolean
    created_at: string
    updated_at: string
    fiscal_year?: FiscalYear | null
    user_role?: CompanyRole
    permissions?: string[]
    users?: CompanyUser[]
    invitations?: CompanyInvitation[]
}



const props = defineProps<{ company: CompanyPayload }>()

const page = usePage()
const { setActions } = usePageActions()
const toast = useToast()
const confirm = useConfirm()

const normalizeCompany = (company: CompanyPayload): CompanyPayload => ({
    ...company,
    industry: company.industry ?? '',
    country: company.country ?? '',
    timezone: company.timezone ?? '',
    language: company.language ?? '',
    locale: company.locale ?? '',
    base_currency: company.base_currency ?? company.currency ?? '',
})

const companyData = ref<CompanyPayload>(normalizeCompany(JSON.parse(JSON.stringify(props.company))))
const companyUsers = ref<CompanyUser[]>(props.company.users ?? [])
const auditEntries = ref<AuditEntry[]>([])
const auditLoading = ref(false)
const auditError = ref<string | null>(null)
const hasMoreAuditEntries = ref(false)
const tabKeys = ['reports', 'people', 'activity'] as const
const selectedTab = ref(0)

watch(
    () => props.company,
    (next) => {
        const normalized = normalizeCompany(JSON.parse(JSON.stringify(next)))
        companyData.value = normalized
        companyUsers.value = next.users ?? []
        updatePageActions()
    },
    { deep: true }
)

const title = computed(() => (companyData.value?.name ? `Company · ${companyData.value.name}` : 'Company'))
const isCurrentCompany = computed(() => (page.props?.currentCompany as any)?.id === companyData.value?.id)
const userRole = computed(() => companyData.value?.user_role?.role ?? 'member')
const canManage = computed(() => {
    // Temporarily make manage always visible for testing
    // In production, uncomment the line below:
    // return ['owner', 'admin'].includes(userRole.value.toLowerCase())
    return true
})
const canInvite = computed(() => {
    // Temporarily make invite always visible for testing
    // In production, uncomment the line below:
    // return canManage.value || userRole.value.toLowerCase() === 'accountant'
    return true
})

const quickActions = computed(() => {
    if (!companyData.value?.is_active || isCurrentCompany.value) return []

    const actions = [
        {
            label: 'Create Invoice',
            icon: 'pi pi-file',
            action: () => router.visit('/invoices/create')
        },
        {
            label: 'New Customer',
            icon: 'pi pi-user-plus',
            action: () => router.visit('/customers/create')
        },
        {
            label: 'Add Vendor',
            icon: 'pi pi-building',
            action: () => router.visit('/vendors/create')
        },
        {
            label: 'Reporting Dashboard',
            icon: 'pi pi-chart-line',
            action: () => router.visit('/reporting/dashboard')
        }
    ]

    return actions
})


const quickLinks = computed(() => [
    {
        label: 'New Customer',
        icon: 'pi pi-user-plus',
        url: '/customers/create',
        action: () => handleQuickAction('create-customer')
    },
    {
        label: 'Add Vendor', 
        icon: 'pi pi-building',
        url: '/vendors/create',
        action: () => handleQuickAction('create-vendor')
    },
    {
        label: 'Create Invoice',
        icon: 'pi pi-file',
        url: '/invoices/create',
        action: () => handleQuickAction('create-invoice')
    },
    {
        label: 'Create Bill',
        icon: 'pi pi-file-edit',
        url: '/bills/create',
        action: () => handleQuickAction('create-bill')
    },
    {
        label: 'Record Payment',
        icon: 'pi pi-money-bill',
        url: '/payments/create',
        action: () => handleQuickAction('record-payment')
    },
    {
        label: 'New Expense',
        icon: 'pi pi-wallet',
        url: '/expenses/create',
        action: () => handleQuickAction('create-expense')
    },
    {
        label: 'Journal Entry',
        icon: 'pi pi-book',
        url: '/journal-entries/create',
        action: () => handleQuickAction('create-journal-entry')
    },
    {
        label: 'Reports',
        icon: 'pi pi-chart-line',
        url: '/reporting/dashboard',
        action: () => handleQuickAction('view-reports')
    }
])

const handleInvite = () => {
    const peopleIndex = tabKeys.indexOf('people')
    if (peopleIndex >= 0) {
        selectedTab.value = peopleIndex
    }
}

const handleQuickAction = (action: string) => {
    toast.add({
        severity: 'info',
        summary: 'Quick Action',
        detail: `Action "${action}" triggered`,
        life: 2000
    })
}

const handleManageMember = (member: CompanyUser) => {
    toast.add({
        severity: 'info',
        summary: 'Member management',
        detail: `Manage controls for ${member.name} coming soon.`,
        life: 3000
    })
}

const handleInviteMember = async (inviteData: { email: string; role: string; message: string }) => {
    if (!companyData.value?.id) return

    try {
        await http.post(`/api/v1/companies/${companyData.value.id}/invitations`, {
            email: inviteData.email,
            role: inviteData.role,
            message: inviteData.message
        })
        
        toast.add({
            severity: 'success',
            summary: 'Invitation sent',
            detail: `Invitation sent to ${inviteData.email}`,
            life: 3000
        })
        
        // Refresh company data to show updated invitations
        refreshCompany()
    } catch (err: any) {
        toast.add({
            severity: 'error',
            summary: 'Invitation failed',
            detail: err?.response?.data?.message || 'Unable to send invitation.',
            life: 4000
        })
        throw err
    }
}

const handleUpdateRole = async (member: CompanyUser, newRole: string) => {
    if (!companyData.value?.id) return

    try {
        await http.patch(`/api/v1/companies/${companyData.value.id}/members/${member.id}/role`, {
            role: newRole
        })
        
        toast.add({
            severity: 'success',
            summary: 'Role updated',
            detail: `${member.name}'s role updated to ${newRole}`,
            life: 3000
        })
        
        // Refresh company data to show updated roles
        refreshCompany()
    } catch (err: any) {
        toast.add({
            severity: 'error',
            summary: 'Role update failed',
            detail: err?.response?.data?.message || 'Unable to update member role.',
            life: 4000
        })
        throw err
    }
}

const loadMoreAuditEntries = async () => {
    if (!hasMoreAuditEntries.value) return
    await loadAuditEntries()
}

const loadAuditEntries = async () => {
    if (!companyData.value?.id) return

    auditLoading.value = true
    auditError.value = null

    try {
        const { data } = await http.get(`/api/v1/companies/${companyData.value.id}/audit-entries`)
        auditEntries.value = data.data ?? []
        hasMoreAuditEntries.value = Boolean(data.meta?.next_page_url)
    } catch (err: any) {
        if (err?.response?.status === 404) {
            auditError.value = 'Activity feed is not enabled for this company yet.'
        } else {
            auditError.value = err?.response?.data?.message || 'Unable to load activity.'
        }
        auditEntries.value = []
        hasMoreAuditEntries.value = false
    } finally {
        auditLoading.value = false
    }
}

const refreshCompany = () => {
    router.reload({
        only: ['company'],
    })
}

const switchToCompany = async () => {
    if (!companyData.value?.id || isCurrentCompany.value) return

    confirm.require({
        header: 'Switch Company Context',
        message: `Switch to ${companyData.value.name}?`,
        icon: 'pi pi-sign-in',
        acceptClass: 'p-button-success',
        accept: async () => {
            try {
                await http.post('/api/v1/company-context/switch', {
                    company_id: companyData.value?.id,
                })
                toast.add({
                    severity: 'success',
                    summary: 'Context switched',
                    detail: `Now working in ${companyData.value?.name}`,
                    life: 3000
                })
                setTimeout(() => window.location.reload(), 1200)
            } catch (err: any) {
                toast.add({
                    severity: 'error',
                    summary: 'Switch failed',
                    detail: err?.response?.data?.message || 'Unable to switch company.',
                    life: 4000
                })
            }
        },
    })
}

const activateCompany = async () => {
    if (!companyData.value?.id) return

    confirm.require({
        header: 'Activate Company',
        message: `Activate ${companyData.value.name}? Users will regain access.`,
        icon: 'pi pi-check',
        acceptClass: 'p-button-success',
        accept: async () => {
            try {
                await http.patch(`/web/companies/${companyData.value?.id}/activate`)
                toast.add({
                    severity: 'success',
                    summary: 'Company activated',
                    detail: `${companyData.value?.name} is back online.`,
                    life: 3000
                })
                refreshCompany()
            } catch (err: any) {
                toast.add({
                    severity: 'error',
                    summary: 'Activation failed',
                    detail: err?.response?.data?.message || 'Unable to activate company.',
                    life: 4000
                })
            }
        },
    })
}

const deactivateCompany = async () => {
    if (!companyData.value?.id) return

    confirm.require({
        header: 'Deactivate Company',
        message: `Deactivate ${companyData.value.name}? Users will lose access until reactivated.`,
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await http.patch(`/web/companies/${companyData.value?.id}/deactivate`)
                toast.add({
                    severity: 'warn',
                    summary: 'Company deactivated',
                    detail: `${companyData.value?.name} is now inactive.`,
                    life: 3000
                })
                refreshCompany()
            } catch (err: any) {
                toast.add({
                    severity: 'error',
                    summary: 'Deactivation failed',
                    detail: err?.response?.data?.message || 'Unable to deactivate company.',
                    life: 4000
                })
            }
        },
    })
}

const updatePageActions = () => {
    if (!companyData.value) return

    setActions([
        {
            key: 'back',
            label: 'Back to Companies',
            icon: 'pi pi-arrow-left',
            severity: 'secondary',
            click: () => router.visit('/companies'),
        },
        {
            key: 'switch',
            label: 'Switch to Company',
            icon: 'pi pi-sign-in',
            severity: 'success',
            click: switchToCompany,
            disabled: () => !companyData.value?.is_active || isCurrentCompany.value,
        },
        {
            key: 'activate',
            label: 'Activate Company',
            icon: 'pi pi-check',
            severity: 'success',
            click: activateCompany,
            disabled: () => companyData.value?.is_active || !canManage.value,
        },
        {
            key: 'deactivate',
            label: 'Deactivate Company',
            icon: 'pi pi-ban',
            severity: 'warning',
            click: deactivateCompany,
            disabled: () => !companyData.value?.is_active || !canManage.value,
        },
    ])
}

watch(
    () => [companyData.value?.id, companyData.value?.is_active, userRole.value],
    () => updatePageActions(),
    { immediate: true }
)

watch(selectedTab, (newIndex) => {
    if (typeof window === 'undefined') return
    const tabKey = tabKeys[newIndex]
    if (!tabKey) return
    const url = new URL(window.location.href)
    url.searchParams.set('tab', tabKey)
    window.history.replaceState({}, '', url.toString())
})

const initializeTabFromUrl = () => {
    if (typeof window === 'undefined') return
    const urlParams = new URLSearchParams(window.location.search)
    const tabFromUrl = urlParams.get('tab') as typeof tabKeys[number] | null
    if (tabFromUrl && tabKeys.includes(tabFromUrl)) {
        selectedTab.value = tabKeys.indexOf(tabFromUrl)
    } else {
        // Default to reports tab if no valid tab in URL
        selectedTab.value = 0 // reports tab
    }
}

onMounted(async () => {
    initializeTabFromUrl()
    await loadAuditEntries()
})

</script>

<template>
    <Head :title="title" />

    <Toast />
    <ConfirmDialog />

    <LayoutShell>
        <template #sidebar>
            <Sidebar title="Companies" />
        </template>

        <CompanyHeader
            :company="companyData"
            :is-current-company="isCurrentCompany"
            :quick-actions="quickActions"
            @switch-context="switchToCompany"
        />

        <section class="mx-auto w-full max-w-7xl px-4 pb-16">
            <div class="gap-6 lg:grid lg:grid-cols-[1fr,320px]">
                <div class="space-y-6">
                    <div class="rounded-3xl border border-slate-100 bg-white/90 p-4 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/80">
                        <TabView v-model:activeIndex="selectedTab" :lazy="true">
                        <TabPanel header="Reports" value="reports">
                            <div class="rounded-3xl border border-slate-100 bg-white/90 p-6 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/70">
                                <div class="text-center py-12">
                                    <div class="w-16 h-16 bg-blue-50 dark:bg-blue-900/20 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i class="pi pi-chart-line text-blue-500 dark:text-blue-400 text-xl"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                                        Financial Reports
                                    </h3>
                                    <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                                        Comprehensive financial reporting and analytics for your company.
                                    </p>
                                </div>
                            </div>
                        </TabPanel>

                    <TabPanel header="People" value="people">
                        <div class="rounded-3xl border border-slate-100 bg-white/90 p-6 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/70">
                            <TeamMembers
                                :members="companyUsers"
                                :can-invite="canInvite"
                                :can-manage="canManage"
                                @invite="handleInvite"
                                @manage-member="handleManageMember"
                                @invite-member="handleInviteMember"
                                @update-role="handleUpdateRole"
                            />
                        </div>
                    </TabPanel>

                    <TabPanel header="Activity" value="activity">
                        <div class="rounded-3xl border border-slate-100 bg-white/90 p-6 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/70">
                            <ActivityFeed
                                :entries="auditEntries"
                                :loading="auditLoading"
                                :error="auditError"
                                :has-more="hasMoreAuditEntries"
                                @refresh="loadAuditEntries"
                                @load-more="loadMoreAuditEntries"
                            />
                        </div>
                    </TabPanel>
                    </TabView>
                    </div>
                </div>
                
                <!-- Quick Links Sidebar -->
                <div class="lg:sticky lg:top-6 h-fit">
                    <QuickLinks
                        :links="quickLinks"
                        title="Quick Actions"
                    />
                </div>
            </div>
        </section>
    </LayoutShell>
</template>
