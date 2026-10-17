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
const trans = (key) => locale[key] ?? key;
const requests = [], toasts = [];
let form;
globalThis.consumeCodeTest = {
    trans,
    useForm: (initial) => {
        form = reactive({ ...initial, errors: {}, processing: false,
            clearErrors() { this.errors = {}; },
            post(endpoint, options) {
                this.processing = true;
                requests.push({ endpoint, data: { code: this.code, location_id: this.location_id }, options });
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
    new RegExp(`import \\{ ${name} \\} from ['"].*?['"];?`), `const ${name} = globalThis.consumeCodeTest.${name};`,
]);
const { default: Component } = await import(compile('../../resources/js/components/check-in/ConsumeEntitlementDialog.vue', [
    ...imports,
    [/import \{ Input \} from ['"].*?['"];?/, `import Input from '${inputUrl}';`],
    [/import \{ FormField \} from ['"].*?['"];?/, `import FormField from '${fieldUrl}';`],
]));
const tick = async () => { await nextTick(); await nextTick(); };
const mount = async (needsUniqueCode = true) => {
    requests.length = 0; toasts.length = 0;
    const open = ref(false);
    const entitlement = ref({ id: 1, name: 'Wristband', needs_unique_code: needsUniqueCode, locations: [{ id: 7, name: 'Gate', in_stock: 2 }] });
    const app = createApp({ setup: () => () => h(Component, {
        open: open.value, entitlement: entitlement.value, endpoint: '/check-in/expected-entitlements/1/issues',
        'onUpdate:open': (value) => { open.value = value; },
    }) });
    app.config.globalProperties.$t = trans;
    app.mount('#app'); open.value = true; await tick();
    return { app, open, entitlement };
};
const enter = () => document.querySelector('input').dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));

test('coded item opens with a focused required code field and cannot submit blank or whitespace', async () => {
    const { app } = await mount();
    try {
        const input = document.querySelector('input');
        assert.equal(document.activeElement, input);
        assert.equal(input.required, true);
        assert.equal(input.maxLength, 64);
        assert.equal(input.value, '');
        assert.equal(document.querySelector('#confirm').disabled, true);
        enter(); form.code = '   '; await tick(); enter();
        assert.equal(document.querySelector('#confirm').disabled, true);
        assert.equal(requests.length, 0);
    } finally { app.unmount(); }
});

test('scanner Enter submits the supplied code once and keeps the dialog open until success', async () => {
    const { app, open } = await mount();
    try {
        const input = document.querySelector('input');
        input.value = 'BAND-0042'; input.dispatchEvent(new window.Event('input', { bubbles: true })); await tick();
        assert.equal(document.querySelector('#confirm').disabled, false);
        enter(); enter(); await tick();
        assert.equal(requests.length, 1);
        assert.deepEqual(requests[0].data, { code: 'BAND-0042', location_id: 7 });
        assert.equal(open.value, true);
        assert.equal(input.disabled, true);
        requests[0].options.onSuccess(); await tick();
        assert.equal(open.value, false);
    } finally { app.unmount(); }
});

test('duplicate response stays on the code field and preserves the code and popup', async () => {
    const { app, open } = await mount();
    try {
        form.code = 'USED-1'; await tick(); document.querySelector('#confirm').click();
        const errors = { code: trans('check_in.errors.code_used') };
        form.processing = false; form.errors = errors; requests[0].options.onError(errors); await tick();
        assert.equal(open.value, true);
        assert.equal(document.querySelector('input').value, 'USED-1');
        assert.equal(document.querySelector('input').getAttribute('aria-invalid'), 'true');
        assert.equal(document.querySelector('[role="alert"]').textContent, errors.code);
        assert.deepEqual(toasts, [errors]);
    } finally { app.unmount(); }
});

test('uncoded item retains its optional code and permits an empty submission', async () => {
    const { app } = await mount(false);
    try {
        assert.equal(document.querySelector('input').required, false);
        assert.match(document.body.textContent, /Code \(optional\)/);
        assert.equal(document.querySelector('#confirm').disabled, false);
        document.querySelector('#confirm').click();
        assert.equal(requests.length, 1);
        assert.equal(requests[0].data.code, '');
    } finally { app.unmount(); }
});

test('opening another entitlement clears the previous code and validation error', async () => {
    const { app, entitlement } = await mount();
    try {
        form.code = 'PREVIOUS'; form.errors = { code: 'Used' };
        entitlement.value = { ...entitlement.value, id: 2 }; await tick();
        assert.equal(form.code, '');
        assert.deepEqual(form.errors, {});
        assert.equal(document.activeElement, document.querySelector('input'));
        assert.equal(document.querySelector('#confirm').disabled, true);
    } finally { app.unmount(); }
});
