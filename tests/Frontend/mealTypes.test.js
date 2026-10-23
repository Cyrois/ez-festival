import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import test from 'node:test';
import { compileScript, parse } from '@vue/compiler-sfc';
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<main id="app"></main>', { url: 'http://localhost' });
for (const key of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node', 'Option', 'DocumentFragment', 'getComputedStyle']) globalThis[key] = dom.window[key];
Object.defineProperty(globalThis, 'navigator', { value: dom.window.navigator, configurable: true });
window.performance.getEntriesByType = () => [];
const { createApp, h, nextTick, reactive } = await import('vue');
const require = createRequire(import.meta.url);
const vue = pathToFileURL(require.resolve('vue')).href;
const encode = (source) => `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
const locale = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'));
const translate = (key) => locale[key] ?? key;
globalThis.mealTypesTranslate = translate;
globalThis.mealTypesPage = reactive({ url: '/meals/settings', props: { auth: { user: { id: 7 } }, activeEvent: { id: 11 } } });
globalThis.mealTypesCore = (await import('datatables.net-dt')).default;
globalThis.mealTypesVue = (await import('datatables.net-vue3')).default.default;
globalThis.mealTypesLayout = { inheritAttrs: false, setup: (_, { slots }) => () => h('main', slots.default?.()) };
let mealReloads = 0;
globalThis.mealTypesMealsCard = { setup: (_, { expose }) => { expose({ reload: () => mealReloads++ }); return () => null; } };
let form;
const submissions = [];
const errors = [];
globalThis.mealTypesUseForm = (initial) => {
    form = reactive({ ...initial, processing: false, errors: {},
        reset() { Object.assign(this, initial); },
        clearErrors() { this.errors = {}; },
        post(url, options) { submissions.push({ method: 'post', url, options }); },
        put(url, options) { submissions.push({ method: 'put', url, options }); },
    });
    return form;
};
globalThis.mealTypesShowError = (error) => errors.push(error);
const compile = (path, replacements = []) => {
    const { descriptor } = parse(readFileSync(new URL(`../../resources/js/${path}.vue`, import.meta.url), 'utf8'));
    let source = compileScript(descriptor, { id: path, inlineTemplate: true }).content
        .replaceAll(/from ['"]vue['"]/g, `from '${vue}'`)
        .replace(/import \{ cn \} from ['"].*?['"];?/, 'const cn = (...values) => values.filter(Boolean).join(" ");')
        .replace(/import \{ Icon \} from ['"].*?['"];?/, 'const Icon = { render: () => null };')
        .replace(/import \{ trans \} from ['"]laravel-vue-i18n['"];?/, 'const trans = globalThis.mealTypesTranslate;');
    for (const [pattern, replacement] of replacements) source = source.replace(pattern, replacement);
    return encode(source);
};
const input = compile('components/ui/input/Input');
const field = compile('components/ui/form-field/FormField');
const title = compile('components/ui/card/CardTitle');
const card = compile('components/ui/card/Card', [[/import CardTitle from ['"].*?['"];?/, `import CardTitle from '${title}';`]]);
const button = compile('components/ui/button/Button', [
    [/import \{ Link \} from ['"].*?['"];?/, 'const Link = "a";'],
    [/from ['"]\.\/buttonVariants['"]/, `from '${new URL('../../resources/js/components/ui/button/buttonVariants.js', import.meta.url).href}'`],
]);
const dialog = compile('components/ui/dialog/Dialog', [[/import \{ Button \} from ['"].*?['"];?/, `import Button from '${button}';`]]);
const table = compile('components/ui/data-table/DataTable', [
    [/import DataTablesCore from ['"].*?['"];?/, 'const DataTablesCore = globalThis.mealTypesCore;'],
    [/import DataTablesVue from ['"].*?['"];?/, 'const DataTablesVue = globalThis.mealTypesVue;'],
    [/import \{[^}]*\} from ['"]laravel-vue-i18n['"];?/, 'const currentLocale = "en", getActiveLanguage = () => "en", isLoaded = () => true, loadLanguageAsync = async () => {}; const trans = globalThis.mealTypesTranslate;'],
]);
const landing = encode(readFileSync(new URL('../../resources/js/composables/useLandingState.js', import.meta.url), 'utf8')
    .replace(/from ['"]vue['"]/g, `from '${vue}'`)
    .replace(/import \{ usePage \} from ['"].*?['"];?/, 'const usePage = () => globalThis.mealTypesPage;'));
const source = compile('pages/Kitchen/Settings', [
    [/import AppLayout from ['"].*?['"];?/, 'const AppLayout = globalThis.mealTypesLayout;'],
    [/import MealsCard from ['"].*?['"];?/, 'const MealsCard = globalThis.mealTypesMealsCard;'],
    [/import \{ Button \} from ['"].*?['"];?/, `import Button from '${button}';`],
    [/import \{ Card, CardTitle \} from ['"].*?['"];?/, `import Card from '${card}'; import CardTitle from '${title}';`],
    [/import \{ DataTable \} from ['"].*?['"];?/, `import DataTable from '${table}';`],
    [/import \{ Dialog \} from ['"].*?['"];?/, `import Dialog from '${dialog}';`],
    [/import \{ FormField \} from ['"].*?['"];?/, `import FormField from '${field}';`],
    [/import \{ Input \} from ['"].*?['"];?/, `import Input from '${input}';`],
    [/import \{ useForm \} from ['"].*?['"];?/, 'const useForm = globalThis.mealTypesUseForm;'],
    [/import \{ useFlashToast \} from ['"].*?['"];?/, 'const useFlashToast = () => ({ showError: globalThis.mealTypesShowError, showFormError: globalThis.mealTypesShowError });'],
    [/import \{ toastFormErrors \} from ['"].*?['"];?/, 'const toastFormErrors = (_form, errors, toast) => toast.showFormError(errors);'],
    [/from ['"]\.\.\/\.\.\/lib\/dataTableRowNavigation['"]/, `from '${new URL('../../resources/js/lib/dataTableRowNavigation.js', import.meta.url).href}'`],
    [/from ['"]\.\.\/\.\.\/composables\/useLandingState['"]/, `from '${landing}'`],
]);
const { default: Settings } = await import(source);
const defaults = () => [
    { id: 4, name: 'Midnight', starts_at: '23:00', ends_at: '01:00' },
    { id: 3, name: 'Dinner', starts_at: '17:00', ends_at: '20:00' },
    { id: 2, name: 'Lunch', starts_at: '11:00', ends_at: '14:00' },
    { id: 1, name: 'Breakfast', starts_at: '07:00', ends_at: '10:00' },
];
let rows = defaults();
const requests = [];
// Exercise the real server-side DataTable while replacing only the HTTP transport.
globalThis.mealTypesCore.util.ajax = (options) => {
    assert.equal(options.url, '/meals/settings/meal-types');
    const request = structuredClone(options.data);
    requests.push(request);
    let data = rows.filter((row) => row.name.toLowerCase().includes(request.search.value.toLowerCase()));
    const count = data.length;
    const order = request.order[0];
    const key = order?.column === 0 ? 'name' : 'starts_at';
    data = data.toSorted((a, b) => a[key].localeCompare(b[key]) * (order?.dir === 'desc' ? -1 : 1));
    options.success({ draw: request.draw, recordsTotal: rows.length, recordsFiltered: count, data: data.slice(request.start, request.start + request.length) });
    return { abort() {} };
};
const tick = async () => { await new Promise((resolve) => setTimeout(resolve, 20)); await nextTick(); };
const mount = (props = {}) => {
    const app = createApp(Settings, { event: { id: 11, name: 'Festival', is_locked: false }, canEdit: true, mealCount: 0, ...props });
    app.config.globalProperties.$t = translate;
    app.mount(document.getElementById('app'));
    return app;
};
const names = () => [...document.querySelectorAll('tbody tr')].map((row) => row.cells[0].textContent.trim());
const clickText = (label) => [...document.querySelectorAll('button')].find((button) => button.textContent.trim() === label).click();
const edit = async (name) => {
    [...document.querySelectorAll('tbody tr')].find((row) => row.cells[0].textContent.trim() === name).querySelector('button').click();
    await tick();
};
const sort = async (column) => { document.querySelectorAll('thead th')[column].click(); await tick(); };
const api = () => globalThis.mealTypesCore.tables({ api: true });
const reset = () => {
    window.sessionStorage.clear();
    globalThis.mealTypesPage.url = '/meals/settings';
    rows = defaults();
    requests.length = submissions.length = errors.length = 0;
    mealReloads = 0;
};

test('list starts in time order and labels only overnight windows, including an end at midnight', async () => {
    reset();
    rows.push({ id: 5, name: 'Late snack', starts_at: '22:00', ends_at: '00:00' });
    const app = mount();
    try {
        await tick();
        assert.deepEqual(names(), ['Breakfast', 'Lunch', 'Dinner', 'Late snack', 'Midnight']);
        for (const row of document.querySelectorAll('tbody tr')) {
            assert.equal(row.cells[1].textContent.includes('ends next day'), ['Late snack', 'Midnight'].includes(row.cells[0].textContent.trim()));
        }
    } finally { app.unmount(); }
});

test('rename sentence appears under Name in Edit and is absent from Add', async () => {
    reset();
    const app = mount();
    try {
        await tick();
        clickText('Add type'); await tick();
        assert.doesNotMatch(document.querySelector('[role="dialog"]').textContent, /Renaming keeps/);
        clickText('Cancel'); await tick();
        await edit('Lunch');
        const name = document.querySelector('[role="dialog"] input:not([type])');
        assert.equal(name.value, 'Lunch');
        assert.match(name.parentElement.textContent, /Renaming keeps everything that uses this meal type\./);
    } finally { app.unmount(); }
});

test('successful Add clears temporary name sorting in a single reload and inserts the type by start', async () => {
    reset();
    const app = mount();
    try {
        await tick(); await sort(0);
        assert.deepEqual(names(), ['Breakfast', 'Dinner', 'Lunch', 'Midnight']);
        clickText('Add type'); await tick();
        Object.assign(form, { name: 'Brunch', starts_at: '10:00', ends_at: '11:00' });
        clickText('Save');
        assert.equal(submissions[0].method, 'post');
        assert.equal(submissions[0].url, '/events/11/meals/types');
        rows.push({ id: 5, name: form.name, starts_at: form.starts_at, ends_at: form.ends_at });
        const before = requests.length;
        await submissions[0].options.onSuccess(); await tick();
        assert.equal(requests.length, before + 1);
        assert.deepEqual(names(), ['Breakfast', 'Brunch', 'Lunch', 'Dinner', 'Midnight']);
        assert.equal(document.querySelector('[role="dialog"]'), null);
        assert.equal(mealReloads, 1);
    } finally { app.unmount(); }
});

test('successful Edit resets time sorting and updates overnight text in both directions', async () => {
    reset();
    const app = mount();
    try {
        await tick();
        for (const [starts_at, ends_at, overnight] of [['01:00', '02:00', false], ['23:00', '01:00', true]]) {
            await sort(1);
            assert.equal(requests.at(-1).order[0].dir, 'desc');
            await edit('Midnight');
            Object.assign(form, { starts_at, ends_at });
            clickText('Save');
            const saved = rows.find((row) => row.id === 4);
            Object.assign(saved, { starts_at, ends_at });
            const submission = submissions.at(-1);
            assert.equal(submission.method, 'put');
            assert.equal(submission.url, '/events/11/meals/types/4');
            const before = requests.length;
            await submission.options.onSuccess(); await tick();
            assert.equal(requests.length, before + 1);
            assert.equal(requests.at(-1).order[0].dir, 'asc');
            assert.equal(names().indexOf('Midnight'), overnight ? 3 : 0);
            const cell = [...document.querySelectorAll('tbody tr')].find((row) => row.cells[0].textContent.trim() === 'Midnight').cells[1];
            assert.equal(cell.textContent.includes('ends next day'), overnight);
        }
    } finally { app.unmount(); }
});

test('a failed save retains the dialog, entered values and temporary header order', async () => {
    reset();
    const app = mount();
    try {
        await tick(); await sort(0); await edit('Lunch');
        form.name = 'Changed';
        clickText('Save');
        const before = requests.length;
        form.errors = { ends_at: 'This window overlaps Dinner.' };
        submissions[0].options.onError(form.errors); await tick();
        assert.equal(requests.length, before);
        assert.deepEqual(names(), ['Breakfast', 'Dinner', 'Lunch', 'Midnight']);
        assert.ok(document.querySelector('[role="dialog"]'));
        assert.equal(form.name, 'Changed');
        assert.match(document.querySelector('[role="alert"]').textContent, /overlaps Dinner/);
        assert.equal(errors.length, 1);
    } finally { app.unmount(); }
});

test('headers sort temporarily but both direct and restored openings return to time order', async () => {
    reset();
    for (const url of ['/meals/settings', '/meals/settings?restore=1']) {
        let app = mount();
        try { await tick(); await sort(0); assert.equal(names()[1], 'Dinner'); }
        finally { app.unmount(); }
        globalThis.mealTypesPage.url = url;
        app = mount();
        try { await tick(); assert.deepEqual(names(), ['Breakfast', 'Lunch', 'Dinner', 'Midnight']); }
        finally { app.unmount(); }
    }
});

test('restoring ignores saved order while retaining search and pagination', async () => {
    reset();
    rows = Array.from({ length: 30 }, (_, i) => ({ id: i + 1, name: `Meal ${String(30 - i).padStart(2, '0')}`, starts_at: `12:${String(i).padStart(2, '0')}`, ends_at: '13:00' }));
    let app = mount();
    try {
        await tick();
        const search = document.querySelector('input[type="search"]');
        search.value = 'Meal'; search.dispatchEvent(new window.Event('input', { bubbles: true }));
        await new Promise((resolve) => setTimeout(resolve, 320)); await tick();
        await sort(0);
        api().page(1).draw('page'); await tick();
    } finally { app.unmount(); }
    globalThis.mealTypesPage.url = '/meals/settings?restore=1';
    app = mount();
    try {
        await tick();
        assert.equal(document.querySelector('input[type="search"]').value, 'Meal');
        assert.equal(requests.at(-1).start, 25);
        assert.deepEqual(requests.at(-1).order, [{ column: 1, dir: 'asc', name: '' }]);
        assert.deepEqual(names(), ['Meal 05', 'Meal 04', 'Meal 03', 'Meal 02', 'Meal 01']);
    } finally { app.unmount(); }
});

for (const [name, props] of [['read-only', { canEdit: false }], ['locked', { event: { id: 11, name: 'Festival', is_locked: true } }]]) {
    test(`${name} list displays overnight text and refuses Add/Edit`, async () => {
        reset();
        const app = mount(props);
        try {
            await tick();
            assert.match(document.querySelector('tbody').textContent, /ends next day/);
            clickText('Add type');
            document.querySelector('tbody button').click();
            document.querySelector('tbody tr').click(); await tick();
            assert.equal(document.querySelector('[role="dialog"]'), null);
            assert.equal(submissions.length, 0);
        } finally { app.unmount(); }
    });
}
