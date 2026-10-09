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
const { createApp, h, nextTick, reactive } = await import('vue');
const require = createRequire(import.meta.url);
const locale = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'));
globalThis.mealReportTranslate = (key, values = {}) => Object.entries(values).reduce((text, [name, value]) => text.replaceAll(`:${name}`, value), locale[key] ?? key);
globalThis.mealReportLayout = { setup: (_, { slots }) => () => h('main', slots.default?.()) };
globalThis.mealReportCore = (await import('datatables.net-dt')).default;
globalThis.mealReportTable = (await import('datatables.net-vue3')).default.default;
globalThis.mealReportCva = require('class-variance-authority').cva;

const compile = (path, replacements = []) => {
    const { descriptor } = parse(readFileSync(new URL(path, import.meta.url), 'utf8'));
    let source = compileScript(descriptor, { id: path, inlineTemplate: true }).content
        .replaceAll(/from ['"]vue['"]/g, `from '${pathToFileURL(require.resolve('vue')).href}'`)
        .replace(/import \{ cn \} from ['"].*?['"];?/, 'const cn = (...values) => values.filter(Boolean).join(" ");')
        .replace(/import \{ cva \} from ['"].*?['"];?/, 'const cva = globalThis.mealReportCva;')
        .replace(/import \{ Icon \} from ['"].*?['"];?/, 'const Icon = { render: () => null };');
    for (const [pattern, replacement] of replacements) source = source.replace(pattern, replacement);
    return `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
};
const dataTable = compile('../../resources/js/components/ui/data-table/DataTable.vue', [
    [/import DataTablesCore from ['"].*?['"];?/, 'const DataTablesCore = globalThis.mealReportCore;'],
    [/import DataTablesVue from ['"].*?['"];?/, 'const DataTablesVue = globalThis.mealReportTable;'],
    [/import \{[^}]*\} from ['"]laravel-vue-i18n['"];?/, 'const currentLocale = "en", getActiveLanguage = () => "en", isLoaded = () => true, loadLanguageAsync = async () => {}; const trans = globalThis.mealReportTranslate;'],
]);
const cardTitle = compile('../../resources/js/components/ui/card/CardTitle.vue');
const card = compile('../../resources/js/components/ui/card/Card.vue', [
    [/import CardTitle from ['"].*?['"];?/, `import CardTitle from '${cardTitle}';`],
]);
const badge = compile('../../resources/js/components/ui/badge/Badge.vue');
const button = compile('../../resources/js/components/ui/button/Button.vue', [
    [/import \{ Link \} from ['"].*?['"];?/, 'const Link = "a";'],
    [/from ['"]\.\/buttonVariants['"]/, `from '${new URL('../../resources/js/components/ui/button/buttonVariants.js', import.meta.url).href}'`],
]);
const empty = compile('../../resources/js/components/ui/empty-state/EmptyState.vue');
const tooltip = compile('../../resources/js/components/ui/tooltip/Tooltip.vue');
const page = compile('../../resources/js/pages/Reports/Index.vue', [
    [/import AppLayout from ['"].*?['"];?/, 'const AppLayout = globalThis.mealReportLayout;'],
    [/import \{ Badge \} from ['"].*?['"];?/, `import Badge from '${badge}';`],
    [/import \{ Button \} from ['"].*?['"];?/, `import Button from '${button}';`],
    [/import \{ Card, CardTitle \} from ['"].*?['"];?/, `import Card from '${card}'; import CardTitle from '${cardTitle}';`],
    [/import \{ DataTable \} from ['"].*?['"];?/, `import DataTable from '${dataTable}';`],
    [/import \{ EmptyState \} from ['"].*?['"];?/, `import EmptyState from '${empty}';`],
    [/import \{ Tooltip \} from ['"].*?['"];?/, `import Tooltip from '${tooltip}';`],
    [/from ['"]\.\.\/\.\.\/lib\/mealDates['"]/, `from '${new URL('../../resources/js/lib/mealDates.js', import.meta.url).href}'`],
    [/import \{ getActiveLanguage, trans \} from ['"]laravel-vue-i18n['"];?/, 'const trans = globalThis.mealReportTranslate; const getActiveLanguage = () => "en";'],
]);
const { default: Report } = await import(page);
const tick = async () => { await new Promise((resolve) => setTimeout(resolve, 20)); await nextTick(); };
const mount = (rows) => {
    const props = reactive({ meals: { rows } });
    const app = createApp({ setup: () => () => h(Report, props) });
    app.config.globalProperties.$t = globalThis.mealReportTranslate;
    app.mount(document.getElementById('app'));
    return { app, props };
};
const row = (changes = {}) => ({ date: '2026-10-02', meal_name: 'Crew lunch', meal_type: 'Lunch', projected: 2, used: 1, remaining: 1, extras: 0, total: 1, ...changes });

test('whole report renders more than ten rows without summary rows or paging, and a native CSV download link', async () => {
    const rows = Array.from({ length: 12 }, (_, index) => row({ meal_name: `Meal ${index + 1}` }));
    const { app } = mount(rows);
    try {
        await tick();
        assert.equal(document.querySelectorAll('tbody tr').length, 12);
        assert.equal(document.querySelector('.dt-paging, input, select'), null);
        assert.deepEqual([...document.querySelectorAll('thead th')].map((cell) => cell.textContent), ['Meal', 'Date', 'Meal type', 'Projected', 'Used', 'Remaining', 'Extras', 'Total']);
        assert.deepEqual([...document.querySelector('tbody tr').cells].map((cell) => cell.dataset.label), ['Meal', 'Date', 'Meal type', 'Projected', 'Used', 'Remaining', 'Extras', 'Total']);
        assert.match(document.querySelector('tbody').textContent, /Fri, Oct 2/);
        assert.equal([...document.querySelectorAll('tbody tr')].filter((row) => row.cells[1].textContent.includes('Fri, Oct 2')).length, 12);
        assert.match(document.querySelector('tbody').textContent, /Meal 12/);
        assert.doesNotMatch(document.querySelector('tbody').textContent, /Fri total|Event total/);
        assert.equal(document.querySelector('h1').textContent.trim(), 'Meals Report');
        assert.equal(document.querySelector('a').getAttribute('href'), '/reports/meals/export');
        assert.equal(document.querySelector('a').getAttribute('aria-disabled'), null);
    } finally { app.unmount(); }
});

test('shared mobile labels follow hidden columns and paging without replacing slots or caller callbacks', async () => {
    const { default: SharedDataTable } = await import(dataTable);
    let table, draws = 0, clicks = 0;
    const app = createApp({
        setup: () => () => h(SharedDataTable, {
            data: [{ name: 'Ava', hidden: 'One', count: 3 }, { name: 'Zoe', hidden: 'Two', count: 7 }],
            columns: [
                { data: 'name', title: '<span>Name</span>' },
                { data: 'hidden', title: 'Hidden', visible: false },
                { data: 'count', title: 'Count' },
                { data: null, title: '', orderable: false, render: { display: '#action' } },
            ],
            options: {
                pageLength: 1,
                createdRow: (row) => { row.dataset.link = 'kept'; },
                drawCallback: function () { table = this.api(); draws++; },
            },
        }, {
            action: () => h('button', { onClick: () => clicks++ }, 'Action'),
        }),
    });
    app.mount(document.getElementById('app'));
    try {
        await tick();
        const labels = () => [...document.querySelector('tbody tr').cells].map((cell) => cell.dataset.label);
        assert.deepEqual(labels(), ['Name', 'Count', '']);
        assert.equal(document.querySelector('tbody tr').dataset.link, 'kept');
        document.querySelector('tbody button').click();
        assert.equal(clicks, 1);
        table.page('next').draw('page');
        await tick();
        assert.match(document.querySelector('tbody').textContent, /Zoe/);
        assert.deepEqual(labels(), ['Name', 'Count', '']);
        table.column(1).visible(true);
        assert.deepEqual(labels(), ['Name', 'Hidden', 'Count', '']);
        table.search('Ava').draw();
        await tick();
        assert.match(document.querySelector('tbody').textContent, /Ava/);
        assert.equal(draws, 3);
        table.search('no match').draw();
        await tick();
        assert.equal(document.querySelector('td.dt-empty').hasAttribute('data-label'), false);
    } finally { app.unmount(); }
});

test('empty report disables export with an accessible tooltip, and populated zero rows become exportable', async () => {
    const { app, props } = mount([]);
    try {
        await tick();
        assert.match(document.body.textContent, /No meals yet/);
        assert.equal(document.querySelector('table, a'), null);
        assert.equal(document.querySelector('button').disabled, true);
        const trigger = document.querySelector('[aria-label="Nothing to export yet."]');
        assert.ok(trigger);
        trigger.dispatchEvent(new dom.window.FocusEvent('focus'));
        await tick();
        assert.equal(document.querySelector('[role="tooltip"]').textContent, 'Nothing to export yet.');
        props.meals.rows = [row({ projected: 0, used: 0, remaining: 0, total: 0 })];
        await tick();
        assert.equal(document.querySelector('[role="tooltip"]'), null);
        assert.equal(document.querySelectorAll('tbody tr').length, 1);
        assert.deepEqual([...document.querySelectorAll('tbody td')].slice(3).map((cell) => cell.textContent), ['0', '-0', '0', '-0', '0']);
        assert.ok(document.querySelector('a[href="/reports/meals/export"]'));
    } finally { app.unmount(); }
});

test('type names are rendered as text and refreshed counts update the displayed report', async () => {
    const { app, props } = mount([row({ meal_name: '<img src=x onerror=alert(1)>' })]);
    try {
        await tick();
        assert.equal(document.querySelector('img'), null);
        assert.match(document.querySelector('tbody').textContent, /<img src=x onerror=alert\(1\)>/);
        props.meals.rows = [row({ used: 3, remaining: 0, extras: 2, total: -3 })];
        await tick();
        assert.deepEqual([...document.querySelectorAll('tbody td')].slice(3).map((cell) => cell.textContent), ['2', '-3', '0', '-2', '-3']);
    } finally { app.unmount(); }
});
