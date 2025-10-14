<script setup lang="ts">
// PrimeVue tooltip wrapper. Keeps the same external API while switching to
// the PrimeVue tooltip directive registered globally as `v-tooltip`.
const props = defineProps({
  text: { type: String, required: true },
  side: { type: String, default: 'right' },
})

const getPosition = (side: string) => {
  const s = (side || 'right').toLowerCase()
  return ['top', 'right', 'bottom', 'left'].includes(s) ? s : 'right'
}
</script>

<template>
  <span
    class="inline-flex"
    tabindex="0"
    v-tooltip="{ value: props.text, showDelay: 0, hideDelay: 0, position: getPosition(props.side) }"
  >
    <slot />
  </span>
</template>
