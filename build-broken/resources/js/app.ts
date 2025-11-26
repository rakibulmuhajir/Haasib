import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';
import { transformError } from './utils/errorHandler';
import axios from 'axios';

// Configure Axios for CSRF token
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Set CSRF token for all requests
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.getAttribute('content');
} else {
    console.error('CSRF token not found: make sure the meta tag is present in the head');
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        const appPages = import.meta.glob<DefineComponent>('./pages/**/*.vue');
        
        // Generic module resolver - handles any module (Accounting, CRM, etc.)
        // Use absolute path from project root
        const allModulePages = import.meta.glob<DefineComponent>('/modules/**/resources/js/pages/**/*.vue');

        // Check if this is a module page (format: ModuleName/PageName)
        const moduleMatch = name.match(/^([A-Z][a-zA-Z]+)\/(.+)$/);
        if (moduleMatch) {
            const [, moduleName, pagePath] = moduleMatch;
            
            // Try explicit feature structure first: /modules/ModuleName/resources/js/pages/{pagePath}.vue
            // This handles: Customers/Customers -> /modules/Accounting/resources/js/pages/Customers/Customers.vue
            let modulePath = `/modules/${moduleName}/resources/js/pages/${pagePath}.vue`;
            
            let resolvedFile = Object.keys(allModulePages).find(key => 
                key.includes(modulePath)
            );
            
            if (resolvedFile) {
                return resolvePageComponent(modulePath, allModulePages);
            }
            
            // Fallback to Accounting module for feature-based components - Updated
            // This handles: Invoicing/Invoices -> /modules/Accounting/resources/js/pages/Invoicing/Invoices.vue
            // since Invoicing doesn't match a module name but is a feature within Accounting
            if (moduleName === 'Invoicing' || moduleName === 'Customers' || moduleName === 'Vendors') {
                modulePath = `/modules/Accounting/resources/js/pages/${moduleName}/${pagePath}.vue`;
                
                resolvedFile = Object.keys(allModulePages).find(key => 
                    key.includes(modulePath)
                );
                
                if (resolvedFile) {
                    return resolvePageComponent(modulePath, allModulePages);
                }
            }
            
            // Fallback to old structure: /modules/ModuleName/resources/js/pages/ModuleName/{pagePath}.vue
            modulePath = `/modules/${moduleName}/resources/js/pages/${moduleName}/${pagePath}.vue`;
            
            resolvedFile = Object.keys(allModulePages).find(key => 
                key.includes(modulePath)
            );
            
            if (resolvedFile) {
                return resolvePageComponent(modulePath, allModulePages);
            }
        }

        // Default to app pages for non-module routes
        return resolvePageComponent(`./pages/${name}.vue`, appPages);
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin);

        // Global error handler for unhandled promise rejections
        app.config.errorHandler = (error, instance, info) => {
            console.error('Global error handler:', error, info);
            
            // Transform the error to user-friendly message
            const friendlyError = transformError(error);
            
            // You can show a toast notification here
            // For now, we'll just log it
            console.warn('User-friendly error:', friendlyError.message);
        };

        app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// Handle unhandled promise rejections
window.addEventListener('unhandledrejection', (event) => {
    const friendlyError = transformError(event.reason);
    console.warn('Unhandled promise rejection:', friendlyError.message);
    
    // Prevent the default browser behavior
    event.preventDefault();
});

// This will set light / dark mode on page load...
initializeTheme();
