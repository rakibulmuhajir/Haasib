import '../css/app.css';
// PrimeVue v4 styled mode injects CSS from the preset JS; no direct CSS file is required
import '../css/themes/blue-whale.css';
import '../css/layout/shell.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
// Early theme bootstrap: set data-theme before Vue mounts
(() => {
  const stored = localStorage.getItem('theme') // 'blue-whale' | 'blue-whale-dark'
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
  const theme = stored || (prefersDark ? 'blue-whale-dark' : 'blue-whale')
  document.documentElement.setAttribute('data-theme', theme)
})()
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import PrimeVue from 'primevue/config'
import ToastService from 'primevue/toastservice'
// PrimeVue preset (static import for reliable styling)
// Ensure you have installed: npm i @primeuix/themes primevue
import Aura from '@primeuix/themes/aura';
// (already imported above to ensure correct order) import '@primeuix/themes/aura/theme.css'
// Optional icons (install with: npm i primeicons)
// import 'primeicons/primeicons.css'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Toolbar from 'primevue/toolbar'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Divider from 'primevue/divider'
import Toast from 'primevue/toast'
import blueWhale from './theme/bluewhale'

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const vue = createApp({ render: () => h(App, props) })
        vue.use(plugin)
        vue.use(ZiggyVue)
        vue.use(PrimeVue, {
            ripple: true,
            unstyled: false,
            theme: {
                preset: Aura,
                options: {
                    darkModeSelector: '[data-theme="blue-whale-dark"]',
                },
                extend: blueWhale,
            },
        })
        vue.use(ToastService)
        // Global PrimeVue components
        vue.component('Button', Button)
        vue.component('Card', Card)
        vue.component('Toolbar', Toolbar)
        vue.component('DataTable', DataTable)
        vue.component('Column', Column)
        vue.component('Divider', Divider)
        vue.component('Toast', Toast)
        vue.mount(el)
        return vue
    },
    progress: {
        color: '#4B5563',
    },
});
