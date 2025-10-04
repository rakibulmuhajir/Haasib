import '../css/app.css';
import '../css/layout/shell.css';
import '../css/themes/blue-whale.css';
import './bootstrap';

import { createInertiaApp, Link } from '@inertiajs/vue3';
import { createI18nInstance } from './services/i18n';
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
import ConfirmationService from 'primevue/confirmationservice'
import Tooltip from 'primevue/tooltip'
// PrimeVue preset (static import for reliable styling)
// Ensure you have installed: npm i @primeuix/themes primevue
import blueWhale from './theme/bluewhale';
// PrimeVue v4 styled mode injects CSS from the preset JS; no direct CSS file is required
// FontAwesome icons
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { 
  faPlus, 
  faTrash, 
  faRotate, 
  faArrowsRotate, 
  faPenToSquare, 
  faGear, 
  faTriangleExclamation,
  faCheck,
  faXmark,
  faFloppyDisk,
  faPaperPlane,
  faFilePdf,
  faEye,
  faPencil,
  faBan,
  faArrowLeft,
  faUserPlus,
  faUsers,
  faBuilding,
  faTruck,
  faMagnifyingGlass,
  faGlobe,
  faMoneyBill,
  faCoins,
  faCalendar,
  faEnvelope,
  faCopy,
  faDownload,
  faBell,
  faArrowUpRightFromSquare,
  faArrowRotateLeft,
  faLink,
  faUser,
  faDollarSign,
  faFile,
  faMinus,
  faHistory
} from '@fortawesome/free-solid-svg-icons'

// Add icons to library
library.add(
  faPlus,
  faTrash,
  faRotate,
  faArrowsRotate,
  faPenToSquare,
  faGear,
  faTriangleExclamation,
  faCheck,
  faXmark,
  faFloppyDisk,
  faPaperPlane,
  faFilePdf,
  faEye,
  faPencil,
  faBan,
  faArrowLeft,
  faUserPlus,
  faUsers,
  faBuilding,
  faTruck,
  faMagnifyingGlass,
  faGlobe,
  faMoneyBill,
  faCoins,
  faCalendar,
  faEnvelope,
  faCopy,
  faDownload,
  faBell,
  faArrowUpRightFromSquare,
  faArrowRotateLeft,
  faLink,
  faUser,
  faDollarSign,
  faFile,
  faMinus,
  faHistory
)
import Button from 'primevue/button'
import Card from 'primevue/card'
import Toolbar from 'primevue/toolbar'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Divider from 'primevue/divider'
import Toast from 'primevue/toast'
import Badge from 'primevue/badge'
import Dropdown from 'primevue/dropdown'
import ProgressSpinner from 'primevue/progressspinner'
import Breadcrumb from 'primevue/breadcrumb'
import Menu from 'primevue/menu'
import Avatar from 'primevue/avatar'
import InputText from 'primevue/inputtext'
import Dialog from 'primevue/dialog'
import ConfirmDialog from 'primevue/confirmdialog'

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    async setup({ el, App, props, plugin }) {
        const vue = createApp({ render: () => h(App, props) })
        const pageProps = props.initialPage?.props ?? {}
        const initialLocale = pageProps.userLocale ?? pageProps.tenantLocale ?? pageProps.appLocale ?? undefined
        const i18n = await createI18nInstance(initialLocale)
        vue.use(plugin)
        vue.use(ZiggyVue)
        vue.use(i18n)
        vue.use(PrimeVue, {
            ripple: true,
            unstyled: false,
            theme: {
                preset: blueWhale,
                options: {
                    prefix: 'p',
                    darkModeSelector: '[data-theme="blue-whale-dark"]',
                },
            },
        })
        vue.use(ToastService)
        vue.use(ConfirmationService)
        vue.directive('tooltip', Tooltip)
        // Global components
        vue.component('Link', Link)
        // Global PrimeVue components
        vue.component('Button', Button)
        vue.component('Card', Card)
        vue.component('Toolbar', Toolbar)
        vue.component('DataTable', DataTable)
        vue.component('Column', Column)
        vue.component('Divider', Divider)
        vue.component('Toast', Toast)
        vue.component('Badge', Badge)
        vue.component('Dropdown', Dropdown)
        vue.component('ProgressSpinner', ProgressSpinner)
        vue.component('Breadcrumb', Breadcrumb)
        vue.component('Menu', Menu)
        vue.component('Avatar', Avatar)
        vue.component('InputText', InputText)
        vue.component('Dialog', Dialog)
        vue.component('ConfirmDialog', ConfirmDialog)
        vue.component('FontAwesomeIcon', FontAwesomeIcon)
        vue.mount(el)
        return vue
    },
    progress: {
        color: '#4B5563',
    },
});
