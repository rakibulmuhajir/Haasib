import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'

export function useSidebar(options = {}) {
  const storageKey = options.storageKey || 'ui.sidebar.expanded'
  const shortcut = options.shortcut || { key: 'b', withMetaOnMac: true }

  const expanded = ref(false)
  try { expanded.value = localStorage.getItem(storageKey) === '1' } catch {}
  watch(expanded, (v) => { try { localStorage.setItem(storageKey, v ? '1' : '0') } catch {} })

  const contentPadClass = computed(() => (expanded.value ? 'md:pl-56' : 'md:pl-16'))

  function toggleSidebar() { expanded.value = !expanded.value }

  function handleKeydown(e) {
    const isMac = navigator.platform.toUpperCase().includes('MAC')
    const ctrlOrMeta = isMac ? e.metaKey : e.ctrlKey
    if (ctrlOrMeta && (e.key === 'b' || e.key === 'B')) {
      e.preventDefault()
      toggleSidebar()
    }
  }

  onMounted(() => window.addEventListener('keydown', handleKeydown))
  onBeforeUnmount(() => window.removeEventListener('keydown', handleKeydown))

  return { expanded, contentPadClass, toggleSidebar }
}

