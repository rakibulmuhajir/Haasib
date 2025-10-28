<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Layouts/Sidebar.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import QuickLinks from '@/Components/QuickLinks.vue'
import { usePageActions } from '@/composables/usePageActions'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import Button from 'primevue/button'
import Badge from 'primevue/badge'
import Dialog from 'primevue/dialog'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { http } from '@/lib/http'

type CompanyInvitation = {
    id: number
    company_id: number
    company_name: string
    role: string
    email: string
    status: 'pending' | 'accepted' | 'rejected'
    invited_by: string
    message?: string | null
    created_at: string
    expires_at?: string | null
}

type UserProfile = {
    id: number
    name: string
    email: string
    email_verified_at?: string | null
    created_at: string
    updated_at: string
}

const page = usePage()
const toast = useToast()
const confirm = useConfirm()
const { actions } = usePageActions()

// Define page actions
const pageActions = [
  {
    key: 'edit-profile',
    label: 'Edit Profile',
    icon: 'pi pi-user-edit',
    severity: 'primary',
    action: () => router.put('/profile')
  },
  {
    key: 'account-settings',
    label: 'Account Settings',
    icon: 'pi pi-cog',
    severity: 'secondary',
    action: () => router.put('/settings')
  }
]

// Define quick links for the profile page
const quickLinks = [
  {
    label: 'Edit Profile',
    url: '#',
    icon: 'pi pi-user-edit',
    action: () => router.put('/profile')
  },
  {
    label: 'Account Settings',
    url: '/settings',
    icon: 'pi pi-cog'
  },
  {
    label: 'Security Settings',
    url: '/settings/security',
    icon: 'pi pi-shield'
  },
  {
    label: 'Notification Preferences',
    url: '/settings/notifications',
    icon: 'pi pi-bell'
  }
]

// Set page actions
actions.value = pageActions

const props = defineProps<{
    auth: {
        user: UserProfile
    }
}>()

const userProfile = ref<UserProfile>(props.auth.user)
const invitations = ref<CompanyInvitation[]>([])
const loading = ref(false)
const refreshing = ref(false)

const pendingInvitations = computed(() =>
    invitations.value.filter(inv => inv.status === 'pending')
)

const acceptedInvitations = computed(() =>
    invitations.value.filter(inv => inv.status === 'accepted')
)

const rejectedInvitations = computed(() =>
    invitations.value.filter(inv => inv.status === 'rejected')
)

const getRoleSeverity = (role: string): 'success' | 'info' | 'secondary' => {
    switch (role.toLowerCase()) {
        case 'owner': return 'success'
        case 'admin': return 'info'
        default: return 'secondary'
    }
}

const getStatusSeverity = (status: string): 'success' | 'warning' | 'danger' | 'secondary' => {
    switch (status.toLowerCase()) {
        case 'accepted': return 'success'
        case 'pending': return 'warning'
        case 'rejected': return 'danger'
        default: return 'secondary'
    }
}

const formatDate = (dateString: string) => {
    try {
        const date = new Date(dateString)
        return new Intl.DateTimeFormat('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date)
    } catch {
        return dateString
    }
}

const isExpired = (expiresAt?: string | null) => {
    if (!expiresAt) return false
    return new Date(expiresAt) < new Date()
}

const loadInvitations = async () => {
    loading.value = true
    try {
        const { data } = await http.get('/api/v1/user/invitations')
        invitations.value = data.data || []
    } catch (error: any) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error?.response?.data?.message || 'Unable to load invitations.',
            life: 4000
        })
    } finally {
        loading.value = false
    }
}

const handleAcceptInvitation = async (invitation: CompanyInvitation) => {
    confirm.require({
        header: 'Accept Invitation',
        message: `Join ${invitation.company_name} as ${invitation.role}?`,
        icon: 'pi pi-check',
        acceptClass: 'p-button-success',
        accept: async () => {
            try {
                await http.post(`/api/v1/user/invitations/${invitation.id}/accept`)

                toast.add({
                    severity: 'success',
                    summary: 'Invitation accepted',
                    detail: `You have joined ${invitation.company_name}`,
                    life: 3000
                })

                await loadInvitations()

                // Optionally redirect to the company
                setTimeout(() => {
                    router.visit(`/companies/${invitation.company_id}`)
                }, 1500)

            } catch (error: any) {
                toast.add({
                    severity: 'error',
                    summary: 'Accept failed',
                    detail: error?.response?.data?.message || 'Unable to accept invitation.',
                    life: 4000
                })
            }
        },
    })
}

const handleRejectInvitation = async (invitation: CompanyInvitation) => {
    confirm.require({
        header: 'Reject Invitation',
        message: `Reject invitation from ${invitation.company_name}?`,
        icon: 'pi pi-times',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await http.post(`/api/v1/user/invitations/${invitation.id}/reject`)

                toast.add({
                    severity: 'success',
                    summary: 'Invitation rejected',
                    detail: `You have rejected the invitation from ${invitation.company_name}`,
                    life: 3000
                })

                await loadInvitations()

            } catch (error: any) {
                toast.add({
                    severity: 'error',
                    summary: 'Reject failed',
                    detail: error?.response?.data?.message || 'Unable to reject invitation.',
                    life: 4000
                })
            }
        },
    })
}

