<script setup lang="ts">
defineProps<{ title?: string }>()
</script>

<template>
  <div class="sidebar-header p-4 flex items-center gap-2">
    <div class="w-8 h-8 rounded-md" style="background: var(--p-primary-500)"></div>
    <div class="text-sm font-semibold" style="color: var(--p-text-color)">{{ title || 'App' }}</div>
  </div>
</template>
