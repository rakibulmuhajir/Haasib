<script setup>
const props = defineProps({
  company: { type: Object, default: null }
})
</script>

<template>
  <div class="overflow-hidden bg-white dark:bg-gray-800 dark:border dark:border-gray-700 shadow sm:rounded-md p-6">
    <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ company?.name || '—' }}</div>
    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Slug: {{ company?.slug }}</div>
    <div class="mt-3 text-sm text-gray-700 dark:text-gray-200">
      <div><span class="text-gray-500 dark:text-gray-400">Currency:</span> {{ company?.base_currency }}</div>
      <div><span class="text-gray-500 dark:text-gray-400">Language:</span> {{ company?.language }}</div>
      <div><span class="text-gray-500 dark:text-gray-400">Locale:</span> {{ company?.locale }}</div>
    </div>
  </div>
</template>
