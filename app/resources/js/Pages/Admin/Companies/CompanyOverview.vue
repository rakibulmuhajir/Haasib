<script setup lang="ts">
interface Company {
  id: string
  name: string
  // Add other properties as needed
}

const props = defineProps<{
  company: Company | null
}>()
</script>

<template>
  <div class="overflow-hidden bg-white dark:bg-gray-800 dark:border dark:border-gray-700 shadow sm:rounded-md p-6">
    <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ company?.name || '—' }}</div>
    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Slug: {{ company?.slug }}</div>
    <div class="mt-3 text-sm text-gray-700 dark:text-gray-200">
      <div><span class="text-gray-500 dark:text-gray-400">Currency:</span> {{ company?.base_currency }}</div>
      <div><span class="text-gray-500 dark:text-gray-400">Language:</span> {{ company?.language }}</div>
      <div><span class="text-gray-500 dark:text-gray-400">Locale:</span> {{ company?.locale }}</div>
      <div v-if="company?.created_by">
        <span class="text-gray-500 dark:text-gray-400">Created by:</span> 
        <span>{{ company.created_by.name }} ({{ company.created_by.email }})</span>
      </div>
      <div v-if="company?.created_at">
        <span class="text-gray-500 dark:text-gray-400">Created:</span> 
        <span>{{ new Date(company.created_at).toLocaleDateString() }}</span>
      </div>
      <div v-if="!company?.is_active" class="text-red-600 dark:text-red-400">
        <span class="text-gray-500 dark:text-gray-400">Status:</span> 
        <span>Inactive</span>
      </div>
    </div>
  </div>
</template>
