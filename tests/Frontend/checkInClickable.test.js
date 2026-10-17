import { labelImports, desktopLabelEnvironment } from './helpers/entitlementLabels.js';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import { compileScript } from '@vue/compiler-sfc';
import { parseWithReturnNavigation as parse } from './helpers/returnNavigation.js';
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<div id="app"></div>', { url: 'http://localhost' });
for (const key of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node', 'Option', 'DocumentFragment', 'getComputedStyle']) globalThis[key] = dom.window[key];
Object.defineProperty(globalThis, 'navigator', { value: dom.window.navigator, configurable: true });
window.performance.getEntriesByType = () => [];
desktopLabelEnvironment();
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
    source = source.replace(...labelImports);
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
const button = compile('../../resources/js/components/ui/button/Button.vue', [
    [/import \{ Link \} from ['"].*?['"];?/, 'const Link = "a";'],
    [/import \{ cn \} from ['"].*?['"];?/, 'const cn = (...values) => values.filter(Boolean).join(" ");'],
    [/import \{ buttonVariants \} from ['"].*?['"];?/, 'const buttonVariants = () => "";'],
]);
const hiddenInfo = compile('../../resources/js/components/people/HiddenPersonalInfo.vue', [
    [/import \{ trans \} from ['"].*?['"];?/, 'const trans = globalThis.checkInTranslate;'],
    [/import \{ Icon \} from ['"].*?['"];?/, 'const Icon = globalThis.checkInBox("i");'],
]);
const page = compile('../../resources/js/pages/CheckIn/Index.vue', [
    [/import HiddenPersonalInfo from ['"].*?['"];?/, `import HiddenPersonalInfo from '${hiddenInfo}';`],
    [/import \{ Link, router \} from ['"].*?['"];?/, 'const Link = "a", router = globalThis.checkInRouter;'],
    [/import \{ trans \} from ['"].*?['"];?/, 'const trans = globalThis.checkInTranslate;'],
    [/import AppLayout from ['"].*?['"];?/, 'const AppLayout = globalThis.checkInBox("main");'],
    ...['Avatar','Badge','Icon','Input','Select'].map((name) => [new RegExp(`import \\{ ${name} \\} from ['"].*?['"];?`), `const ${name} = globalThis.checkInBox("${name === 'Icon' ? 'i' : 'span'}");`]),
    [/import \{ Button \} from ['"].*?['"];?/, `import Button from '${button}';`],
    [/import \{ Tooltip \} from ['"].*?['"];?/, `import Tooltip from '${tooltip}';`],
    [/import \{ DataTable \} from ['"].*?['"];?/, `import DataTable from '${dataTable}';`],
    [/import \{ navigateDataTableRow \} from ['"].*?['"];?/, 'const navigateDataTableRow = globalThis.checkInNavigate;'],
    [/import \{ checkInQuery \} from ['"].*?['"];?/, 'const checkInQuery = globalThis.checkInQuery;'],
]);
const { default: CheckIn } = await import(page);

for (const [type, hasPass] of [['artist', true], ['team', false], ['team', true]]) {
test(`${type} rows navigate without Edit passes; has pass: ${hasPass}`, async () => {
    const requests = [], visits = [], opened = [], filterVisits = [];
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
    router.get = (href, data) => { visits.push(href); if (data) filterVisits.push(data); };
    window.open = (href) => opened.push(href);
    const app = createApp(CheckIn, { passes: [], filters: { type, search: ' Maya ' }, event: { id: 1 } });
    app.config.globalProperties.$t = translate;
    try {
        app.mount('#app');
        await new Promise((resolve) => setTimeout(resolve, 30));
        await nextTick();
        assert.equal(requests[0].url, '/check-in/data');
        assert.equal(requests[0].data.length, '25');
        assert.equal(requests[0].data.search, 'Maya');
        assert.equal(requests[0].data.status, 'incomplete');
        const statusGroup = document.querySelector('[role="group"][aria-label="Status"]');
        assert.deepEqual([...statusGroup.querySelectorAll('button')].map(button => button.textContent.trim()), ['Incomplete', 'Complete']);
        assert.equal(statusGroup.querySelector('button[aria-pressed="true"]').textContent.trim(), 'Incomplete');
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
        const complete = [...statusGroup.querySelectorAll('button')].find(button => button.textContent.trim() === 'Complete');
        complete.click();
        await nextTick();
        assert.deepEqual(filterVisits, [{ type, pass: undefined, status: 'complete', search: 'Maya' }]);
        assert.equal(statusGroup.querySelector('button[aria-pressed="true"]').textContent.trim(), 'Complete');
        statusGroup.querySelector('button').click();
        await nextTick();
        assert.deepEqual(filterVisits[1], { type, pass: undefined, status: 'incomplete', search: 'Maya' });
    } finally {
        app.unmount();
        globalThis.XMLHttpRequest = originalXhr;
        router.get = originalGet;
        window.open = originalOpen;
    }
});

}

const stockTableUrl = compile('../../resources/js/components/check-in/InventoryStockTable.vue', [
    [/import \{ trans \} from ['"].*?['"];?/, 'const trans = globalThis.checkInTranslate;'],
    [/import \{ DataTable \} from ['"].*?['"];?/, `import DataTable from '${dataTable}';`],
]);
const { default: InventoryStockTable } = await import(stockTableUrl);
test('Inventory tables send a validated flat search and escape item names', async () => {
    const originalXhr = globalThis.XMLHttpRequest;
    const requests = [];
    globalThis.XMLHttpRequest = class {
        open(method, url) { this.url = new URL(url, 'http://localhost'); }
        setRequestHeader() {}
        abort() {}
        send() {
            requests.push(Object.fromEntries(this.url.searchParams));
            setTimeout(() => {
                this.readyState = 4; this.status = 200;
                this.responseText = JSON.stringify({ draw: Number(this.url.searchParams.get('draw')), recordsTotal: 1, recordsFiltered: 1,
                    data: [{ id: 2, name: '<img src=x onerror="alert(1)">Wristband', balance: 12 }] });
                this.onreadystatechange();
            }, 0);
        }
    };
    const app = createApp(InventoryStockTable, { endpoint: '/check-in/inventory/stock', timezone: 'America/Vancouver' });
    app.config.globalProperties.$t = translate;
    try {
        app.mount('#app'); await new Promise((resolve) => setTimeout(resolve, 35)); await nextTick();
        assert.equal(requests[0].search, '');
        assert.equal(requests[0]['search[value]'], undefined);
        assert.equal(requests[0].length, '25');
        assert.match(document.querySelector('tbody').textContent, /Wristband/);
        assert.equal(document.querySelector('tbody img'), null);
    } finally { app.unmount(); globalThis.XMLHttpRequest = originalXhr; }
});


test('hidden personal info renders inside detached DataTable slots without global translation injection', async () => {
    const { default: HiddenPersonalInfo } = await import(hiddenInfo);
    const app = createApp(HiddenPersonalInfo);
    try {
        app.mount('#app'); await nextTick();
        assert.match(document.querySelector('#app').textContent, /Hidden for your role/);
        assert.equal(document.querySelector('#app span[title]').title, translate('permissions.hidden'));
    } finally { app.unmount(); }
});

const holdingsInput = compile('../../resources/js/components/ui/input/Input.vue', [
    [/import \{ cn \} from ['"].*?['"];?/, 'const cn = (...values) => values.filter(Boolean).join(" ");'],
]);
globalThis.checkInActivate = (await import('../../resources/js/lib/dataTableRowNavigation.js')).activateDataTableRow;
globalThis.holdingsDropdown = {
    props: ['modelValue', 'items'], emits: ['update:modelValue'],
    setup: (props, { emit }) => () => h('select', { value: props.modelValue, onChange: (event) => emit('update:modelValue', event.target.value) }, props.items.map((item) => h('option', { value: item.value }, item.title))),
};
const holdingsSectionUrl = compile('../../resources/js/components/check-in/InventoryHoldingsSection.vue', [
    [/import \{ trans \} from ['"].*?['"];?/, 'const trans = globalThis.checkInTranslate;'],
    [/import \{ Card, CardTitle \} from ['"].*?['"];?/, 'const Card = globalThis.checkInBox("section"), CardTitle = globalThis.checkInBox("h2");'],
    ...['Badge', 'Icon'].map((name) => [new RegExp(`import \\{ ${name} \\} from ['"].*?['"];?`), `const ${name} = globalThis.checkInBox("span");`]),
    [/import \{ Input \} from ['"].*?['"];?/, `import Input from '${holdingsInput}';`],
    [/import \{ CustomDropdown \} from ['"].*?['"];?/, 'const CustomDropdown = globalThis.holdingsDropdown;'],
    [/import \{ DataTable \} from ['"].*?['"];?/, `import DataTable from '${dataTable}';`],
    [/import \{ activateDataTableRow \} from ['"].*?['"];?/, 'const activateDataTableRow = globalThis.checkInActivate;'],
]);
const { default: HoldingsSection } = await import(holdingsSectionUrl);

test('People and Locations / storage independently filter and paginate their holdings', async () => {
    const requests = [], selected = [];
    const originalXhr = globalThis.XMLHttpRequest;
    globalThis.XMLHttpRequest = class {
        open(method, url) { this.url = new URL(url, 'http://localhost'); }
        setRequestHeader() {}
        abort() {}
        send() {
            const data = Object.fromEntries(this.url.searchParams);
            requests.push(data);
            setTimeout(() => {
                this.readyState = 4; this.status = 200;
                this.responseText = JSON.stringify({ draw: Number(data.draw), recordsTotal: 30, recordsFiltered: 30, data: [{
                    id: data.holder_type === 'person' ? 8 : 9, name: data.holder_type === 'person' ? 'Riley' : 'Main Gate', holder_type: data.holder_type,
                    total: 7, inactive: false, items: [{ id: 1, name: 'Wristband', balance: 7 }],
                }] });
                this.onreadystatechange();
            }, 0);
        }
    };
    const app = createApp({ render: () => h('div', ['person', 'location'].map((holderType) => h(HoldingsSection, { holderType, items: [{ id: 1, name: 'Wristband' }], onSelectHolder: (holder) => selected.push(holder) }))) });
    app.config.globalProperties.$t = translate;
    const settle = async () => { await new Promise((resolve) => setTimeout(resolve, 35)); await nextTick(); };
    try {
        app.mount('#app'); await settle();
        const sections = [...document.querySelectorAll('section')];
        assert.deepEqual(sections.map((section) => section.querySelector('h2').textContent), ['People', 'Locations / storage']);
        assert.deepEqual(requests.map((request) => request.holder_type), ['person', 'location']);
        assert.ok(requests.every((request) => request.length === '25'));
        assert.equal(sections[1].querySelector('input'), null);
        const locationRequests = () => requests.filter((request) => request.holder_type === 'location');
        const next = sections[0].querySelector('.dt-paging-button.next');
        next.click(); await settle();
        assert.equal(requests.at(-1).holder_type, 'person');
        assert.equal(requests.at(-1).start, '25');
        assert.equal(locationRequests().length, 1);
        const input = sections[0].querySelector('input');
        input.value = 'Riley'; input.dispatchEvent(new window.Event('input', { bubbles: true })); await settle();
        assert.equal(requests.at(-1).search, 'Riley');
        assert.equal(requests.at(-1).start, '0');
        assert.equal(locationRequests().length, 1);
        const item = sections[1].querySelector('select');
        item.value = '1'; item.dispatchEvent(new window.Event('change', { bubbles: true })); await settle();
        assert.equal(requests.at(-1).holder_type, 'location');
        assert.equal(requests.at(-1).item, '1');
        assert.equal(requests.at(-1).search, '');
        sections[0].querySelector('tr[data-row-link] td').click();
        sections[1].querySelector('tbody button').click();
        assert.deepEqual(selected.map((holder) => holder.name), ['Riley', 'Main Gate']);
    } finally { app.unmount(); globalThis.XMLHttpRequest = originalXhr; }
});
