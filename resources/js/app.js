import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { i18nVue, loadLanguageAsync } from 'laravel-vue-i18n';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import englishMessages from '../../lang/en.json';
import './icons';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const languages = import.meta.glob([
    '../../lang/*.json',
    '!../../lang/en.json',
]);

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob('./pages/**/*.vue'),
        ),
    async setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18nVue, {
                lang: 'en',
                resolve: (lang) => {
                    if (lang === 'en') return englishMessages;

                    return languages[`../../lang/${lang}.json`]();
                },
            })
            .component('FontAwesomeIcon', FontAwesomeIcon);

        // Initialize bundled English messages before DataTables captures labels.
        await loadLanguageAsync('en');

        return app.mount(el);
    },
    progress: { color: '#1F7A74' },
});
