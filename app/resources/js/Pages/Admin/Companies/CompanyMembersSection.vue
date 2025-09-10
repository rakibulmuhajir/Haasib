<script setup>
import Button from 'primevue/button'
import Card from 'primevue/card'
import Message from 'primevue/message'
import UserPicker from '@/Components/Pickers/UserPicker.vue'
import CompanyMemberList from '@/Components/CompanyMemberList.vue'
import { onMounted, computed } from 'vue'
import { useCompanyMembers } from '@/composables/useCompanyMembers.js'

const props = defineProps({
  company: { type: String, required: true }
})

const {
  members,
  loading,
  error,
  q,
  roleOptions,
  assign,
  assignLoading,
  assignError,
  loadMembers,
  assignUser,
  updateRole,
  unassign,
} = useCompanyMembers(computed(() => props.company))

onMounted(loadMembers)
</script>

<template>
  <div class="space-y-4">
    <div v-if="error" class="rounded border border-red-200 dark:border-red-700 bg-red-50 dark:bg-red-900/30 p-3 text-sm text-red-700 dark:text-red-300">{{ error }}</div>

    <div class="overflow-hidden bg-white dark:bg-gray-800 dark:border dark:border-gray-700 shadow sm:rounded-md p-6">
      <div class="font-medium mb-3">Assign Existing User</div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
        <div>
          <label class="block text-sm font-medium mb-2">User</label>
          <UserPicker v-model="assign.email" class="mt-1 block w-full" placeholder="Find user by name or email…" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-2">Role</label>
          <select v-model="assign.role" class="mt-1 block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
            <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
          </select>
        </div>
        <div>
          <Button @click="assignUser" :disabled="assignLoading" :loading="assignLoading" label="Assign" />
          <span v-if="assignLoading" class="ms-2 text-sm text-gray-500">Assigning…</span>
        </div>
      </div>
      <div v-if="assignError" class="mt-3 rounded border border-red-200 dark:border-red-700 bg-red-50 dark:bg-red-900/30 p-2 text-xs text-red-700 dark:text-red-300">{{ assignError }}</div>
    </div>

    <CompanyMemberList
      :members="members"
      :loading="loading"
      :role-options="roleOptions"
      v-model:query="q"
      @update-role="(member) => { 
    console.log('🎯 update-role event RECEIVED - CompanyMembersSection.vue')
    console.log('Received member data:', member)
    console.log('About to call updateRole function...')
    updateRole(member)
    console.log('✅ updateRole function called')
  }"
      @unassign="unassign"
    />
  </div>
</template>
