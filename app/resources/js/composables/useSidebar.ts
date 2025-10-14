import { ref, watch, onMounted } from 'vue'

/**
 * The key used to store the sidebar's slim state in localStorage.
 */
const STORAGE_KEY = 'sidebar.slim'

/**
 * A shared ref for the sidebar's slim state.
 * Defined outside the composable function to be a singleton.
 */
const isSlim = ref(false)

/**
 * A composable to manage the global state of the sidebar (e.g., slim mode).
 */
export function useSidebar() {
  /**
   * Initializes the sidebar state from localStorage.
   * Should be called once in the main layout component.
   */
  function initializeSidebar() {
    if (typeof window !== 'undefined') {
      isSlim.value = localStorage.getItem(STORAGE_KEY) === '1'
    }
  }

  // Watch for changes and persist to localStorage
  watch(isSlim, (value) => {
    if (typeof window !== 'undefined') localStorage.setItem(STORAGE_KEY, value ? '1' : '0')
  })

  return { isSlim, initializeSidebar }
}
