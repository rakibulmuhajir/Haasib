<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import PrimaryButton from '@/Components/PrimaryButton.vue'

interface Props {
    companySlug: string
    currentUserRole: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
    (e: 'invited'): void
}>()

const form = useForm({
    email: '',
    role: 'member',
})

// Available roles based on current user's role
const availableRoles = computed(() => {
    const allRoles = [
        { value: 'owner', label: 'Owner', description: 'Full control over the company' },
        { value: 'admin', label: 'Admin', description: 'Manage users and settings' },
        { value: 'accountant', label: 'Accountant', description: 'Access to financial records' },
        { value: 'member', label: 'Member', description: 'Standard access' },
        { value: 'viewer', label: 'Viewer', description: 'Read-only access' },
    ]

    // Owners can assign any role
    if (props.currentUserRole === 'owner') {
        return allRoles
    }

    // Admins can assign admin, accountant, member, viewer (not owner)
    if (props.currentUserRole === 'admin') {
        return allRoles.filter(role => role.value !== 'owner')
    }

    // Others can't invite
    return []
})

const canInvite = computed(() => {
    return ['owner', 'admin'].includes(props.currentUserRole)
})

const submit = () => {
    form.post(`/${props.companySlug}/users/invite`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
            emit('invited')
        },
    })
}
</script>

<template>
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                Invite Team Member
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Send an invitation to join this company
            </p>
        </div>

        <div v-if="!canInvite" class="px-6 py-5">
            <div class="rounded-md bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 p-4">
                <p class="text-sm text-yellow-800 dark:text-yellow-200">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    You don't have permission to invite users. Only owners and admins can send invitations.
                </p>
            </div>
        </div>

        <form v-else @submit.prevent="submit" class="px-6 py-5">
            <div class="space-y-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Email Address
                    </label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        placeholder="colleague@example.com"
                        class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm"
                        :disabled="form.processing"
                    >
                    <div v-if="form.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.email }}
                    </div>
                </div>

                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Role
                    </label>
                    <select
                        id="role"
                        v-model="form.role"
                        required
                        class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm"
                        :disabled="form.processing"
                    >
                        <option v-for="role in availableRoles" :key="role.value" :value="role.value">
                            {{ role.label }} - {{ role.description }}
                        </option>
                    </select>
                    <div v-if="form.errors.role" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.role }}
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <PrimaryButton
                    type="submit"
                    :disabled="form.processing"
                >
                    <i v-if="form.processing" class="fas fa-spinner fa-spin mr-2"></i>
                    <i v-else class="fas fa-paper-plane mr-2"></i>
                    {{ form.processing ? 'Sending...' : 'Send Invitation' }}
                </PrimaryButton>
            </div>

            <!-- Success Message -->
            <div v-if="form.recentlySuccessful" class="mt-4 rounded-md bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-3">
                <p class="text-sm text-green-800 dark:text-green-200">
                    <i class="fas fa-check-circle mr-2"></i>
                    Invitation sent successfully!
                </p>
            </div>
        </form>
    </div>
</template>
