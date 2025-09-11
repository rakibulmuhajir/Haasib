<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Select from 'primevue/select'
import SvgIcon from '@/Components/SvgIcon.vue';
import { useToasts } from '@/composables/useToasts.js';
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

const { addToast } = useToasts()
const selectedRoles = ref<Record<string, string>>({})

const copyToClipboard = async (text) => {
  try {
    await navigator.clipboard.writeText(text)
    addToast('Copied to clipboard', 'success')
  } catch (err) {
    console.error('Failed to copy text: ', err)
    // Fallback for older browsers
    const textArea = document.createElement('textarea')
    textArea.value = text
    document.body.appendChild(textArea)
    textArea.select()
    document.execCommand('copy')
    document.body.removeChild(textArea)
    addToast('Copied to clipboard', 'success')
  }
}

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
          <div class="flex-1">
            <!-- Clickable user name with copy icon -->
            <div class="flex items-center gap-2">
              <Link :href="route('admin.users.show', m.id)" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ m.name }}</Link>
              <button 
                @click="copyToClipboard(m.name)"
                class="opacity-0 hover:opacity-100 transition-opacity text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                v-tooltip="'Copy User Name'"
              >
                <SvgIcon name="copy" set="line" class="w-3 h-3" />
              </button>
            </div>
            <!-- Email with copy icon -->
            <div class="flex items-center gap-1 mt-0.5 group">
              <span class="text-xs text-gray-500 dark:text-gray-400">{{ m.email }}</span>
              <button 
                @click="copyToClipboard(m.email)"
                class="opacity-0 group-hover:opacity-100 transition-opacity text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                v-tooltip="'Copy Email'"
              >
                <SvgIcon name="copy" set="line" class="w-3 h-3" />
              </button>
            </div>
            <!-- User ID with copy icon -->
            <div class="flex items-center gap-1 mt-0.5 group">
              <span class="text-xs text-gray-500 dark:text-gray-500 font-mono">ID: {{ m.id }}</span>
              <button 
                @click="copyToClipboard(m.id)"
                class="opacity-0 group-hover:opacity-100 transition-opacity text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                v-tooltip="'Copy User ID'"
              >
                <SvgIcon name="copy" set="line" class="w-3 h-3" />
              </button>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <Select :modelValue="selectedRoles[m.id] || m.role" @update:modelValue="(value) => onRoleChange(m.id, value)" :options="roleOptions" optionLabel="label" optionValue="value" class="w-32" />
            <Button @click="handleUpdateRole(m)" size="small">Update</Button>
            <Button @click="$emit('unassign', m)" size="small" severity="secondary">Remove</Button>
          </div>
        </div>
      </li>
      <li v-if="!loading && members.length === 0" class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">No members found.</li>
      <li v-if="loading" class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Loading…</li>
    </ul>
  </div>
</template>
