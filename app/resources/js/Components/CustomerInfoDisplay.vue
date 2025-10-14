<template>
  <div class="flex items-start gap-2">
    <div class="flex-shrink-0 mt-1">
      <i class="fas fa-user-circle text-gray-400 text-lg"></i>
    </div>
    <div>
      <div class="font-medium text-gray-900">
        {{ name }}
      </div>
      <div class="text-xs text-gray-500 flex items-center gap-1" v-if="email">
        <i class="fas fa-envelope"></i>
        {{ email }}
      </div>
      <div class="text-xs text-gray-500 flex items-center gap-1" v-if="phone">
        <i class="fas fa-phone"></i>
        {{ phone }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  name: string
  email?: string
  phone?: string
}>()
</script>