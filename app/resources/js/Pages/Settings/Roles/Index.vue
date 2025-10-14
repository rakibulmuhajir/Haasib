<template>
  <div>
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Role Management</h2>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Manage user roles and permissions for {{ company.name }}
      </p>
    </div>

    <!-- Permissions Legend -->
    <div class="mb-6 bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
      <h3 class="text-lg font-semibold mb-3 text-gray-900 dark:text-white">Permission Levels</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        <div v-for="roleInfo in roleDescriptions" :key="roleInfo.role" 
             class="flex items-start space-x-2">
          <span class="text-sm font-medium px-2 py-1 rounded"
                :class="getRoleBadgeClass(roleInfo.role)">
            {{ roleInfo.title }}
          </span>
          <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
            {{ roleInfo.description }}
          </p>
        </div>
      </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
      <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
          Company Users ({{ users.length }})
        </h3>
        
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
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="user in users" :key="user.id">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                      {{ user.name }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                      {{ user.email }}
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div v-if="canAssignRoles">
                    <select 
                      :value="user.role" 
                      @change="updateUserRole(user.id, $event.target.value)"
                      class="text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                      :disabled="isUpdatingRole === user.id"
                    >
                      <option value="">No role</option>
                      <option value="viewer">Viewer</option>
                      <option value="employee">Employee</option>
                      <option value="accountant">Accountant</option>
                      <option value="manager">Manager</option>
                      <option value="admin">Admin</option>
                      <option value="owner">Owner</option>
                    </select>
                  </div>
                  <span v-else 
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="getRoleBadgeClass(user.role)">
                    {{ user.role_display }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  <button
                    v-if="canDeactivateUsers && user.role !== 'owner'"
                    @click="confirmRemoveUser(user)"
                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                    :disabled="isRemoving"
                  >
                    Remove
                  </button>
                  <span v-else class="text-gray-400">-</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Confirmation Modal -->
    <Modal v-if="showRemoveModal" @close="showRemoveModal = false">
      <template #title>Remove User from Company</template>
      <template #content>
        <p class="text-sm text-gray-600 dark:text-gray-400">
          Are you sure you want to remove <strong>{{ selectedUser?.name }}</strong> from {{ company.name }}?
        </p>
        <p class="mt-2 text-sm text-red-600">
          This action will deactivate their access to the company and can only be reversed by another company member.
        </p>
      </template>
      <template #footer>
        <button @click="showRemoveModal = false" 
                class="btn-secondary mr-3">
          Cancel
        </button>
        <button @click="removeUser" 
                :disabled="isRemoving"
                class="btn btn-danger">
          {{ isRemoving ? 'Removing...' : 'Remove User' }}
        </button>
      </template>
    </Modal>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { usePermissions } from '@/composables/usePermissions'
import Modal from '@/Components/Modal.vue'

const page = usePage()
const { has, hasRole } = usePermissions()

const props = defineProps({
  company: Object,
  users: Array,
  roles: Array,
})

const canAssignRoles = computed(() => has('users.roles.assign'))
const canDeactivateUsers = computed(() => has('users.deactivate'))

const isUpdatingRole = ref(null)
const isRemoving = ref(false)
const showRemoveModal = ref(false)
const selectedUser = ref(null)

const roleDescriptions = [
  {
    role: 'owner',
    title: 'Owner',
    description: 'Full access to all company settings and data'
  },
  {
    role: 'admin',
    title: 'Admin',
    description: 'Manage users, settings, and all operations except billing'
  },
  {
    role: 'manager',
    title: 'Manager',
    description: 'Day-to-day operations, create and manage invoices/payments'
  },
  {
    role: 'accountant',
    title: 'Accountant',
    description: 'Full access to ledger, reports, and financial data'
  },
  {
    role: 'employee',
    title: 'Employee',
    description: 'Basic operations, create invoices and view data'
  },
  {
    role: 'viewer',
    title: 'Viewer',
    description: 'Read-only access to view company data'
  }
]

function getRoleBadgeClass(role) {
  const classes = {
    'owner': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    'admin': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    'manager': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    'accountant': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    'employee': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    'viewer': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
  }
  return classes[role] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
}

async function updateUserRole(userId, newRole) {
  isUpdatingRole.value = userId
  
  try {
    const response = await axios.put(
      route('companies.roles.update', [props.company.id, userId]),
      { role: newRole }
    )
    
    // Update local user data
    const userIndex = props.users.findIndex(u => u.id === userId)
    if (userIndex !== -1) {
      props.users[userIndex].role = response.data.role
      props.users[userIndex].role_display = response.data.role_display
    }
    
    page.props.flash.success = 'Role updated successfully'
  } catch (error) {
    console.error('Error updating role:', error)
    page.props.flash.error = error.response?.data?.message || 'Failed to update role'
  } finally {
    isUpdatingRole.value = null
  }
}

function confirmRemoveUser(user) {
  selectedUser.value = user
  showRemoveModal.value = true
}

async function removeUser() {
  isRemoving.value = true
  
  try {
    await axios.delete(
      route('companies.roles.remove', [props.company.id, selectedUser.value.id])
    )
    
    // Remove user from local array
    const userIndex = props.users.findIndex(u => u.id === selectedUser.value.id)
    if (userIndex !== -1) {
      props.users.splice(userIndex, 1)
    }
    
    showRemoveModal.value = false
    selectedUser.value = null
    page.props.flash.success = 'User removed successfully'
  } catch (error) {
    console.error('Error removing user:', error)
    page.props.flash.error = error.response?.data?.message || 'Failed to remove user'
  } finally {
    isRemoving.value = false
  }
}
</script>