const refreshInvitations = async () => {
    refreshing.value = true
    try {
        await loadInvitations()
        toast.add({
            severity: 'info',
            summary: 'Refreshed',
            detail: 'Invitations have been refreshed',
            life: 2000
        })
    } finally {
        refreshing.value = false
    }
}

onMounted(() => {
    loadInvitations()
})
</script>

<template>
    <Head title="Profile" />

    <Toast />
    <ConfirmDialog />

    <LayoutShell>
        <template #sidebar>
            <Sidebar title="Profile" />
        </template>

        <!-- Universal Page Header -->
        <UniversalPageHeader
          title="Profile"
          description="Manage your personal information and account settings"
          subDescription="Update your profile and preferences"
          :show-search="false"
        />

        <!-- Main Content Grid -->
        <div class="content-grid-5-6">
            <!-- Left Column - Main Content -->
            <div class="main-content">
                <section class="mx-auto w-full max-w-7xl px-4 pb-16">
                    <div class="space-y-6">
                <!-- Profile Header -->
                <div class="rounded-3xl border border-slate-100 bg-white/90 p-6 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/80">
                    <div class="flex items-center gap-6">
                        <div class="w-20 h-20 bg-slate-200 dark:bg-slate-700 rounded-full flex items-center justify-center">
                            <span class="text-2xl font-bold text-slate-600 dark:text-slate-300">
                                {{ userProfile.name?.charAt(0)?.toUpperCase() || '?' }}
                            </span>
                        </div>
                        <div class="flex-1">
                            <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-2">
                                {{ userProfile.name }}
                            </h1>
                            <p class="text-slate-600 dark:text-slate-400 mb-4">{{ userProfile.email }}</p>
                            <div class="flex items-center gap-4 text-sm text-slate-500 dark:text-slate-500">
                                <span>Member since {{ formatDate(userProfile.created_at) }}</span>
                                <Badge
                                    v-if="userProfile.email_verified_at"
                                    value="Verified"
                                    severity="success"
                                    size="small"
                                />
                                <Badge
                                    v-else
                                    value="Not Verified"
                                    severity="warning"
                                    size="small"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Refresh Button -->
                <div class="flex justify-end mb-4">
                    <div class="flex flex-col gap-2">
                            <Button
                                @click="refreshInvitations"
                                variant="outline"
                                size="small"
                                :loading="refreshing"
                            >
                                <i class="pi pi-refresh mr-2"></i>
                                Refresh
                            </Button>
                    </div>
                </div>

                <!-- Company Invitations Section -->
                <div class="rounded-3xl border border-slate-100 bg-white/90 p-4 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/80">
                    <TabView :lazy="true">
                        <!-- Pending Invitations -->
                        <TabPanel header="Pending Invitations" value="pending">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-medium text-slate-900 dark:text-white">
                                        Pending Invitations
                                        <Badge
                                            v-if="pendingInvitations.length > 0"
                                            :value="pendingInvitations.length"
                                            severity="warning"
                                            class="ml-2"
                                        />
                                    </h3>

                                </div>

                                <!-- Loading State -->
                                <div v-if="loading" class="text-center py-8">
                                    <i class="pi pi-spin pi-spinner text-2xl text-slate-400"></i>
                                    <p class="text-slate-500 mt-2">Loading invitations...</p>
                                </div>

                                <!-- Empty State -->
                                <div v-else-if="pendingInvitations.length === 0" class="text-center py-12">
                                    <div class="w-16 h-16 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i class="pi pi-envelope text-slate-400 text-xl"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-slate-900 dark:text-white mb-2">
                                        No pending invitations
                                    </h3>
                                    <p class="text-slate-500 dark:text-slate-400">
                                        You don't have any company invitations waiting for your response.
                                    </p>
                                </div>

                                <!-- Pending Invitations List -->
                                <div v-else class="space-y-3">
                                    <div
                                        v-for="invitation in pendingInvitations"
                                        :key="invitation.id"
                                        class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 hover:shadow-sm transition-shadow"
                                    >
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-3 mb-2">
                                                    <h4 class="font-medium text-slate-900 dark:text-white">
                                                        {{ invitation.company_name }}
                                                    </h4>
                                                    <Badge
                                                        :value="invitation.role"
                                                        :severity="getRoleSeverity(invitation.role)"
                                                        size="small"
                                                    />
                                                    <Badge
                                                        value="Pending"
                                                        severity="warning"
                                                        size="small"
                                                    />
                                                    <Badge
                                                        v-if="isExpired(invitation.expires_at)"
                                                        value="Expired"
                                                        severity="danger"
                                                        size="small"
                                                    />
                                                </div>

                                                <p class="text-sm text-slate-600 dark:text-slate-400 mb-2">
                                                    Invited by {{ invitation.invited_by }} • {{ formatDate(invitation.created_at) }}
                                                </p>

                                                <p v-if="invitation.message" class="text-sm text-slate-500 dark:text-slate-500 mb-3 italic">
                                                    "{{ invitation.message }}"
                                                </p>

                                                <p v-if="invitation.expires_at" class="text-xs text-slate-500 dark:text-slate-500">
                                                    Expires: {{ formatDate(invitation.expires_at) }}
                                                </p>
                                            </div>

                                            <div class="flex gap-2 ml-4">
                                                <Button
                                                    v-if="!isExpired(invitation.expires_at)"
                                                    size="small"
                                                    @click="handleAcceptInvitation(invitation)"
                                                    :loading="false"
                                                >
                                                    <i class="pi pi-check mr-1"></i>
                                                    Accept
                                                </Button>
                                                <Button
                                                    variant="danger"
                                                    size="small"
                                                    @click="handleRejectInvitation(invitation)"
                                                    :loading="false"
                                                >
                                                    <i class="pi pi-times mr-1"></i>
                                                    Reject
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </TabPanel>

                        <!-- Accepted Invitations -->
                        <TabPanel header="Accepted" value="accepted">
                            <div class="space-y-4">
                                <h3 class="text-lg font-medium text-slate-900 dark:text-white">
                                    Accepted Invitations
                                    <Badge
                                        v-if="acceptedInvitations.length > 0"
                                        :value="acceptedInvitations.length"
                                        severity="success"
                                        class="ml-2"
                                    />
                                </h3>

                                <!-- Empty State -->
                                <div v-if="acceptedInvitations.length === 0" class="text-center py-12">
                                    <div class="w-16 h-16 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i class="pi pi-check-circle text-slate-400 text-xl"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-slate-900 dark:text-white mb-2">
                                        No accepted invitations
                                    </h3>
                                    <p class="text-slate-500 dark:text-slate-400">
                                        You haven't accepted any company invitations yet.
                                    </p>
                                </div>

                                <!-- Accepted List -->
                                <div v-else class="space-y-3">
                                    <div
                                        v-for="invitation in acceptedInvitations"
                                        :key="invitation.id"
                                        class="border border-slate-200 dark:border-slate-700 rounded-lg p-4"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="flex items-center gap-3 mb-2">
                                                    <h4 class="font-medium text-slate-900 dark:text-white">
                                                        {{ invitation.company_name }}
                                                    </h4>
                                                    <Badge
                                                        :value="invitation.role"
                                                        :severity="getRoleSeverity(invitation.role)"
                                                        size="small"
                                                    />
                                                    <Badge
                                                        value="Accepted"
                                                        severity="success"
                                                        size="small"
                                                    />
                                                </div>
                                                <p class="text-sm text-slate-600 dark:text-slate-400">
                                                    Joined {{ formatDate(invitation.created_at) }}
                                                </p>
                                            </div>
                                            <Button
                                                variant="outline"
                                                size="small"
                                                @click="router.visit(`/companies/${invitation.company_id}`)"
                                            >
                                                <i class="pi pi-external-link mr-1"></i>
                                                View Company
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </TabPanel>

                        <!-- Rejected Invitations -->
                        <TabPanel header="Rejected" value="rejected">
                            <div class="space-y-4">
                                <h3 class="text-lg font-medium text-slate-900 dark:text-white">
                                    Rejected Invitations
                                    <Badge
                                        v-if="rejectedInvitations.length > 0"
                                        :value="rejectedInvitations.length"
                                        severity="danger"
                                        class="ml-2"
                                    />
                                </h3>

                                <!-- Empty State -->
                                <div v-if="rejectedInvitations.length === 0" class="text-center py-12">
                                    <div class="w-16 h-16 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i class="pi pi-times-circle text-slate-400 text-xl"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-slate-900 dark:text-white mb-2">
                                        No rejected invitations
                                    </h3>
                                    <p class="text-slate-500 dark:text-slate-400">
                                        You haven't rejected any company invitations.
                                    </p>
                                </div>

                                <!-- Rejected List -->
                                <div v-else class="space-y-3">
                                    <div
                                        v-for="invitation in rejectedInvitations"
                                        :key="invitation.id"
                                        class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 opacity-75"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="flex items-center gap-3 mb-2">
                                                    <h4 class="font-medium text-slate-900 dark:text-white">
                                                        {{ invitation.company_name }}
                                                    </h4>
                                                    <Badge
                                                        :value="invitation.role"
                                                        :severity="getRoleSeverity(invitation.role)"
                                                        size="small"
                                                    />
                                                    <Badge
                                                        value="Rejected"
                                                        severity="danger"
                                                        size="small"
                                                    />
                                                </div>
                                                <p class="text-sm text-slate-600 dark:text-slate-400">
                                                    Rejected {{ formatDate(invitation.created_at) }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </TabPanel>
                    </TabView>
                    </div>
                </div>
            </section>
            </div>
        </div>

        <!-- Right Column - Quick Links -->
        <div class="sidebar-content">
            <QuickLinks
                :links="quickLinks"
                title="Quick Actions"
            />
        </div>
    </LayoutShell>
</template>

