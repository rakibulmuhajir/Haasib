import { ref, computed, onMounted, onUnmounted } from 'vue'

const isSlim = ref(false)
const isMobile = ref(false)

export function useSidebar() {
  const initializeSidebar = () => {
    // Check for saved sidebar state or default to expanded
    const savedState = localStorage.getItem('sidebar-slim')
    if (savedState !== null) {
      isSlim.value = savedState === 'true'
    } else {
      // Default to expanded on desktop, slim on mobile
      isSlim.value = window.innerWidth < 768
    }
    
    // Check mobile state
    updateMobileState()
    
    // Apply initial state
    applySidebarState()
  }

  const updateMobileState = () => {
    isMobile.value = window.innerWidth < 1024
    
    // Auto-adjust for mobile
    if (isMobile.value && !isSlim.value) {
      // Don't auto-change on mobile, let user control
    }
  }

  const applySidebarState = () => {
    const wrapper = document.querySelector('.layout-wrapper')
    if (wrapper) {
      if (isMobile.value) {
        // On mobile, sidebar is hidden by default
        wrapper.classList.remove('layout-sidebar-expanded')
      } else {
        // On desktop, apply slim state
        if (isSlim.value) {
          wrapper.classList.add('layout-sidebar-slim')
        } else {
          wrapper.classList.remove('layout-sidebar-slim')
        }
        wrapper.classList.add('layout-sidebar-expanded')
      }
    }
  }

  const toggleSidebar = () => {
    if (isMobile.value) {
      // On mobile, toggle visibility
      const wrapper = document.querySelector('.layout-wrapper')
      wrapper?.classList.toggle('layout-mobile-active')
    } else {
      // On desktop, toggle slim mode
      isSlim.value = !isSlim.value
      localStorage.setItem('sidebar-slim', isSlim.value.toString())
      applySidebarState()
    }
  }

  const setSlim = (slim) => {
    if (typeof slim === 'boolean') {
      isSlim.value = slim
      localStorage.setItem('sidebar-slim', slim.toString())
      applySidebarState()
    }
  }

  const expandSidebar = () => {
    if (isMobile.value) {
      const wrapper = document.querySelector('.layout-wrapper')
      wrapper?.classList.add('layout-mobile-active')
    } else {
      setSlim(false)
    }
  }

  const collapseSidebar = () => {
    if (isMobile.value) {
      const wrapper = document.querySelector('.layout-wrapper')
      wrapper?.classList.remove('layout-mobile-active')
    } else {
      setSlim(true)
    }
  }

  const closeMobileSidebar = () => {
    if (isMobile.value) {
      const wrapper = document.querySelector('.layout-wrapper')
      wrapper?.classList.remove('layout-mobile-active')
    }
  }

  // Handle window resize
  const handleResize = () => {
    const wasMobile = isMobile.value
    updateMobileState()
    
    // Transition between mobile and desktop
    if (wasMobile !== isMobile.value) {
      if (isMobile.value) {
        // Switched to mobile
        closeMobileSidebar()
      } else {
        // Switched to desktop
        applySidebarState()
      }
    }
  }

  onMounted(() => {
    window.addEventListener('resize', handleResize)
    handleResize() // Initial check
  })

  onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
  })

  return {
    isSlim: computed(() => isSlim.value),
    isMobile: computed(() => isMobile.value),
    initializeSidebar,
    toggleSidebar,
    setSlim,
    expandSidebar,
    collapseSidebar,
    closeMobileSidebar
  }
}