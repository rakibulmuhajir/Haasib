<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
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
</script>

<template>
  <div class="overflow-hidden bg-white shadow sm:rounded-md">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-3">
      <div class="font-medium">Members</div>
      <div class="flex items-center gap-2">
        <TextInput :modelValue="query" @update:modelValue="$emit('update:query', $event)" placeholder="Filter by name or email…" class="w-72" />
      </div>
    </div>
    <ul role="list" class="divide-y divide-gray-200">
      <li v-for="m in members" :key="m.id + ':' + m.email" class="px-6 py-4">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-sm font-medium text-gray-900">{{ m.name }}</div>
            <div class="text-xs text-gray-500">{{ m.email }}</div>
          </div>
          <div class="flex items-center gap-2">
            <select v-model="m.role" class="rounded border-gray-300 text-sm">
              <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
            </select>
            <PrimaryButton @click="$emit('update-role', m)">Update</PrimaryButton>
            <SecondaryButton @click="$emit('unassign', m)">Remove</SecondaryButton>
          </div>
        </div>
        <Collapsible>
          <template #trigger>
            <button class="mt-2 text-xs text-indigo-600 hover:underline">More</button>
          </template>
          <div class="mt-2 rounded border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700">
            <div class="flex items-center justify-between">
              <div>
                <div><span class="text-gray-500">User ID:</span> {{ m.id }}</div>
                <div><span class="text-gray-500">Email:</span> {{ m.email }}</div>
              </div>
              <div class="flex items-center gap-2">
                <Link :href="route('admin.users.show', m.id)" class="text-indigo-600 hover:underline">Open user</Link>
                <button type="button" class="text-gray-600 hover:text-gray-900" @click="navigator.clipboard?.writeText(m.email)">Copy email</button>
              </div>
            </div>
          </div>
        </Collapsible>
      </li>
      <li v-if="!loading && members.length === 0" class="px-6 py-4 text-sm text-gray-500">No members found.</li>
      <li v-if="loading" class="px-6 py-4 text-sm text-gray-500">Loading…</li>
    </ul>
  </div>
</template>
