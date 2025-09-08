<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'

const mobileActive = ref(false)
const staticInactive = ref(false)
const slim = ref(false)

function toggleMobile() { mobileActive.value = !mobileActive.value }
function closeMobile() { mobileActive.value = false }
function toggleStatic() { staticInactive.value = !staticInactive.value }

const wrapperClass = computed(() => ({
  'layout-wrapper': true,
  'layout-mobile-active': mobileActive.value,
  'layout-static-inactive': staticInactive.value,
  'layout-slim': slim.value,
}))

onMounted(() => {
  // Close mobile on wide screens
  const mq = window.matchMedia('(min-width: 992px)')
  const sync = () => { if (mq.matches) mobileActive.value = false }
  try { mq.addEventListener('change', sync) } catch { mq.onchange = sync }

  // restore slim preference
  const pref = localStorage.getItem('sidebar.slim')
  if (pref === '1') slim.value = true

  // keyboard shortcuts
  const onKey = (e: KeyboardEvent) => {
    if (['INPUT','TEXTAREA'].includes((e.target as HTMLElement)?.tagName)) return
    if (e.key.toLowerCase() === 'm') {
      // mobile toggle only effective under 992px
      if (window.matchMedia('(max-width: 991px)').matches) toggleMobile()
    } else if (e.key.toLowerCase() === 's') {
      toggleSlim()
    } else if (e.key.toLowerCase() === 't') {
      const cur = document.documentElement.getAttribute('data-theme') || 'blue-whale'
      const next = cur === 'blue-whale-dark' ? 'blue-whale' : 'blue-whale-dark'
      document.documentElement.setAttribute('data-theme', next)
      try { localStorage.setItem('theme', next) } catch {}
    }
  }
  window.addEventListener('keydown', onKey)
})

function toggleSlim() { slim.value = !slim.value }

defineExpose({ toggleMobile, closeMobile, toggleStatic, toggleSlim })

watch(slim, v => {
  try { localStorage.setItem('sidebar.slim', v ? '1' : '0') } catch {}
})
</script>

<template>
  <div :class="wrapperClass">
    <!-- Sidebar Slot -->
    <slot name="sidebar" />

    <!-- Content -->
    <div class="layout-content-wrapper">
      <div class="layout-content-wrapper-inside">
        <div class="layout-topbar p-3">
          <slot name="topbar" />
        </div>
        <div class="layout-content">
          <slot />
        </div>
        <div class="layout-footer">
          <slot name="footer" />
        </div>
      </div>
    </div>

    <div class="layout-mask" @click="closeMobile" />
  </div>
</template>
