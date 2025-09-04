<script setup>
const props = defineProps({
  company: { type: Object, default: null }
})
</script>

<template>
  <div class="overflow-hidden bg-white shadow sm:rounded-md p-6">
    <div class="text-lg font-semibold">{{ company?.name || '—' }}</div>
    <div class="text-xs text-gray-500 mt-1">Slug: {{ company?.slug }}</div>
    <div class="mt-3 text-sm text-gray-700">
      <div><span class="text-gray-500">Currency:</span> {{ company?.base_currency }}</div>
      <div><span class="text-gray-500">Language:</span> {{ company?.language }}</div>
      <div><span class="text-gray-500">Locale:</span> {{ company?.locale }}</div>
    </div>
  </div>
</template>
