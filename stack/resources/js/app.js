import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import PrimeVue from 'primevue/config'
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import Tooltip from 'primevue/tooltip'
import Aura from '@primevue/themes/aura'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { createI18n } from 'vue-i18n'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
    faHome,
    faBuilding,
    faUsers,
    faFile,
    faCalculator,
    faChartBar,
    faCog,
    faInfoCircle,
    faCalendar,
    faThLarge,
    faHistory,
    faUser,
    faSignOutAlt,
    faChevronRight,
    faChevronDown,
    faPlus,
    faBars,
    faTachometerAlt,
    faHandHoldingUsd,
    faCreditCard,
    faUniversity,
    faBalanceScale,
    faFileImport,
    faFileAlt,
    faBook,
    faBalanceScaleLeft,
    faCalendarCheck,
    faTasks,
    faShieldAlt,
    faChartLine,
    faChartPie,
    faFileInvoice,
    faFileInvoiceDollar,
    faUsersCog,
    faUserCog,
    faQuestionCircle,
    faTimes
} from '@fortawesome/free-solid-svg-icons'
import './styles/app.css'

// Add Figtree font for the Blue Whale theme
import '@fontsource/figtree/400.css'
import '@fontsource/figtree/600.css'
import '@fontsource/roboto-flex/400.css'

// Import locale files
import en from './locales/en.json'
import ar from './locales/ar.json'

// Add FontAwesome icons to library
library.add(
    faHome,
    faBuilding,
    faUsers,
    faFile,
    faCalculator,
    faChartBar,
    faCog,
    faInfoCircle,
    faCalendar,
    faThLarge,
    faHistory,
    faUser,
    faSignOutAlt,
    faChevronRight,
    faChevronDown,
    faPlus,
    faBars,
    faTachometerAlt,
    faHandHoldingUsd,
    faCreditCard,
    faUniversity,
    faBalanceScale,
    faFileImport,
    faFileAlt,
    faBook,
    faBalanceScaleLeft,
    faCalendarCheck,
    faTasks,
    faShieldAlt,
    faChartLine,
    faChartPie,
    faFileInvoice,
    faFileInvoiceDollar,
    faUsersCog,
    faUserCog,
    faQuestionCircle,
    faTimes
)

const appName = import.meta.env.VITE_APP_NAME || 'Haasib'

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const i18n = createI18n({
            legacy: false,
            locale: 'en',
            fallbackLocale: 'en',
            messages: {
                en,
                ar
            }
        })

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: {
                        prefix: 'p',
                        darkModeSelector: '.dark',
                        cssLayer: false
                    }
                }
            })
            .use(ToastService)
            .use(ConfirmationService)
            .use(i18n)
            .directive('tooltip', Tooltip)
            .component('font-awesome-icon', FontAwesomeIcon)
            .mount(el)
    },
    progress: {
        color: '#4B5563',
    },
})
