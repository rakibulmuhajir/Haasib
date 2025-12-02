<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import InviteForm from '@/Components/InviteForm.vue'
import MemberRow from '@/Components/MemberRow.vue'
import RoleBadge from '@/Components/RoleBadge.vue'

interface User {
    id: string
    name: string
    email: string
    role: string
    is_active: boolean
    joined_at: string
}

interface Invitation {
    id: string
    email: string
    role: string
    token: string
    expires_at: string
    created_at: string
    inviter_name: string
}

interface Company {
    id: string
    name: string
    slug: string
}

interface Props {
    company: Company
    users: User[]
    pendingInvitations: Invitation[]
    currentUserRole: string
}

const props = defineProps<Props>()
const page = usePage()
const currentUser = computed(() => page.props.auth?.user)

const refreshPage = () => {
    // Simple page reload to refresh data
    window.location.reload()
}

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

const isRevoking = ref<Record<string, boolean>>({})

const revokeInvitation = (invitationId: string) => {
    if (!confirm('Are you sure you want to revoke this invitation?')) {
        return
    }

    isRevoking.value[invitationId] = true

    router.delete(`/${props.company.slug}/invitations/${invitationId}`, {
        preserveScroll: true,
        onSuccess: () => {
            refreshPage()
        },
        onFinish: () => {
            isRevoking.value[invitationId] = false
        },
    })
}
</script>

<template>
    <LayoutShell>
        <UniversalPageHeader
            :title="`${company.name} - Team Members`"
            :description="`Manage users and invitations for ${company.name}`"
            :show-search="false"
        />

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="space-y-6">
                <!-- Invite Form -->
                <InviteForm
                    :company-slug="company.slug"
                    :current-user-role="props.currentUserRole"
                    @invited="refreshPage"
                />

                <!-- Pending Invitations -->
                <div v-if="pendingInvitations.length > 0" class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                            Pending Invitations
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ pendingInvitations.length }} pending {{ pendingInvitations.length === 1 ? 'invitation' : 'invitations' }}
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Email
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Role
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Invited By
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Expires
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="invitation in pendingInvitations" :key="invitation.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full bg-yellow-100 dark:bg-yellow-900/30 flex items-center justify-center">
                                                    <i class="fas fa-envelope text-yellow-600 dark:text-yellow-400"></i>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ invitation.email }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    Invited {{ formatDate(invitation.created_at) }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <RoleBadge :role="invitation.role" />
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ invitation.inviter_name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDate(invitation.expires_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button
                                            v-if="['owner', 'admin'].includes(props.currentUserRole)"
                                            @click="revokeInvitation(invitation.id)"
                                            :disabled="isRevoking[invitation.id]"
                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50"
                                            title="Revoke invitation"
                                        >
                                            <i v-if="isRevoking[invitation.id]" class="fas fa-spinner fa-spin"></i>
                                            <i v-else class="fas fa-times"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Members List -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                                    Team Members
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ users.length }} {{ users.length === 1 ? 'member' : 'members' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="users.length === 0" class="px-6 py-12 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full mb-4">
                            <i class="fas fa-users text-2xl text-gray-400"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                            No team members yet
                        </h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-6">
                            Start building your team by inviting members above
                        </p>
                    </div>

                    <!-- Members Table -->
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Member
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Role
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Joined
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <MemberRow
                                    v-for="user in users"
                                    :key="user.id"
                                    :member="user"
                                    :company-slug="company.slug"
                                    :current-user-role="props.currentUserRole"
                                    :current-user-id="currentUser?.id || ''"
                                    @role-changed="refreshPage"
                                    @member-removed="refreshPage"
                                />
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Role Permissions Info -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
                    <h4 class="text-sm font-medium text-blue-900 dark:text-blue-200 mb-3">
                        <i class="fas fa-info-circle mr-2"></i>
                        Role Permissions
                    </h4>
                    <dl class="space-y-2 text-sm text-blue-800 dark:text-blue-300">
                        <div class="flex">
                            <dt class="font-medium w-24">Owner:</dt>
                            <dd>Full control over company, can manage all settings and users</dd>
                        </div>
                        <div class="flex">
                            <dt class="font-medium w-24">Admin:</dt>
                            <dd>Can manage users, edit company settings, and access all features</dd>
                        </div>
                        <div class="flex">
                            <dt class="font-medium w-24">Accountant:</dt>
                            <dd>Access to financial records and accounting features</dd>
                        </div>
                        <div class="flex">
                            <dt class="font-medium w-24">Member:</dt>
                            <dd>Standard access to company features</dd>
                        </div>
                        <div class="flex">
                            <dt class="font-medium w-24">Viewer:</dt>
                            <dd>Read-only access to company information</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </LayoutShell>
</template>
