<script setup lang="ts">
import { computed } from 'vue'
import { Moon, Sun, Monitor } from "lucide-vue-next"
import { useColorMode } from '@vueuse/core'

const colorMode = useColorMode()

const themes = [
  { value: 'light', icon: Sun, label: 'Light' },
  { value: 'dark', icon: Moon, label: 'Dark' },
  { value: 'system', icon: Monitor, label: 'System' }
] as const

const getIcon = (theme: string) => {
  return themes.find(t => t.value === theme)?.icon || Monitor
}

const handleClick = () => {
  const currentIndex = themes.findIndex(t => t.value === colorMode.value)
  const nextIndex = (currentIndex + 1) % themes.length
  colorMode.value = themes[nextIndex].value as 'light' | 'dark' | 'system'
}

const currentTheme = computed(() => {
  return colorMode.value || 'system'
})
</script>

<template>
  <button
    @click="handleClick"
    class="inline-flex h-9 w-9 items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-border bg-background"
    :title="`Current theme: ${currentTheme}. Click to cycle through themes.`"
  >
    <component
      :is="getIcon(currentTheme)"
      class="h-4 w-4"
      :class="[
        'transition-all duration-200',
        colorMode.value === 'light' && 'text-yellow-500',
        colorMode.value === 'dark' && 'text-blue-400',
        colorMode.value === 'system' && 'text-green-500'
      ]"
    />
    <span class="sr-only">Toggle theme</span>
  </button>
</template>