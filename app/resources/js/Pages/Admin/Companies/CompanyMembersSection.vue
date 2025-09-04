<script setup>
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
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
    <div v-if="error" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>

    <div class="overflow-hidden bg-white shadow sm:rounded-md p-6">
      <div class="font-medium mb-3">Assign Existing User</div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
        <div>
          <InputLabel value="User" />
          <UserPicker v-model="assign.email" class="mt-1 block w-full" placeholder="Find user by name or email…" />
        </div>
        <div>
          <InputLabel value="Role" />
          <select v-model="assign.role" class="mt-1 block w-full rounded border-gray-300">
            <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
          </select>
        </div>
        <div>
          <PrimaryButton @click="assignUser" :disabled="assignLoading">Assign</PrimaryButton>
          <span v-if="assignLoading" class="ms-2 text-sm text-gray-500">Assigning…</span>
        </div>
      </div>
      <div v-if="assignError" class="mt-3 rounded border border-red-200 bg-red-50 p-2 text-xs text-red-700">{{ assignError }}</div>
    </div>

    <CompanyMemberList
      :members="members"
      :loading="loading"
      :role-options="roleOptions"
      v-model:query="q"
      @update-role="updateRole"
      @unassign="unassign"
    />
  </div>
</template>
