<script setup lang="ts">
import { computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import RoleBadge from '@/Components/RoleBadge.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

interface Company {
    id: string
    name: string
    slug: string
    base_currency: string
    is_active: boolean
    created_at: string
    industry: string | null
    country: string | null
}

interface Stats {
    total_users: number
    active_users: number
    admins: number
}

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
    token: string
    email: string
    role: string
    expires_at: string
    created_at: string
    inviter_name: string
}

interface Props {
    company: Company
    stats: Stats
    users: User[]
    currentUserRole: string
    pendingInvitations?: Invitation[]
}

const props = defineProps<Props>()
const page = usePage()

const hasPendingInvitations = computed(() =>
    props.pendingInvitations && props.pendingInvitations.length > 0
)

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

const goToInvitation = (token: string) => {
    router.visit(`/invite/${token}`)
}

const goToTeamMembers = () => {
    router.visit(`/${props.company.slug}/users`)
}
</script>

<template>
    <LayoutShell>
        <UniversalPageHeader
            :title="company.name"
            :description="`Company Dashboard - ${company.industry || 'Business'}`"
            :show-search="false"
        />

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Dashboard Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Box 1: Pending Invitations -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-envelope text-blue-600 dark:text-blue-400 mr-2"></i>
                            Pending Invitations
                        </h3>
                    </div>

                    <div v-if="hasPendingInvitations" class="space-y-3">
                        <div
                            v-for="invitation in props.pendingInvitations"
                            :key="invitation.id"
                            class="border border-blue-200 dark:border-blue-800 rounded-lg p-3 bg-blue-50 dark:bg-blue-900/20"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ invitation.email }}
                                    </p>
                                    <div class="mt-1">
                                        <RoleBadge :role="invitation.role" size="sm" />
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Invited by {{ invitation.inviter_name }}
                                    </p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">
                                        Expires {{ formatDate(invitation.expires_at) }}
                                    </p>
                                </div>
                            </div>
                            <button
                                @click="goToInvitation(invitation.token)"
                                class="mt-2 w-full text-center px-3 py-1.5 text-sm bg-blue-600 text-white rounded hover:bg-blue-700"
                            >
                                View Invitation
                            </button>
                        </div>
                    </div>

                    <div v-else class="text-center py-8">
                        <i class="fas fa-check-circle text-4xl text-green-500 dark:text-green-400 mb-2"></i>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            No pending invitations
                        </p>
                    </div>
                </div>

                <!-- Box 2: Team Members -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-users text-purple-600 dark:text-purple-400 mr-2"></i>
                            Team Members
                        </h3>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-3xl font-bold text-gray-900 dark:text-white">
                                    {{ stats.total_users }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Total Members</p>
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-semibold text-green-600 dark:text-green-400">
                                    {{ stats.active_users }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Active</p>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                                <i class="fas fa-shield-halved mr-1"></i>
                                {{ stats.admins }} Admin{{ stats.admins !== 1 ? 's' : '' }}
                            </p>
                            <button
                                @click="goToTeamMembers"
                                class="w-full px-3 py-2 text-sm bg-purple-600 text-white rounded hover:bg-purple-700"
                            >
                                Manage Team
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Box 3: Quick Actions -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-bolt text-yellow-600 dark:text-yellow-400 mr-2"></i>
                            Quick Actions
                        </h3>
                    </div>

                    <div class="space-y-3">
                        <a
                            :href="`/${company.slug}/settings`"
                            class="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            <i class="fas fa-cog text-gray-500 dark:text-gray-400 mr-3"></i>
                            <span class="text-sm text-gray-700 dark:text-gray-300">Company Settings</span>
                        </a>
                        <a
                            :href="`/${company.slug}/users`"
                            class="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            <i class="fas fa-user-plus text-gray-500 dark:text-gray-400 mr-3"></i>
                            <span class="text-sm text-gray-700 dark:text-gray-300">Invite Members</span>
                        </a>
                        <a
                            href="/dashboard"
                            class="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            <i class="fas fa-home text-gray-500 dark:text-gray-400 mr-3"></i>
                            <span class="text-sm text-gray-700 dark:text-gray-300">My Dashboard</span>
                        </a>
                    </div>
                </div>

                <!-- Box 4: Company Info (Full width below) -->
                <div class="lg:col-span-3 bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-building text-gray-600 dark:text-gray-400 mr-2"></i>
                            Company Information
                        </h3>
                        <span :class="[
                            'inline-flex items-center px-3 py-1 rounded-full text-xs font-medium',
                            company.is_active
                                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400'
                        ]">
                            <span :class="[
                                'w-2 h-2 rounded-full mr-2',
                                company.is_active ? 'bg-green-500' : 'bg-gray-400'
                            ]"></span>
                            {{ company.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Industry</p>
                            <p class="text-base font-medium text-gray-900 dark:text-white">
                                {{ company.industry || 'Not specified' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Base Currency</p>
                            <p class="text-base font-medium text-gray-900 dark:text-white">
                                {{ company.base_currency }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Country</p>
                            <p class="text-base font-medium text-gray-900 dark:text-white">
                                {{ company.country || 'Not specified' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Created</p>
                            <p class="text-base font-medium text-gray-900 dark:text-white">
                                {{ formatDate(company.created_at) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Your Role</p>
                            <RoleBadge :role="currentUserRole" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </LayoutShell>
</template>
