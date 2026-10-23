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
const empty = compile('../../resources/js/components/ui/empty-state/EmptyState.vue');
const reportLib = `data:text/javascript;base64,${Buffer.from(readFileSync(new URL('../../resources/js/lib/mealReport.js', import.meta.url), 'utf8').replace(/import .*? from ['"]laravel-vue-i18n['"];?/, 'const getActiveLanguage = () => "en";')).toString('base64')}`;
const counts = compile('../../resources/js/components/reports/MealReportCounts.vue', [
    [/from ['"]\.\.\/\.\.\/lib\/mealReport['"]/, `from '${reportLib}'`],
]);
const page = compile('../../resources/js/pages/Reports/Index.vue', [
    [/import AppLayout from ['"].*?['"];?/, 'const AppLayout = globalThis.mealReportLayout;'],
    [/import \{ Badge \} from ['"].*?['"];?/, `import Badge from '${badge}';`],
    [/import \{ Card, CardTitle \} from ['"].*?['"];?/, `import Card from '${card}'; import CardTitle from '${cardTitle}';`],
    [/import \{ DataTable \} from ['"].*?['"];?/, `import DataTable from '${dataTable}';`],
    [/import \{ EmptyState \} from ['"].*?['"];?/, `import EmptyState from '${empty}';`],
    [/import MealReportCounts from ['"].*?['"];?/, `import MealReportCounts from '${counts}';`],
    [/from ['"]\.\.\/\.\.\/lib\/mealDates['"]/, `from '${new URL('../../resources/js/lib/mealDates.js', import.meta.url).href}'`],
    [/from ['"]\.\.\/\.\.\/lib\/mealReport['"]/, `from '${reportLib}'`],
    [/import \{ getActiveLanguage, trans \} from ['"]laravel-vue-i18n['"];?/, 'const trans = globalThis.mealReportTranslate; const getActiveLanguage = () => "en";'],
]);
const { default: Report } = await import(page);
const tick = async () => { await new Promise((resolve) => setTimeout(resolve, 20)); await nextTick(); };
const mount = (days) => {
    const props = reactive({ meals: { days } });
    const app = createApp({ setup: () => () => h(Report, props) });
    app.config.globalProperties.$t = globalThis.mealReportTranslate;
    app.mount(document.getElementById('app'));
    return { app, props };
};
const totals = (changes = {}) => ({ assigned: 3, projected: 2, used: 1, remaining: 1, extras: 0, total: 1, ...changes });
const row = (changes = {}) => ({ date: '2026-10-02', meal_id: 1, meal_type_id: 1, meal_name: 'Crew lunch', meal_type: 'Lunch', ...totals(), ...changes });
const group = (changes = {}) => ({ meal_type_id: 1, meal_type: 'Lunch', meals: [row()], totals: totals(), ...changes });
const day = (changes = {}) => ({ date: '2026-10-02', meal_types: [group()], totals: totals(), ...changes });
const cells = (selector) => [...document.querySelector(selector).cells].slice(2).map((cell) => cell.textContent.trim());
const labelledCounts = (element) => Object.fromEntries([...element.querySelectorAll('dl > div')].map((item) => [item.querySelector('dt').textContent.trim(), item.querySelector('dd').textContent.trim()]));

test('full report shows day headings, expanded meal groups, Assigned first and day totals without paging or export', async () => {
    const lunchMeals = Array.from({ length: 12 }, (_, index) => row({ meal_id: index + 1, meal_name: `Meal ${index + 1}` }));
    const { app } = mount([
        day({ meal_types: [group({ meals: lunchMeals }), group({ meal_type_id: 2, meal_type: 'Dinner', meals: [row({ meal_id: 13, meal_name: 'Crew dinner', meal_type: 'Dinner' })] })] }),
        day({ date: '2026-10-04', meal_types: [group({ meals: [row({ meal_id: 14, date: '2026-10-04', meal_name: 'Sunday lunch' })] })] }),
    ]);
    try {
        await tick();
        assert.equal(document.querySelectorAll('[data-meal-report-kind="meal"]').length, 14);
        assert.equal(document.querySelectorAll('[data-meal-report-kind="meal_type"]').length, 3);
        assert.equal(document.querySelectorAll('[data-meal-report-kind="day_total"]').length, 2);
        assert.deepEqual([...document.querySelectorAll('[data-meal-report-day]')].map((row) => row.textContent.trim()), ['Fri, Oct 2', 'Sun, Oct 4']);
        assert.equal(document.querySelector('[data-meal-report-day] th').colSpan, 8);
        assert.deepEqual([...document.querySelectorAll('thead th')].map((cell) => cell.textContent), ['Meal', 'Meal type', 'Assigned', 'Projected', 'Used', 'Remaining', 'Extras', 'Total']);
        assert.match(document.querySelector('tbody').textContent, /Meal 12/);
        assert.equal(document.querySelector('.dt-paging, input, select, a, button'), null);
        assert.doesNotMatch(document.body.textContent, /Export CSV|Nothing to export|Event total|Counter override/);
        assert.match(document.body.textContent, /Meals to serve per day and meal time, from shifts, direct meals and extra meals\./);
        assert.match(document.body.textContent, /Total is Projected minus Used and may be negative\./);
        assert.equal(document.querySelector('h1').textContent.trim(), 'Meals Report');
    } finally { app.unmount(); }
});

test('phone has one labelled card per type with named meals inside and one Day total card per day', async () => {
    const negative = totals({ assigned: 3, projected: 0, used: 3, remaining: 0, extras: 3, total: -3 });
    const { app } = mount([day({ meal_types: [group({ meals: [row({ meal_name: 'Lunch A', ...negative }), row({ meal_id: 2, meal_name: 'Lunch B' })], totals: negative })], totals: negative })]);
    try {
        await tick();
        assert.equal(document.querySelectorAll('[data-meal-type-card]').length, 1);
        assert.equal(document.querySelectorAll('[data-meal-type-card] [data-meal-detail]').length, 2);
        assert.equal(document.querySelectorAll('[data-day-total-card]').length, 1);
        const type = document.querySelector('[data-meal-type-card]');
        assert.match(type.textContent, /Lunch.*2 meals/s);
        assert.deepEqual(labelledCounts(type.querySelector('dl')), { Assigned: '3', Projected: '0', Used: '-3', Remaining: '0', Extras: '-3', Total: '-3' });
        assert.deepEqual(labelledCounts(document.querySelector('[data-day-total-card]')), { Assigned: '3', Projected: '0', Used: '-3', Remaining: '0', Extras: '-3', Total: '-3' });
        assert.deepEqual(cells('[data-meal-report-kind="meal_type"]'), ['3', '0', '-3', '0', '-3', '-3']);
        assert.deepEqual(cells('[data-meal-report-kind="day_total"]'), ['3', '0', '-3', '0', '-3', '-3']);
    } finally { app.unmount(); }
});

test('empty report has no export control and configured zero meals render a complete group', async () => {
    const { app, props } = mount([]);
    try {
        await tick();
        assert.match(document.body.textContent, /No meals yet\./);
        assert.equal(document.querySelector('table, a, button, [role="tooltip"], [data-day-total-card]'), null);
        const zero = totals({ assigned: 0, projected: 0, used: 0, remaining: 0, extras: 0, total: 0 });
        props.meals.days = [day({ meal_types: [group({ meals: [row(zero)], totals: zero })], totals: zero })];
        await tick();
        assert.equal(document.querySelectorAll('[data-meal-report-kind="meal"]').length, 1);
        assert.deepEqual(cells('[data-meal-report-kind="meal"]'), ['0', '0', '-0', '0', '-0', '0']);
        assert.equal(document.querySelectorAll('[data-meal-type-card]').length, 1);
        assert.equal(document.querySelector('a, button'), null);
    } finally { app.unmount(); }
});

test('names render as text and refreshed report data replaces headings, groups and both layouts counts', async () => {
    const name = '<img src=x onerror=alert(1)>';
    const { app, props } = mount([day({ meal_types: [group({ meal_type: name, meals: [row({ meal_name: name, meal_type: name })] })] })]);
    try {
        await tick();
        assert.equal(document.querySelector('img'), null);
        assert.match(document.querySelector('tbody').textContent, /<img src=x onerror=alert\(1\)>/);
        assert.match(document.querySelector('[data-meal-type-card]').textContent, /<img src=x onerror=alert\(1\)>/);
        const changed = totals({ assigned: 4, projected: 2, used: 3, remaining: 0, extras: 2, total: -1 });
        props.meals.days = [day({ date: '2026-10-03', meal_types: [group({ meals: [row({ date: '2026-10-03', ...changed })], totals: changed })], totals: changed })];
        await tick();
        assert.equal(document.querySelectorAll('[data-meal-report-day]').length, 1);
        assert.match(document.querySelector('[data-meal-report-day]').textContent, /Sat, Oct 3/);
        assert.deepEqual(cells('[data-meal-report-kind="meal"]'), ['4', '2', '-3', '0', '-2', '-1']);
        assert.equal(labelledCounts(document.querySelector('[data-day-total-card]')).Total, '-1');
        props.meals.days = [];
        await tick();
        assert.equal(document.querySelector('table, [data-meal-type-card]'), null);
        assert.match(document.body.textContent, /No meals yet\./);
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
