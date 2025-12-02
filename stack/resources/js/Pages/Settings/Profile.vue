<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import DangerButton from '@/Components/DangerButton.vue'

const page = usePage()
const user = computed(() => page.props.auth?.user)

// Update profile form
const profileForm = useForm({
    name: user.value?.name || '',
    email: user.value?.email || '',
})

// Update password form
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const updateProfile = () => {
    profileForm.put('/settings/profile', {
        preserveScroll: true,
        onSuccess: () => {
            // Show success message
        },
    })
}

const updatePassword = () => {
    passwordForm.put('/settings/password', {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset()
        },
    })
}
</script>

<template>
    <LayoutShell>
        <UniversalPageHeader
            title="Profile Settings"
            description="Manage your personal information and account settings"
            :show-search="false"
        />

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="space-y-6">
                <!-- Profile Information -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                            Profile Information
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Update your account's profile information and email address
                        </p>
                    </div>

                    <form @submit.prevent="updateProfile" class="px-6 py-5">
                        <div class="space-y-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Name
                                </label>
                                <input
                                    id="name"
                                    v-model="profileForm.name"
                                    type="text"
                                    required
                                    class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm"
                                    :disabled="profileForm.processing"
                                >
                                <div v-if="profileForm.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    {{ profileForm.errors.name }}
                                </div>
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Email
                                </label>
                                <input
                                    id="email"
                                    v-model="profileForm.email"
                                    type="email"
                                    required
                                    class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm"
                                    :disabled="profileForm.processing"
                                >
                                <div v-if="profileForm.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    {{ profileForm.errors.email }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <PrimaryButton
                                type="submit"
                                :disabled="profileForm.processing"
                            >
                                <i v-if="profileForm.processing" class="fas fa-spinner fa-spin mr-2"></i>
                                {{ profileForm.processing ? 'Saving...' : 'Save Changes' }}
                            </PrimaryButton>
                        </div>

                        <!-- Success Message -->
                        <div v-if="profileForm.recentlySuccessful" class="mt-4 rounded-md bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-3">
                            <p class="text-sm text-green-800 dark:text-green-200">
                                <i class="fas fa-check-circle mr-2"></i>
                                Profile updated successfully!
                            </p>
                        </div>
                    </form>
                </div>

                <!-- Update Password -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                            Update Password
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Ensure your account is using a long, random password to stay secure
                        </p>
                    </div>

                    <form @submit.prevent="updatePassword" class="px-6 py-5">
                        <div class="space-y-6">
                            <div>
                                <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Current Password
                                </label>
                                <input
                                    id="current_password"
                                    v-model="passwordForm.current_password"
                                    type="password"
                                    required
                                    autocomplete="current-password"
                                    class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm"
                                    :disabled="passwordForm.processing"
                                >
                                <div v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    {{ passwordForm.errors.current_password }}
                                </div>
                            </div>

                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    New Password
                                </label>
                                <input
                                    id="password"
                                    v-model="passwordForm.password"
                                    type="password"
                                    required
                                    autocomplete="new-password"
                                    class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm"
                                    :disabled="passwordForm.processing"
                                >
                                <div v-if="passwordForm.errors.password" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    {{ passwordForm.errors.password }}
                                </div>
                            </div>

                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Confirm Password
                                </label>
                                <input
                                    id="password_confirmation"
                                    v-model="passwordForm.password_confirmation"
                                    type="password"
                                    required
                                    autocomplete="new-password"
                                    class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm"
                                    :disabled="passwordForm.processing"
                                >
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <PrimaryButton
                                type="submit"
                                :disabled="passwordForm.processing"
                            >
                                <i v-if="passwordForm.processing" class="fas fa-spinner fa-spin mr-2"></i>
                                {{ passwordForm.processing ? 'Updating...' : 'Update Password' }}
                            </PrimaryButton>
                        </div>

                        <!-- Success Message -->
                        <div v-if="passwordForm.recentlySuccessful" class="mt-4 rounded-md bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-3">
                            <p class="text-sm text-green-800 dark:text-green-200">
                                <i class="fas fa-check-circle mr-2"></i>
                                Password updated successfully!
                            </p>
                        </div>
                    </form>
                </div>

                <!-- Account Information -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                            Account Information
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            View your account details
                        </p>
                    </div>

                    <div class="px-6 py-5">
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Account ID</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white font-mono">{{ user?.id }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Member Since</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                    {{ new Date(user?.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </LayoutShell>
</template>
