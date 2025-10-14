<template>
  <div class="flex items-center gap-2">
    <i class="fas fa-globe-americas text-gray-500"></i>
    <template v-if="country">
      <span class="text-lg mr-1">{{ country.flag }}</span>
      <span>{{ country.name || '-' }}</span>
    </template>
    <template v-else>
      <span>-</span>
    </template>
  </div>
</template>

<script setup lang="ts">
interface Country {
  code: string
  name: string
  flag: string
}

defineProps<{
  country?: Country | null
}>()
</script>