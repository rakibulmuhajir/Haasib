import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { createSSRApp, DefineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const localPages = import.meta.glob<DefineComponent>('./pages/**/*.vue');
const modulePages = import.meta.glob<DefineComponent>('../../modules/**/Resources/js/pages/**/*.vue');

const resolvePage = async (name: string) => {
    const normalized = name.startsWith('/') ? name.slice(1) : name;
    const local = localPages[`./pages/${normalized}.vue`];
    if (local) return (await local()).default;

    const stripped = normalized.replace(/^accounting\//, '');
    const candidates = new Set<string>([normalized, stripped]);
    const parts = stripped.split('/');
    if (parts.length > 1) {
        const folder = parts[parts.length - 2];
        const file = parts[parts.length - 1];
        if (file === 'Index') {
            candidates.add(`${folder}/Index`);
        }
    }

    for (const candidate of candidates) {
        const moduleMatch = Object.keys(modulePages).find((key) => key.endsWith(`/${candidate}.vue`));
        if (moduleMatch) return (await modulePages[moduleMatch]()).default;
    }

    for (const candidate of candidates) {
        const directCandidate = candidate.replace(/^accounting\//, '');
        if (!directCandidate) continue;
        try {
            const direct = await import(
                /* @vite-ignore */ `../../modules/Accounting/Resources/js/pages/${directCandidate}.vue`
            );
            return direct.default;
        } catch (_e) {
            // continue
        }
    }

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
