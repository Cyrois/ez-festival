import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { compileScript } from '@vue/compiler-sfc';
import { parseWithReturnNavigation as parse } from './helpers/returnNavigation.js';
import { labelImports, desktopLabelEnvironment } from './helpers/entitlementLabels.js';
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<div id="app"></div>', { url: 'http://localhost' });
for (const key of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node', 'Option', 'DocumentFragment', 'getComputedStyle']) globalThis[key] = dom.window[key];
Object.defineProperty(globalThis, 'navigator', { value: dom.window.navigator, configurable: true });
window.performance.getEntriesByType = () => [];
desktopLabelEnvironment();
const { createApp, h, ref, reactive, nextTick } = await import('vue');
const require = createRequire(import.meta.url);
const translations = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'));
const trans = (key, values = {}) => Object.entries(values).reduce((text, [name, value]) => text.replaceAll(`:${name}`, value), translations[key] ?? key);
const compile = (path, replacements = []) => {
    const { descriptor } = parse(readFileSync(new URL(path, import.meta.url), 'utf8'));
    let source = compileScript(descriptor, { id: path, inlineTemplate: true }).content.replaceAll(/from ['"]vue['"]/g, `from '${pathToFileURL(require.resolve('vue')).href}'`);
    source = source.replace(...labelImports);
    for (const [pattern, replacement] of replacements) source = source.replace(pattern, replacement);
    return `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
};
const requests = [], errors = [], successes = [];
let form;
const box = (tag) => ({ setup: (_, { slots }) => () => h(tag, slots.default?.()) });
globalThis.holdingsTest = {
    trans,
    Card: box('section'), CardTitle: box('h2'), Badge: box('span'), Icon: box('i'),
    Button: { props: ['disabled'], setup: (props, { attrs, slots }) => () => h('button', { ...attrs, disabled: props.disabled }, slots.default?.()) },
    FormField: { props: ['label', 'error', 'hint'], setup: (props, { slots }) => () => h('label', [props.label, slots.default?.({ id: props.label, invalid: !!props.error }), props.error || props.hint]) },
    Input: { props: ['modelValue'], emits: ['update:modelValue'], setup: (props, { attrs, emit }) => () => h('input', { ...attrs, value: props.modelValue, onInput: (e) => emit('update:modelValue', e.target.value) }) },
    Textarea: { props: ['modelValue'], emits: ['update:modelValue'], setup: (props, { attrs, emit }) => () => h('textarea', { ...attrs, value: props.modelValue, onInput: (e) => emit('update:modelValue', e.target.value) }) },
    CustomDropdown: { props: { items: Array, modelValue: [String, Number, Array], multiple: Boolean }, emits: ['update:modelValue'], setup: (props, { attrs, emit }) => () => h('select', { ...attrs, value: props.modelValue, multiple: props.multiple,
        onChange: (e) => emit('update:modelValue', props.multiple ? [...e.target.selectedOptions].map((option) => Number(option.value)) : e.target.value === '' ? '' : Number(e.target.value)) }, [h('option', { value: '' }), ...props.items.map((item) => h('option', { value: item.value }, item.title))]) },
    Radio: { props: ['modelValue', 'value', 'label', 'disabled'], emits: ['update:modelValue'], setup: (props, { emit }) => () => h('label', [h('input', { type: 'radio', value: props.value, checked: props.modelValue === props.value, disabled: props.disabled, onChange: () => emit('update:modelValue', props.value) }), props.label]) },
    InventoryPicker: { props: ['modelValue', 'parameters'], emits: ['update:modelValue'], setup: (props, { emit }) => () => h('select', { class: 'person-picker', value: props.modelValue, 'data-parameters': JSON.stringify(props.parameters), onChange: (e) => emit('update:modelValue', Number(e.target.value)) }, [h('option', { value: '' }), h('option', { value: 9 }, 'Riley')]) },
    SegmentedControl: { props: ['modelValue', 'options'], emits: ['update:modelValue'], setup: (props, { emit }) => () => h('div', props.options.map((option) => h('button', { 'data-direction': option.value, onClick: () => emit('update:modelValue', option.value) }, option.label))) },
    Dialog: { props: ['open', 'confirmDisabled', 'busy', 'confirmLabel'], emits: ['confirm', 'update:open'], setup: (props, { slots, emit }) => () => props.open ? h('section', [slots.default?.(), h('button', { id: 'confirm', disabled: props.confirmDisabled || props.busy, onClick: () => emit('confirm') }, props.confirmLabel)]) : null },
    useForm: (initial) => {
        let transform = (data) => data;
        form = reactive({ ...structuredClone(initial), errors: {}, processing: false, clearErrors() { this.errors = {}; },
            transform(callback) { transform = callback; return this; },
            post(url, options) { const data = transform(JSON.parse(JSON.stringify(this))); requests.push({ url, data, options }); },
        });
        return form;
    },
    useFlashToast: () => ({ showError: (value) => errors.push(value), showFormError: (value) => errors.push(value), showSuccess: (value) => successes.push(value) }),
};
const replace = (name) => [new RegExp(`import (?:\\{ ${name} \\}|${name}) from ['"].*?['"];?`), `const ${name} = globalThis.holdingsTest.${name};`];
const countUrl = compile('../../resources/js/components/check-in/InventoryStockCount.vue', [replace('trans'), replace('Icon')]);
globalThis.holdingsCore = (await import('datatables.net-dt')).default;
globalThis.holdingsTable = (await import('datatables.net-vue3')).default.default;
const tableUrl = compile('../../resources/js/components/ui/data-table/DataTable.vue', [
    [/import DataTablesCore from ['"].*?['"];?/, 'const DataTablesCore = globalThis.holdingsCore;'],
    [/import DataTablesVue from ['"].*?['"];?/, 'const DataTablesVue = globalThis.holdingsTable;'],
    [/import \{[^}]*\} from ['"]laravel-vue-i18n['"];?/, 'const currentLocale = "en", getActiveLanguage = () => "en", isLoaded = () => true, loadLanguageAsync = async () => {}; const trans = globalThis.holdingsTest.trans;'],
    [/import \{ cn \} from ['"].*?['"];?/, 'const cn = (...values) => values.filter(Boolean).join(" ");'],
]);
const gridUrl = compile('../../resources/js/components/check-in/InventoryHoldingsSection.vue', [replace('trans'),
    [/import \{ Card, CardTitle \} from ['"].*?['"];?/, 'const { Card, CardTitle } = globalThis.holdingsTest;'],
    ...['Badge', 'Button', 'CustomDropdown'].map(replace),
    [/import \{ DataTable \} from ['"].*?['"];?/, `import DataTable from '${tableUrl}';`],
    [/import InventoryStockCount from ['"].*?['"];?/, `import InventoryStockCount from '${countUrl}';`],
]);
const batchUrl = compile('../../resources/js/components/check-in/InventoryBatchDialog.vue', ['useForm', 'trans', 'Dialog', 'Button', 'Input', 'Textarea', 'Radio', 'FormField', 'CustomDropdown', 'SegmentedControl', 'useFlashToast', 'InventoryPicker'].map(replace));
const pickerUrl = compile('../../resources/js/components/check-in/InventoryPicker.vue', [
    [/import \{ getActiveLanguage, trans \} from ['"].*?['"];?/, 'const getActiveLanguage = () => "en", trans = globalThis.holdingsTest.trans;'],
    replace('CustomDropdown'), replace('Button'),
]);
const { default: Picker } = await import(pickerUrl);
const { default: Grid } = await import(gridUrl);
const { default: Batch } = await import(batchUrl);
const { default: Count } = await import(countUrl);
const tick = async () => { await nextTick(); await nextTick(); await new Promise((resolve) => setTimeout(resolve, 5)); await nextTick(); };
const items = [{ id: 2, name: '<script>Wristband</script>', low_stock_level: 5, labels: [{ id: 1, name: 'VIP', color: 'violet' }] }, { id: 4, name: 'Voucher', low_stock_level: 0, labels: [] }];
const mount = (component, props) => { const app = createApp(component, props); app.config.globalProperties.$t = trans; app.mount('#app'); return app; };

test('grid uses server pagination, safe labeled headers, zero warnings, inactive actions and shared phone cards', async () => {
    const urls = [], actions = [];
    globalThis.fetch = async (url) => { const parsed = new URL(url, 'http://localhost'); urls.push(parsed); return { ok: true, json: async () => ({ draw: Number(parsed.searchParams.get('draw')), recordsTotal: 30, recordsFiltered: 30, canAct: true, data: [{ id: 9, name: 'Riley', inactive: true, items: [{ id: 2, balance: 5 }] }] }) }; };
    const app = mount(Grid, { items: [...items, ...Array.from({ length: 8 }, (_, index) => ({ id: index + 10, name: `Extra ${index}`, labels: [] }))], scope: { shift_id: 7 }, onTakeBack: (row) => actions.push(row) });
    try {
        await tick();
        assert.equal(urls[0].searchParams.get('shift_id'), '7');
        assert.equal(urls[0].searchParams.get('length'), '25');
        assert.ok(document.querySelector('table.dt-cards.dt-matrix'));
        assert.match(document.querySelector('thead').textContent, /VIP/);
        assert.match(document.querySelector('thead').textContent, /<script>Wristband<\/script>/);
        assert.equal(document.querySelector('script'), null);
        assert.equal(document.querySelectorAll('tbody [title^="Low:"]').length, 2);
        const buttons = [...document.querySelectorAll('tbody button')];
        assert.equal(buttons.find((button) => button.textContent === 'Give').disabled, true);
        buttons.find((button) => button.textContent === 'Take back').click();
        assert.equal(actions[0].id, 9);
        document.querySelector('.dt-paging-button.next').click(); await tick();
        assert.equal(urls.at(-1).searchParams.get('start'), '25');
        const select = document.querySelector('select');
        [...select.options].forEach((option) => { option.selected = option.value === '4'; });
        select.dispatchEvent(new window.Event('change')); await tick();
        assert.equal(document.querySelectorAll('thead th').length, 3);
    } finally { app.unmount(); }
});

test('low-stock warning respects above, equal, below, null and zero boundaries', async () => {
    for (const [count, level, warns] of [[6,5,false], [5,5,true], [2,5,true], [0,null,false], [0,0,true]]) {
        const app = mount(Count, { count, level });
        assert.equal(Boolean(document.querySelector('[title]')), warns);
        app.unmount();
    }
});

const mountBatch = async (mode, extra = {}) => {
    requests.length = 0; errors.length = 0; successes.length = 0;
    const open = ref(false);
    const app = createApp({ setup: () => () => h(Batch, { open: open.value, mode, person: { id: 9, name: 'Riley' }, eventId: 7, scope: { shift_id: 8 }, ownPersonId: 5, canReceive: true, canManage: true,
        ownItems: [{ id: 2, name: 'Wristband', balance: 6 }], items, locations: [{ id: 3, name: 'Gate' }], ...extra, 'onUpdate:open': (value) => { open.value = value; } }) });
    app.config.globalProperties.$t = trans; app.mount('#app'); open.value = true; await tick(); return { app, open };
};

test('Give submits multiple distinct lines, preserves errors and resets every field when reopened', async () => {
    globalThis.fetch = async () => ({ ok: true, json: async () => ({ data: [{ location_id: 3, entitlement_item_id: 4, balance: 10 }] }) });
    const { app, open } = await mountBatch('give');
    try {
        form.items = [{ entitlement_item_id: 2, quantity: 2, location_id: '' }, { entitlement_item_id: 4, quantity: 3, location_id: 3 }]; await tick();
        document.querySelector('#confirm').click();
        assert.equal(requests[0].url, '/events/7/check-in/inventory/gives');
        assert.deepEqual(requests[0].data.items, [{ entitlement_item_id: 2, quantity: 2 }, { entitlement_item_id: 4, quantity: 3, location_id: 3 }]);
        assert.equal(requests[0].data.shift_id, 8);
        requests[0].options.onError({ 'items.1.quantity': 'Available: 1' });
        assert.equal(form.items.length, 2);
        assert.equal(open.value, true);
        open.value = false; await tick(); open.value = true; await tick();
        assert.equal(form.items.length, 1); assert.equal(form.items[0].entitlement_item_id, ''); assert.equal(form.note, '');
    } finally { app.unmount(); }
});

test('Take back loads all items, pre-fills full counts, skips zeros and refuses an empty confirmation', async () => {
    const urls = [];
    globalThis.fetch = async (url) => { urls.push(url); return { ok: true, json: async () => ({ data: [{ id: 2, name: 'Wristband', balance: 6 }, { id: 4, name: 'Voucher', balance: 3 }] }) }; };
    const { app } = await mountBatch('take_back');
    try {
        assert.deepEqual(form.items.map((line) => line.quantity), [6,3]);
        assert.match(urls[0], /holdings\/stock\?shift_id=8&person_id=9/);
        form.items.forEach((line) => { line.quantity = 0; }); await tick();
        assert.equal(document.querySelector('#confirm').disabled, true);
        form.items[0].quantity = 7; await tick(); assert.equal(document.querySelector('#confirm').disabled, true);
        form.items[0].quantity = 2; form.destination = 'location'; form.location_id = 3; form.note = 'End'; await tick();
        document.querySelector('#confirm').click();
        assert.equal(requests[0].url, '/events/7/check-in/inventory/take-backs');
        assert.deepEqual(requests[0].data.items.map((line) => line.quantity), [2,0]);
        assert.equal(requests[0].data.note, 'End');
    } finally { app.unmount(); }
});

test('Adjust starts with no person, changes source direction safely and only person returns have a note', async () => {
    globalThis.fetch = async () => ({ ok: true, json: async () => ({ data: [{ id: 2, name: 'Wristband', balance: 6 }] }) });
    const { app } = await mountBatch('adjust', { person: null });
    try {
        assert.equal(form.person_id, ''); assert.equal(document.querySelector('textarea'), null);
        assert.equal(document.querySelector('#confirm').disabled, true);
        document.querySelector('[data-direction="to_location"]').click(); await tick();
        form.person_id = 9; await tick();
        assert.ok(document.querySelector('textarea'));
        form.location_id = 3; form.items = [{ entitlement_item_id: 2, quantity: 2 }]; form.note = 'Returned'; await tick();
        document.querySelector('#confirm').click();
        assert.equal(requests[0].data.direction, 'to_location'); assert.equal(requests[0].data.note, 'Returned');
        assert.equal(requests[0].url, '/events/7/check-in/inventory/adjustments');
    } finally { app.unmount(); }
});


test('remote shift picker sends the empty-search contract and formats event-local wall clocks without a zone conversion', async () => {
    const urls = [];
    globalThis.fetch = async (url) => { urls.push(new URL(url, 'http://localhost')); return { ok: true, json: async () => ({ data: [{ id: 3, name: 'Gate shift', location: 'Main gate', starts_at: '2026-10-22 08:00:00', ends_at: '2026-10-22 14:00:00', period: 1 }], links: { next: null } }) }; };
    const app = mount(Picker, { endpoint: '/check-in/inventory/scopes', parameters: { type: 'shift' }, shift: true, label: 'Choose a shift' });
    try {
        await tick();
        assert.equal(urls[0].searchParams.get('search'), '');
        assert.equal(urls[0].searchParams.get('type'), 'shift');
        assert.equal(urls[0].searchParams.get('page'), '1');
        assert.match(document.querySelector('option[value="3"]').textContent, /Gate shift · Main gate · Oct 22, 2026, 8:00 AM/);
    } finally { app.unmount(); }
});
