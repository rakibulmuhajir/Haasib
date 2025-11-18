<script setup>
import { computed, onMounted } from 'vue'
import { useTheme } from '@/composables/useTheme'
import { useSidebar } from '@/composables/useSidebar'
import { useFlashMessages } from '@/composables/useFlashMessages'
import ConfirmDialog from 'primevue/confirmdialog'
import Toast from 'primevue/toast'
import Topbar from './Topbar.vue'
import Sidebar from '@/Layouts/Sidebar.vue'

const { initializeTheme } = useTheme()
const { isSlim } = useSidebar()
const { } = useFlashMessages() // Initialize flash message handling

const wrapperClass = computed(() => ({
  'layout-wrapper': true,
  'layout-slim': isSlim.value,
}))

// Initialize theme and sidebar state
onMounted(() => {
  initializeTheme()
})

// Close mobile sidebar when clicking outside
const closeMobileSidebar = () => {
  document.querySelector('.layout-wrapper')?.classList.remove('layout-mobile-active')
}
</script>

<template>
  <div :class="wrapperClass">
    <Topbar />

    <div class="layout-body">
      <Sidebar />

      <div class="layout-main">
        <div class="layout-content">
          <slot />
        </div>

        <div class="layout-footer">
          <slot name="footer" />
        </div>
      </div>
    </div>

    <div class="layout-mask" @click="closeMobileSidebar" />

    <Toast position="top-right" />
    <ConfirmDialog />
  </div>
</template>

<style scoped>
.layout-wrapper {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background-color: var(--surface-ground);
}

.layout-body {
  flex: 1;
  display: flex;
  gap: 0;
  padding-top: 4rem; /* height of fixed topbar */
}

.layout-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.layout-content {
  flex: 1;
  padding: 1.5rem;
}

.layout-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--p-content-border-color, var(--surface-border));
  background-color: var(--p-surface-0, var(--surface-card));
}

.layout-mask {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  z-index: 996;
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s ease;
  pointer-events: none;
}

.layout-wrapper.layout-mobile-active .layout-mask {
  opacity: 1;
  visibility: visible;
  pointer-events: auto;
}

@media (max-width: 991px) {
  .layout-body {
    flex-direction: column;
    padding-top: 4rem;
  }

  .layout-main {
    width: 100%;
  }
}
</style>
