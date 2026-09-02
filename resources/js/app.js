import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { i18n, syncLocale } from './i18n';
import { applyTheme, watchSystemTheme } from './theme';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

let utilisateurCourant = null;

const appliquerPreferences = (page) => {
    const utilisateur = page.props?.auth?.user ?? null;
    utilisateurCourant = utilisateur;
    syncLocale(utilisateur?.locale);
    applyTheme(utilisateur?.theme ?? 'light');
};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        appliquerPreferences(props.initialPage);

        watchSystemTheme(() => utilisateurCourant?.theme ?? 'light');

        router.on('navigate', (event) => {
            appliquerPreferences(event.detail.page);
        });

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
