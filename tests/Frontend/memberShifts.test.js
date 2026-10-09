import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { JSDOM } from 'jsdom';
import { parse, compileScript } from '@vue/compiler-sfc';
import { memberShiftTimeLabel } from '../../resources/js/lib/memberShifts.js';
import { scheduleDateLabel } from '../../resources/js/lib/scheduleTimeline.js';

const dom = new JSDOM('<div id="app"></div>', { url: 'http://localhost' });
for (const key of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node', 'Option', 'DocumentFragment', 'getComputedStyle']) globalThis[key] = dom.window[key];
Object.defineProperty(globalThis, 'navigator', { value: dom.window.navigator, configurable: true });
const { createApp, h, nextTick } = await import('vue');
const require = createRequire(import.meta.url);
const translations = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'));
const translate = (key, values = {}) => Object.entries(values).reduce((text, [name, value]) => text.replaceAll(`:${name}`, value), translations[key] ?? key);
globalThis.memberShiftTranslate = translate;
globalThis.memberShiftDateLabel = scheduleDateLabel;
globalThis.memberShiftTimeLabel = memberShiftTimeLabel;
globalThis.memberShiftBox = (tag) => ({ setup: (_, { slots }) => () => h(tag, slots.default?.()) });
globalThis.memberShiftCore = (await import('datatables.net-dt')).default;
globalThis.memberShiftTable = (await import('datatables.net-vue3')).default.default;

const compile = (path, replacements) => {
    const { descriptor } = parse(readFileSync(new URL(path, import.meta.url), 'utf8'));
    let source = compileScript(descriptor, { id: path, inlineTemplate: true }).content
        .replaceAll(/from ['"]vue['"]/g, `from '${pathToFileURL(require.resolve('vue')).href}'`);
    for (const [pattern, replacement] of replacements) source = source.replace(pattern, replacement);
    return `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
};
const dataTable = compile('../../resources/js/components/ui/data-table/DataTable.vue', [
    [/import DataTablesCore from ['"].*?['"];?/, 'const DataTablesCore = globalThis.memberShiftCore;'],
    [/import DataTablesVue from ['"].*?['"];?/, 'const DataTablesVue = globalThis.memberShiftTable;'],
    [/import \{[^}]*\} from ['"]laravel-vue-i18n['"];?/, 'const currentLocale = "en", getActiveLanguage = () => "en", isLoaded = () => true, loadLanguageAsync = async () => {}; const trans = globalThis.memberShiftTranslate;'],
    [/import \{ cn \} from ['"].*?['"];?/, 'const cn = (...values) => values.filter(Boolean).join(" ");'],
]);
const card = compile('../../resources/js/components/team/TeamMemberShiftsCard.vue', [
    [/import \{ Link \} from ['"].*?['"];?/, "const Link = 'a';"],
    [/import \{ getActiveLanguage, trans \} from ['"].*?['"];?/, 'const getActiveLanguage = () => "en", trans = globalThis.memberShiftTranslate;'],
    [/import \{ Card, CardTitle \} from ['"].*?['"];?/, 'const Card = globalThis.memberShiftBox("div"), CardTitle = globalThis.memberShiftBox("h2");'],
    [/import \{ DataTable \} from ['"].*?['"];?/, `import DataTable from '${dataTable}';`],
    [/import \{ useFlashToast \} from ['"].*?['"];?/, 'const useFlashToast = () => ({ showError: () => globalThis.memberShiftErrors.push("error") });'],
    [/import \{ scheduleDateLabel \} from ['"].*?['"];?/, 'const scheduleDateLabel = globalThis.memberShiftDateLabel;'],
    [/import \{ memberShiftTimeLabel \} from ['"].*?['"];?/, 'const memberShiftTimeLabel = globalThis.memberShiftTimeLabel;'],
]);
const { default: ShiftsCard } = await import(card);
const row = (overrides = {}) => ({ id: 12, day: '2026-10-03', location: 'Gate', starts_at: '2026-10-03T00:30', ends_at: '2026-10-03T02:00', role_name: 'Crew', ...overrides });
const tick = async () => { await new Promise((resolve) => setTimeout(resolve, 20)); await nextTick(); };
const mount = async (rows = [row()], respond) => {
    const requests = [];
    globalThis.memberShiftErrors = [];
    globalThis.fetch = async (url, options) => {
        requests.push({ url, ...options });
        if (respond) return respond(url, options);
        const draw = Number(new URL(url, 'http://localhost').searchParams.get('draw'));
        return { ok: true, json: async () => ({ draw, recordsTotal: rows.length, recordsFiltered: rows.length, data: rows }) };
    };
    const app = createApp(ShiftsCard, { memberId: 7 });
    app.config.globalProperties.$t = translate;
    app.mount(document.getElementById('app'));
    await tick();
    return { app, requests };
};

test('renders four read-only columns, Saturday own hours, escaped names, and the Schedule link', async () => {
    const { app, requests } = await mount([row({ location: '<img src=x>', role_name: null })]);
    try {
        assert.deepEqual([...document.querySelectorAll('th')].map((cell) => cell.textContent.trim()), ['Day', 'Location', 'Time', 'Role']);
        assert.match(document.querySelector('tbody').textContent, /Sat, Oct 3/);
        assert.match(document.querySelector('tbody').textContent, /00:30 – 02:00/);
        assert.match(document.querySelector('tbody').textContent, /<img src=x>/);
        assert.equal(document.querySelector('img'), null);
        assert.equal(document.querySelectorAll('tbody td')[3].textContent.trim(), '-');
        assert.equal(document.querySelector('#shifts a').getAttribute('href'), '/team/scheduling?tab=schedule');
        assert.equal(document.querySelector('#shifts a').textContent.trim(), 'Open in Scheduling');
        const params = new URL(requests[0].url, 'http://localhost').searchParams;
        assert.equal(params.get('length'), '25');
        assert.equal(params.get('order[0][column]'), '0');
        assert.match(requests[0].url, /^\/team\/members\/7\/shifts\?/);
        assert.deepEqual(globalThis.memberShiftErrors, []);
        assert.equal(document.querySelector('.dt-paging-button.first'), null);
        assert.equal(document.querySelector('.dt-paging-button.last'), null);
    } finally { app.unmount(); }
});

test('empty assignments use the localized in-table empty state', async () => {
    const { app } = await mount([]);
    try {
        assert.match(document.querySelector('tbody').textContent, /No assigned shifts yet\./);
        assert.equal(document.querySelector('tbody td').getAttribute('colspan'), '4');
    } finally { app.unmount(); }
});

test('a refused reload clears previously visible shifts and shows the shared error toast', async () => {
    let refused = false;
    const { app } = await mount([], async (url) => ({
        ok: !refused,
        json: async () => ({ draw: Number(new URL(url, 'http://localhost').searchParams.get('draw')), recordsTotal: 1, recordsFiltered: 1, data: [row()] }),
    }));
    try {
        assert.match(document.querySelector('tbody').textContent, /Crew/);
        refused = true;
        const input = document.querySelector('input');
        input.value = 'gate';
        input.dispatchEvent(new window.Event('input', { bubbles: true }));
        await new Promise((resolve) => setTimeout(resolve, 500)); await tick();
        assert.doesNotMatch(document.querySelector('tbody').textContent, /Crew/);
        assert.deepEqual(globalThis.memberShiftErrors, ['error']);
    } finally { app.unmount(); }
});

test('removing the card cancels its pending request and ignores a late response', async () => {
    let complete;
    const { app, requests } = await mount([], () => new Promise((resolve) => { complete = resolve; }));
    app.unmount();
    assert.equal(requests[0].signal.aborted, true);
    complete({ ok: true, json: async () => ({ draw: 1, recordsTotal: 1, recordsFiltered: 1, data: [row()] }) });
    await tick();
    assert.equal(document.querySelector('#shifts'), null);
    assert.deepEqual(globalThis.memberShiftErrors, []);
});

test('overnight formatting and the start day stay unchanged in different browser timezones', () => {
    const original = process.env.TZ;
    try {
        for (const timezone of ['America/Vancouver', 'Pacific/Auckland', 'America/New_York']) {
            process.env.TZ = timezone;
            assert.equal(memberShiftTimeLabel(row(), 'en', translate), '00:30 – 02:00');
            assert.equal(memberShiftTimeLabel(row({ starts_at: '2026-10-02T23:15', ends_at: '2026-10-03T01:45' }), 'en', translate), '23:15 – Sat, Oct 3 01:45');
            assert.equal(scheduleDateLabel('2026-10-03', 'en'), 'Sat, Oct 3');
        }
    } finally {
        if (original === undefined) delete process.env.TZ;
        else process.env.TZ = original;
    }
});
