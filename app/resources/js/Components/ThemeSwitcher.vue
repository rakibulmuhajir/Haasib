<script setup>
import { ref, onMounted } from 'vue'

const theme = ref('blue-whale')

function applyTheme(next) {
  document.documentElement.setAttribute('data-theme', next)
  localStorage.setItem('theme', next)
}

function toggle() {
  theme.value = theme.value === 'blue-whale' ? 'blue-whale-dark' : 'blue-whale'
  applyTheme(theme.value)
}

onMounted(() => {
  const stored = localStorage.getItem('theme')
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
  theme.value = stored || (prefersDark ? 'blue-whale-dark' : 'blue-whale')
  applyTheme(theme.value)
})
</script>

<template>
  <button type="button"
          @click="toggle"
          class="inline-flex items-center rounded-md border px-3 py-1 text-xs"
          :class="theme==='blue-whale' ? 'bg-primary text-white border-primary-600' : 'bg-surface-800 text-surface-0 border-surface-700'">
    <span v-if="theme==='blue-whale'">Light</span>
    <span v-else>Dark</span>
  </button>
</template>

