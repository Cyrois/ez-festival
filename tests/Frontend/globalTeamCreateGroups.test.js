import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import test from 'node:test';
import { JSDOM } from 'jsdom';
import { compileScript } from '@vue/compiler-sfc';
import { parseWithReturnNavigation as parse } from './helpers/returnNavigation.js';

const dom = new JSDOM('<div id="app"></div>', { url: 'http://localhost' });
for (const key of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node']) globalThis[key] = dom.window[key];
const { createApp, h, nextTick, reactive } = await import('vue');
const require = createRequire(import.meta.url);
const locale = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'));
const translate = (key) => locale[key] ?? key;
const state = { form: null, writes: [] };
const box = { setup: (_, { slots }) => () => h('div', slots.default?.()) };
const dropdown = {
    props: ['modelValue', 'items', 'disabled'], emits: ['update:modelValue'],
    setup: (props, { attrs, emit }) => () => h('select', {
        ...attrs, value: props.modelValue, disabled: props.disabled,
        onChange: (event) => emit('update:modelValue', props.items.find((item) => String(item.value) === event.target.value)?.value ?? ''),
    }, props.items.map((item) => h('option', { value: item.value }, item.title))),
};
const useForm = (values) => {
    const keys = Object.keys(values);
    state.form = reactive({ ...values, errors: {}, processing: false, isDirty: false,
        post(url) { state.writes.push({ url, data: JSON.parse(JSON.stringify(Object.fromEntries(keys.map((key) => [key, state.form[key]])))) }); },
    });
    return state.form;
};
const compile = async (path, transforms = []) => {
    const { descriptor } = parse(readFileSync(new URL(`../../resources/js/${path}`, import.meta.url), 'utf8'));
    let source = compileScript(descriptor, { id: path, inlineTemplate: true }).content
        .replaceAll(/from ['"]vue['"]/g, `from '${pathToFileURL(require.resolve('vue')).href}'`);
    for (const [pattern, replacement] of transforms) source = source.replace(pattern, replacement);
    return (await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`)).default;
};
const FormField = await compile('components/ui/form-field/FormField.vue', [
    [/from ['"]\.\.\/\.\.\/\.\.\/lib\/utils['"]/, `from '${new URL('../../resources/js/lib/utils.js', import.meta.url).href}'`],
]);
const ui = (name) => {
    if (name === 'FormField') return FormField;
    if (name === 'CustomDropdown') return dropdown;
    if (name === 'Dialog') return { render: () => null };
    if (name === 'Input') return { props: ['modelValue'], setup: (props, { attrs }) => () => h('input', { ...attrs, value: props.modelValue }) };
    return box;
};
globalThis.globalTeamCreateTest = { state, useForm, ui, box, translate };
const Create = await compile('pages/Settings/Team/Create.vue', [
    [/import \{ Link, router, useForm \} from ['"]@inertiajs\/vue3['"];?/, 'const Link = "a"; const router = {}; const useForm = globalThis.globalTeamCreateTest.useForm;'],
    [/import SettingsLayout from ['"][^'"]+['"];?/, 'const SettingsLayout = globalThis.globalTeamCreateTest.box;'],
    [/import \{([^}]+)\} from ['"][^'"]*components\/ui\/[^'"]+['"];?/g, (_, names) => names.split(',').map((name) => `const ${name.trim()} = globalThis.globalTeamCreateTest.ui('${name.trim()}');`).join('\n')],
    [/import \{ trans \} from ['"]laravel-vue-i18n['"];?/, 'const trans = globalThis.globalTeamCreateTest.translate;'],
    [/import \{ toastFormErrors \} from ['"][^'"]+['"];?/, 'const toastFormErrors = () => {};'],
    [/import \{ useFlashToast \} from ['"][^'"]+['"];?/, 'const useFlashToast = () => ({ showError() {}, showFormError() {} });'],
]);
const events = [
    { id: 4, name: 'Locked event', locked: true, groups: [{ id: 40, name: 'Locked group' }] },
    { id: 7, name: 'Summer event', locked: false, groups: [{ id: 70, name: 'Gate' }] },
    { id: 9, name: 'Winter event', locked: false, groups: [{ id: 90, name: 'Kitchen' }] },
];
const mount = () => {
    state.writes = [];
    const app = createApp(Create, { events, roles: [{ id: 3, name: 'Staff' }], statuses: ['applied', 'reviewing', 'hired'] });
    app.config.globalProperties.$t = translate;
    app.mount(document.getElementById('app'));
    return app;
};
const row = (name) => [...document.querySelectorAll('p')].find((p) => p.textContent.trim() === name).parentElement.parentElement;
const change = async (select, value) => {
    select.value = String(value);
    select.dispatchEvent(new window.Event('change', { bubbles: true }));
    await nextTick();
};
const submit = async () => {
    document.getElementById('global-team-add').dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
    await nextTick();
};

test('Hired requires an event-scoped group picker and submits the chosen group for each selected event', async () => {
    const app = mount();
    try {
        await change(document.querySelector('select'), 'hired');
        assert.equal(row('Locked event').querySelector('select').disabled, true);
        await change(row('Winter event').querySelector('select'), 3);
        let picker = row('Winter event').querySelectorAll('select')[1];
        assert.equal(picker.getAttribute('aria-required'), 'true');
        assert.equal(row('Winter event').querySelector('label').textContent.trim(), 'Group*');
        assert.deepEqual([...picker.options].map((option) => option.textContent), ['Kitchen']);
        await change(picker, 90);
        await submit();
        assert.deepEqual(state.writes[0].data.event_access, [{ event_id: 9, role_id: 3, group_id: 90 }]);
        state.form.errors = { 'event_access.0.group_id': 'Choose a group for Winter' };
        await nextTick();
        assert.match(row('Winter event').textContent, /Choose a group for Winter/);
        assert.doesNotMatch(row('Summer event').textContent, /Choose a group for Winter/);
        await change(row('Summer event').querySelector('select'), 3);
        await change(row('Summer event').querySelectorAll('select')[1], 70);
        await submit();
        assert.deepEqual(state.writes[1].data.event_access, [
            { event_id: 7, role_id: 3, group_id: 70 },
            { event_id: 9, role_id: 3, group_id: 90 },
        ]);
        state.form.processing = true;
        await nextTick();
        picker = row('Winter event').querySelectorAll('select')[1];
        assert.equal(picker.disabled, true);
    } finally { app.unmount(); }
});

test('Applied defaults to an optional empty group and changing status updates the requirement', async () => {
    const app = mount();
    try {
        assert.equal(state.form.status, 'applied');
        assert.equal(row('Summer event').querySelectorAll('select').length, 1);
        await change(row('Summer event').querySelector('select'), 3);
        const picker = row('Summer event').querySelectorAll('select')[1];
        assert.equal(picker.getAttribute('aria-required'), 'false');
        assert.equal(row('Summer event').querySelector('label').textContent.trim(), 'Group');
        assert.equal(picker.options[0].textContent, 'No group');
        await submit();
        assert.deepEqual(state.writes[0].data.event_access, [{ event_id: 7, role_id: 3, group_id: null }]);
        await change(document.querySelector('select'), 'hired');
        assert.equal(picker.getAttribute('aria-required'), 'true');
        assert.ok(![...picker.options].some((option) => option.value === ''));
        await change(document.querySelector('select'), 'applied');
        assert.equal(picker.getAttribute('aria-required'), 'false');
        assert.equal(picker.options[0].textContent, 'No group');
        await change(row('Summer event').querySelector('select'), '');
        assert.equal(row('Summer event').querySelectorAll('select').length, 1);
        await submit();
        assert.deepEqual(state.writes[1].data.event_access, []);
    } finally { app.unmount(); }
});
