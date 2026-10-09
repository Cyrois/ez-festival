import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<div id="app"></div>');
for (const key of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node']) {
    globalThis[key] = dom.window[key];
}
const vue = await import('vue');
const i18n = await import('laravel-vue-i18n');
const translations = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'));

test('application mounts with translated labels without loading a language chunk', async () => {
    let app;
    let labels;
    const languageRequests = [];
    globalThis.appEnglishMessages = translations;
    globalThis.appTranslationDeps = {
        ...vue,
        ...i18n,
        FontAwesomeIcon: {},
        resolvePageComponent: () => {},
        createInertiaApp: ({ setup }) => {
            globalThis.appTranslationSetup = setup({
                el: document.getElementById('app'),
                App: {
                    setup() {
                        labels = ['team.member.shifts.day', 'team.scheduling.table.location', 'team.member.shifts.time', 'team.scheduling.slots.role', 'data_table.search', 'data_table.info'].map(i18n.trans);
                        return () => vue.h('div');
                    },
                },
                props: {},
                plugin: { install(instance) { app = instance; } },
            });
        },
    };
    globalThis.appTranslationLanguages = {
        '../../lang/en.json': async () => {
            languageRequests.push('en');
            throw new Error('Language chunk unavailable');
        },
    };
    const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8')
        .replace(/import ['"][^'"]+['"];?/g, '')
        .replace(/import englishMessages from ['"][^'"]+['"];?/, 'const englishMessages = globalThis.appEnglishMessages;')
        .replace(/import \{([^}]+)\} from ['"][^'"]+['"];?/g, 'const {$1} = globalThis.appTranslationDeps;')
        .replace('import.meta.env.VITE_APP_NAME', 'undefined')
        .replace("import.meta.glob('./pages/**/*.vue')", '{}')
        .replace(/import.meta.glob\(\[[\s\S]*?\]\)/g, 'globalThis.appTranslationLanguages');
    try {
        await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
        await globalThis.appTranslationSetup;
        assert.deepEqual(labels, ['Day', 'Location', 'Time', 'Role', 'Search:', translations['data_table.info']]);
        assert.deepEqual(languageRequests, []);
    } finally {
        app?.unmount();
        i18n.reset();
    }
});
