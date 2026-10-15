import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { parse, compileScript } from '@vue/compiler-sfc';
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
let form;
globalThis.inventoryTest = {
    trans,
    useForm: (initial) => {
        form = reactive({ ...initial, errors: {}, processing: false,
            clearErrors() { this.errors = {}; },
            reset() { Object.assign(this, initial); },
            post(url, options) { requests.push({ method: 'post', url, data: { person_id: this.person_id, location_id: this.location_id, entitlement_item_id: this.entitlement_item_id, quantity: this.quantity }, options }); },
            put(url, options) { requests.push({ method: 'put', url, data: { location_id: this.location_id, entitlement_item_id: this.entitlement_item_id, quantity: this.quantity }, options }); },
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
    CustomDropdown: { props: ['items', 'modelValue'], emits: ['update:modelValue'], setup: (props, { attrs, emit }) => () => h('select', { ...attrs, value: props.modelValue, onChange: (e) => emit('update:modelValue', Number(e.target.value)) }, [h('option', { value: '' }), ...props.items.map((item) => h('option', { value: item.value }, item.title))]) },
};
const path = '../../resources/js/components/check-in/InventoryMovementDialog.vue';
const { descriptor } = parse(readFileSync(new URL(path, import.meta.url), 'utf8'));
let source = compileScript(descriptor, { id: path, inlineTemplate: true }).content.replaceAll(/from ['"]vue['"]/g, `from '${pathToFileURL(require.resolve('vue')).href}'`);
for (const name of ['useForm', 'trans', 'Dialog', 'FormField', 'Input', 'CustomDropdown', 'useFlashToast']) {
    source = source.replace(new RegExp(`import \\{ ${name} \\} from ['"].*?['"];?`), `const ${name} = globalThis.inventoryTest.${name};`);
}
const { default: Component } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const tick = async () => { await nextTick(); await nextTick(); };
const mount = async (mode) => {
    requests.length = 0; errors.length = 0;
    const open = ref(false);
    const currentMode = ref(mode);
    const saved = [];
    const app = createApp({ setup: () => () => h(Component, { open: open.value, mode: currentMode.value, eventId: 7,
        items: [{ id: 2, name: '<script>Wristband</script>' }], ownItems: [{ id: 2, name: '<script>Wristband</script>', balance: 12 }],
        locations: [{ id: 3, name: 'Main gate' }, { id: 4, name: 'Empty returns' }],
        'onUpdate:open': (value) => { open.value = value; }, onSaved: () => saved.push(true),
    }) });
    app.config.globalProperties.$t = trans;
    app.mount('#app');
    open.value = true; await tick();
    return { app, open, saved, mode: currentMode };
};

test('Return defaults to all stock, confirms safely, sends a partial return and refreshes after success', async () => {
    const { app, open, saved } = await mount('return');
    try {
        form.location_id = 4; form.entitlement_item_id = 2; await tick();
        assert.equal(form.quantity, 12);
        form.quantity = 5; await tick();
        document.querySelector('#confirm').click(); await tick();
        assert.equal(requests.length, 0, 'Review must not move stock');
        assert.match(document.body.textContent, /Return 5 <script>Wristband<\/script> to Empty returns/);
        assert.equal(document.querySelector('script'), null, 'Confirmation names must remain text');
        document.querySelector('#confirm').click(); await tick();
        assert.equal(requests.length, 1);
        assert.equal(requests[0].method, 'put');
        assert.equal(requests[0].url, '/events/7/check-in/inventory/returns');
        assert.deepEqual(requests[0].data, { location_id: 4, entitlement_item_id: 2, quantity: 5 });
        requests[0].options.onSuccess(); await tick();
        assert.equal(open.value, false); assert.equal(saved.length, 1);
        assert.deepEqual(successes, [trans('inventory.toast.returned')]);
    } finally { app.unmount(); }
});

test('Return rejects invalid quantities and cancellation makes no request', async () => {
    const { app, open } = await mount('return');
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
    const { app, open } = await mount('return');
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
        assert.equal(form.quantity, 12, 'Re-selecting the item defaults to all currently held stock');
    } finally { app.unmount(); }
});


test('cancelling a pending Pull cannot leave a subsequent Return disabled by stale loading', async () => {
    const originalFetch = globalThis.fetch;
    const pending = [];
    globalThis.fetch = () => new Promise((resolve) => pending.push(resolve));
    const { app, open, mode } = await mount('pull');
    try {
        open.value = false; await tick();
        mode.value = 'return'; open.value = true; await tick();
        form.location_id = 4; form.entitlement_item_id = 2; await tick();
        assert.equal(form.quantity, 12);
        assert.equal(document.querySelector('#confirm').disabled, false);
    } finally {
        app.unmount();
        for (const resolve of pending) resolve({ ok: true, json: async () => ({ data: [], links: { next: null } }) });
        globalThis.fetch = originalFetch;
    }
});
