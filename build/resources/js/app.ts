import '../css/app.css'

import { createInertiaApp } from '@inertiajs/vue3'
import type { DefineComponent } from 'vue'
import { createApp, defineComponent, Fragment, h, onBeforeUnmount, onMounted, ref } from 'vue'
import { initializeTheme } from './composables/useAppearance'
import { useFlashMessages } from './composables/useFlashMessages'
import CommandPalette from './components/palette/CommandPalette.vue'

const appName = import.meta.env.VITE_APP_NAME || 'Laravel'

const localPages = import.meta.glob<DefineComponent>('./pages/**/*.vue')
const modulePages = import.meta.glob<DefineComponent>('../../modules/**/Resources/js/pages/**/*.vue')

const resolvePage = async (name: string) => {
  const normalized = name.startsWith('/') ? name.slice(1) : name
  const local = localPages[`./pages/${normalized}.vue`]
  if (local) return (await local()).default

  // Build candidate paths: exact, accounting/ stripped, and the leaf folder (accounts -> accounts/Index, etc)
  const stripped = normalized.replace(/^accounting\//, '')
  const candidates = new Set<string>([normalized, stripped])
  const parts = stripped.split('/')
  if (parts.length > 1) {
    const folder = parts[parts.length - 2]
    const file = parts[parts.length - 1]
    if (file === 'Index') {
      candidates.add(`${folder}/Index`)
    }
  }

  // First try glob matches to avoid failed network requests
  for (const candidate of candidates) {
    const moduleMatch = Object.keys(modulePages).find((key) => key.endsWith(`/${candidate}.vue`))
    if (moduleMatch) return (await modulePages[moduleMatch]()).default
  }

  // Fallback direct imports into the Accounting module path (stripped only) to avoid glob misses.
  for (const candidate of candidates) {
    const directCandidate = candidate.replace(/^accounting\//, '')
    if (!directCandidate) continue
    try {
      const direct = await import(
        /* @vite-ignore */ `../../modules/Accounting/Resources/js/pages/${directCandidate}.vue`
      )
      return direct.default
    } catch (_e) {
      // ignore and continue
    }
  }

  throw new Error(`Page not found: ${name}`)
}

createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  resolve: resolvePage,
  setup({ el, App, props, plugin }) {
    // Root component that wraps the app and adds the command palette
    const Root = defineComponent({
      name: 'AppRoot',
      setup() {
        const paletteVisible = ref(false)

        // Initialize global flash message handler
        useFlashMessages()

        function togglePalette() {
          paletteVisible.value = !paletteVisible.value
        }

        function closePalette() {
          paletteVisible.value = false
        }

        function handleKeydown(e: KeyboardEvent) {
          // Cmd+K or Ctrl+K to toggle palette
          if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault()
            togglePalette()
            return
          }

          // Escape to close (backup, palette handles this internally too)
          if (e.key === 'Escape' && paletteVisible.value) {
            closePalette()
          }
        }

        onMounted(() => {
          document.addEventListener('keydown', handleKeydown)
        })

        onBeforeUnmount(() => {
          document.removeEventListener('keydown', handleKeydown)
        })

        return () =>
          h(Fragment, [
            h(App, props),
            h(CommandPalette, {
              visible: paletteVisible.value,
              'onUpdate:visible': (v: boolean) => {
                paletteVisible.value = v
              },
            }),
          ])
      },
    })

    const app = createApp(Root)
    app.use(plugin)
    app.mount(el)
  },
  progress: {
    color: '#22d3ee',
  },
})

// Initialize theme (light/dark mode)
initializeTheme()
