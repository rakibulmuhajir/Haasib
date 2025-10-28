<script setup>
import { computed } from 'vue'

const props = defineProps({
  name: {
    type: String,
    required: true
  },
  size: {
    type: [String, Number],
    default: '1em'
  },
  color: {
    type: String,
    default: 'currentColor'
  }
})

const iconMap = {
  dashboard: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="3" width="18" height="18" rx="5" fill="currentColor"/><path d="M12 7v5l3 3" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  sales: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="5" width="18" height="14" rx="4" fill="currentColor"/><path d="M7 9h10M7 13h6" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>',
  banking: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 3L3 8v2h18V8l-9-5z" fill="currentColor"/><path d="M5 11h14v8H5z" fill="currentColor" opacity="0.8"/></svg>',
  accounting: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="3" width="16" height="18" rx="2" fill="currentColor"/><path d="M8 7h8M8 11h6M8 15h4" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>',
  close: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="4" width="18" height="17" rx="4" fill="currentColor"/><path d="M8 10h8M8 14h8" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>',
  reporting: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="3" width="18" height="18" rx="4" fill="currentColor"/><path d="M8 16V11M12 16V8M16 16v-4" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>',
  organization: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="7" width="18" height="14" rx="4" fill="currentColor"/><path d="M8 7V5a4 4 0 018 0v2" stroke="white" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="12" r="2.5" fill="white"/></svg>',
  settings: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" fill="currentColor" opacity="0.85"/><circle cx="12" cy="12" r="4" fill="white"/></svg>'
}

const iconMarkup = computed(() => iconMap[props.name] || iconMap.dashboard)

const sizeStyle = computed(() => {
  const value = typeof props.size === 'number' ? `${props.size}px` : props.size
  return {
    width: value,
    height: value,
    color: props.color
  }
})
</script>

<template>
  <span class="svg-icon" :style="sizeStyle" v-html="iconMarkup" />
</template>

<style scoped>
.svg-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: inherit;
}

.svg-icon :deep(svg) {
  width: 100%;
  height: 100%;
  color: inherit;
}

.svg-icon :deep(*) {
  color: inherit;
  fill: currentColor;
  stroke: currentColor;
}
</style>
