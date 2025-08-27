// resources/js/app.js
import './bootstrap'   // if Breeze created it; harmless if present
import { createInertiaApp } from '@inertiajs/vue3'
import { createApp, h } from 'vue'
import { ZiggyVue } from '../../vendor/tightenco/ziggy'
import '../css/app.css';

createInertiaApp({
  resolve: name => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
    return pages[`./Pages/${name}.vue`]
  },
  setup({ el, App, props, plugin }) {
    const vue = createApp({ render: () => h(App, props) })
    vue.use(plugin)
    vue.use(ZiggyVue, {
      ...props.initialPage.props.ziggy,
      location: new URL(props.initialPage.props.ziggy.location),
    })
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
