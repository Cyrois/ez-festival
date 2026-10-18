import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { compileScript, parse } from '@vue/compiler-sfc';
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<div id="app"></div>', { url: 'http://localhost' });
for (const key of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node']) globalThis[key] = dom.window[key];
const { createApp, h, ref, reactive, nextTick } = await import('vue');
const require = createRequire(import.meta.url);
const locale = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'));
const trans = (key, params = {}) => Object.entries(params).reduce((text, [name, value]) => text.replace(`:${name}`, value), locale[key] ?? key);
const requests = [], toasts = [];
let form;
globalThis.ownStockConsumeTest = {
    trans,
    useForm: (initial) => {
        form = reactive({ ...initial, errors: {}, processing: false,
            clearErrors() { this.errors = {}; },
            post(endpoint, options) {
                this.processing = true;
                requests.push({ endpoint, data: { code: this.code }, options });
            },
        });
        return form;
    },
    useFlashToast: () => ({ showFormError: (errors) => toasts.push(errors) }),
    Dialog: {
        props: ['open', 'confirmDisabled', 'busy', 'confirmLabel'], emits: ['confirm', 'update:open'],
        setup: (props, { slots, emit }) => () => props.open ? h('section', { role: 'dialog' }, [
            slots.description?.(), slots.default?.(),
            h('button', { id: 'confirm', disabled: props.confirmDisabled || props.busy, onClick: () => emit('confirm') }, props.confirmLabel),
        ]) : null,
    },
    CustomDropdown: { props: ['modelValue'], setup: () => () => h('div', { 'data-location': true }) },
    EntitlementItemName: { props: ['name'], setup: (props) => () => h('strong', props.name) },
};
const compile = (path, replacements = []) => {
    const { descriptor } = parse(readFileSync(new URL(path, import.meta.url), 'utf8'));
    let source = compileScript(descriptor, { id: path, inlineTemplate: true }).content
        .replaceAll(/from ['"]vue['"]/g, `from '${pathToFileURL(require.resolve('vue')).href}'`)
        .replace(/import \{ cn \} from ['"].*?['"];?/, 'const cn = (...values) => values.filter(Boolean).join(" ");');
    for (const [pattern, replacement] of replacements) source = source.replace(pattern, replacement);
    return `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
};
const inputUrl = compile('../../resources/js/components/ui/input/Input.vue');
const fieldUrl = compile('../../resources/js/components/ui/form-field/FormField.vue');
const imports = ['useForm', 'trans', 'Dialog', 'CustomDropdown', 'EntitlementItemName', 'useFlashToast'].map((name) => [
    new RegExp(`import \\{ ${name} \\} from ['"].*?['"];?`), `const ${name} = globalThis.ownStockConsumeTest.${name};`,
]);
const { default: Component } = await import(compile('../../resources/js/components/check-in/ConsumeEntitlementDialog.vue', [
    ...imports,
    [/import \{ Input \} from ['"].*?['"];?/, `import Input from '${inputUrl}';`],
    [/import \{ FormField \} from ['"].*?['"];?/, `import FormField from '${fieldUrl}';`],
]));
const tick = async () => { await nextTick(); await nextTick(); };
const mount = async (stock = 12, canConsume = true, blockedReason = null) => {
    requests.length = 0; toasts.length = 0;
    const open = ref(false);
    const entitlement = ref({ id: 1, name: 'Wristband', own_stock: stock, can_consume: canConsume, consume_blocked_reason: blockedReason });
    const app = createApp({ setup: () => () => h(Component, {
        open: open.value, entitlement: entitlement.value, endpoint: '/check-in/expected-entitlements/1/issues',
        'onUpdate:open': (value) => { open.value = value; },
    }) });
    app.config.globalProperties.$t = trans;
    app.mount('#app'); open.value = true; await tick();
    return { app, open, entitlement };
};

test('Consume shows only own count, retains optional Code and posts no source selector', async () => {
    const { app, open } = await mount();
    try {
        assert.match(document.querySelector('[role="dialog"]').textContent, /You have 12 left/);
        assert.equal(document.querySelector('select, [data-location]'), null);
        assert.match(document.body.textContent, /Code \(optional\)/);
        assert.equal(document.querySelector('input').maxLength, 64);
        assert.equal(document.querySelector('#confirm').disabled, false);
        document.querySelector('#confirm').click(); await tick();
        assert.deepEqual(requests[0].data, { code: '' });
        assert.equal(open.value, true);
        assert.equal(document.querySelector('#confirm').disabled, true);
        requests[0].options.onSuccess(); await tick();
        assert.equal(open.value, false);
    } finally { app.unmount(); }
});

for (const [count, key] of [[0, 'check_in.no_stock'], [12, 'check_in.inactive_stock'], [12, 'artists.check_in.locked'], [12, 'check_in.needs_edit_permission']]) {
    test('blocked dialog never submits: ' + key, async () => {
        const { app } = await mount(count, false, trans(key));
        try {
            assert.equal(document.querySelector('#confirm').disabled, true);
            assert.equal(document.querySelector('[role="alert"]').textContent.trim(), trans(key));
            document.querySelector('#confirm').click();
            assert.equal(requests.length, 0);
        } finally { app.unmount(); }
    });
}

test('server stock race keeps dialog and code, displays the quantity error and one toast', async () => {
    const { app, open } = await mount();
    try {
        form.code = 'BAND-42'; await tick();
        document.querySelector('#confirm').click();
        const errors = { quantity: trans('check_in.no_stock') };
        form.processing = false; form.errors = errors;
        requests[0].options.onError(errors); await tick();
        assert.equal(open.value, true);
        assert.equal(document.querySelector('input').value, 'BAND-42');
        assert.equal(document.querySelector('[role="alert"]').textContent.trim(), errors.quantity);
        assert.deepEqual(toasts, [errors]);
    } finally { app.unmount(); }
});

test('opening a different entitlement clears old input and errors and reflects its count', async () => {
    const { app, entitlement } = await mount();
    try {
        form.code = 'PREVIOUS'; form.errors = { code: 'Error' };
        entitlement.value = { ...entitlement.value, id: 2, own_stock: 4 }; await tick();
        assert.equal(document.querySelector('input').value, '');
        assert.deepEqual(form.errors, {});
        assert.match(document.querySelector('[role="dialog"]').textContent, /You have 4 left/);
    } finally { app.unmount(); }
});
