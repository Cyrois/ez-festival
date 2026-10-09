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
const radio = await compile('../../resources/js/components/ui/radio/Radio.vue');
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
    [/import \{ Radio \} from ['"].*?['"];?/, `import Radio from '${radio}';`],
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
const row = (shift = 10) => ({ assignment_id: shift, meal_id: 4, source_shift_id: shift, name: 'Fri Lunch', date: '2026-10-01', type: 'Lunch', starts_at: '12:00', ends_at: '14:00', shift_location: 'Gate', shift_start: '2026-10-01T09:00', shift_end: '2026-10-01T16:00', used: false, claim_token: null, used_at: null });
const mount = ({ canClaim = true, canOverride = false, canRemove = false, rows = [row(), row(11)], matched = false, reply = null, unclaimReply = null, overrideOptions = [], overrideReply = null, removeReply = null } = {}) => {
    const requests = [], successes = [], errors = [];
    globalThis.kitchenToasts = { showSuccess: (message) => successes.push(message), showError: (message) => errors.push(message), showFormError: (message) => errors.push(message) };
    globalThis.fetch = async (url, options = {}) => {
        requests.push({ url, ...options });
        if (url.endsWith('/overrides')) {
            if (!options.method) return { ok: true, json: async () => ({ data: overrideOptions }) };
            const body = JSON.parse(options.body);
            const removing = options.method === 'DELETE';
            const result = removing
                ? (removeReply ? removeReply(body, rows) : { status: 200, data: { status: 'override_removed', message: 'Override removed' } })
                : (overrideReply ? overrideReply(body, rows) : { status: 201, data: { status: 'overridden', message: 'Override given and claimed', assignment_id: 99 } });
            if (result.status < 400) {
                if (removing) rows.splice(rows.findIndex((meal) => meal.assignment_id === body.assignment_id), 1);
                else rows.push({ ...row(), source_shift_id: null, shift_location: null, shift_start: null, shift_end: null, is_override: true, override_at: '13:10', used: body.claim !== false, used_at: body.claim !== false ? '13:10' : null, assignment_id: 99, claim_token: body.claim !== false ? '00000000-0000-4000-8000-000000000099' : null });
            }
            return { ok: result.status < 400, status: result.status, json: async () => result.data };
        }
        if (options.method === 'DELETE') {
            const body = JSON.parse(options.body);
            const result = unclaimReply ? unclaimReply(body, rows) : { status: 200, data: { status: 'unclaimed', message: 'Unclaimed' } };
            if (result.status === 200) Object.assign(rows.find((meal) => meal.assignment_id === body.assignment_id), { used: false, used_at: null, claim_token: null });
            return { ok: result.status < 400, status: result.status, json: async () => result.data };
        }
        if (options.method === 'POST') {
            const body = JSON.parse(options.body);
            const result = reply ? reply(body, rows) : { status: 201, data: { status: 'claimed', message: 'Claimed', assignment_id: 10, claim_token: '00000000-0000-4000-8000-000000000051' } };
            if (result.status === 201) Object.assign(rows.find((meal) => meal.assignment_id === body.assignment_id), { used: true, used_at: '13:05', claim_token: result.data.claim_token });
            return { ok: result.status < 400, status: result.status, json: async () => result.data };
        }
        if (url.startsWith('/meals/people?')) return { ok: true, json: async () => ({ people: [person], total: 1, page: 1, last_page: 1, matched_id: matched ? person.id : null }) };
        return { ok: true, json: async () => ({ draw: Number(new URL(url, 'http://localhost').searchParams.get('draw')), recordsTotal: rows.length, recordsFiltered: rows.length, today: '2026-10-01', can_claim: canClaim, can_override: canOverride, can_remove_override: canRemove, is_locked: false, person: { ...person, status: 'Hired' }, counts: rows.length ? [{ date: '2026-10-01', type_id: 1, type: 'Lunch', total: rows.length, left: rows.filter((meal) => !meal.used).length, overrides: rows.filter((meal) => meal.is_override && meal.used).length }] : [], data: rows }) };
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
        assert.deepEqual(JSON.parse(request.body), { assignment_id: 10, confirm_warning: false });
    } finally { state.app.unmount(); }
});

test('exact barcode auto-opens; same-type warning is titleless, Cancel does nothing, Claim anyway sends explicit confirmation', async () => {
    const state = mount({ matched: true, reply: (body) => body.confirm_warning ? { status: 201, data: { status: 'claimed', message: 'Claimed', assignment_id: 10, claim_token: '00000000-0000-4000-8000-000000000051' } } : { status: 409, data: { status: 'warning_required', message: 'Already had Lunch. Claim another Lunch?' } } });
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
        Object.assign(rows[0], { used: true, used_at: '12:31', claim_token: '00000000-0000-4000-8000-000000000099' });
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
        assert.match(document.body.textContent, /No meals found/);
        assert.doesNotMatch(document.body.textContent, /today, .* left/);
    } finally { state.app.unmount(); }
});

