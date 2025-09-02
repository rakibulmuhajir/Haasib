<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { computePosition, autoUpdate, offset, flip, shift } from '@floating-ui/dom'

const props = defineProps({
  text: { type: String, required: true },
  side: { type: String, default: 'right' }, // preferred placement
})

const referenceEl = ref(null)
const floatingEl = ref(null)
const open = ref(false)
let cleanup = null

function show() { open.value = true }
function hide() { open.value = false }

async function position() {
  if (!referenceEl.value || !floatingEl.value) return
  const placementMap = { right: 'right', left: 'left', top: 'top', bottom: 'bottom' }
  const placement = placementMap[props.side] || 'right'
  try {
    if (cleanup) cleanup()
    cleanup = autoUpdate(referenceEl.value, floatingEl.value, async () => {
      const { x, y } = await computePosition(referenceEl.value, floatingEl.value, {
        placement,
        strategy: 'fixed',
        middleware: [offset(8), flip(), shift({ padding: 8 })],
      })
      Object.assign(floatingEl.value.style, {
        left: `${x}px`,
        top: `${y}px`,
      })
    })
  } catch { /* ignore */ }
}

watch(open, (val) => { if (val) position() })
onMounted(() => { if (open.value) position() })
onBeforeUnmount(() => { if (cleanup) cleanup() })
</script>

<template>
  <span class="relative inline-flex" ref="referenceEl" @mouseenter="show" @mouseleave="hide" @focusin="show" @focusout="hide" tabindex="0">
    <slot />
    <span v-show="open" ref="floatingEl"
      class="pointer-events-none fixed z-50 whitespace-nowrap rounded bg-gray-900 px-2 py-1 text-xs text-white shadow"
      role="tooltip"
    >
      {{ text }}
    </span>
  </span>
</template>
