import '../css/app.css'

import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import type { DefineComponent } from 'vue'
import { createApp, defineComponent, Fragment, h, onBeforeUnmount, onMounted, ref } from 'vue'
import { initializeTheme } from './composables/useAppearance'
import CommandPalette from './components/palette/CommandPalette.vue'

const appName = import.meta.env.VITE_APP_NAME || 'Laravel'

createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  resolve: (name) =>
    resolvePageComponent(
      `./pages/${name}.vue`,
      import.meta.glob<DefineComponent>('./pages/**/*.vue'),
    ),
  setup({ el, App, props, plugin }) {
    // Root component that wraps the app and adds the command palette
    const Root = defineComponent({
      name: 'AppRoot',
      setup() {
        const paletteVisible = ref(false)

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
