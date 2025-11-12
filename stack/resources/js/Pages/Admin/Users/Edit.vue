<script setup>
import { ref, computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'

const page = usePage()
const user = ref(page.props.user)
const companies = ref(page.props.companies || [])
const roles = ref(page.props.roles || [])
const companyRoles = ref(page.props.companyRoles || [])

// Form using Inertia's useForm
const form = useForm({
    name: user.value.name,
    email: user.value.email,
    username: user.value.username,
    password: '',
    password_confirmation: '',
    system_role: user.value.system_role,
    is_active: user.value.is_active,
    companies: user.value.companies ? user.value.companies.map(company => ({
        company_id: company.id,
        role: company.pivot?.role || 'member'
    })) : []
})

// Computed property to check if form is ready to submit
const canSubmit = computed(() => {
    return form.name && 
           form.email && 
           form.username && 
           form.system_role &&
           (!form.password || form.password === form.password_confirmation)
})

// Add company to user
function addCompany() {
    form.companies.push({
        company_id: null,
        role: 'member'
    })
}

// Remove company from user
function removeCompany(index) {
    form.companies.splice(index, 1)
}

// Submit form
function submit() {
    form.put(route('admin.users.update', user.value.id), {
        onSuccess: () => {
            // Form will automatically reset and redirect on success
        },
        onError: () => {
            // Form errors will be automatically displayed
        }
    })
}

// Cancel and go back
function cancel() {
    window.location.href = route('admin.users.index')
}

// Get role display name
function getRoleDisplayName(role) {
    const roleMap = {
        'super_admin': 'Super Admin',
        'admin': 'Admin',
        'user': 'User',
        'guest': 'Guest'
    }
    return roleMap[role] || role
}

// Get company role display name
function getCompanyRoleDisplayName(role) {
    const roleMap = {
        'owner': 'Owner',
        'admin': 'Admin',
        'member': 'Member',
        'viewer': 'Viewer'
    }
    return roleMap[role] || role
}
</script>

<template>
    <LayoutShell>
        <template #default>
            <!-- Page Header -->
            <UniversalPageHeader
                title="Edit User"
                :description="`Edit user: ${user.name}`"
                :default-actions="[
                    { key: 'cancel', label: 'Cancel', icon: 'fas fa-arrow-left', action: cancel, severity: 'secondary' },
                    { key: 'save', label: 'Update User', icon: 'fas fa-save', action: submit, severity: 'primary', disabled: !canSubmit || form.processing }
                ]"
            />

            <div class="max-w-2xl mx-auto">
                <form @submit.prevent="submit" class="space-y-8">
                    <!-- User Information -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-6">User Information</h3>
                        
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- Name -->
                            <div class="sm:col-span-2">
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Full Name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.name }"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                            </div>

                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.email }"
                                />
                                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                            </div>

                            <!-- Username -->
                            <div>
                                <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Username <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="username"
                                    v-model="form.username"
                                    type="text"
                                    required
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.username }"
                                />
                                <p v-if="form.errors.username" class="mt-1 text-sm text-red-600">{{ form.errors.username }}</p>
                            </div>

                            <!-- System Role -->
                            <div>
                                <label for="system_role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    System Role <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="system_role"
                                    v-model="form.system_role"
                                    required
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.system_role }"
                                >
                                    <option value="">Select Role</option>
                                    <option v-for="role in roles" :key="role" :value="role">
                                        {{ getRoleDisplayName(role) }}
                                    </option>
                                </select>
                                <p v-if="form.errors.system_role" class="mt-1 text-sm text-red-600">{{ form.errors.system_role }}</p>
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Account Status
                                </label>
                                <div class="flex items-center space-x-4">
                                    <label class="flex items-center">
                                        <input
                                            v-model="form.is_active"
                                            type="radio"
                                            :value="true"
                                            class="mr-2"
                                        />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input
                                            v-model="form.is_active"
                                            type="radio"
                                            :value="false"
                                            class="mr-2"
                                        />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Inactive</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Password Information -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-6">Password</h3>
                        
                        <div class="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle text-blue-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-blue-700 dark:text-blue-200">
                                        Leave password fields empty to keep the current password unchanged.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- Password -->
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    New Password
                                </label>
                                <input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    minlength="8"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.password }"
                                    placeholder="Leave empty to keep current"
                                />
                                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                                <p v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400">Minimum 8 characters</p>
                            </div>

                            <!-- Confirm Password -->
                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Confirm New Password
                                </label>
                                <input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    type="password"
                                    minlength="8"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    placeholder="Leave empty to keep current"
                                />
                                <p v-if="form.password && form.password_confirmation && form.password !== form.password_confirmation" 
                                   class="mt-1 text-sm text-red-600">
                                    Passwords do not match
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Company Associations -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Company Associations</h3>
                            <button
                                type="button"
                                @click="addCompany"
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800"
                            >
                                <i class="fas fa-plus mr-2"></i>
                                Add Company
                            </button>
                        </div>
                        
                        <div v-if="form.companies.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                            <i class="fas fa-building text-4xl mb-4"></i>
                            <p>No companies assigned. User will have access to no companies.</p>
                        </div>

                        <div v-else class="space-y-4">
                            <div v-for="(company, index) in form.companies" :key="index" 
                                 class="flex items-center space-x-4 p-4 border border-gray-200 dark:border-gray-600 rounded-lg">
                                <!-- Company Selection -->
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Company <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="company.company_id"
                                        required
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    >
                                        <option value="">Select Company</option>
                                        <option v-for="companyOption in companies" :key="companyOption.id" :value="companyOption.id">
                                            {{ companyOption.name }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Role Selection -->
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Company Role <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="company.role"
                                        required
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    >
                                        <option v-for="role in companyRoles" :key="role" :value="role">
                                            {{ getCompanyRoleDisplayName(role) }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Remove Button -->
                                <div class="pt-6">
                                    <button
                                        type="button"
                                        @click="removeCompany(index)"
                                        class="inline-flex items-center px-3 py-2 border border-red-300 text-sm leading-4 font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800"
                                    >
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <p v-if="form.errors.companies" class="mt-2 text-sm text-red-600">{{ form.errors.companies }}</p>
                    </div>

                    <!-- User Information Card -->
                    <div class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-blue-400"></i>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white">User Information</h4>
                                <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    <p><strong>User ID:</strong> {{ user.id }}</p>
                                    <p><strong>Created:</strong> {{ new Date(user.created_at).toLocaleDateString() }}</p>
                                    <p><strong>Last Updated:</strong> {{ new Date(user.updated_at).toLocaleDateString() }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-end space-x-4">
                        <button
                            type="button"
                            @click="cancel"
                            :disabled="form.processing"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-white dark:border-gray-600 disabled:opacity-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="!canSubmit || form.processing"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                        >
                            <i v-if="form.processing" class="fas fa-spinner fa-spin mr-2"></i>
                            <i v-else class="fas fa-save mr-2"></i>
                            {{ form.processing ? 'Updating...' : 'Update User' }}
                        </button>
                    </div>
                </form>
            </div>
        </template>
    </LayoutShell>
</template>