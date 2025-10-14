import { ref, onMounted, watch, computed } from 'vue'

// These refs are defined outside the function, so they are shared across all components that use this composable.
const theme = ref('blue-whale')

/**
 * Applies the given theme to the document and local storage.
 * @param {string} newTheme - The theme to apply.
 */
function applyTheme(newTheme: string) {
  theme.value = newTheme
  if (typeof document !== 'undefined') {
    document.documentElement.setAttribute('data-theme', newTheme)
    localStorage.setItem('theme', newTheme)
  }
}

/**
 * A Vue composable for managing application theme.
 */
export function useTheme() {
  const isDark = computed(() => theme.value.includes('dark'))

  function toggleTheme() {
    const newTheme = isDark.value ? 'blue-whale' : 'blue-whale-dark'
    applyTheme(newTheme)
  }

  /**
   * Initializes the theme from localStorage or system preference.
   * Should be called once in your main layout component.
   */
  function initializeTheme() {
    if (typeof window !== 'undefined') {
      const stored = localStorage.getItem('theme')
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
      applyTheme(stored || (prefersDark ? 'blue-whale-dark' : 'blue-whale'))
    }
  }

  return { theme, isDark, toggleTheme, initializeTheme }
}
