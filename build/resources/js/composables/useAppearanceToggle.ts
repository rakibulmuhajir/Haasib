import { useAppearance } from '@/composables/useAppearance'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

/**
 * The appearance control's state, without its chrome.
 *
 * `useAppearance` stores the preference; it does not know what "system"
 * currently resolves to, so anything drawing a sun or a moon has to watch the
 * media query itself. The sidebar did that inline. Rather than have the header
 * repeat it, the watching lives here and each shell renders whatever it likes
 * over the same three values.
 */
export function useAppearanceToggle() {
    const { appearance, updateAppearance } = useAppearance()
    const systemPrefersDark = ref(false)
    const removeMediaListener = ref<(() => void) | null>(null)

    onMounted(() => {
        if (typeof window === 'undefined') return
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
        systemPrefersDark.value = mediaQuery.matches

        const handleChange = (event: MediaQueryListEvent) => {
            systemPrefersDark.value = event.matches
        }

        mediaQuery.addEventListener('change', handleChange)
        removeMediaListener.value = () => mediaQuery.removeEventListener('change', handleChange)
    })

    onBeforeUnmount(() => {
        removeMediaListener.value?.()
    })

    const isDark = computed(
        () =>
            appearance.value === 'dark' ||
            (appearance.value === 'system' && systemPrefersDark.value),
    )

    // The label is the non-colour indicator: "System: Dark" says both what the
    // setting is and what it currently resolves to, which an icon alone cannot.
    const appearanceLabel = computed(() => {
        if (appearance.value === 'system') {
            return systemPrefersDark.value ? 'System: Dark' : 'System: Light'
        }

        return appearance.value === 'dark' ? 'Dark mode' : 'Light mode'
    })

    const toggleAppearance = () => updateAppearance(isDark.value ? 'light' : 'dark')
    const setSystem = () => updateAppearance('system')

    return { appearance, isDark, appearanceLabel, toggleAppearance, setSystem }
}
