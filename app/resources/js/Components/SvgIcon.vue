<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  name: string
  size?: number | string
  set?: 'solid' | 'line' | 'duo' | 'gradient' | 'neon'
  monochrome?: boolean
  class?: string
}>()

// Eagerly import all SVGs under resources/icons (including nested folders)
const registry = import.meta.glob('../../icons/**/*.svg', { as: 'raw', eager: true }) as Record<string, string>

function findSvg(): string | null {
  const set = props.set || 'solid'
  // The paths need to be relative to this file for import.meta.glob to find them.
  const candidates = [
    `../../icons/${set}/${props.name}.svg`,
    `../../icons/${props.name}.svg`, // Check root of icons folder
  ]

  for (const k of candidates) {
    if (registry[k]) return registry[k]
  }

  // If not found, try to find it in any set as a fallback.
  // This is useful if you forget to specify a set but the icon exists somewhere.
  for (const key in registry) {
    // Check if the key ends with the icon name, e.g., '/line/arrow-right-circle.svg'
    // This is more specific than `includes` and avoids matching parts of a folder name.
    if (key.endsWith(`/${props.name}.svg`)) {
      return registry[key]
    }
  }

  return null
}

const html = computed(() => {
  let svg = findSvg()
  if (svg) {
    // Remove existing width/height attributes and add responsive ones.
    svg = svg.replace(/width="[^"]*"/g, '').replace(/height="[^"]*"/g, '')

    let attributes = 'width="100%" height="100%"'

    // If monochrome is requested, force fill and stroke to currentColor.
    // Otherwise, respect the colors within the SVG file.
    if (props.monochrome) {
      attributes += ' fill="currentColor" stroke="currentColor"'
    }

    svg = svg.replace('<svg', `<svg ${attributes}`)
  }
  return svg
})
</script>

<template>
  <span
    class="inline-flex items-center justify-center"
    :class="props.class"
    v-html="
      html ||
      `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='100%' height='100%' fill='currentColor'><rect x='4' y='4' width='16' height='16' rx='3' opacity='.4'/><path d='M8 12h8v2H8z'/></svg>`
    "
  />
</template>
