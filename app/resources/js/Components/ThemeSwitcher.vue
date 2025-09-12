<script setup lang="ts">
import { useTheme } from '@/composables/useTheme' // Adjust the import path if needed

const { isDark, toggleTheme, initializeTheme } = useTheme()

// The theme should be initialized in your main layout component (e.g., App.vue or AuthenticatedLayout.vue)
// by calling initializeTheme() in its onMounted hook.
// If this ThemeSwitcher is the only component that needs to do it, you can call it here.
// import { onMounted } from 'vue'
// onMounted(() => {
//   initializeTheme()
// })
</script>

<template>
  <button type="button"
          @click="toggleTheme"
          class="inline-flex items-center rounded-md border px-3 py-1 text-xs"
          :class="!isDark ? 'bg-primary text-white border-primary-600' : 'bg-surface-800 text-surface-0 border-surface-700'">
    <span v-if="!isDark">Light</span>
    <span v-else>Dark</span>
  </button>
</template>
