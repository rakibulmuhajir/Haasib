<script setup>
import Button from 'primevue/button'
import { Link } from '@inertiajs/vue3'
import Menu from 'primevue/menu'
import { ref, computed, onMounted, watch, nextTick, onBeforeUnmount } from 'vue'
import { usePageActions, resolveDisabled } from '@/composables/usePageActions'

const props = defineProps({
  maxVisible: {
    type: Number,
    default: 3
  }
})
const { actions } = usePageActions()

// Debug actions when they change
watch(actions, (newActions) => {
  console.log('🔗 [DEBUG] PageActions actions updated:', newActions.map(a => ({
    label: a.label,
    disabled: typeof a.disabled === 'function' ? a.disabled() : a.disabled,
    hasClick: !!a.click
  })))
}, { deep: true })

function onClick(a) {
  console.log('🔗 [DEBUG] PageActions onClick called for action:', a.label)
  console.log('🔗 [DEBUG] Action has click function:', !!a.click)
  if (a.click) {
    console.log('🔗 [DEBUG] Executing click function')
    a.click()
  }
}

// Auto-fit with ResizeObserver
const container = ref(null)
const btnRefs = ref([])
const moreBtnMeasure = ref(null)
const visibleCount = ref(props.maxVisible)

function setBtnRef(el, idx) {
  if (!el) return
  // PrimeVue Button is a component; use its root element
  const node = el.$el ? el.$el : (el)
  btnRefs.value[idx] = node
}

function computeVisible() {
  // Viewport-based heuristic ensures predictable behavior across layouts
  const w = window.innerWidth || document.documentElement.clientWidth || 1024
  let maxByBreakpoint = 3
  if (w >= 1536) maxByBreakpoint = 6
  else if (w >= 1280) maxByBreakpoint = 5
  else if (w >= 1024) maxByBreakpoint = 4
  else if (w >= 768) maxByBreakpoint = 3
  else if (w >= 640) maxByBreakpoint = 2
  else maxByBreakpoint = 1

  visibleCount.value = Math.min(actions.value.length, maxByBreakpoint)
}

let ro = null
onMounted(async () => {
  await nextTick()
  computeVisible()
  if ('ResizeObserver' in window) {
    ro = new ResizeObserver(() => computeVisible())
    if (container.value) ro.observe(container.value)
  } else {
    window.addEventListener('resize', computeVisible)
  }
})

onBeforeUnmount(() => {
  if (ro) ro.disconnect()
  else window.removeEventListener('resize', computeVisible)
})

watch(actions, async () => {
  await nextTick()
  computeVisible()
})

const visibleActions = computed(() => actions.value.slice(0, visibleCount.value))
const overflowActions = computed(() => actions.value.slice(visibleCount.value))

const moreMenu = ref()
const moreItems = computed(() => overflowActions.value.map(a => ({
  label: a.label,
  icon: a.icon,
  disabled: resolveDisabled(a),
  command: () => onClick(a)
})))

function toggleMore(e) {
  moreMenu.value?.toggle(e)
}
</script>

<template>
  <div v-if="actions.length" class="page-actions" ref="container">
    <template v-for="(a, idx) in visibleActions" :key="a.key || a.label + '-' + idx">
      <component :is="a.href || a.routeName ? Link : 'div'" :href="a.href" :data-route-name="a.routeName">
        <Button
          :label="a.label"
          :icon="a.icon"
          :severity="a.severity || 'secondary'"
          :outlined="a.outlined === true"
          :text="a.text === true"
          size="small"
          class="page-action-btn"
          :disabled="resolveDisabled(a)"
          v-tooltip.bottom="a.tooltip || ''"
          @click="a.click ? onClick(a) : null"
          @click.capture="() => console.log('🔗 [DEBUG] Button clicked:', a.label, 'hasClick:', !!a.click)"
          :ref="(el) => setBtnRef(el, idx)"
        />
      </component>
    </template>
    <template v-if="overflowActions.length">
      <Button label="More" icon="pi pi-ellipsis-h" size="small" outlined class="page-action-btn" @click="toggleMore" aria-haspopup="true" aria-controls="more_menu" />
      <Menu ref="moreMenu" id="more_menu" :model="moreItems" :popup="true" />
    </template>
    <!-- hidden measurer for More button width -->
    <div style="position:absolute; left:-9999px; visibility:hidden;">
      <Button ref="moreBtnMeasure" label="More" icon="pi pi-ellipsis-h" size="small" outlined />
    </div>
  </div>
  <div v-else class="page-actions--empty" />
  <!-- Empty placeholder keeps layout stable when no actions -->
</template>

<style scoped>
.page-actions { display: flex; align-items: center; gap: .5rem; flex-wrap: nowrap; min-width: 0; }
.page-action-btn { white-space: nowrap; }
</style>