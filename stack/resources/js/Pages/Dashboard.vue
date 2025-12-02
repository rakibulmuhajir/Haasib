<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import CompanyCard from '@/Components/CompanyCard.vue'
import RoleBadge from '@/Components/RoleBadge.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

interface Company {
    id: string
    name: string
    slug: string
    base_currency: string
    industry: string | null
    country: string | null
    is_active: boolean
    role: string
    created_at: string
}

interface Invitation {
    id: string
    token: string
    role: string
    expires_at: string
    created_at: string
    company_id: string
    company_name: string
    company_slug: string
    inviter_name: string
    inviter_email: string
}

interface Props {
    companies: Company[]
    pendingInvitations: Invitation[]
}

const props = defineProps<Props>()
const page = usePage()
const user = computed(() => page.props.auth?.user)

const hasCompanies = computed(() => props.companies.length > 0)
const hasInvitations = computed(() => props.pendingInvitations.length > 0)

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

const goToCompany = (slug: string) => {
    router.visit(`/${slug}`)
}

const goToInvitation = (token: string) => {
    router.visit(`/invite/${token}`)
}
</script>

<template>
    <LayoutShell>
        <UniversalPageHeader
            title="Dashboard"
            :description="`Welcome back, ${user?.name || 'User'}!`"
            :show-search="false"
        />

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Pending Invitations -->
            <div v-if="hasInvitations" class="mb-8">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    <i class="fas fa-envelope text-blue-600 dark:text-blue-400 mr-2"></i>
                    Pending Invitations
                </h2>
                <div class="grid grid-cols-1 gap-4">
                    <div
                        v-for="invitation in props.pendingInvitations"
                        :key="invitation.id"
                        class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border-l-4 border-blue-500"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                        {{ invitation.company_name }}
                                    </h3>
                                    <RoleBadge :role="invitation.role" class="ml-3" />
                                </div>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Invited by <span class="font-medium">{{ invitation.inviter_name }}</span> on {{ formatDate(invitation.created_at) }}
                                </p>
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                    Expires {{ formatDate(invitation.expires_at) }}
                                </p>
                            </div>
                            <div>
                                <PrimaryButton @click="goToInvitation(invitation.token)">
                                    View Invitation
                                </PrimaryButton>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Companies Section -->
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        <i class="fas fa-building text-gray-600 dark:text-gray-400 mr-2"></i>
                        Your Companies
                    </h2>
                    <Link href="/companies/create">
                        <PrimaryButton>
                            <i class="fas fa-plus mr-2"></i>
                            Create Company
                        </PrimaryButton>
                    </Link>
                </div>

                <!-- Empty State -->
                <div v-if="!hasCompanies" class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="px-6 py-12 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full mb-6">
                            <i class="fas fa-building text-2xl text-gray-400"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">
                            No companies yet
                        </h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-8 max-w-md mx-auto">
                            Create a company to get started, or wait for an invitation from a team member
                        </p>
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <Link href="/companies/create">
                                <PrimaryButton>
                                    <i class="fas fa-plus mr-2"></i>
                                    Create Your First Company
                                </PrimaryButton>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Companies Grid -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="company in props.companies"
                        :key="company.id"
                        @click="goToCompany(company.slug)"
                        class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 cursor-pointer hover:shadow-lg transition-shadow border border-gray-200 dark:border-gray-700 hover:border-blue-500 dark:hover:border-blue-500"
                    >
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">
                                    {{ company.name }}
                                </h3>
                                <p v-if="company.industry" class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ company.industry }}
                                </p>
                            </div>
                            <RoleBadge :role="company.role" size="sm" />
                        </div>

                        <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                            <div class="flex items-center">
                                <i class="fas fa-coins w-5 mr-2"></i>
                                <span>{{ company.base_currency }}</span>
                            </div>
                            <div v-if="company.country" class="flex items-center">
                                <i class="fas fa-globe w-5 mr-2"></i>
                                <span>{{ company.country }}</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-calendar w-5 mr-2"></i>
                                <span>Created {{ formatDate(company.created_at) }}</span>
                            </div>
                        </div>

                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <span :class="[
                                'inline-flex items-center text-xs font-medium',
                                company.is_active
                                    ? 'text-green-600 dark:text-green-400'
                                    : 'text-gray-500 dark:text-gray-400'
                            ]">
                                <span :class="[
                                    'w-2 h-2 rounded-full mr-2',
                                    company.is_active ? 'bg-green-500' : 'bg-gray-400'
                                ]"></span>
                                {{ company.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div v-if="hasCompanies" class="mt-8 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
                <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-200 mb-3">
                    <i class="fas fa-bolt mr-2"></i>
                    Quick Actions
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <Link href="/companies" class="flex items-center text-sm text-blue-700 dark:text-blue-300 hover:text-blue-800 dark:hover:text-blue-200">
                        <i class="fas fa-list mr-2"></i>
                        View all companies
                    </Link>
                    <Link href="/settings/profile" class="flex items-center text-sm text-blue-700 dark:text-blue-300 hover:text-blue-800 dark:hover:text-blue-200">
                        <i class="fas fa-user-cog mr-2"></i>
                        Profile settings
                    </Link>
                    <Link href="/companies/create" class="flex items-center text-sm text-blue-700 dark:text-blue-300 hover:text-blue-800 dark:hover:text-blue-200">
                        <i class="fas fa-plus-circle mr-2"></i>
                        Create new company
                    </Link>
                </div>
            </div>
        </div>
    </LayoutShell>
</template>
