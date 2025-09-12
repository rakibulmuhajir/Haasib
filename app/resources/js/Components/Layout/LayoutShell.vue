<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import CompanySwitcher from '@/Components/CompanySwitcher.vue'
import { useTheme } from '@/composables/useTheme'
import { useSidebar } from '@/composables/useSidebar'

const { initializeTheme, toggleTheme } = useTheme()
const { isSlim, initializeSidebar } = useSidebar()

const mobileActive = ref(false)
const staticInactive = ref(false)

function toggleMobile() { mobileActive.value = !mobileActive.value }
function closeMobile() { mobileActive.value = false }

const wrapperClass = computed(() => ({
  'layout-wrapper': true,
  'layout-mobile-active': mobileActive.value,
  'layout-static-inactive': staticInactive.value,
  'layout-slim': isSlim.value,
}))

onMounted(() => {
  // Initialize theme from localStorage or system preference
  initializeTheme()

  // Initialize sidebar state from localStorage
  initializeSidebar()

  // Close mobile on wide screens
  const mq = window.matchMedia('(min-width: 992px)')
  const sync = () => { if (mq.matches) mobileActive.value = false }
  try { mq.addEventListener('change', sync) } catch { mq.onchange = sync }

  // keyboard shortcuts
  const onKey = (e: KeyboardEvent) => {
    if (['INPUT','TEXTAREA'].includes((e.target as HTMLElement)?.tagName)) return
    if (e.key.toLowerCase() === 'm') {
      // mobile toggle only effective under 992px
      if (window.matchMedia('(max-width: 991px)').matches) toggleMobile()
    } else if (e.key.toLowerCase() === 's') {
      isSlim.value = !isSlim.value
    } else if (e.key.toLowerCase() === 't') {
      toggleTheme()
    }
  }
  window.addEventListener('keydown', onKey)
})

defineExpose({ toggleMobile, closeMobile, toggleSlim: () => { isSlim.value = !isSlim.value } })
</script>

<template>
  <div :class="wrapperClass">
    <!-- Sidebar Slot -->
    <slot name="sidebar" />

    <!-- Content -->
    <div class="layout-content-wrapper">
      <div class="layout-topbar p-3">
        <div class="flex items-center justify-between w-full">
          <slot name="topbar" />
          <CompanySwitcher class="ml-auto" />
        </div>
      </div>
      <div class="layout-content">
        <slot />
      </div>
      <div class="layout-footer">
        <slot name="footer" />
      </div>
    </div>

    <div class="layout-mask" @click="closeMobile" />

    <!-- Toast container for notifications -->
    <Toast position="top-right" />
  </div>
</template>
