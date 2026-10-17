import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { compileScript } from '@vue/compiler-sfc';
import { parseWithReturnNavigation as parse } from './helpers/returnNavigation.js';
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<div id="app"></div>', { url: 'http://localhost' });
for (const key of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node']) globalThis[key] = dom.window[key];
const { createApp, h, ref, reactive, nextTick } = await import('vue');
const require = createRequire(import.meta.url);
const translations = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'));
const trans = (key, values = {}) => Object.entries(values).reduce((text, [name, value]) => text.replaceAll(`:${name}`, value), translations[key] ?? key);
const requests = [];
const errors = [];
const successes = [];
globalThis.fetch = async () => ({ ok: true, json: async () => ({ data: [], links: { next: null } }) });
let form;
globalThis.inventoryTest = {
    trans,
    useForm: (initial) => {
        form = reactive({ ...initial, errors: {}, processing: false,
            clearErrors() { this.errors = {}; },
            reset() { Object.assign(this, initial); },
            post(url, options) { requests.push({ method: 'post', url, data: { person_id: this.person_id, location_id: this.location_id, entitlement_item_id: this.entitlement_item_id, quantity: this.quantity, destination: this.destination, note: this.note }, options }); },
            put(url, options) { requests.push({ method: 'put', url, data: { location_id: this.location_id, entitlement_item_id: this.entitlement_item_id, quantity: this.quantity, ...(this.person_id ? { person_id: this.person_id } : {}) }, options }); },
        });
        return form;
    },
    useFlashToast: () => ({ showSuccess: (value) => successes.push(value), showError: (value) => errors.push(value), showFormError: (value) => errors.push(value) }),
    Dialog: {
        props: ['open', 'description', 'confirmDisabled', 'busy', 'confirmLabel'], emits: ['confirm', 'update:open'],
        setup: (props, { slots, emit }) => () => props.open ? h('section', [h('p', props.description), slots.default?.(),
            h('button', { id: 'confirm', disabled: props.confirmDisabled || props.busy, onClick: () => emit('confirm') }, props.confirmLabel),
            h('button', { id: 'cancel', onClick: () => emit('update:open', false) }, 'Cancel')]) : null,
    },
    FormField: { props: ['label', 'error'], setup: (props, { slots }) => () => h('label', [props.label, slots.default?.({ id: props.label, invalid: !!props.error }), props.error]) },
    Input: { props: ['modelValue'], emits: ['update:modelValue'], setup: (props, { attrs, emit }) => () => h('input', { ...attrs, value: props.modelValue, onInput: (e) => emit('update:modelValue', e.target.value) }) },
    Textarea: { props: ['modelValue'], emits: ['update:modelValue'], setup: (props, { attrs, emit }) => () => h('textarea', { ...attrs, value: props.modelValue, onInput: (e) => emit('update:modelValue', e.target.value) }) },
    Radio: { props: ['modelValue', 'value', 'label', 'disabled'], emits: ['update:modelValue'], setup: (props, { emit }) => () => h('label', [h('input', { type: 'radio', value: props.value, checked: props.modelValue === props.value, disabled: props.disabled, onChange: () => emit('update:modelValue', props.value) }), props.label]) },
    CustomDropdown: { props: ['items', 'modelValue'], emits: ['update:modelValue'], setup: (props, { attrs, emit }) => () => h('select', { ...attrs, value: props.modelValue, onChange: (e) => emit('update:modelValue', Number.isNaN(Number(e.target.value)) ? e.target.value : Number(e.target.value)) }, [h('option', { value: '' }), ...props.items.map((item) => h('option', { value: item.value, 'data-group': item.group }, item.title))]) },
};
const path = '../../resources/js/components/check-in/InventoryMovementDialog.vue';
const { descriptor } = parse(readFileSync(new URL(path, import.meta.url), 'utf8'));
let source = compileScript(descriptor, { id: path, inlineTemplate: true }).content.replaceAll(/from ['"]vue['"]/g, `from '${pathToFileURL(require.resolve('vue')).href}'`);
for (const name of ['useForm', 'trans', 'Dialog', 'FormField', 'Input', 'Textarea', 'Radio', 'CustomDropdown', 'useFlashToast']) {
    source = source.replace(new RegExp(`import \\{ ${name} \\} from ['"].*?['"];?`), `const ${name} = globalThis.inventoryTest.${name};`);
}
const { default: Component } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const tick = async () => { await nextTick(); await nextTick(); };
const mount = async (mode, extra = {}) => {
    requests.length = 0; errors.length = 0;
    const open = ref(false);
    const currentMode = ref(mode);
    const saved = [];
    const app = createApp({ setup: () => () => h(Component, { open: open.value, mode: currentMode.value, eventId: 7,
        items: [{ id: 2, name: '<script>Wristband</script>' }], ownItems: [{ id: 2, name: '<script>Wristband</script>', balance: 12 }],
        locations: [{ id: 3, name: 'Main gate' }, { id: 4, name: 'Empty returns' }],
        ...extra,
        'onUpdate:open': (value) => { open.value = value; }, onSaved: () => saved.push(true),
    }) });
    app.config.globalProperties.$t = trans;
    app.mount('#app');
    open.value = true; await tick();
    return { app, open, saved, mode: currentMode };
};

test('Move defaults to one, confirms safely, sends a partial location return and refreshes after success', async () => {
    const { app, open, saved } = await mount('move');
    try {
        form.location_id = 4; form.entitlement_item_id = 2; await tick();
        assert.equal(form.quantity, 1);
        form.quantity = 5; await tick();
        document.querySelector('#confirm').click(); await tick();
        assert.equal(requests.length, 0, 'Review must not move stock');
        assert.match(document.body.textContent, /Move 5 <script>Wristband<\/script> to Empty returns/);
        assert.equal(document.querySelector('script'), null, 'Confirmation names must remain text');
        document.querySelector('#confirm').click(); await tick();
        assert.equal(requests.length, 1);
        assert.equal(requests[0].method, 'put');
        assert.equal(requests[0].url, '/events/7/check-in/inventory/returns');
        assert.deepEqual(requests[0].data, { location_id: 4, entitlement_item_id: 2, quantity: 5 });
        requests[0].options.onSuccess(); await tick();
        assert.equal(open.value, false); assert.equal(saved.length, 1);
        assert.deepEqual(successes, [trans('inventory.toast.moved')]);
    } finally { app.unmount(); }
});

test('Move rejects invalid quantities and cancellation makes no request', async () => {
    const { app, open } = await mount('move');
    try {
        form.location_id = 3; form.entitlement_item_id = 2; await tick();
        for (const value of [0, -1, 1.5, 13]) {
            form.quantity = value; await tick();
            assert.equal(document.querySelector('#confirm').disabled, true);
        }
        document.querySelector('#cancel').click(); await tick();
        assert.equal(open.value, false); assert.equal(requests.length, 0);
    } finally { app.unmount(); }
});

test('Pull restricts location/item choices, confirms actor intent and surfaces stale-stock errors', async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async (url) => ({ ok: true, json: async () => url.includes('availability') ? { data: [{ location_id: 3, entitlement_item_id: 2, balance: 8 }] } : { data: [{ id: 9, name: 'Morgan' }], links: { next: null } } });
    const { app } = await mount('pull');
    try {
        await tick();
        const location = [...document.querySelectorAll('select')][0];
        assert.equal(location.textContent.includes('Empty returns'), false);
        form.location_id = 3; await tick();
        form.person_id = 9; form.entitlement_item_id = 2; form.quantity = 8; await tick();
        document.querySelector('#confirm').click(); await tick();
        assert.match(document.body.textContent, /Give 8 .* from Main gate to Morgan/);
        assert.equal(requests.length, 0);
        document.querySelector('#confirm').click(); await tick();
        assert.equal(requests[0].method, 'post');
        assert.equal(requests[0].url, '/events/7/check-in/inventory/pulls');
        requests[0].options.onError({ quantity: 'Stock changed' }); await tick();
        assert.deepEqual(errors, [{ quantity: 'Stock changed' }]);
        assert.ok(document.querySelector('select'), 'Failure returns to editable fields');
    } finally { app.unmount(); globalThis.fetch = originalFetch; }
});


test('reopening starts with empty choices even after Inertia adopts submitted values as defaults', async () => {
    const { app, open } = await mount('move');
    try {
        form.location_id = 3; form.entitlement_item_id = 2; await tick();
        form.quantity = 5;
        // Inertia updates reset defaults following a successful submission.
        form.reset = () => Object.assign(form, { location_id: 3, entitlement_item_id: 2, person_id: '', quantity: 5 });
        open.value = false; await tick();
        open.value = true; await tick();
        assert.equal(form.location_id, '');
        assert.equal(form.entitlement_item_id, '');
        assert.equal(document.querySelector('#confirm').disabled, true);
        form.entitlement_item_id = 2; await tick();
        assert.equal(form.quantity, 1, 'Re-selecting the item starts at one');
    } finally { app.unmount(); }
});


test('cancelling a pending Pull cannot leave a subsequent Move disabled by stale loading', async () => {
    const originalFetch = globalThis.fetch;
    const pending = [];
    globalThis.fetch = () => new Promise((resolve) => pending.push(resolve));
    const { app, open, mode } = await mount('pull');
    try {
        open.value = false; await tick();
        mode.value = 'move'; open.value = true; await tick();
        form.location_id = 4; form.entitlement_item_id = 2; await tick();
        assert.equal(form.quantity, 1);
        assert.equal(document.querySelector('#confirm').disabled, false);
    } finally {
        app.unmount();
        for (const resolve of pending) resolve({ ok: true, json: async () => ({ data: [], links: { next: null } }) });
        globalThis.fetch = originalFetch;
    }
});

test('Move stock lists managers beside locations and confirms a manager return without a location', async () => {
    const originalFetch = globalThis.fetch;
    const urls = [];
    globalThis.fetch = async (url) => { urls.push(url); return { ok: true, json: async () => ({ data: [{ id: 9, name: '<b>Jordan</b>', is_manager: true }], links: { next: null } }) }; };
    const { app } = await mount('move');
    try {
        await tick();
        const select = document.querySelectorAll('select')[1];
        assert.match(select.textContent, /Main gate/);
        assert.match(select.textContent, /<b>Jordan<\/b>/);
        select.value = 'person:9'; select.dispatchEvent(new window.Event('change')); await tick();
        form.entitlement_item_id = 2; await tick();
        form.quantity = 4;
        document.querySelector('#confirm').click(); await tick();
        assert.match(document.body.textContent, /Move 4 .* to <b>Jordan<\/b>/);
        assert.equal(document.querySelector('b'), null);
        document.querySelector('#confirm').click(); await tick();
        assert.equal(form.location_id, '');
        assert.equal(requests[0].data.person_id, 9);
        assert.equal(requests[0].method, 'put');
        assert.equal(requests[0].url, '/events/7/check-in/inventory/returns');
        assert.match(urls[0], /\/move-receivers\?/);
    } finally { app.unmount(); globalThis.fetch = originalFetch; }
});

test('Move starts at one, includes its scoped people, confirms and clears an expired receiver on error', async () => {
    const originalFetch = globalThis.fetch;
    const urls = [];
    globalThis.fetch = async (url) => { urls.push(url); return { ok: true, json: async () => ({ data: [{ id: 9, name: 'Riley', on_shift: true, in_group: true }], links: { next: null } }) }; };
    const { app, open } = await mount('move');
    try {
        await tick();
        form.entitlement_item_id = 2; form.person_id = 9; await tick();
        assert.equal(document.querySelector('option[value="person:9"]').getAttribute('data-group'), trans('inventory.on_shift'));
        assert.equal(form.quantity, 1);
        form.quantity = 10; await tick();
        document.querySelector('#confirm').click(); await tick();
        assert.match(document.body.textContent, /Move 10 .* to Riley/);
        assert.equal(requests.length, 0);
        document.querySelector('#confirm').click(); await tick();
        assert.equal(requests[0].url, '/events/7/check-in/inventory/transfers');
        requests[0].options.onError({ person_id: 'Shift window expired' }); await tick();
        assert.equal(form.person_id, '');
        assert.equal(document.querySelector('#confirm').disabled, true);
        assert.equal(open.value, true);
        assert.match(urls[0], /\/move-receivers\?/);
    } finally { app.unmount(); globalThis.fetch = originalFetch; }
});

test('Take back defaults to all source stock, supports every destination and submits the optional note', async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async () => ({ ok: true, json: async () => ({ data: [{ id: 9, name: 'Riley' }], links: { next: null } }) });
    for (const destination of ['me', 'location', 'person']) {
        const { app } = await mount('take_back', { ownPersonId: 5, holder: { id: 20, person_id: 6, name: '<b>Morgan</b>', items: [{ id: 2, name: 'Wristband', balance: 7 }] } });
        try {
            await tick();
            form.entitlement_item_id = 2; await tick();
            assert.equal(form.quantity, 7);
            form.destination = destination; await tick();
            if (destination === 'location') form.location_id = 4;
            if (destination === 'person') form.person_id = 9;
            form.quantity = 3; form.note = 'Late handover'; await tick();
            document.querySelector('#confirm').click(); await tick();
            assert.match(document.body.textContent, /Take back 3 Wristband from <b>Morgan<\/b> to/);
            assert.equal(document.querySelector('b'), null);
            document.querySelector('#confirm').click(); await tick();
            assert.equal(requests[0].url, '/events/7/check-in/inventory/holders/20/take-backs');
            assert.equal(requests[0].data.destination, destination);
            assert.equal(requests[0].data.note, 'Late handover');
        } finally { app.unmount(); }
    }
    globalThis.fetch = originalFetch;
});

test('Take back cannot choose Me when viewing your own holder and reopening clears destination and note', async () => {
    const { app, open } = await mount('take_back', { ownPersonId: 5, holder: { id: 20, person_id: 5, name: 'Me', items: [{ id: 2, name: 'Wristband', balance: 7 }] } });
    try {
        assert.equal(document.querySelector('input[type="radio"][value="me"]').disabled, true);
        form.entitlement_item_id = 2; await tick();
        assert.equal(document.querySelector('#confirm').disabled, true);
        form.destination = 'location'; await tick();
        form.location_id = 3; form.note = 'Previous';
        open.value = false; await tick(); open.value = true; await tick();
        assert.equal(form.location_id, ''); assert.equal(form.person_id, ''); assert.equal(form.note, '');
        assert.equal(document.querySelector('#confirm').disabled, true);
    } finally { app.unmount(); }
});
