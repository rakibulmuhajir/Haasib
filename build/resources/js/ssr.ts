import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { createSSRApp, DefineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const localPages = import.meta.glob<DefineComponent>('./pages/**/*.vue');
const modulePages = import.meta.glob<DefineComponent>('../modules/**/Resources/js/pages/**/*.vue');

const resolvePage = (name: string) => {
    const normalized = name.startsWith('/') ? name.slice(1) : name;
    const local = localPages[`./pages/${normalized}.vue`];
    if (local) return local;

    const moduleMatch = Object.keys(modulePages).find((key) => key.endsWith(`/${normalized}.vue`));
    if (moduleMatch) return modulePages[moduleMatch];

    throw new Error(`Page not found: ${name}`);
};

createServer(
    (page) =>
        createInertiaApp({
            page,
            render: renderToString,
            title: (title) => (title ? `${title} - ${appName}` : appName),
            resolve: resolvePage,
            setup: ({ App, props, plugin }) =>
                createSSRApp({ render: () => h(App, props) }).use(plugin),
        }),
    { cluster: true },
);
