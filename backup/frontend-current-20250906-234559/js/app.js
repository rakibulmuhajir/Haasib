// resources/js/app.js
import './bootstrap'   // if Breeze created it; harmless if present
import { createInertiaApp } from '@inertiajs/vue3'
import { createApp, h } from 'vue'
// (pre-migration) no explicit Inertia router import
import { ZiggyVue } from '../../vendor/tightenco/ziggy'
import '../css/app.css';
import '../css/primevue-overrides.css';
import { useTheme } from './utils/theme';
// PrimeVue setup
import PrimeVue from 'primevue/config'
import Aura from '@primeuix/themes/aura'
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice';
import Tooltip from 'primevue/tooltip'

// initialize theme handling on app startup
useTheme();

createInertiaApp({
  resolve: name => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
    return pages[`./Pages/${name}.vue`]
  },
  setup({ el, App, props, plugin }) {
    const vue = createApp({ render: () => h(App, props) })
    vue.use(plugin)
    // PrimeVue plugin + services
    vue.use(PrimeVue, {
      theme: {
        preset: Aura,
      },
    })
    vue.use(ToastService)
    vue.use(ConfirmationService)
    vue.directive('tooltip', Tooltip)
    vue.use(ZiggyVue, {
      ...props.initialPage.props.ziggy,
      location: new URL(props.initialPage.props.ziggy.location),
    })

    // (pre‑migration) no special CSRF meta/header synchronization here
    vue.mount(el)
  },
})

// Axios header so API gets tenant context automatically
import axios from 'axios'
axios.defaults.withCredentials = true
axios.interceptors.request.use((config) => {
  const cid = window.localStorage.getItem('currentCompanyId')
  if (cid) config.headers['X-Company-Id'] = cid
  return config
})
