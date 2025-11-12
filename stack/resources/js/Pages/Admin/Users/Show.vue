<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'

const page = usePage()
const user = ref(page.props.user)

// Format date
const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString()
}

// Format role display
const formatRole = (role) => {
    const roleMap = {
        'super_admin': 'Super Admin',
        'admin': 'Admin',
        'user': 'User',
        'guest': 'Guest'
    }
    return roleMap[role] || role
}

// Get role badge color
const getRoleBadgeClass = (role) => {
    const classes = {
        'super_admin': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        'admin': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        'user': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'guest': 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    }
    return classes[role] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
}

// Get status badge color
const getStatusBadgeClass = (isActive) => {
    return isActive 
        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200' 
        : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
}

// Get company role badge color
const getCompanyRoleBadgeClass = (role) => {
    const classes = {
        'owner': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        'admin': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        'member': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        'viewer': 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    }
    return classes[role] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
}

// Format company role display
const formatCompanyRole = (role) => {
    const roleMap = {
        'owner': 'Owner',
        'admin': 'Admin',
        'member': 'Member',
        'viewer': 'Viewer'
    }
    return roleMap[role] || role
}

// Edit user
function editUser() {
    window.location.href = `/admin/users/${user.value.id}/edit`
}

// Go back to list
function goBack() {
    window.location.href = '/admin/users'
}
</script>

<template>
    <LayoutShell>
        <template #default>
            <!-- Page Header -->
            <UniversalPageHeader
                title="User Details"
                :description="`Viewing information for ${user.name}`"
                :default-actions="[
                    { key: 'back', label: 'Back to Users', icon: 'fas fa-arrow-left', action: goBack, severity: 'secondary' },
                    { key: 'edit', label: 'Edit User', icon: 'fas fa-edit', action: editUser, severity: 'primary' }
                ]"
            />

            <div class="max-w-4xl mx-auto space-y-6">
                <!-- User Profile Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="h-20 w-20 rounded-full bg-white dark:bg-gray-300 flex items-center justify-center">
                                    <span class="text-2xl font-bold text-blue-600 dark:text-blue-800">
                                        {{ user.name.charAt(0).toUpperCase() }}
                                    </span>
                                </div>
                            </div>
                            <div class="ml-6">
                                <h1 class="text-2xl font-bold text-white">{{ user.name }}</h1>
                                <p class="text-blue-100">{{ user.email }}</p>
                                <div class="mt-2 flex items-center space-x-3">
                                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full" 
                                          :class="getRoleBadgeClass(user.system_role)">
                                        {{ formatRole(user.system_role) }}
                                    </span>
                                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full" 
                                          :class="getStatusBadgeClass(user.is_active)">
                                        {{ user.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6 border-t border-gray-200 dark:border-gray-700">
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Username</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ user.username }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">User ID</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white font-mono">{{ user.id }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">System Role</dt>
                                <dd class="mt-1">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full" 
                                          :class="getRoleBadgeClass(user.system_role)">
                                        {{ formatRole(user.system_role) }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Account Status</dt>
                                <dd class="mt-1">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full" 
                                          :class="getStatusBadgeClass(user.is_active)">
                                        {{ user.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Member Since</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDate(user.created_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Updated</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDate(user.updated_at) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Company Associations -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Company Associations</h3>
                    
                    <div v-if="!user.companies || user.companies.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                        <i class="fas fa-building text-4xl mb-4"></i>
                        <p>No company associations found.</p>
                        <p class="text-sm">This user has access to no companies.</p>
                    </div>

                    <div v-else class="overflow-hidden">
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            <li v-for="company in user.companies" :key="company.id" class="py-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                                                <i class="fas fa-building text-blue-600 dark:text-blue-300"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ company.name }}</p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Company ID: {{ company.id }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full" 
                                              :class="getCompanyRoleBadgeClass(company.pivot?.role)">
                                            {{ formatCompanyRole(company.pivot?.role) }}
                                        </span>
                                        <span v-if="company.pivot?.is_active" 
                                              class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                                            Active
                                        </span>
                                        <span v-else 
                                              class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                            Inactive
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Additional company details -->
                                <div v-if="company.pivot" class="mt-3 ml-14">
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <span class="text-gray-500 dark:text-gray-400">Added by:</span>
                                            <span class="ml-2 text-gray-900 dark:text-white">{{ company.pivot.created_by || 'Unknown' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500 dark:text-gray-400">Added on:</span>
                                            <span class="ml-2 text-gray-900 dark:text-white">{{ formatDate(company.pivot.created_at) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Activity Summary -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Account Summary</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="text-center">
                            <div class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                                {{ user.companies?.length || 0 }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Company Associations</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-green-600 dark:text-green-400">
                                {{ user.is_active ? 'Active' : 'Inactive' }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Account Status</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-purple-600 dark:text-purple-400">
                                {{ formatRole(user.system_role) }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">System Role</div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <button
                            @click="editUser"
                            class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            <i class="fas fa-edit mr-2"></i>
                            Edit User
                        </button>
                        
                        <button
                            onclick="window.location.href = `/admin/users/${user.id}/reset-password`"
                            class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500"
                        >
                            <i class="fas fa-key mr-2"></i>
                            Reset Password
                        </button>
                        
                        <button
                            onclick="window.location.href = `/admin/users/${user.id}/toggle-status`"
                            class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white"
                            :class="user.is_active ? 'bg-orange-600 hover:bg-orange-700' : 'bg-green-600 hover:bg-green-700'"
                        >
                            <i :class="user.is_active ? 'fas fa-user-slash mr-2' : 'fas fa-user-check mr-2'"></i>
                            {{ user.is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                        
                        <button
                            onclick="if(confirm('Are you sure you want to delete this user? This action cannot be undone.')) { window.location.href = `/admin/users/${user.id}` }"
                            class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                        >
                            <i class="fas fa-trash mr-2"></i>
                            Delete User
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </LayoutShell>
</template>