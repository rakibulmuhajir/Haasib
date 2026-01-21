<script setup lang="ts">
import { computed, getCurrentInstance } from 'vue'

const props = withDefaults(defineProps<{
  percent: number
  size?: number
  label?: string
}>(), {
  size: 48,
  label: 'Tank level',
})

const clamped = computed(() => {
  const value = Number(props.percent ?? 0)
  if (!Number.isFinite(value)) return 0
  return Math.min(100, Math.max(0, value))
})

const uid = getCurrentInstance()?.uid ?? 0
const gradientId = `tank-gradient-${uid}`

const palette = computed(() => {
  if (clamped.value >= 70) {
    return { top: '#22c55e', bottom: '#86efac' }
  }
  if (clamped.value >= 35) {
    return { top: '#f59e0b', bottom: '#fcd34d' }
  }
  return { top: '#ef4444', bottom: '#fca5a5' }
})

const fillHeight = computed(() => Math.round((clamped.value / 100) * 100))
const fillY = computed(() => 10 + (100 - fillHeight.value))
const height = computed(() => Math.round(props.size * 1.6))
</script>

<template>
  <svg
    :width="size"
    :height="height"
    viewBox="0 0 80 120"
    role="img"
    :aria-label="label"
    class="text-slate-300 dark:text-slate-600"
  >
    <defs>
      <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" :stop-color="palette.top" />
        <stop offset="100%" :stop-color="palette.bottom" />
      </linearGradient>
    </defs>

    <rect x="18" y="10" width="44" height="100" rx="12" fill="#f8fafc" class="dark:fill-slate-900" />
    <rect x="22" :y="fillY" width="36" :height="fillHeight" rx="10" :fill="`url(#${gradientId})`" />
    <rect x="18" y="10" width="44" height="100" rx="12" fill="none" stroke="currentColor" stroke-width="2" />
  </svg>
</template>
