<script setup lang="ts">
import { ref, watch } from 'vue'
import SvgIcon from '@/Components/SvgIcon.vue'


interface UserMembership {
  id: string
  name: string
  slug: string
  role: string
  created_at: string
  updated_at: string
}

interface RoleOption {
  value: string
  label: string
}

const props = defineProps<{
  memberships: UserMembership[],
  loading?: boolean,
  roleOptions: RoleOption[],
}>()

const emit = defineEmits<{
  (e: 'update-role', membership: UserMembership): void,
  (e: 'unassign', membership: UserMembership): void,
}>()

const selectedRoles = ref<Record<string, string>>({})
const updatingRole = ref<string | null>(null)
const removingMembership = ref<string | null>(null)

// Watch for changes in memberships and clear selected roles when memberships are updated
watch(() => props.memberships, (newMemberships) => {
  newMemberships.forEach(membership => {
    if (selectedRoles.value[membership.id]) {
      // Clear selected role if it matches the current membership role (meaning it was successfully updated)
      if (selectedRoles.value[membership.id] === membership.role) {
        delete selectedRoles.value[membership.id]
      }
    }
  })
}, { deep: true })


const handleUpdateRole = async (membership: UserMembership) => {
  console.log('🔥 UPDATE BUTTON CLICKED - UserMembershipList.vue')
  console.log('Membership data:', membership)
  const newRole = selectedRoles.value[membership.id] || membership.role
  console.log('Selected role for membership:', membership.id, '->', newRole)

  if (!newRole || newRole === membership.role) {
    console.log('❌ No role change detected, returning early')
    return
  }

  updatingRole.value = membership.id

  try {
    console.log('Emitting update-role event with:', { ...membership, role: newRole })
    emit('update-role', { ...membership, role: newRole })
    console.log('✅ Event emitted successfully')

    // Don't clear the selected role - let the parent component handle the state update
    // The parent should update the memberships prop with the new role
  } catch (error) {
    console.error('❌ Update failed:', error)
  } finally {
    setTimeout(() => {
      updatingRole.value = null
    }, 1000)
  }
}

const onRoleChange = (membershipId: string, event: Event) => {
  const newRole = (event.target as HTMLSelectElement).value
  console.log('📝 ROLE CHANGED - UserMembershipList.vue')
  console.log('Membership ID:', membershipId, 'New Role:', newRole)
  selectedRoles.value[membershipId] = newRole
}

const handleUnassign = (membership: UserMembership) => {
  removingMembership.value = membership.id
  emit('unassign', membership)

  // Reset loading state after a delay
  setTimeout(() => {
    removingMembership.value = null
  }, 1000)
}

const copyToClipboard = async (text: string) => {
  try {
    await navigator.clipboard.writeText(text)
    // Simple feedback - could use a toast if available
  } catch (err) {
    console.error('Failed to copy text: ', err)
    // Fallback for older browsers
    const textArea = document.createElement('textarea')
    textArea.value = text
    document.body.appendChild(textArea)
    textArea.select()
    document.execCommand('copy')
    document.body.removeChild(textArea)
  }
}

// Add CSS for copy group hover behavior
const copyStyles = `
<style>
.copy-group:hover .copy-icon {
  opacity: 1 !important;
}
.copy-icon:hover {
  opacity: 1 !important;
}
</style>
`
</script>

<template>
  <div class="space-y-2">
    <!-- Membership Items -->
    <div v-for="m in memberships" :key="m.id" class="group relative bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 transition-colors">
      <div class="p-3">
        <div class="flex items-start justify-between">
          <!-- Company Info -->
          <div class="flex items-start gap-3 flex-1">
            <!-- Company Avatar -->
            <div class="w-8 h-8 rounded-md bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-600 dark:text-gray-300 text-sm font-medium">
              {{ m.name?.charAt(0)?.toUpperCase() }}
            </div>

            <!-- Company Details -->
            <div class="flex-1 min-w-0">
              <!-- Company Name with Copy -->
              <div class="copy-group flex items-center gap-2 mb-1">
                <h3 class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ m.name }}</h3>
                <button
                  @click="copyToClipboard(m.name)"
                  class="copy-icon opacity-0 transition-opacity text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                  v-tooltip="'Copy Company Name'"
                >
                  <SvgIcon name="copy" set="line" class="w-3 h-3" />
                </button>
              </div>

              <!-- Company Slug with Copy -->
              <div class="copy-group flex items-center gap-2 mb-1">
                <p class="text-sm text-gray-500 dark:text-gray-400 font-mono text-xs">{{ m.slug }}</p>
                <button
                  @click="copyToClipboard(m.slug)"
                  class="copy-icon opacity-0 transition-opacity text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                  v-tooltip="'Copy Company Slug'"
                >
                  <SvgIcon name="copy" set="line" class="w-3 h-3" />
                </button>
              </div>

              <!-- ID -->
              <div class="copy-group flex items-center gap-2">
                <span class="text-xs text-gray-400 dark:text-gray-500 font-mono">{{ m.id }}</span>
                <button
                  @click="copyToClipboard(m.id)"
                  class="copy-icon opacity-0 transition-opacity text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                  v-tooltip="'Copy Company ID'"
                >
                  <SvgIcon name="copy" set="line" class="w-3 h-3" />
                </button>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-1 ml-2">
            <!-- Role Selector -->
            <select
              :value="selectedRoles[m.id] || m.role"
              @change="onRoleChange(m.id, $event)"
              class="text-xs border border-gray-300 dark:border-gray-600 rounded px-2 py-1 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
              :disabled="updatingRole === m.id"
            >
              <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
            </select>

            <!-- Update Button -->
            <button
              @click="handleUpdateRole(m)"
              :disabled="!selectedRoles[m.id] || selectedRoles[m.id] === m.role || updatingRole === m.id"
              class="p-1 text-gray-400 hover:text-green-600 dark:hover:text-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              v-tooltip="!selectedRoles[m.id] || selectedRoles[m.id] === m.role ? 'Change user role from dropdown first' : 'Apply role change'"
            >
              <SvgIcon name="check" set="line" class="w-4 h-4" />
            </button>

            <!-- Remove Button -->
            <button
              @click="handleUnassign(m)"
              :disabled="removingMembership === m.id"
              class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              v-tooltip="'Remove user from company'"
            >
              <SvgIcon name="user-remove" set="line" class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="!loading && memberships.length === 0" class="text-center py-12">
      <div class="text-gray-400 dark:text-gray-500 text-sm">No company memberships</div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-8">
      <div class="inline-block animate-spin w-4 h-4 border-2 border-gray-300 border-t-blue-500 rounded-full"></div>
      <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Loading...</div>
    </div>
  </div>
  
  <!-- Copy styles -->
  <div v-html="copyStyles"></div>
</template>
