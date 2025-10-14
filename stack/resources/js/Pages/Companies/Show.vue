<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Badge from 'primevue/badge'
import Avatar from 'primevue/avatar'
import TabPanel from 'primevue/tabpanel'
import TabView from 'primevue/tabview'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import ProgressSpinner from 'primevue/progressspinner'
import Toast from 'primevue/toast'
import Message from 'primevue/message'
import Tooltip from 'primevue/tooltip'
import Divider from 'primevue/divider'

const { t } = useI18n()
const page = usePage()
const toast = ref()

// Props from backend
const company = computed(() => page.props.company)
const fiscalYear = computed(() => company.value?.fiscal_year)
const userRole = computed(() => company.value?.user_role?.role)
const permissions = computed(() => company.value?.permissions || [])
const canManage = computed(() => permissions.value.includes('company.manage'))
const canInvite = computed(() => permissions.value.includes('company.invite'))
const canViewSettings = computed(() => permissions.value.includes('settings.manage'))

// Local state
const loading = ref(false)
const activeTab = ref(0)
const companyUsers = ref([])
const companyInvitations = ref([])
const auditEntries = ref([])

// Computed properties
const isCurrentCompany = computed(() => {
    return page.props.currentCompany?.id === company.value?.id
})

const activeUsersCount = computed(() => {
    return companyUsers.value.filter(user => user.is_active).length
})

const pendingInvitationsCount = computed(() => {
    return companyInvitations.value.filter(inv => inv.status === 'pending').length
})

const recentActivity = computed(() => {
    return auditEntries.value.slice(0, 10)
})

// Methods
const loadCompanyUsers = async () => {
    try {
        const response = await fetch(`/api/v1/companies/${company.value.id}/users`)
        const data = await response.json()
        
        if (response.ok) {
            companyUsers.value = data.data || []
        }
    } catch (error) {
        console.error('Failed to load company users:', error)
    }
}

const loadCompanyInvitations = async () => {
    try {
        const response = await fetch(`/api/v1/companies/${company.value.id}/invitations`)
        const data = await response.json()
        
        if (response.ok) {
            companyInvitations.value = data.data || []
        }
    } catch (error) {
        console.error('Failed to load company invitations:', error)
    }
}

const loadAuditEntries = async () => {
    try {
        const response = await fetch(`/api/v1/companies/${company.value.id}/audit-entries`)
        const data = await response.json()
        
        if (response.ok) {
            auditEntries.value = data.data || []
        }
    } catch (error) {
        console.error('Failed to load audit entries:', error)
    }
}

const switchToCompany = async () => {
    if (isCurrentCompany.value) return

    try {
        const response = await fetch('/api/v1/company-context/switch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify({
                company_id: company.value.id
            })
        })

        if (response.ok) {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: `Switched to ${company.value.name}`,
                life: 2000
            })
            
            setTimeout(() => {
                window.location.reload()
            }, 1000)
        }
    } catch (error) {
        console.error('Failed to switch company:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to switch company',
            life: 3000
        })
    }
}

