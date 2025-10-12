import { ref, readonly, onMounted } from 'vue'

const theme = ref('light')
const isDark = ref(false)

export function useTheme() {
  const initializeTheme = () => {
    // Check for saved theme preference or default to light
    const savedTheme = localStorage.getItem('theme') || 'light'
    theme.value = savedTheme
    isDark.value = savedTheme === 'dark'
    
    // Apply theme to document root
    document.documentElement.setAttribute('data-theme', isDark.value ? 'dark' : 'light')
    document.documentElement.classList.toggle('dark', isDark.value)
  }

  const toggleTheme = () => {
    isDark.value = !isDark.value
    theme.value = isDark.value ? 'dark' : 'light'
    
    // Update DOM
    document.documentElement.setAttribute('data-theme', theme.value)
    document.documentElement.classList.toggle('dark', isDark.value)
    
    // Save preference
    localStorage.setItem('theme', theme.value)
  }

  const setTheme = (newTheme) => {
    if (newTheme !== 'light' && newTheme !== 'dark') return
    
    theme.value = newTheme
    isDark.value = newTheme === 'dark'
    
    // Update DOM
    document.documentElement.setAttribute('data-theme', theme.value)
    document.documentElement.classList.toggle('dark', isDark.value)
    
    // Save preference
    localStorage.setItem('theme', theme.value)
  }

  // Auto-detect system preference if no saved preference
  const detectSystemTheme = () => {
    if (!localStorage.getItem('theme')) {
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
      setTheme(prefersDark ? 'dark' : 'light')
    }
  }

  onMounted(() => {
    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
      if (!localStorage.getItem('theme')) {
        setTheme(e.matches ? 'dark' : 'light')
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