import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { JSDOM } from 'jsdom';
import { parse, compileScript } from '@vue/compiler-sfc';

const dom = new JSDOM('<div id="app"></div>', { url: 'http://localhost' });
for (const key of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node', 'Option', 'DocumentFragment', 'getComputedStyle']) globalThis[key] = dom.window[key];
Object.defineProperty(globalThis, 'navigator', { value: dom.window.navigator, configurable: true });
const { createApp, h, nextTick } = await import('vue');
const require = createRequire(import.meta.url);
const locale = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'));
globalThis.memberMealsTranslate = (key, values = {}) => Object.entries(values).reduce((text, [name, value]) => text.replaceAll(`:${name}`, value), locale[key] ?? key);
const compile = async (path, replacements = []) => {
    const { descriptor } = parse(readFileSync(new URL(path, import.meta.url), 'utf8'));
    let source = compileScript(descriptor, { id: path, inlineTemplate: true }).content
        .replaceAll(/from ['"]vue['"]/g, `from '${pathToFileURL(require.resolve('vue')).href}'`)
        .replace(/import \{ cn \} from ['"].*?['"];?/, 'const cn = (...values) => values.filter(Boolean).join(" ");')
        .replace(/import \{ Icon \} from ['"].*?['"];?/, 'const Icon = { render: () => null };');
    for (const [pattern, replacement] of replacements) source = source.replace(pattern, replacement);
    return `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
};
const box = (tag = 'div') => ({ setup: (_, { slots }) => () => h(tag, slots.default?.({ id: 'field' })) });
globalThis.memberMealsBox = box;
globalThis.memberMealsCore = (await import('datatables.net-dt')).default;
globalThis.memberMealsTable = (await import('datatables.net-vue3')).default.default;
const dataTable = await compile('../../resources/js/components/ui/data-table/DataTable.vue', [
    [/import DataTablesCore from ['"].*?['"];?/, 'const DataTablesCore = globalThis.memberMealsCore;'],
    [/import DataTablesVue from ['"].*?['"];?/, 'const DataTablesVue = globalThis.memberMealsTable;'],
    [/import \{[^}]*\} from ['"]laravel-vue-i18n['"];?/, 'const currentLocale = "en", getActiveLanguage = () => "en", isLoaded = () => true, loadLanguageAsync = async () => {}; const trans = globalThis.memberMealsTranslate;'],
]);
const component = await compile('../../resources/js/components/team/TeamMemberMeals.vue', [
    [/import \{ DataTable \} from ['"].*?['"];?/, `import DataTable from '${dataTable}';`],
    [/import \{ Badge \} from ['"].*?['"];?/, 'const Badge = globalThis.memberMealsBox("span");'],
    [/import \{ Card, CardTitle \} from ['"].*?['"];?/, 'const Card = globalThis.memberMealsBox(); const CardTitle = globalThis.memberMealsBox("h2");'],
    [/from ['"]\.\.\/\.\.\/lib\/mealDates['"]/, `from '${new URL('../../resources/js/lib/mealDates.js', import.meta.url).href}'`],
    [/import \{ getActiveLanguage, trans \} from ['"]laravel-vue-i18n['"];?/, 'const trans = globalThis.memberMealsTranslate; const getActiveLanguage = () => "en";'],
]);
const { default: TeamMemberMeals } = await import(component);
const tick = async () => { await new Promise((resolve) => setTimeout(resolve, 20)); await nextTick(); };
const row = (id) => ({ assignment_id: id, name: `Lunch ${id}`, type: 'Lunch', date: '2026-10-02', starts_at: '12:00', ends_at: '14:00', source_shift_id: id, source_removed: false, shift_location: 'Gate', shift_start: '2026-10-02T09:00', shift_end: '2026-10-02T21:00', used: false, used_at: null });
const day = (rows) => ({ date: rows[0]?.date ?? '2026-10-02', rows, counts: [{ type: 'Lunch', total: rows.length, used: rows.filter((meal) => meal.used).length }] });
const mount = (days) => {
    const app = createApp(TeamMemberMeals, { meals: { days } });
    app.config.globalProperties.$t = globalThis.memberMealsTranslate;
    app.mount(document.getElementById('app'));
    return app;
};

test('daily tables render duplicate grants, used snapshots and overnight dates read-only', async () => {
    const rows = [row(1), { ...row(2), name: 'Lunch 1', used: true, used_at: '13:05', source_removed: true }, { ...row(3), starts_at: '23:30', ends_at: '00:30' }];
    const app = mount([day(rows), day([{ ...row(4), date: '2026-10-04', source_shift_id: null }])]);
    try {
        await tick();
        assert.equal(document.querySelectorAll('table').length, 2);
        assert.equal(document.querySelectorAll('tbody tr').length, 4);
        assert.doesNotMatch(document.body.textContent, /Lunch ×/);
        assert.match(document.body.textContent, /Used 13:05/);
        assert.match(document.body.textContent, /Not used/);
        assert.match(document.body.textContent, /Gate, Fri 09:00–21:00/);
        assert.match(document.body.textContent, /from a shift they're no longer on/);
        assert.match(document.body.textContent, /ends Sat/);
        assert.match(document.body.textContent, /Direct assignment/);
        assert.equal(document.querySelectorAll('button, input, a').length, 0);
        assert.equal(document.querySelector('.dt-paging'), null);
    } finally { app.unmount(); }
});

test('client pagination displays ten meals and the remaining meals on the second page', async () => {
    const app = mount([day(Array.from({ length: 12 }, (_, index) => row(index + 1)))]);
    try {
        await tick();
        assert.equal(document.querySelectorAll('tbody tr').length, 10);
        document.querySelector('button[data-dt-idx="next"]').click();
        await tick();
        assert.equal(document.querySelectorAll('tbody tr').length, 2);
        assert.match(document.querySelector('tbody').textContent, /Lunch 11/);
        assert.match(document.querySelector('tbody').textContent, /Lunch 12/);
        assert.equal(document.querySelector('input, select'), null);
    } finally { app.unmount(); }
});

test('empty member shows plain empty copy with no table or actions', async () => {
    const app = mount([]);
    try {
        await tick();
        assert.match(document.body.textContent, /No meals yet/);
        assert.match(document.body.textContent, /Read-only: meals are claimed on the Kitchen page/);
        assert.equal(document.querySelector('table, button, input'), null);
    } finally { app.unmount(); }
});

// Mount the actual parent page so saved status and permission changes exercise
// its visibility condition, including stale props retained by Inertia.
globalThis.memberMealsForm = null;
const memberPage = await compile('../../resources/js/pages/Team/Member.vue', [
    [/import TeamMemberMeals from ['"].*?['"];?/, `import TeamMemberMeals from '${component}';`],
    [/import \{ Link, useForm \} from ['"]@inertiajs\/vue3['"];?/, `import { reactive } from '${pathToFileURL(require.resolve('vue')).href}'; const Link = 'a'; const useForm = (values) => (globalThis.memberMealsForm = reactive({ ...values, errors: {} }));`],
    [/import \{ trans, transChoice \} from ['"]laravel-vue-i18n['"];?/, 'const trans = globalThis.memberMealsTranslate; const transChoice = trans;'],
    [/import \{ useFlashToast \} from ['"].*?['"];?/, 'const useFlashToast = () => ({ showError() {}, showFormError() {} });'],
    [/import \{ toastFormErrors \} from ['"].*?['"];?/, 'const toastFormErrors = () => {};'],
    [/import ([A-Za-z]+) from ['"]\.\.\/[^'"]+['"];?/g, (_, name) => `const ${name} = globalThis.memberMealsBox();`],
    [/import \{ ([^}]+) \} from ['"]\.\.\/\.\.\/components\/ui\/[^'"]+['"];?/g, (_, names) => names.split(',').map((name) => `const ${name.trim()} = globalThis.memberMealsBox();`).join('\n')],
]);
const { default: Member } = await import(memberPage);
const { reactive } = await import('vue');

test('parent uses saved hired status and removes the card after permissions are revoked even with stale meals', async () => {
    const props = reactive({
        engagement: { id: 1, name: 'Ava Lee', status: 'hired', employment_type: 'volunteer', pass_assignments: [] },
        event: { id: 7 }, groups: [], statuses: [], employmentTypes: [], roles: [],
        canWrite: false, canAddNotes: false, canChangeRole: false, canReadNotes: false, canReadMeals: true,
        meals: { days: [day([row(1)])] },
    });
    const app = createApp({ setup: () => () => h(Member, props) });
    app.config.globalProperties.$t = globalThis.memberMealsTranslate;
    app.config.globalProperties.$page = { props: { permissions: {}, auth: { user: { id: 2 } } } };
    app.mount(document.getElementById('app'));
    try {
        await tick();
        assert.ok(document.getElementById('meals'));
        globalThis.memberMealsForm.status = 'declined';
        await tick();
        assert.ok(document.getElementById('meals'));
        props.canReadMeals = false;
        await tick();
        assert.equal(document.getElementById('meals'), null);
        props.canReadMeals = true;
        props.engagement.status = 'applied';
        globalThis.memberMealsForm.status = 'hired';
        await tick();
        assert.equal(document.getElementById('meals'), null);
    } finally { app.unmount(); }
});
