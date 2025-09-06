import { ref, watch, onMounted, onUnmounted } from 'vue'

/**
 * Manages tab state, persisting it to localStorage and the URL hash.
 * @param {string[]} tabNames - An array of tab names.
 * @param {import('vue').Ref<string>} storageKey - A reactive ref for the localStorage key.
 * @returns {{selectedTab: import('vue').Ref<number>}}
 */
export function usePersistentTabs(tabNames, storageKey) {
  const selectedTab = ref(0)

  function applyTabFromHash() {
    const params = new URLSearchParams(window.location.hash.substring(1))
    const name = params.get('tab')
    if (name) {
      const idx = Math.max(0, tabNames.indexOf(name))
      if (selectedTab.value !== idx) {
        selectedTab.value = idx
      }
      return true // Found tab in hash
    }
    return false // No tab in hash
  }

  function applyTabFromStorage() {
    if (!storageKey.value) return
    try {
      const name = localStorage.getItem(storageKey.value)
      if (name) {
        const idx = Math.max(0, tabNames.indexOf(name))
        if (selectedTab.value !== idx) {
          selectedTab.value = idx
        }
      }
    } catch (e) {
      console.error('Failed to read from localStorage', e)
    }
  }

  function initializeTab() {
    // Hash takes precedence over local storage
    if (!applyTabFromHash()) {
      applyTabFromStorage()
    }
  }

  watch(selectedTab, (i) => {
    const name = tabNames[i] || tabNames[0]

    if (storageKey.value) {
      try { localStorage.setItem(storageKey.value, name) } catch (e) { console.error('Failed to write to localStorage', e) }
    }

    const params = new URLSearchParams(window.location.hash.substring(1))
    params.set('tab', name)
    const nextHash = '#' + params.toString()

    if (window.location.hash !== nextHash) {
      history.replaceState(null, '', window.location.pathname + window.location.search + nextHash)
    }
  })

  watch(storageKey, initializeTab, { immediate: true })

  onMounted(() => window.addEventListener('hashchange', applyTabFromHash))
  onUnmounted(() => window.removeEventListener('hashchange', applyTabFromHash))

  return { selectedTab }
}

