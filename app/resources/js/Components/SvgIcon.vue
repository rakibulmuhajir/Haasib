<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  name: string
  size?: number | string
  set?: 'solid' | 'line' | 'duo' | 'gradient'
  class?: string
}>()

// Eagerly import all SVGs under resources/icons (including nested folders)
const registry = import.meta.glob('../../icons/**/*.svg', { as: 'raw', eager: true }) as Record<string, string>

function findSvg(): string | null {
  const set = props.set || 'solid'
  const candidates = [
    `../../icons/${set}/${props.name}.svg`,
    `../../icons/${props.name}.svg`,
    `../../icons/solid/${props.name}.svg`,
    `../../icons/line/${props.name}.svg`,
    `../../icons/duo/${props.name}.svg`,
    `../../icons/gradient/${props.name}.svg`,
  ]
  for (const k of candidates) {
    if (registry[k]) return registry[k]
  }
  return null
}

const html = computed(() => findSvg())
const box = computed(() => (typeof props.size === 'number' ? `${props.size}px` : props.size || '18px'))
</script>

<template>
  <span
    class="inline-flex items-center justify-center"
    :class="props.class"
    :style="{ width: box, height: box }"
    v-html="html || '<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' width=\'100%\' height=\'100%\' fill=\'currentColor\'><rect x=\'4\' y=\'4\' width=\'16\' height=\'16\' rx=\'3\' opacity=\'.4\'/><path d=\'M8 12h8v2H8z\'/></svg>'"
  />
</template>
