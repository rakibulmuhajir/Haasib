import { ref, readonly, onMounted } from 'vue'

const LIGHT_THEME = 'blue-whale'
const DARK_THEME = 'blue-whale-dark'
const LEGACY_LIGHT = 'light'
const LEGACY_DARK = 'dark'

const theme = ref(LIGHT_THEME)
const isDark = ref(false)

const normalizeThemeValue = (value) => {
  if (value === DARK_THEME || value === LIGHT_THEME) {
    return value
  }

  if (value === LEGACY_DARK) {
    return DARK_THEME
  }

  if (value === LEGACY_LIGHT) {
    return LIGHT_THEME
  }

  return LIGHT_THEME
}

const applyThemeToDocument = (currentTheme) => {
  document.documentElement.setAttribute('data-theme', currentTheme)
  const darkModeActive = currentTheme === DARK_THEME
  document.documentElement.classList.toggle('dark', darkModeActive)
  isDark.value = darkModeActive
  theme.value = currentTheme
}

export function useTheme() {
  const initializeTheme = () => {
    const storedTheme = localStorage.getItem('theme')

    if (storedTheme) {
      const normalized = normalizeThemeValue(storedTheme)
      applyThemeToDocument(normalized)
      localStorage.setItem('theme', normalized)
      return
    }

    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
    applyThemeToDocument(prefersDark ? DARK_THEME : LIGHT_THEME)
  }

  const toggleTheme = () => {
    const nextTheme = isDark.value ? LIGHT_THEME : DARK_THEME
    applyThemeToDocument(nextTheme)
    localStorage.setItem('theme', nextTheme)
  }

  const setTheme = (newTheme) => {
    if (!newTheme) return
    const normalized = normalizeThemeValue(newTheme)
    applyThemeToDocument(normalized)
    localStorage.setItem('theme', normalized)
  }

  const detectSystemTheme = () => {
    if (!localStorage.getItem('theme')) {
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
      const systemTheme = prefersDark ? DARK_THEME : LIGHT_THEME
      applyThemeToDocument(systemTheme)
    }
  }

  onMounted(() => {
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
    mediaQuery.addEventListener('change', (event) => {
      if (!localStorage.getItem('theme')) {
        const systemTheme = event.matches ? DARK_THEME : LIGHT_THEME
        applyThemeToDocument(systemTheme)
      }
    })

    detectSystemTheme()
  })

  return {
    theme: readonly(theme),
    isDark: readonly(isDark),
    initializeTheme,
    toggleTheme,
    setTheme,
    detectSystemTheme
  }
}
