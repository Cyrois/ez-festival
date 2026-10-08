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
globalThis.kitchenTranslate = (key, values = {}) => Object.entries(values).reduce((text, [name, value]) => text.replaceAll(`:${name}`, value), locale[key] ?? key);
const compile = async (path, replacements = []) => {
    const { descriptor } = parse(readFileSync(new URL(path, import.meta.url), 'utf8'));
    let source = compileScript(descriptor, { id: path, inlineTemplate: true }).content
        .replaceAll(/from ['"]vue['"]/g, `from '${pathToFileURL(require.resolve('vue')).href}'`)
        .replace(/import \{ cn \} from ['"].*?['"];?/, 'const cn = (...values) => values.filter(Boolean).join(" ");')
        .replace(/import \{ Icon \} from ['"].*?['"];?/, 'const Icon = { render: () => null };');
    for (const [pattern, replacement] of replacements) source = source.replace(pattern, replacement);
    return `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
};
const button = await compile('../../resources/js/components/ui/button/Button.vue', [
    [/import \{ Link \} from ['"].*?['"];?/, "const Link = 'a';"],
    [/import \{ buttonVariants \} from ['"].*?['"];?/, 'const buttonVariants = () => "";'],
]);
const input = await compile('../../resources/js/components/ui/input/Input.vue');
const dialog = await compile('../../resources/js/components/ui/dialog/Dialog.vue', [
    [/import \{ Button \} from ['"].*?['"];?/, `import Button from '${button}';`],
]);
const box = (tag = 'div') => ({ setup: (_, { slots }) => () => h(tag, slots.default?.()) });
globalThis.kitchenBox = box;
globalThis.kitchenAvatar = { props: ['name'], setup: (props) => () => h('span', props.name) };
// Exercise the real DataTables Vue integration and its Ajax/reload/slot behavior.
globalThis.kitchenCore = (await import('datatables.net-dt')).default;
globalThis.kitchenTable = (await import('datatables.net-vue3')).default.default;
const dataTable = await compile('../../resources/js/components/ui/data-table/DataTable.vue', [
    [/import DataTablesCore from ['"].*?['"];?/, 'const DataTablesCore = globalThis.kitchenCore;'],
    [/import DataTablesVue from ['"].*?['"];?/, 'const DataTablesVue = globalThis.kitchenTable;'],
    [/import \{[^}]*\} from ['"]laravel-vue-i18n['"];?/, 'const currentLocale = "en", getActiveLanguage = () => "en", isLoaded = () => true, loadLanguageAsync = async () => {}; const trans = globalThis.kitchenTranslate;'],
]);
const page = await compile('../../resources/js/pages/Kitchen/Index.vue', [
    [/import AppLayout from ['"].*?['"];?/, 'const AppLayout = globalThis.kitchenBox();'],
    [/import \{ Button \} from ['"].*?['"];?/, `import Button from '${button}';`],
    [/import \{ Input \} from ['"].*?['"];?/, `import Input from '${input}';`],
    [/import \{ Dialog \} from ['"].*?['"];?/, `import Dialog from '${dialog}';`],
    [/import \{ DataTable \} from ['"].*?['"];?/, `import DataTable from '${dataTable}';`],
    [/import \{ Avatar \} from ['"].*?['"];?/, 'const Avatar = globalThis.kitchenAvatar;'],
    [/import \{ Badge \} from ['"].*?['"];?/, 'const Badge = globalThis.kitchenBox("span");'],
    [/import \{ Card, CardTitle \} from ['"].*?['"];?/, 'const Card = globalThis.kitchenBox(); const CardTitle = globalThis.kitchenBox("h2");'],
    [/import \{ EmptyState \} from ['"].*?['"];?/, 'const EmptyState = { props: ["title"], setup: (props) => () => props.title };'],
    [/import \{ Tooltip \} from ['"].*?['"];?/, 'const Tooltip = globalThis.kitchenBox("span");'],
    [/import \{ useFlashToast \} from ['"].*?['"];?/, 'const useFlashToast = () => globalThis.kitchenToasts;'],
    [/import \{ xsrfToken \} from ['"].*?['"];?/, 'const xsrfToken = () => "test-csrf";'],
    [/from ['"]\.\.\/\.\.\/lib\/mealDates['"]/, `from '${new URL('../../resources/js/lib/mealDates.js', import.meta.url).href}'`],
    [/import \{ getActiveLanguage, trans \} from ['"]laravel-vue-i18n['"];?/, 'const trans = globalThis.kitchenTranslate; const getActiveLanguage = () => "en";'],
]);
const { default: Kitchen } = await import(page);
const tick = async () => { await new Promise((resolve) => setTimeout(resolve, 20)); await nextTick(); };
const person = { id: 3, name: 'Ava Lee', type: 'Volunteer', codes: ['WB-123'] };
const row = (shift = 10) => ({ meal_id: 4, source_shift_id: shift, name: 'Fri Lunch', date: '2026-10-01', type: 'Lunch', starts_at: '12:00', ends_at: '14:00', shift_location: 'Gate', shift_start: '2026-10-01T09:00', shift_end: '2026-10-01T16:00', used: false, claim_id: null, used_at: null });
const mount = ({ canClaim = true, rows = [row(), row(11)], matched = false, reply = null, unclaimReply = null } = {}) => {
    const requests = [], successes = [], errors = [];
    globalThis.kitchenToasts = { showSuccess: (message) => successes.push(message), showError: (message) => errors.push(message), showFormError: (message) => errors.push(message) };
    globalThis.fetch = async (url, options = {}) => {
        requests.push({ url, ...options });
        if (options.method === 'DELETE') {
            const body = JSON.parse(options.body);
            const result = unclaimReply ? unclaimReply(body, rows) : { status: 200, data: { status: 'unclaimed', message: 'Unclaimed' } };
            if (result.status === 200) Object.assign(rows.find((meal) => meal.claim_id === body.claim_id), { used: false, used_at: null, claim_id: null });
            return { ok: result.status < 400, status: result.status, json: async () => result.data };
        }
        if (options.method === 'POST') {
            const body = JSON.parse(options.body);
            const result = reply ? reply(body, rows) : { status: 201, data: { status: 'claimed', message: 'Claimed', claim_id: 51 } };
            if (result.status === 201) Object.assign(rows.find((meal) => meal.source_shift_id === body.source_shift_id), { used: true, used_at: '13:05', claim_id: 51 });
            return { ok: result.status < 400, status: result.status, json: async () => result.data };
        }
        if (url.startsWith('/meals/people?')) return { ok: true, json: async () => ({ people: [person], total: 1, page: 1, last_page: 1, matched_id: matched ? person.id : null }) };
        return { ok: true, json: async () => ({ draw: Number(new URL(url, 'http://localhost').searchParams.get('draw')), recordsTotal: rows.length, recordsFiltered: rows.length, today: '2026-10-01', can_claim: canClaim, is_locked: false, person: { ...person, status: 'Hired' }, counts: rows.length ? [{ date: '2026-10-01', type_id: 1, type: 'Lunch', total: rows.length, left: rows.filter((meal) => !meal.used).length }] : [], data: rows }) };
    };
    const app = createApp(Kitchen, { event: { id: 7, timezone: 'America/Vancouver', is_locked: false } });
    app.config.globalProperties.$t = globalThis.kitchenTranslate;
    app.mount(document.getElementById('app'));
    return { app, requests, successes, errors };
};
const enter = async (value) => {
    const input = document.querySelector('input'); input.value = value;
    input.dispatchEvent(new window.Event('input', { bubbles: true }));
    await new Promise((resolve) => setTimeout(resolve, 280)); await tick();
};
const buttonNamed = (name) => [...document.querySelectorAll('button')].find((button) => button.textContent.trim() === name);
const pick = async () => { document.querySelector('button[aria-pressed]').click(); await tick(); };

for (const matched of [false, true]) {
    test(`submitting ${matched ? 'a barcode' : 'a name'} before the search delay finishes displays results without getting stuck loading`, async () => {
        const state = mount({ matched });
        try {
            const input = document.querySelector('input');
            input.value = matched ? 'WB-123' : 'Ava';
            input.dispatchEvent(new window.Event('input', { bubbles: true }));
            await nextTick();
            document.querySelector('form').dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
            await new Promise((resolve) => setTimeout(resolve, 300));
            await tick();
            assert.doesNotMatch(document.body.textContent, /Loading records/);
            assert.equal(state.requests.filter((request) => request.url.startsWith('/meals/people?')).length, 1);
            assert.ok(document.querySelector('button[aria-pressed]'));
            if (!matched) {
                assert.equal(document.querySelector('table'), null);
                await pick();
            }
            assert.equal(document.querySelectorAll('tbody tr').length, 2);
            assert.deepEqual(state.errors, []);
        } finally { state.app.unmount(); }
    });
}

test('one name result waits for selection; Claim uses Ajax and retains search while refreshing status, counts and highlight', async () => {
    const state = mount();
    try {
        await enter('Ava');
        assert.equal(document.querySelector('table'), null);
        await pick();
        assert.equal(document.querySelectorAll('tbody tr').length, 2);
        // Clicking the selected person again refreshes without losing the header or claim buttons.
        await pick();
        assert.ok(document.querySelector('h2').textContent);
        assert.ok(buttonNamed('Claim'));
        assert.match(document.body.textContent, /2 today, 2 left/);
        buttonNamed('Claim').click(); await tick(); await tick();
        assert.match(document.body.textContent, /Used 13:05/);
        assert.match(document.body.textContent, /2 today, 1 left/);
        assert.equal(document.querySelector('input').value, 'Ava');
        assert.ok(document.querySelector('tr.bg-success\\/10'));
        assert.deepEqual(state.successes, ['Claimed']);
        const request = state.requests.find((request) => request.method === 'POST');
        assert.equal(request.headers['X-XSRF-TOKEN'], 'test-csrf');
        assert.deepEqual(JSON.parse(request.body), { meal_id: 4, source_shift_id: 10, confirm_warning: false });
    } finally { state.app.unmount(); }
});

test('exact barcode auto-opens; same-type warning is titleless, Cancel does nothing, Claim anyway sends explicit confirmation', async () => {
    const state = mount({ matched: true, reply: (body) => body.confirm_warning ? { status: 201, data: { status: 'claimed', message: 'Claimed', claim_id: 51 } } : { status: 409, data: { status: 'warning_required', message: 'Already had Lunch. Claim another Lunch?' } } });
    try {
        await enter('WB-123'); await tick();
        assert.ok(document.querySelector('table'));
        buttonNamed('Claim').click(); await tick();
        assert.ok(document.querySelector('[role="alertdialog"]'));
        assert.equal(document.querySelector('[role="alertdialog"] h2'), null);
        buttonNamed('Cancel').click(); await tick();
        assert.equal(state.requests.filter((request) => request.method === 'POST').length, 1);
        assert.equal(document.querySelector('[role="alertdialog"]'), null);
        buttonNamed('Claim').click(); await tick();
        buttonNamed('Claim anyway').click(); await tick(); await tick();
        assert.equal(JSON.parse(state.requests.filter((request) => request.method === 'POST').at(-1).body).confirm_warning, true);
        assert.equal(document.querySelector('[role="alertdialog"]'), null);
    } finally { state.app.unmount(); }
});

test('conflict renders a red alert and refreshes stale rows without reporting success', async () => {
    const state = mount({ matched: true, reply: (_, rows) => {
        Object.assign(rows[0], { used: true, used_at: '12:31', claim_id: 99 });
        return { status: 409, data: { status: 'already_used', message: 'This meal was already used at 12:31.' } };
    } });
    try {
        await enter('WB-123'); await tick();
        buttonNamed('Claim').click(); await tick(); await tick();
        assert.match(document.querySelector('[role="alert"]').textContent, /already used at 12:31/);
        assert.match(document.querySelector('table').textContent, /Used 12:31/);
        assert.deepEqual(state.successes, []);
    } finally { state.app.unmount(); }
});

test('View-only displays disabled Claim controls and no-meal result has no count chips', async () => {
    let state = mount({ canClaim: false, matched: true });
    try {
        await enter('WB-123'); await tick();
        assert.ok(buttonNamed('Claim').disabled);
        assert.match(buttonNamed('Claim').parentElement.getAttribute('label'), /You need Claim meals/);
        assert.equal(state.requests.filter((request) => request.method === 'POST').length, 0);
        const input = document.querySelector('input'); input.value = '';
        input.dispatchEvent(new window.Event('input', { bubbles: true })); await tick();
        assert.equal(document.querySelector('table'), null);
        assert.match(document.body.textContent, /Find a Team member/);
    } finally { state.app.unmount(); }
    state = mount({ rows: [], matched: true });
    try {
        await enter('WB-123'); await tick();
        assert.match(document.body.textContent, /No meals today/);
        assert.doesNotMatch(document.body.textContent, /today, .* left/);
    } finally { state.app.unmount(); }
});

test('Unclaim on the right restores status and counts without clearing the selected person or search', async () => {
    const used = { ...row(), used: true, used_at: '13:05', claim_id: 51 };
    const state = mount({ rows: [used, row(11)], matched: true });
    try {
        await enter('WB-123'); await tick();
        assert.match(document.body.textContent, /2 today, 1 left/);
        assert.equal(buttonNamed('Unclaim').closest('td'), document.querySelector('tbody tr').lastElementChild);
        buttonNamed('Unclaim').click(); await tick(); await tick();
        assert.match(document.body.textContent, /2 today, 2 left/);
        assert.doesNotMatch(document.querySelector('table').textContent, /Used 13:05/);
        assert.equal(buttonNamed('Unclaim'), undefined);
        assert.equal(document.querySelector('input').value, 'WB-123');
        assert.equal(document.querySelector('button[aria-pressed]').getAttribute('aria-pressed'), 'true');
        const request = state.requests.find((request) => request.method === 'DELETE');
        assert.equal(request.url, '/events/7/meals/people/3/claims');
        assert.equal(request.headers['X-XSRF-TOKEN'], 'test-csrf');
        assert.deepEqual(JSON.parse(request.body), { claim_id: 51 });
        assert.deepEqual(state.successes, ['Unclaimed']);
    } finally { state.app.unmount(); }
});

test('view-only Unclaim stays disabled and a stale correction refreshes without showing success', async () => {
    const used = () => ({ ...row(), used: true, used_at: '13:05', claim_id: 51 });
    let state = mount({ rows: [used()], canClaim: false, matched: true });
    try {
        await enter('WB-123'); await tick();
        assert.ok(buttonNamed('Unclaim').disabled);
        assert.equal(state.requests.filter((request) => request.method === 'DELETE').length, 0);
    } finally { state.app.unmount(); }
    state = mount({ rows: [used()], matched: true, unclaimReply: (_, rows) => {
        Object.assign(rows[0], { used: false, used_at: null, claim_id: null });
        return { status: 409, data: { status: 'already_unclaimed', message: 'Already unclaimed.' } };
    } });
    try {
        await enter('WB-123'); await tick();
        buttonNamed('Unclaim').click(); await tick(); await tick();
        assert.match(document.querySelector('[role="alert"]').textContent, /Already unclaimed/);
        assert.ok(buttonNamed('Claim'));
        assert.match(document.body.textContent, /1 today, 1 left/);
        assert.deepEqual(state.successes, []);
    } finally { state.app.unmount(); }
});
