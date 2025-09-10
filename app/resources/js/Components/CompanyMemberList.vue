<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Collapsible from '@/Components/Collapsible.vue';
import type { CompanyMember, RoleOption } from '@/types'

defineProps<{
  members: CompanyMember[],
  loading?: boolean,
  query?: string,
  roleOptions: RoleOption[],
}>()

defineEmits<{
  (e: 'update:query', value: string): void,
  (e: 'update-role', member: CompanyMember): void,
  (e: 'unassign', member: CompanyMember): void,
}>()

const selectedRoles = ref<Record<string, string>>({})

const handleUpdateRole = (member: CompanyMember) => {
  console.log('🔥 UPDATE BUTTON CLICKED - CompanyMemberList.vue')
  console.log('Member data:', member)
  const newRole = selectedRoles.value[member.id] || member.role
  console.log('Selected role for member:', member.id, '->', newRole)
  console.log('Emitting update-role event with:', { ...member, role: newRole })
  $emit('update-role', { ...member, role: newRole })
  console.log('✅ Event emitted successfully')
}

const onRoleChange = (memberId: string, newRole: string) => {
  selectedRoles.value[memberId] = newRole
}
</script>

<template>
  <div class="overflow-hidden bg-white dark:bg-gray-800 shadow sm:rounded-md">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-3">
      <div class="font-medium text-gray-900 dark:text-gray-100">Members</div>
      <div class="flex items-center gap-2">
        <InputText :modelValue="query" @update:modelValue="$emit('update:query', $event)" placeholder="Filter by name or email…" class="w-72" />
      </div>
    </div>
    <ul role="list" class="divide-y divide-gray-200 dark:divide-gray-700">
      <li v-for="m in members" :key="m.id + ':' + m.email" class="px-6 py-4">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ m.name }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">{{ m.email }}</div>
          </div>
          <div class="flex items-center gap-2">
            <Select :modelValue="selectedRoles[m.id] || m.role" @update:modelValue="(value) => onRoleChange(m.id, value)" :options="roleOptions" optionLabel="label" optionValue="value" class="w-32" />
            <Button @click="handleUpdateRole(m)" size="small">Update</Button>
            <Button @click="$emit('unassign', m)" size="small" severity="secondary">Remove</Button>
          </div>
        </div>
        <Collapsible>
          <template #trigger>
            <button class="mt-2 text-xs text-indigo-600 hover:underline">More</button>
          </template>
          <div class="mt-2 rounded border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 p-3 text-xs text-gray-700 dark:text-gray-400">
            <div class="flex items-center justify-between">
              <div>
                <div><span class="text-gray-500 dark:text-gray-500">User ID:</span> {{ m.id }}</div>
                <div><span class="text-gray-500 dark:text-gray-500">Email:</span> {{ m.email }}</div>
              </div>
              <div class="flex items-center gap-2">
                <Link :href="route('admin.users.show', m.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">Open user</Link>
                <button type="button" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100" @click="navigator.clipboard?.writeText(m.email)">Copy email</button>
              </div>
            </div>
          </div>
        </Collapsible>
      </li>
      <li v-if="!loading && members.length === 0" class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">No members found.</li>
      <li v-if="loading" class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Loading…</li>
    </ul>
  </div>
</template>
