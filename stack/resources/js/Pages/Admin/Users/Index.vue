<script setup>
import { ref, onMounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'

const page = usePage()
const user = page.props.auth?.user
const users = ref(page.props.users)
const filters = ref(page.props.filters || {})
const loading = ref(false)
const roles = ref(page.props.roles || [])

// Form state
const search = ref(filters.value.search || '')
const role = ref(filters.value.role || '')
const isActive = ref(filters.value.is_active === null ? '' : filters.value.is_active.toString())

// Apply filters
function applyFilters() {
  loading.value = true
  
  const params = new URLSearchParams({
    search: search.value,
    role: role.value,
    is_active: isActive.value,
  })

  router.visit(`/admin/users?${params.toString()}`)
}

// Clear filters
function clearFilters() {
  search.value = ''
  role.value = ''
  isActive.value = ''
  applyFilters()
}

// Toggle user status
async function toggleUserStatus(user) {
  try {
    const response = await fetch(`/admin/users/${user.id}/toggle-status`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      }
    })

    if (response.ok) {
      const data = await response.json()
      user.is_active = data.user.is_active
      
      // Show success message
      const message = `User ${user.is_active ? 'activated' : 'deactivated'} successfully`
      // You could use a toast notification here
      console.log(message)
    } else {
      console.error('Failed to toggle user status')
    }
  } catch (error) {
    console.error('Error:', error)
  }
}

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

// Show user details
function showUser(user) {
  router.visit(`/admin/users/${user.id}`)
}

// Edit user
function editUser(user) {
  router.visit(`/admin/users/${user.id}/edit`)
}

// Delete user (with confirmation)
function deleteUser(user) {
  if (!confirm(`Are you sure you want to delete ${user.name}? This action cannot be undone.`)) {
    return
  }

  fetch(`/admin/users/${user.id}`, {
    method: 'DELETE',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    }
  })
  .then(response => {
    if (response.ok) {
      router.visit('/admin/users')
    } else {
      alert('Failed to delete user')
    }
  })
  .catch(error => {
    console.error('Error:', error)
    alert('Error deleting user')
  })
}

// Reset user password
function resetPassword(user) {
  const newPassword = prompt('Enter new password (min 8 characters):')
  if (!newPassword || newPassword.length < 8) {
    return
  }

  fetch(`/admin/users/${user.id}/reset-password`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    },
    body: JSON.stringify({ password: newPassword })
  })
  .then(response => {
    if (response.ok) {
      alert('Password reset successfully')
    } else {
      alert('Failed to reset password')
    }
  })
  .catch(error => {
    console.error('Error:', error)
    alert('Error resetting password')
  })
}

onMounted(() => {
  // Users data is already passed from props
})
</script>

