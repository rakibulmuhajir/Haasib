<script setup>
import { computed, onMounted } from 'vue'
import { useTheme } from '@/composables/useTheme'
import { useSidebar } from '@/composables/useSidebar'
import ConfirmDialog from 'primevue/confirmdialog'
import Toast from 'primevue/toast'
import Topbar from './Topbar.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'

const { initializeTheme } = useTheme()
const { isSlim, initializeSidebar } = useSidebar()

const wrapperClass = computed(() => ({
  'layout-wrapper': true,
  'layout-slim': isSlim.value,
}))

// Initialize theme and sidebar state
onMounted(() => {
  initializeTheme()
  initializeSidebar()
})

// Close mobile sidebar when clicking outside
const closeMobileSidebar = () => {
  document.querySelector('.layout-wrapper')?.classList.remove('layout-mobile-active')
}
</script>

<template>
  <div :class="wrapperClass">
    <!-- Sidebar -->
    <Sidebar />
    
    <!-- Main Content Area -->
    <div class="layout-main">
      <!-- Topbar -->
      <Topbar />
      
      <!-- Page Content -->
      <div class="layout-content">
        <slot />
      </div>
      
      <!-- Footer -->
      <div class="layout-footer">
        <slot name="footer" />
      </div>
    </div>

    <!-- Mobile Overlay -->
    <div class="layout-mask" @click="closeMobileSidebar" />

    <!-- Toast container for notifications -->
    <Toast position="top-right" />
    
    <!-- Confirmation dialog container -->
    <ConfirmDialog />
  </div>
</template>

<style scoped>
.layout-wrapper {
  display: flex;
  min-height: 100vh;
  background-color: var(--surface-ground);
}

.layout-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  margin-left: 0;
  transition: none;
}

.layout-wrapper.layout-slim .layout-main { margin-left: 0; }

.layout-content {
  flex: 1;
  padding: 1.5rem;
  margin-top: 4rem; /* Account for fixed topbar */
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

@media (max-width: 991px) { }
</style>