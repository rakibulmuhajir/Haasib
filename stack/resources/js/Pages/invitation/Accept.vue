<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import DangerButton from '@/Components/DangerButton.vue'

interface Invitation {
    id: string
    email: string
    role: string
    status: string
    expires_at: string
    is_expired: boolean
    is_valid: boolean
    company: {
        id: string
        name: string
        slug: string
    }
    inviter: {
        name: string
        email: string
    }
}

interface Props {
    invitation: Invitation
    token: string
}

const props = defineProps<Props>()
const page = usePage()
const user = computed(() => page.props.auth?.user)

const acceptForm = useForm({})
const rejectForm = useForm({})

const isLoggedIn = computed(() => !!user.value)
const canAccept = computed(() => {
    return isLoggedIn.value &&
           user.value?.email === props.invitation.email &&
           props.invitation.is_valid
})

const acceptInvitation = () => {
    acceptForm.post(`/invite/${props.token}/accept`, {
        onSuccess: () => {
            router.visit(`/${props.invitation.company.slug}`)
        }
    })
}

const rejectInvitation = () => {
    if (!confirm('Are you sure you want to reject this invitation?')) {
        return
    }

    rejectForm.post(`/invite/${props.token}/reject`, {
        onSuccess: () => {
            router.visit('/dashboard')
        }
    })
}

const getRoleBadgeClass = (role: string) => {
    const classes = {
        owner: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
        admin: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        accountant: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        member: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
        viewer: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
    }
    return classes[role as keyof typeof classes] || classes.member
}

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    })
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="mx-auto h-16 w-16 flex items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30 mb-4">
                    <i class="fas fa-envelope-open-text text-blue-600 dark:text-blue-400 text-2xl"></i>
                </div>
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white">
                    Company Invitation
                </h2>
            </div>

            <!-- Main Card -->
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden">
                <!-- Expired/Invalid State -->
                <div v-if="!invitation.is_valid" class="p-8">
                    <div class="text-center">
                        <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30 mb-4">
                            <i class="fas fa-times-circle text-red-600 dark:text-red-400 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                            Invitation {{ invitation.is_expired ? 'Expired' : 'Invalid' }}
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-6">
                            {{ invitation.is_expired
                                ? 'This invitation has expired. Please contact the company owner for a new invitation.'
                                : 'This invitation is no longer valid.'
                            }}
                        </p>
                        <a href="/dashboard" class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 font-medium">
                            Go to Dashboard →
                        </a>
                    </div>
                </div>

                <!-- Valid Invitation -->
                <div v-else class="p-8">
                    <!-- Invitation Details -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">You've been invited to join</span>
                            <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', getRoleBadgeClass(invitation.role)]">
                                {{ invitation.role }}
                            </span>
                        </div>

                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                            {{ invitation.company.name }}
                        </h3>

                        <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                            <div class="flex items-center">
                                <i class="fas fa-user-circle w-5 mr-2"></i>
                                <span>Invited by <span class="font-medium text-gray-900 dark:text-white">{{ invitation.inviter.name }}</span></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-envelope w-5 mr-2"></i>
                                <span>{{ invitation.email }}</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-clock w-5 mr-2"></i>
                                <span>Expires {{ formatDate(invitation.expires_at) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Role Description -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                        <p class="text-sm text-blue-800 dark:text-blue-300">
                            <i class="fas fa-info-circle mr-2"></i>
                            <span v-if="invitation.role === 'owner'">
                                As an <strong>Owner</strong>, you'll have full control over the company settings and can manage all aspects of the business.
                            </span>
                            <span v-else-if="invitation.role === 'admin'">
                                As an <strong>Admin</strong>, you'll be able to manage users, edit company settings, and access all features.
                            </span>
                            <span v-else-if="invitation.role === 'accountant'">
                                As an <strong>Accountant</strong>, you'll have access to financial records and accounting features.
                            </span>
                            <span v-else-if="invitation.role === 'member'">
                                As a <strong>Member</strong>, you'll have standard access to company features.
                            </span>
                            <span v-else-if="invitation.role === 'viewer'">
                                As a <strong>Viewer</strong>, you'll have read-only access to company information.
                            </span>
                        </p>
                    </div>

                    <!-- Not Logged In -->
                    <div v-if="!isLoggedIn" class="space-y-4">
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                            <p class="text-sm text-yellow-800 dark:text-yellow-300">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                Please log in or create an account to accept this invitation.
                            </p>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-3">
                            <a href="/login" class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Log In
                            </a>
                            <a href="/register" class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Sign Up
                            </a>
                        </div>
                    </div>

                    <!-- Wrong Email -->
                    <div v-else-if="user.email !== invitation.email" class="space-y-4">
                        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                            <p class="text-sm text-red-800 dark:text-red-300">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                This invitation was sent to <strong>{{ invitation.email }}</strong>, but you're logged in as <strong>{{ user.email }}</strong>.
                            </p>
                            <p class="text-sm text-red-800 dark:text-red-300 mt-2">
                                Please log in with the correct account or contact the person who invited you.
                            </p>
                        </div>

                        <div class="flex justify-center">
                            <a href="/logout" class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 font-medium text-sm">
                                Log out and try again →
                            </a>
                        </div>
                    </div>

                    <!-- Can Accept -->
                    <div v-else-if="canAccept" class="space-y-4">
                        <div class="flex flex-col sm:flex-row gap-3">
                            <PrimaryButton
                                @click="acceptInvitation"
                                :disabled="acceptForm.processing"
                                class="flex-1 justify-center"
                            >
                                <i v-if="acceptForm.processing" class="fas fa-spinner fa-spin mr-2"></i>
                                <i v-else class="fas fa-check mr-2"></i>
                                {{ acceptForm.processing ? 'Accepting...' : 'Accept Invitation' }}
                            </PrimaryButton>

                            <DangerButton
                                @click="rejectInvitation"
                                :disabled="rejectForm.processing"
                                class="flex-1 justify-center"
                            >
                                <i v-if="rejectForm.processing" class="fas fa-spinner fa-spin mr-2"></i>
                                <i v-else class="fas fa-times mr-2"></i>
                                {{ rejectForm.processing ? 'Rejecting...' : 'Reject' }}
                            </DangerButton>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-6 text-center">
                <a href="/dashboard" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                    ← Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</template>