<template>
  <LayoutShell>
    <template #default>
      <!-- Page Header -->
      <UniversalPageHeader
        title="User Management"
        description="Manage users, roles, and permissions"
        :default-actions="[
          { key: 'create', label: 'Add User', icon: 'pi pi-plus', routeName: 'admin.users.create', severity: 'primary' },
          { key: 'refresh', label: 'Refresh', icon: 'pi pi-refresh', action: applyFilters }
        ]"
      />

      <!-- Filters -->
      <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 p-4">
        <div class="grid grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Search
            </label>
            <input
              v-model="search"
              type="text"
              placeholder="Search by name, email, or username..."
              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Role
            </label>
            <select
              v-model="role"
              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
            >
              <option value="">All Roles</option>
              <option v-for="availableRole in roles" :key="availableRole" :value="availableRole">
                {{ formatRole(availableRole) }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Status
            </label>
            <select
              v-model="isActive"
              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
            >
              <option value="">All Users</option>
              <option value="true">Active</option>
              <option value="false">Inactive</option>
            </select>
          </div>

          <div class="flex items-end space-x-2">
            <button
              @click="applyFilters"
              :disabled="loading"
              class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
            >
              <i :class="loading ? 'fas fa-spinner fa-spin' : 'fas fa-search'" class="mr-2"></i>
              Apply Filters
            </button>
            
            <button
              @click="clearFilters"
              :disabled="loading"
              class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-white dark:border-gray-600 disabled:opacity-50"
            >
              <i class="fas fa-times mr-2"></i>
              Clear
            </button>
          </div>
        </div>
      </div>

      <!-- Users Table -->
      <div v-if="users.data && users.data.length > 0" class="bg-white dark:bg-gray-800 shadow overflow-hidden rounded-lg">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  User
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Role
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Companies
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Status
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Joined
                </th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="user in users.data" :key="user.id">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center">
                      <div class="h-8 w-8 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                          {{ user.name.charAt(0).toUpperCase() }}
                        </span>
                      </div>
                    </div>
                    <div class="ml-4">
                      <div class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ user.name }}
                      </div>
                      <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ user.email }}
                      </div>
                    </div>
                  </div>
                </td>

                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full" :class="getRoleBadgeClass(user.system_role)">
                    {{ formatRole(user.system_role) }}
                  </span>
                </td>

                <td class="px-6 py-4 whitespace-nowrap">
                  <div v-if="user.companies && user.companies.length > 0">
                    <div class="flex flex-wrap gap-1">
                      <span v-for="company in user.companies.slice(0, 2)" :key="company.id" class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                        {{ company.name.substring(0, 15) }}{{ company.name.length > 15 ? '...' : '' }}
                      </span>
                      <span v-if="user.companies.length > 2" class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200">
                        +{{ user.companies.length - 2 }}
                      </span>
                    </div>
                  </div>
                  <div v-else class="text-sm text-gray-500 dark:text-gray-400">
                    No companies
                  </div>
                </td>

                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full" :class="getStatusBadgeClass(user.is_active)">
                    {{ user.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>

                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ formatDate(user.created_at) }}
                </td>

                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div class="flex justify-end space-x-2">
                    <!-- View -->
                    <button
                      @click="showUser(user)"
                      class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                      title="View User"
                    >
                      <i class="fas fa-eye"></i>
                    </button>

                    <!-- Edit -->
                    <button
                      @click="editUser(user)"
                      class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                      title="Edit User"
                    >
                      <i class="fas fa-edit"></i>
                    </button>

                    <!-- Toggle Status -->
                    <button
                      @click="toggleUserStatus(user)"
                      :class="user.is_active ? 'text-orange-600 hover:text-orange-900 dark:text-orange-400 dark:hover:text-orange-300' : 'text-emerald-600 hover:text-emerald-900 dark:text-emerald-400 dark:hover:text-emerald-300'"
                      :title="user.is_active ? 'Deactivate User' : 'Activate User'"
                    >
                      <i :class="user.is_active ? 'fas fa-user-slash' : 'fas fa-user-check'"></i>
                    </button>

                    <!-- Reset Password -->
                    <button
                      @click="resetPassword(user)"
                      class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300"
                      title="Reset Password"
                    >
                      <i class="fas fa-key"></i>
                    </button>

                    <!-- Delete -->
                    <button
                      @click="deleteUser(user)"
                      class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                      title="Delete User"
                    >
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="users.links" class="bg-white dark:bg-gray-800 px-4 py-3 flex items-center justify-between border-t border-gray-200 dark:border-gray-700">
          <div class="flex-1 flex justify-between items-center">
            <div class="text-sm text-gray-700 dark:text-gray-300">
              Showing {{ users.from }} to {{ users.to }} of {{ users.total }} results
            </div>
            <div class="flex space-x-2">
              <template v-for="(link, index) in users.links" :key="index">
                <Link
                  v-if="link.url"
                  :href="link.url"
                  :class="{
                    'px-3 py-2 text-sm leading-4 font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500': true,
                    'bg-blue-600 text-white border-blue-600': link.active,
                  }"
                >
                  <span v-html="link.label"></span>
                </Link>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="bg-white dark:bg-gray-800 shadow rounded-lg p-8 text-center">
        <i class="fas fa-users text-gray-400 text-5xl mb-4"></i>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No users found</h3>
        <p class="text-gray-600 dark:text-gray-400">
          {{ search ? 'No users match your search criteria' : 'Get started by creating your first user' }}
        </p>
      </div>
    </template>
  </LayoutShell>
</template>