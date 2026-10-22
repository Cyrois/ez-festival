import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import test from 'node:test';
import { parse, compileScript } from '@vue/compiler-sfc';
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<main id="app"></main>', { url: 'http://localhost' });
for (const key of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node', 'DocumentFragment']) globalThis[key] = dom.window[key];
Object.defineProperty(globalThis, 'navigator', { value: dom.window.navigator, configurable: true });
const { createApp, h, nextTick, onMounted, ref } = await import('vue');
const require = createRequire(import.meta.url);
const locale = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'));
const translate = (key, values = {}) => Object.entries(values).reduce((text, [key, value]) => text.replaceAll(`:${key}`, value), locale[key] ?? key);
const row = { id: 1, when: '2026-10-03T01:00:00Z', type: 'give_out', item: '<script>item</script>', quantity: 1, giver: '<b>Giver</b>', receiver: '<img src=x>', recorded_by: 'Recorder', note: '<script>note</script>', code: 'BAND-42', labels: [] };
const state = { requests: [], rows: [], table: null, optionFailure: false, historyFailure: false };
const fetcher = async (input) => {
    const url = new URL(input, 'http://localhost');
    state.requests.push(url);
    if (url.pathname.endsWith('/options')) {
        if (state.optionFailure) return { ok: false };
        const actor = url.searchParams.get('option') === 'actor';
        return { ok: true, json: async () => ({ data: [{ value: actor ? '8' : 'person:42', name: actor ? 'Former recorder' : 'Checked-in person', kind: actor ? 'actor' : 'person' }], links: { next: url.searchParams.get('page') === '1' ? 'next' : null } }) };
    }
    if (state.historyFailure) return { ok: false, json: async () => ({ errors: { end_date: ['Choose an end date.'] } }) };
    return { ok: true, json: async () => ({ draw: Number(url.searchParams.get('draw')), recordsTotal: 35, recordsFiltered: 35, data: [row] }) };
};
const button = { props: ['href', 'as'], setup: (props, { attrs, slots }) => () => h(props.as || (props.href ? 'a' : 'button'), { ...attrs, href: props.href }, slots.default?.()) };
const field = { props: ['label', 'error'], setup: (props, { slots }) => () => h('label', [props.label, slots.default?.({ id: props.label, invalid: !!props.error }), props.error && h('span', { role: 'alert' }, props.error)]) };
const input = { props: ['modelValue'], emits: ['update:modelValue'], setup: (props, { attrs, emit }) => () => h('input', { ...attrs, value: props.modelValue, onInput: (event) => emit('update:modelValue', event.target.value) }) };
const dropdown = { props: { modelValue: {}, items: Array, remote: Boolean, hasMore: Boolean, disabled: Boolean }, emits: ['update:modelValue', 'search', 'load-more'], setup: (props, { attrs, emit }) => () => h('div', { 'data-dropdown': attrs['aria-label'] }, [
    ...props.items.map((item) => h('button', { type: 'button', disabled: props.disabled, 'data-value': item.value, onClick: () => emit('update:modelValue', item.value) }, item.title)),
    props.remote && h('input', { 'aria-label': `${attrs['aria-label']} search`, onInput: (event) => emit('search', event.target.value) }),
    props.hasMore && h('button', { type: 'button', 'data-more': '', onClick: () => emit('load-more') }, 'More'),
]) };
const dataTable = { props: ['ajax', 'options'], setup: (props, { expose, slots }) => {
    const rows = ref([]);
    const paging = { start: 0, length: props.options.pageLength, draw: 0 };
    const load = async () => props.ajax({ ...paging, draw: ++paging.draw }, (body) => { state.rows = body.data; rows.value = body.data; });
    const reload = () => { paging.start = 0; return load(); };
    const setPageLength = (length) => { paging.length = length; return reload(); };
    expose({ reload, setPageLength });
    state.table = { paging, load };
    onMounted(load);
    return () => h('section', { 'data-table': '' }, rows.value.map((rowData) => h('div', Object.entries(slots).map(([name, slot]) => h('div', { 'data-cell': name }, slot({ rowData }))))));
} };
const itemName = { props: ['name'], setup: (props) => () => h('span', props.name) };
globalThis.inventoryHistoryTest = { button, field, input, dropdown, dataTable, itemName, translate };
const { descriptor } = parse(readFileSync(new URL('../../resources/js/components/check-in/InventoryHistoryTable.vue', import.meta.url), 'utf8'));
const ui = { Button: 'button', FormField: 'field', Input: 'input', CustomDropdown: 'dropdown', DataTable: 'dataTable', EntitlementItemName: 'itemName' };
let source = compileScript(descriptor, { id: 'history', inlineTemplate: true }).content
    .replaceAll(/from ['"]vue['"]/g, `from '${pathToFileURL(require.resolve('vue')).href}'`)
    .replace(/import \{ trans \} from ['"]laravel-vue-i18n['"];?/, 'const trans = globalThis.inventoryHistoryTest.translate;')
    .replace(/import \{ (\w+) \} from ['"][^'"]+['"];?/g, (match, name) => ui[name] ? `const ${name} = globalThis.inventoryHistoryTest.${ui[name]};` : match);
const { default: History } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const tick = async () => { await new Promise((resolve) => setTimeout(resolve, 0)); await nextTick(); };
const props = { endpoint: '/check-in/history/data', optionsEndpoint: '/check-in/history/options', exportUrl: '/check-in/history/export', timezone: 'America/Vancouver', items: [{ id: 4, name: 'Wristband', labels: [] }], movementTypes: ['add', 'give_out'] };
const mount = async (overrides = {}) => {
    state.requests = []; state.rows = []; state.optionFailure = false; state.historyFailure = false;
    globalThis.fetch = fetcher;
    const app = createApp(History, { ...props, ...overrides });
    app.config.globalProperties.$t = translate;
    app.mount(document.getElementById('app'));
    await tick();
    return app;
};
const choose = (label, value) => document.querySelector(`[data-dropdown="${label}"] button[data-value="${value}"]`).click();
const clickText = (text) => [...document.querySelectorAll('button')].find((button) => button.textContent.trim() === text).click();
const historyRequests = () => state.requests.filter((url) => url.pathname.endsWith('/data'));

test('history uses ten-row server paging, renders codes beside recipients and escapes all user text', async () => {
    const app = await mount();
    try {
        assert.equal(historyRequests()[0].searchParams.get('length'), '10');
        assert.equal(document.querySelector('[data-cell="receiverCell"]').textContent.trim(), '<img src=x> BAND-42');
        assert.ok(document.body.textContent.includes('<script>note</script>'));
        assert.equal(document.querySelector('script, img, b'), null);
        assert.match(document.querySelector('[data-cell="whenCell"]').textContent, /Oct 2, 2026/);
        assert.equal(document.querySelector('input[type="search"]'), null);
        assert.equal(document.querySelector('a').getAttribute('href'), '/check-in/history/export?');
    } finally { app.unmount(); }
});

test('all five filters reset paging and export the same filters without pagination', async () => {
    const app = await mount();
    try {
        state.table.paging.start = 20;
        choose('Item', 4); choose('Holder · Giver or Receiver', 'person:42'); choose('Recorded by', '8'); choose('Movement', 'give_out');
        for (const [id, value] of [['From', '2026-10-02'], ['To', '2026-10-03']]) {
            const input = document.getElementById(id); input.value = value; input.dispatchEvent(new window.Event('input', { bubbles: true }));
        }
        document.querySelector('form').dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true })); await tick();
        const query = historyRequests().at(-1).searchParams;
        assert.deepEqual(Object.fromEntries(query), { draw: '2', start: '0', length: '10', item_id: '4', holder: 'person:42', recorded_by: '8', type: 'give_out', start_date: '2026-10-02', end_date: '2026-10-03' });
        const download = new URL(document.querySelector('a').href);
        for (const key of ['item_id', 'holder', 'recorded_by', 'type', 'start_date', 'end_date']) assert.equal(download.searchParams.get(key), query.get(key));
        assert.equal(download.searchParams.has('length'), false);
        choose('Rows per page', 20); await tick();
        assert.equal(historyRequests().at(-1).searchParams.get('length'), '20');
        assert.equal(historyRequests().at(-1).searchParams.get('start'), '0');
        clickText('Clear filters'); await tick();
        assert.equal(historyRequests().at(-1).searchParams.get('holder'), '');
    } finally { app.unmount(); }
});

test('item history fixes its item and omits CSV for item-only viewers', async () => {
    const app = await mount({ itemId: 4, exportUrl: null, endpoint: '/credentials/entitlements/4/data', optionsEndpoint: '/credentials/entitlements/4/options' });
    try {
        assert.equal(historyRequests()[0].searchParams.get('item_id'), '4');
        assert.ok([...document.querySelectorAll('[data-dropdown="Item"] button')].every((button) => button.disabled));
        assert.equal(document.querySelector('a'), null);
        clickText('Clear filters'); await tick();
        assert.equal(historyRequests().at(-1).searchParams.get('item_id'), '4');
    } finally { app.unmount(); }
});

test('failed history shows a named error and validation, and Retry reloads successfully', async () => {
    const app = await mount();
    try {
        state.historyFailure = true;
        await state.table.load(); await tick();
        assert.match(document.body.textContent, /Movement history could not be loaded/);
        assert.match(document.body.textContent, /Choose an end date/);
        assert.equal(document.querySelector('a'), null);
        state.historyFailure = false; clickText('Retry'); await tick();
        assert.ok(document.querySelector('a'));
        assert.doesNotMatch(document.body.textContent, /could not be loaded|Choose an end date/);
    } finally { app.unmount(); }
});

test('historical people and actor catalogues page remotely and have retry on failure', async () => {
    const app = await mount();
    try {
        const holder = document.querySelector('[data-dropdown="Holder · Giver or Receiver"]');
        holder.querySelector('[data-more]').click(); await tick();
        assert.ok(state.requests.some((url) => url.searchParams.get('option') === 'holder' && url.searchParams.get('page') === '2'));
        state.optionFailure = true;
        const input = holder.querySelector('input'); input.value = 'Former'; input.dispatchEvent(new window.Event('input', { bubbles: true }));
        await new Promise((resolve) => setTimeout(resolve, 220)); await tick();
        assert.match(document.body.textContent, /Retry loading holders/);
        state.optionFailure = false; clickText('Retry loading holders'); await tick();
        assert.doesNotMatch(document.body.textContent, /Retry loading holders/);
    } finally { app.unmount(); }
});
