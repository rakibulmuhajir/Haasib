<script setup lang="ts">
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import type { UserMembership, RoleOption } from '@/types'

defineProps<{
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

const getRoleSeverity = (role: string): 'success' | 'warning' | 'info' | 'danger' | 'secondary' => {
  switch (role) {
    case 'owner': return 'danger'
    case 'admin': return 'warning'
    case 'accountant': return 'info'
    case 'viewer': return 'secondary'
    default: return 'secondary'
  }
}

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
    
    // Clear the selected role after successful update
    delete selectedRoles.value[membership.id]
  } catch (error) {
    console.error('❌ Update failed:', error)
  } finally {
    setTimeout(() => {
      updatingRole.value = null
    }, 1000)
  }
}

const onRoleChange = (membershipId: string, newRole: string) => {
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
</script>

<template>
  <div class="space-y-3">
    <!-- Membership Cards -->
    <div v-for="m in memberships" :key="m.id" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <!-- Company Info -->
        <div class="flex items-start gap-3 flex-1">
          <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold">
            {{ m.name?.charAt(0)?.toUpperCase() }}
          </div>
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
              <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ m.name }}</h3>
              <Button 
                @click="navigator.clipboard?.writeText(m.name)"
                icon="pi pi-copy"
                size="small"
                severity="secondary"
                text
                v-tooltip.top="'Copy Company Name'"
                class="p-1"
              />
            </div>
            <div class="flex items-center gap-2 mb-1">
              <p class="text-sm text-gray-500 dark:text-gray-400">{{ m.slug }}</p>
              <Button 
                @click="navigator.clipboard?.writeText(m.slug)"
                icon="pi pi-copy"
                size="small"
                severity="secondary"
                text
                v-tooltip.top="'Copy Company Slug'"
                class="p-1"
              />
            </div>
            <div class="flex items-center gap-2">
              <p class="text-xs text-gray-400 dark:text-gray-500">ID: {{ m.id }}</p>
              <Badge :value="m.role" :severity="getRoleSeverity(m.role)" />
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2">
          <Dropdown 
            v-model="selectedRoles[m.id]" 
            :options="roleOptions" 
            optionLabel="label" 
            optionValue="value"
            :placeholder="m.role"
            class="w-32"
            @change="onRoleChange(m.id, $event.value)"
            :loading="updatingRole === m.id"
          />
          <Button 
            @click="handleUpdateRole(m)"
            :loading="updatingRole === m.id"
            :disabled="!selectedRoles[m.id] || selectedRoles[m.id] === m.role"
            icon="pi pi-check"
            size="small"
            severity="success"
            v-tooltip.top="'Update Role'"
          />
          <Button 
            @click="handleUnassign(m)"
            icon="pi pi-user-minus"
            size="small"
            severity="danger"
            v-tooltip.top="'Remove from Company'"
            :loading="removingMembership === m.id"
          />
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="!loading && memberships.length === 0" class="text-center py-8">
      <div class="text-gray-500 dark:text-gray-400 mb-2">No company memberships found</div>
      <div class="text-sm text-gray-400 dark:text-gray-500">This user is not assigned to any companies</div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-8">
      <ProgressSpinner style="width: 40px; height: 40px" strokeWidth="4" />
      <div class="mt-2 text-gray-500 dark:text-gray-400">Loading memberships...</div>
    </div>
  </div>
</template>