const deactivateCompany = async () => {
    if (!confirm(`Are you sure you want to deactivate ${company.value.name}? This action can be reversed.`)) {
        return
    }

    try {
        const response = await fetch(`/api/v1/companies/${company.value.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify({
                is_active: false
            })
        })

        if (response.ok) {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Company deactivated successfully',
                life: 3000
            })
            
            setTimeout(() => {
                router.visit('/companies')
            }, 1500)
        }
    } catch (error) {
        console.error('Failed to deactivate company:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to deactivate company',
            life: 3000
        })
    }
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString()
}

const formatDateTime = (dateString) => {
    return new Date(dateString).toLocaleString()
}

const getRoleSeverity = (role) => {
    switch (role) {
        case 'owner': return 'success'
        case 'admin': return 'info'
        case 'accountant': return 'warning'
        case 'member': return 'secondary'
        default: return 'secondary'
    }
}

const getInvitationStatusSeverity = (status) => {
    switch (status) {
        case 'pending': return 'warning'
        case 'accepted': return 'success'
        case 'rejected': return 'danger'
        case 'expired': return 'secondary'
        default: return 'secondary'
    }
}

const getActionSeverity = (action) => {
    switch (action) {
        case 'create': return 'success'
        case 'update': return 'info'
        case 'delete': return 'danger'
        case 'activate': return 'success'
        case 'deactivate': return 'warning'
        default: return 'secondary'
    }
}

// Lifecycle
onMounted(async () => {
    loading.value = true
    try {
        await Promise.all([
            loadCompanyUsers(),
            loadCompanyInvitations(),
            loadAuditEntries()
        ])
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <div class="company-show">
        <Toast ref="toast" />
        
        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center py-12">
            <ProgressSpinner />
        </div>

        <div v-else-if="company">
            <!-- Header -->
            <div class="flex justify-between items-start mb-6">
                <div class="flex items-center gap-4">
                    <Avatar
                        :label="company.name.charAt(0)"
                        class="bg-primary text-primary-contrast"
                        size="xlarge"
                    />
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                            {{ company.name }}
                        </h1>
                        <div class="flex items-center gap-3 mt-2">
                            <Badge
                                :value="company.industry"
                                severity="secondary"
                            />
                            <Badge
                                :value="company.user_role?.role"
                                :severity="getRoleSeverity(company.user_role?.role)"
                            />
                            <Badge
                                :value="company.is_active ? 'Active' : 'Inactive'"
                                :severity="company.is_active ? 'success' : 'danger'"
                            />
                            <span v-if="!isCurrentCompany" class="text-sm text-gray-500">
                                Not currently active
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button
                        v-if="!isCurrentCompany && company.is_active"
                        @click="switchToCompany"
                        icon="pi pi-sign-in"
                        label="Switch to Company"
                        severity="success"
                    />
                    
                    <Link v-if="canManage" :href="`/companies/${company.id}/edit`">
                        <Button
                            icon="pi pi-pencil"
                            label="Edit"
                            severity="secondary"
                            outlined
                        />
                    </Link>

                    <Button
                        v-if="canManage && company.is_active && company.user_role?.role === 'owner'"
                        @click="deactivateCompany"
                        icon="pi pi-times"
                        label="Deactivate"
                        severity="danger"
                        outlined
                    />

                    <Link href="/companies">
                        <Button
                            icon="pi pi-arrow-left"
                            label="Back"
                            severity="secondary"
                            outlined
                        />
                    </Link>
                </div>
            </div>

            <!-- Company Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <Card>
                    <template #content>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-primary">{{ activeUsersCount }}</div>
                            <div class="text-gray-600 dark:text-gray-400">Active Users</div>
                        </div>
                    </template>
                </Card>

                <Card>
                    <template #content>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-primary">{{ pendingInvitationsCount }}</div>
                            <div class="text-gray-600 dark:text-gray-400">Pending Invitations</div>
                        </div>
                    </template>
                </Card>

                <Card>
                    <template #content>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-primary">{{ company.currency }}</div>
                            <div class="text-gray-600 dark:text-gray-400">Currency</div>
                        </div>
                    </template>
                </Card>

                <Card>
                    <template #content>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-primary">
                                {{ fiscalYear ? fiscalYear.name : 'N/A' }}
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">Fiscal Year</div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Details Tabs -->
            <Card>
                <TabView v-model:activeIndex="activeTab">
                    <!-- Overview Tab -->
                    <TabPanel header="Overview">
                        <div class="space-y-6">
                            <!-- Company Information -->
                            <div>
                                <h3 class="text-lg font-semibold mb-4">Company Information</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Industry</label>
                                        <p class="text-gray-900 dark:text-white">{{ company.industry }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Country</label>
                                        <p class="text-gray-900 dark:text-white">{{ company.country }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Timezone</label>
                                        <p class="text-gray-900 dark:text-white">{{ company.timezone || 'Not set' }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Language</label>
                                        <p class="text-gray-900 dark:text-white">{{ company.language || 'en' }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Created</label>
                                        <p class="text-gray-900 dark:text-white">{{ formatDate(company.created_at) }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Last Updated</label>
                                        <p class="text-gray-900 dark:text-white">{{ formatDate(company.updated_at) }}</p>
                                    </div>
                                </div>
                            </div>

                            <Divider />

                            <!-- Fiscal Year Information -->
                            <div v-if="fiscalYear">
                                <h3 class="text-lg font-semibold mb-4">Fiscal Year</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Fiscal Year</label>
                                        <p class="text-gray-900 dark:text-white">{{ fiscalYear.name }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Start Date</label>
                                        <p class="text-gray-900 dark:text-white">{{ formatDate(fiscalYear.start_date) }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">End Date</label>
                                        <p class="text-gray-900 dark:text-white">{{ formatDate(fiscalYear.end_date) }}</p>
                                    </div>
                                </div>
                            </div>

                            <Divider />

                            <!-- Your Role -->
                            <div v-if="company.user_role">
                                <h3 class="text-lg font-semibold mb-4">Your Role in This Company</h3>
                                <div class="flex items-center gap-4">
                                    <Badge
                                        :value="company.user_role.role"
                                        :severity="getRoleSeverity(company.user_role.role)"
                                        size="large"
                                    />
                                    <div>
                                        <p class="text-gray-900 dark:text-white">
                                            {{ company.user_role.role.charAt(0).toUpperCase() + company.user_role.role.slice(1) }}
                                        </p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            Member since {{ formatDate(company.user_role.joined_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </TabPanel>

                    <!-- Users Tab -->
                    <TabPanel header="Users">
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-semibold">Company Users ({{ companyUsers.length }})</h3>
                                <Button
                                    v-if="canInvite"
                                    icon="pi pi-user-plus"
                                    label="Invite User"
                                    severity="secondary"
                                    outlined
                                    @click="activeTab = 2"
                                />
                            </div>

                            <DataTable
                                :value="companyUsers"
                                :paginator="true"
                                :rows="10"
                                scrollable
                                scroll-height="400px"
                            >
                                <Column field="name" header="Name">
                                    <template #body="{ data }">
                                        <div class="flex items-center gap-3">
                                            <Avatar
                                                :label="data.name.charAt(0)"
                                                class="bg-primary text-primary-contrast"
                                            />
                                            <div>
                                                <div class="font-semibold">{{ data.name }}</div>
                                                <div class="text-sm text-gray-500">{{ data.email }}</div>
                                            </div>
                                        </div>
                                    </template>
                                </Column>

                                <Column field="role" header="Role">
                                    <template #body="{ data }">
                                        <Badge
                                            :value="data.pivot.role"
                                            :severity="getRoleSeverity(data.pivot.role)"
                                        />
                                    </template>
                                </Column>

                                <Column field="pivot.is_active" header="Status">
                                    <template #body="{ data }">
                                        <Badge
                                            :value="data.pivot.is_active ? 'Active' : 'Inactive'"
                                            :severity="data.pivot.is_active ? 'success' : 'danger'"
                                        />
                                    </template>
                                </Column>

                                <Column field="pivot.created_at" header="Joined">
                                    <template #body="{ data }">
                                        {{ formatDate(data.pivot.created_at) }}
                                    </template>
                                </Column>

                                <template #empty>
                                    <div class="text-center py-8">
                                        <i class="pi pi-users text-4xl text-gray-400"></i>
                                        <p class="text-gray-500 dark:text-gray-400 mt-4">
                                            No users found in this company
                                        </p>
                                    </div>
                                </template>
                            </DataTable>
                        </div>
                    </TabPanel>

                    <!-- Invitations Tab -->
                    <TabPanel header="Invitations">
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-semibold">
                                    Pending Invitations ({{ pendingInvitationsCount }})
                                </h3>
                                <Button
                                    v-if="canInvite"
                                    icon="pi pi-plus"
                                    label="Send Invitation"
                                />
                            </div>

                            <DataTable
                                :value="companyInvitations"
                                :paginator="true"
                                :rows="10"
                                scrollable
                                scroll-height="400px"
                            >
                                <Column field="email" header="Email" />
                                <Column field="role" header="Role">
                                    <template #body="{ data }">
                                        <Badge
                                            :value="data.role"
                                            :severity="getRoleSeverity(data.role)"
                                        />
                                    </template>
                                </Column>
                                <Column field="status" header="Status">
                                    <template #body="{ data }">
                                        <Badge
                                            :value="data.status"
                                            :severity="getInvitationStatusSeverity(data.status)"
                                        />
                                    </template>
                                </Column>
                                <Column field="expires_at" header="Expires">
                                    <template #body="{ data }">
                                        {{ formatDate(data.expires_at) }}
                                    </template>
                                </Column>
                                <Column field="created_at" header="Sent">
                                    <template #body="{ data }">
                                        {{ formatDate(data.created_at) }}
                                    </template>
                                </Column>

                                <template #empty>
                                    <div class="text-center py-8">
                                        <i class="pi pi-envelope text-4xl text-gray-400"></i>
                                        <p class="text-gray-500 dark:text-gray-400 mt-4">
                                            No invitations found
                                        </p>
                                    </div>
                                </template>
                            </DataTable>
                        </div>
                    </TabPanel>

                    <!-- Activity Tab -->
                    <TabPanel header="Activity">
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold">Recent Activity</h3>

                            <div v-if="recentActivity.length > 0" class="space-y-3">
                                <div
                                    v-for="entry in recentActivity"
                                    :key="entry.id"
                                    class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg"
                                >
                                    <Badge
                                        :value="entry.action"
                                        :severity="getActionSeverity(entry.action)"
                                    />
                                    <div class="flex-1">
                                        <p class="text-sm font-medium">{{ entry.entity_type }} {{ entry.action }}</p>
                                        <p class="text-xs text-gray-500">
                                            {{ formatDateTime(entry.created_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="text-center py-8">
                                <i class="pi pi-history text-4xl text-gray-400"></i>
                                <p class="text-gray-500 dark:text-gray-400 mt-4">
                                    No recent activity found
                                </p>
                            </div>
                        </div>
                    </TabPanel>
                </TabView>
            </Card>
        </div>

        <!-- Error State -->
        <div v-else class="text-center py-12">
            <i class="pi pi-exclamation-triangle text-4xl text-yellow-500"></i>
            <h2 class="text-xl font-semibold mt-4">Company Not Found</h2>
            <p class="text-gray-600 dark:text-gray-400 mt-2">
                The company you're looking for doesn't exist or you don't have access to it.
            </p>
            <Link href="/companies">
                <Button label="Back to Companies" class="mt-4" />
            </Link>
        </div>
    </div>
</template>

<style scoped>
.company-show {
    @apply p-6;
}
</style>