test('Unclaim on the right restores status and counts without clearing the selected person or search', async () => {
    const used = { ...row(), used: true, used_at: '13:05', assignment_id: 10, claim_token: '00000000-0000-4000-8000-000000000051' };
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
        assert.deepEqual(JSON.parse(request.body), { assignment_id: 10, claim_token: '00000000-0000-4000-8000-000000000051' });
        assert.deepEqual(state.successes, ['Unclaimed']);
    } finally { state.app.unmount(); }
});

test('view-only Unclaim stays disabled and a stale correction refreshes without showing success', async () => {
    const used = () => ({ ...row(), used: true, used_at: '13:05', assignment_id: 10, claim_token: '00000000-0000-4000-8000-000000000051' });
    let state = mount({ rows: [used()], canClaim: false, matched: true });
    try {
        await enter('WB-123'); await tick();
        assert.ok(buttonNamed('Unclaim').disabled);
        assert.equal(state.requests.filter((request) => request.method === 'DELETE').length, 0);
    } finally { state.app.unmount(); }
    state = mount({ rows: [used()], matched: true, unclaimReply: (_, rows) => {
        Object.assign(rows[0], { used: false, used_at: null, claim_token: null });
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

test('a direct person assignment renders without shift metadata and supports Claim and Unclaim', async () => {
    const state = mount({ rows: [{ ...row(), source_shift_id: null, shift_location: null, shift_start: null, shift_end: null }], matched: true });
    try {
        await enter('WB-123'); await tick();
        assert.match(document.querySelector('table').textContent, /Direct assignment/);
        buttonNamed('Claim').click(); await tick(); await tick();
        assert.match(document.body.textContent, /1 today, 0 left/);
        buttonNamed('Unclaim').click(); await tick(); await tick();
        assert.match(document.body.textContent, /1 today, 1 left/);
        assert.ok(buttonNamed('Claim'));
        assert.deepEqual(state.errors, []);
    } finally { state.app.unmount(); }
});

const overrideOption = (changes = {}) => ({ id: 4, name: 'Fri Lunch', date: '2026-10-01', type: 'Lunch', starts_at: '12:00', ends_at: '14:00', available: true, extra: false, ...changes });
const selectOverrideMeal = async () => {
    const radio = document.querySelector('input[type="radio"]:not([disabled])');
    radio.checked = true; radio.dispatchEvent(new window.Event('change', { bubbles: true })); await tick();
};

test('Override is independent of Claim permission; picker disables unused types and Cancel writes nothing', async () => {
    const state = mount({ canClaim: false, canOverride: true, rows: [], matched: true,
        overrideOptions: [overrideOption(), overrideOption({ id: 5, name: 'Fri Dinner', type: 'Dinner', available: false })] });
    try {
        await enter('WB-123'); await tick();
        assert.equal([...document.querySelectorAll('button')].filter((button) => button.textContent.trim() === 'Give meal').length, 1);
        buttonNamed('Give meal').click(); await tick();
        assert.match(document.querySelector('[role="alertdialog"]').textContent, /Give meal to Ava Lee/);
        assert.equal(document.querySelectorAll('input[type="radio"][disabled]').length, 1);
        assert.match(document.body.textContent, /still has an unused Dinner/);
        assert.ok(buttonNamed('Give and claim').disabled);
        assert.ok(buttonNamed('Give').disabled);
        assert.ok(buttonNamed('Cancel').classList.contains('sm:mr-auto'));
        assert.equal(buttonNamed('Continue'), undefined);
        await selectOverrideMeal();
        buttonNamed('Cancel').click(); await tick();
        assert.equal(document.querySelector('[role="alertdialog"]'), null);
        assert.equal(state.requests.filter((request) => request.method === 'POST').length, 0);
        assert.equal(document.querySelector('input').value, 'WB-123');
    } finally { state.app.unmount(); }
});

for (const extra of [false, true]) {
    test(`Override ${extra ? 'extra' : 'walk-up'} confirmation gives and claims in one request and refreshes Used and origin`, async () => {
        const state = mount({ canOverride: true, rows: [], matched: true, overrideOptions: [overrideOption({ extra })] });
        try {
            await enter('WB-123'); await tick();
            buttonNamed('Give meal').click(); await tick(); await selectOverrideMeal();
            assert.equal(document.querySelectorAll('[role="alertdialog"]').length, 1);
            buttonNamed('Give and claim').click(); buttonNamed('Give and claim').click(); await tick(); await tick();
            const requests = state.requests.filter((request) => request.method === 'POST');
            assert.equal(requests.length, 1);
            assert.deepEqual(JSON.parse(requests[0].body), { meal_id: 4, confirmed: true, claim: true });
            assert.equal(requests[0].headers['X-XSRF-TOKEN'], 'test-csrf');
            assert.match(document.querySelector('table').textContent, /Used 13:10/);
            assert.match(document.querySelector('table').textContent, /Override 13:10/);
            assert.match(document.querySelector('table').textContent, /No shift \(override\)/);
            assert.match(document.body.textContent, /1 today, 0 left, 1 override/);
            assert.equal(buttonNamed('Remove override'), undefined);
            assert.ok(buttonNamed('Unclaim'));
            assert.equal(document.querySelector('input').value, 'WB-123');
            assert.deepEqual(state.successes, ['Override given and claimed']);
        } finally { state.app.unmount(); }
    });
}

test('Override requires its own permission and resets the picker when the lookup changes', async () => {
    let state = mount({ matched: true });
    try {
        await enter('WB-123'); await tick();
        assert.ok(buttonNamed('Give meal').disabled);
        assert.match(buttonNamed('Give meal').parentElement.getAttribute('label'), /You need Override meals/);
    } finally { state.app.unmount(); }
    state = mount({ canOverride: true, rows: [], matched: true, overrideOptions: [overrideOption()] });
    try {
        await enter('WB-123'); await tick(); buttonNamed('Give meal').click(); await tick();
        await enter('Other name'); await tick();
        assert.equal(document.querySelector('[role="alertdialog"]'), null);
        assert.equal(state.requests.filter((request) => request.method === 'POST').length, 0);
    } finally { state.app.unmount(); }
});

test('claimed overrides must be Unclaimed before confirmed removal; cancellation preserves the grant', async () => {
    const usedOverride = { ...row(), is_override: true, override_at: '13:10', used: true, used_at: '13:10', claim_token: '00000000-0000-4000-8000-000000000051', source_shift_id: null };
    const state = mount({ canRemove: true, matched: true, rows: [usedOverride] });
    try {
        await enter('WB-123'); await tick();
        assert.equal(buttonNamed('Remove override'), undefined);
        buttonNamed('Unclaim').click(); await tick(); await tick();
        assert.match(document.body.textContent, /1 today, 1 left/);
        assert.doesNotMatch(document.body.textContent, /1 override/);
        assert.match(document.querySelector('table').textContent, /Override 13:10/);
        buttonNamed('Remove override').click(); await tick();
        assert.match(document.querySelector('[role="alertdialog"]').textContent, /Remove the unclaimed override/);
        buttonNamed('Cancel').click(); await tick();
        assert.equal(state.requests.filter((request) => request.url.endsWith('/overrides') && request.method === 'DELETE').length, 0);
        buttonNamed('Remove override').click(); await tick();
        const confirm = [...document.querySelectorAll('[role="alertdialog"] button')].find((button) => button.textContent.trim() === 'Remove override');
        confirm.click(); await tick(); await tick();
        const request = state.requests.find((request) => request.url.endsWith('/overrides') && request.method === 'DELETE');
        assert.deepEqual(JSON.parse(request.body), { assignment_id: 10, confirmed: true });
        assert.doesNotMatch(document.body.textContent, /1 override/);
        assert.match(document.body.textContent, /No meals found/);
        assert.equal(document.querySelector('input').value, 'WB-123');
        assert.deepEqual(state.successes, ['Unclaimed', 'Override removed']);
    } finally { state.app.unmount(); }
});

test('unused overrides show disabled removal without its permission and stale Give refreshes without success', async () => {
    let state = mount({ rows: [{ ...row(), is_override: true, override_at: '13:10' }], matched: true });
    try {
        await enter('WB-123'); await tick();
        assert.ok(buttonNamed('Remove override').disabled);
        assert.match(buttonNamed('Remove override').parentElement.getAttribute('label'), /You need Remove meal overrides/);
    } finally { state.app.unmount(); }
    state = mount({ canOverride: true, rows: [], matched: true, overrideOptions: [overrideOption()], overrideReply: (_, rows) => {
        rows.push(row()); return { status: 409, data: { status: 'unused_meal', message: 'Use Claim on the unused Lunch.' } };
    } });
    try {
        await enter('WB-123'); await tick(); buttonNamed('Give meal').click(); await tick(); await selectOverrideMeal();
        buttonNamed('Give and claim').click(); await tick(); await tick();
        assert.match(document.querySelector('[role="alert"]').textContent, /Use Claim/);
        assert.match(document.body.textContent, /1 today, 1 left/);
        assert.ok(buttonNamed('Claim'));
        assert.equal(document.querySelector('[role="alertdialog"]'), null);
        assert.deepEqual(state.successes, []);
    } finally { state.app.unmount(); }
});


test('nearby meal dates display and outside-day Claim and Unclaim stay disabled', async () => {
    const state = mount({ matched: true, rows: [
        { ...row(10), date: '2026-09-28', eligible_today: false, source_shift_id: null },
        { ...row(11), date: '2026-10-04', eligible_today: false, used: true, source_shift_id: null },
        { ...row(12), eligible_today: true, source_shift_id: null },
    ] });
    try {
        await enter('WB-123'); await tick();
        const rows = [...document.querySelectorAll('tbody tr')];
        assert.match(rows[0].textContent, /Sep 28/);
        assert.match(rows[1].textContent, /Oct 4/);
        assert.match(rows[2].textContent, /Oct 1/);
        assert.ok(rows[0].querySelector('button').disabled);
        assert.ok(rows[1].querySelector('button').disabled);
        assert.equal(rows[2].querySelector('button').disabled, false);
        rows[0].querySelector('button').click(); rows[1].querySelector('button').click(); await tick();
        assert.equal(state.requests.filter((request) => request.method).length, 0);
    } finally { state.app.unmount(); }
});


test('Give creates an unused override directly from the picker', async () => {
    const state = mount({ canOverride: true, canRemove: true, rows: [], matched: true, overrideOptions: [overrideOption()] });
    try {
        await enter('WB-123'); await tick(); buttonNamed('Give meal').click(); await tick();
        assert.ok(buttonNamed('Give').disabled);
        await selectOverrideMeal();
        assert.equal(buttonNamed('Give').disabled, false);
        buttonNamed('Give').click(); await tick(); await tick();
        const requests = state.requests.filter((request) => request.method === 'POST');
        assert.equal(requests.length, 1);
        assert.deepEqual(JSON.parse(requests[0].body), { meal_id: 4, confirmed: true, claim: false });
        assert.equal(document.querySelector('[role="alertdialog"]'), null);
        assert.match(document.querySelector('table').textContent, /Not used/);
        assert.ok(buttonNamed('Claim'));
        assert.ok(buttonNamed('Remove override'));
        assert.match(document.body.textContent, /1 today, 1 left/);
    } finally { state.app.unmount(); }
});
