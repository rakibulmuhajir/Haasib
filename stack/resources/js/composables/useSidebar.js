import { ref, onMounted, onUnmounted } from 'vue'

const isSlim = ref(true)
const isMobile = ref(false)
const isMobileMenuOpen = ref(false)

const checkMobile = () => {
  const mobile = window.innerWidth < 992
  isMobile.value = mobile
  if (!mobile) {
    isMobileMenuOpen.value = false
  }
}

const setSlim = (value = true) => {
  isSlim.value = value
}

const toggleSidebar = () => {
  if (!isMobile.value) return
  isMobileMenuOpen.value = !isMobileMenuOpen.value
}

const openMobileSidebar = () => {
  if (!isMobile.value) return
  isMobileMenuOpen.value = true
}

const closeMobileSidebar = () => {
  isMobileMenuOpen.value = false
}

const toggleMobileMenu = () => toggleSidebar()
const closeMobileMenu = () => closeMobileSidebar()

onMounted(() => {
  checkMobile()
  window.addEventListener('resize', checkMobile)
})

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile)
})

export const useSidebar = () => ({
  isSlim,
  isMobile,
  isMobileMenuOpen,
  setSlim,
  toggleSidebar,
  openMobileSidebar,
  closeMobileSidebar,
  toggleMobileMenu,
  closeMobileMenu
})
