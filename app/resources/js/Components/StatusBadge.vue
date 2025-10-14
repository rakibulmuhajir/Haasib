<template>
  <div class="flex items-center gap-2">
    <i v-if="isActive" class="fas fa-check-circle text-green-500"></i>
    <i v-else class="fas fa-times-circle text-gray-400"></i>
    <Badge
      :value="label"
      :severity="severity"
      size="small"
    />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Badge from 'primevue/badge'

const props = defineProps<{
  isActive: boolean
  status?: string
}>()

const label = computed(() => {
  return props.isActive ? 'Active' : 'Inactive'
})

const severity = computed(() => {
  return props.isActive ? 'success' : 'secondary'
})
</script>