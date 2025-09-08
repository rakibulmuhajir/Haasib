<script setup>
import { ref, computed, onMounted } from 'vue'

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
})

function toggleSlim() { slim.value = !slim.value }

defineExpose({ toggleMobile, closeMobile, toggleStatic, toggleSlim })
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
