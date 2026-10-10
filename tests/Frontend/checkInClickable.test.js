import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import { parse, compileScript } from '@vue/compiler-sfc';
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<div id="app"></div>', { url: 'http://localhost' });
for (const key of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node', 'Option', 'DocumentFragment', 'getComputedStyle']) globalThis[key] = dom.window[key];
Object.defineProperty(globalThis, 'navigator', { value: dom.window.navigator, configurable: true });
window.performance.getEntriesByType = () => [];
const { createApp, h, nextTick } = await import('vue');
const { router } = await import('@inertiajs/vue3');
const require = createRequire(import.meta.url);
const translations = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'));
const translate = (key) => translations[key] ?? key;
globalThis.checkInTranslate = translate;
globalThis.checkInBox = (tag) => ({ setup: (_, { slots }) => () => h(tag, slots.default?.()) });
globalThis.checkInCore = (await import('datatables.net-dt')).default;
globalThis.checkInTable = (await import('datatables.net-vue3')).default.default;
globalThis.checkInNavigate = (await import('../../resources/js/lib/dataTableRowNavigation.js')).navigateDataTableRow;
globalThis.checkInRouter = router;
globalThis.checkInQuery = (await import('../../resources/js/pages/CheckIn/filters.js')).checkInQuery;
const compile = (path, replacements) => {
    const { descriptor } = parse(readFileSync(new URL(path, import.meta.url), 'utf8'));
    let source = compileScript(descriptor, { id: path, inlineTemplate: true }).content.replaceAll(/from ['"]vue['"]/g, `from '${pathToFileURL(require.resolve('vue')).href}'`);
    for (const [pattern, replacement] of replacements) source = source.replace(pattern, replacement);
    return `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
};
const dataTable = compile('../../resources/js/components/ui/data-table/DataTable.vue', [
    [/import DataTablesCore from ['"].*?['"];?/, 'const DataTablesCore = globalThis.checkInCore;'],
    [/import DataTablesVue from ['"].*?['"];?/, 'const DataTablesVue = globalThis.checkInTable;'],
    [/import \{[^}]*\} from ['"]laravel-vue-i18n['"];?/, 'const currentLocale = "en", getActiveLanguage = () => "en", isLoaded = () => true, loadLanguageAsync = async () => {}; const trans = globalThis.checkInTranslate;'],
    [/import \{ cn \} from ['"].*?['"];?/, 'const cn = (...values) => values.filter(Boolean).join(" ");'],
]);
const tooltip = compile('../../resources/js/components/ui/tooltip/Tooltip.vue', []);
const page = compile('../../resources/js/pages/CheckIn/Index.vue', [
    [/import HiddenPersonalInfo from ['"].*?['"];?/, 'const HiddenPersonalInfo = globalThis.checkInBox("span");'],
    [/import \{ Link, router \} from ['"].*?['"];?/, 'const Link = "a", router = globalThis.checkInRouter;'],
    [/import \{ trans \} from ['"].*?['"];?/, 'const trans = globalThis.checkInTranslate;'],
    [/import AppLayout from ['"].*?['"];?/, 'const AppLayout = globalThis.checkInBox("main");'],
    ...['Avatar','Badge','Button','Icon','Input','Select'].map((name) => [new RegExp(`import \\{ ${name} \\} from ['"].*?['"];?`), `const ${name} = globalThis.checkInBox("${name === 'Icon' ? 'i' : 'span'}");`]),
    [/import \{ Tooltip \} from ['"].*?['"];?/, `import Tooltip from '${tooltip}';`],
    [/import \{ DataTable \} from ['"].*?['"];?/, `import DataTable from '${dataTable}';`],
    [/import \{ navigateDataTableRow \} from ['"].*?['"];?/, 'const navigateDataTableRow = globalThis.checkInNavigate;'],
    [/import \{ checkInQuery \} from ['"].*?['"];?/, 'const checkInQuery = globalThis.checkInQuery;'],
]);
const { default: CheckIn } = await import(page);

for (const [type, hasPass] of [['artist', true], ['team', false], ['team', true]]) {
test(`${type} rows navigate without Edit passes; has pass: ${hasPass}`, async () => {
    const requests = [], visits = [], opened = [];
    const originalXhr = globalThis.XMLHttpRequest;
    const originalGet = router.get;
    const originalOpen = window.open;
    const person = { person_id: 17, engagement_id: 8, name: 'Maya Chen', subtitle: 'maya@example.com', type, has_pass: hasPass, context: 'River Hollow', pass_name: 'Artist pass', check_in_status: 'not_started', can_edit: true };
    globalThis.XMLHttpRequest = class {
        open(method, url) { this.url = new URL(url, 'http://localhost'); }
        setRequestHeader() {}
        abort() {}
        send() {
            requests.push({ url: this.url.pathname, data: Object.fromEntries(this.url.searchParams) });
            setTimeout(() => {
                this.readyState = 4;
                this.status = 200;
                this.responseText = JSON.stringify({ draw: Number(this.url.searchParams.get('draw')), recordsTotal: 1, recordsFiltered: 1, data: [person] });
                this.onreadystatechange();
            }, 0);
        }
    };
    router.get = (href) => visits.push(href);
    window.open = (href) => opened.push(href);
    const app = createApp(CheckIn, { passes: [], filters: { type, status: 'not_started', search: ' Maya ' }, event: { id: 1 } });
    app.config.globalProperties.$t = translate;
    try {
        app.mount('#app');
        await new Promise((resolve) => setTimeout(resolve, 30));
        await nextTick();
        assert.equal(requests[0].url, '/check-in/data');
        assert.equal(requests[0].data.length, '25');
        assert.equal(requests[0].data.search, 'Maya');
        assert.equal(requests[0].data.status, undefined);
        assert.equal([...document.querySelectorAll('button')].some(button => ['Not started', 'Partial', 'Complete'].includes(button.textContent.trim())), false);
        assert.equal(requests[0].data.type, type);
        assert.equal(requests[0].data.pass, '');
        const row = document.querySelector('tbody tr[data-row-link]');
        assert.ok(row);
        const chevron = row.lastElementChild.querySelector('a');
        assert.equal(chevron.getAttribute('href'), `/check-in/${type}s/8?person=17`);
        assert.equal(chevron.getAttribute('aria-label'), translate('check_in.actions.check_in'));
        assert.ok(chevron.querySelector('i'));
        const warning = row.querySelector(`[aria-label="${translate('check_in.no_pass')}"]`);
        assert.equal(Boolean(warning), type === 'team' && !hasPass);
        if (warning) {
            warning.dispatchEvent(new window.MouseEvent('mouseenter'));
            await nextTick();
            assert.equal(document.querySelector('[role="tooltip"]').textContent.trim(), translate('check_in.no_pass'));
            warning.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
            assert.deepEqual(visits, []);
        }
        assert.equal(document.querySelector('th:last-child').textContent.trim(), '');
        row.children[1].dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
        assert.deepEqual(visits, [`/check-in/${type}s/8?person=17`]);
        assert.equal(row.querySelector('a[href$="#passes"]'), null);
        assert.ok(!row.textContent.includes(translate('check_in.actions.edit_passes')));
        row.children[1].dispatchEvent(new window.MouseEvent('click', { bubbles: true, ctrlKey: true }));
        assert.deepEqual(opened, [`/check-in/${type}s/8?person=17`]);
    } finally {
        app.unmount();
        globalThis.XMLHttpRequest = originalXhr;
        router.get = originalGet;
        window.open = originalOpen;
    }
});

}
