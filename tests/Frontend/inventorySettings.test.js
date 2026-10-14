import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { JSDOM } from 'jsdom';
import { parse, compileScript } from '@vue/compiler-sfc';

const dom = new JSDOM('<div id="app"></div>', { url: 'http://localhost' });
for (const key of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node', 'DocumentFragment', 'getComputedStyle']) globalThis[key] = dom.window[key];
Object.defineProperty(globalThis, 'navigator', { value: dom.window.navigator, configurable: true });
const { createApp, h, nextTick, reactive } = await import('vue');
const require = createRequire(import.meta.url);
const locale = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'));
const translate = (key, values = {}) => Object.entries(values).reduce((text, [name, value]) => text.replaceAll(`:${name}`, value), locale[key] ?? key);
const state = { page: reactive({ props: { permissions: { 'inventory.manage': true }, auth: { user: { is_admin: false } } }, url: '/settings/locations' }), forms: [], calls: [], breadcrumbs: [] };
const button = { props: ['href', 'disabled'], setup: (props, { slots, attrs }) => () => h(props.href ? 'a' : 'button', { ...attrs, href: props.href, disabled: props.disabled }, slots.default?.()) };
const box = { setup: (_, { slots }) => () => h('div', slots.default?.()) };
const form = (values) => {
    const keys = Object.keys(values);
    let transform = (data) => data;
    const result = reactive({ ...values, processing: false, errors: {},
        transform(fn) { transform = fn; return result; },
        reset() { Object.assign(result, values); }, clearErrors() { result.errors = {}; },
        post(url) { state.calls.push({ method: 'post', url, data: transform(Object.fromEntries(keys.map((key) => [key, result[key]]))) }); },
        put(url) { state.calls.push({ method: 'put', url, data: transform(Object.fromEntries(keys.map((key) => [key, result[key]]))) }); },
    });
    state.forms.push(result);
    return result;
};
const ui = (name) => {
    if (name === 'Button') return button;
    if (name === 'Icon') return { render: () => null };
    if (name === 'Input') return { props: ['modelValue'], setup: (props, { attrs }) => () => h('input', { ...attrs, value: props.modelValue }) };
    if (name === 'FormField') return { props: ['label'], setup: (props, { slots }) => () => h('div', { 'data-field': props.label }, [h('label', props.label), slots.default?.({ id: props.label, invalid: false })]) };
    if (name === 'Dialog') return { props: ['open'], setup: (props) => () => props.open ? h('div', { 'data-dialog': '' }) : null };
    return box;
};
globalThis.inventorySettingsTest = { state, form, ui, button, box, translate };
const encode = (source) => `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
const compile = (path) => {
    const { descriptor } = parse(readFileSync(new URL(`../../resources/js/${path}`, import.meta.url), 'utf8'));
    return compileScript(descriptor, { id: path, inlineTemplate: true }).content
        .replaceAll(/from ['"]vue['"]/g, `from '${pathToFileURL(require.resolve('vue')).href}'`)
        .replace(/import \{([^}]+)\} from ['"]@inertiajs\/vue3['"];?/g, (_, names) => names.split(',').map((part) => {
            const name = part.trim();
            if (name === 'usePage') return 'const usePage = () => globalThis.inventorySettingsTest.state.page;';
            if (name === 'useForm') return 'const useForm = globalThis.inventorySettingsTest.form;';
            if (name === 'router') return 'const router = { delete: (url) => globalThis.inventorySettingsTest.state.calls.push({ method: "delete", url }), get() {} };';
            return `const ${name} = globalThis.inventorySettingsTest.button;`;
        }).join('\n'))
        .replace(/import \{[^}]+\} from ['"]laravel-vue-i18n['"];?/, 'const trans = globalThis.inventorySettingsTest.translate;')
        .replace(/import \{\s*([^}]+)\s*\} from ['"][^'"]*\/ui\/[^'"]+['"];?/g, (_, names) => names.split(',').map((name) => name.trim()).filter(Boolean).map((name) => `const ${name} = globalThis.inventorySettingsTest.ui('${name}');`).join('\n'))
        .replace(/import \{ useFlashToast \} from ['"][^'"]+['"];?/, 'const useFlashToast = () => ({ showError() {}, showFormError() {}, showSuccess() {} });')
        .replace(/import \{ fieldError, toastFormErrors \} from ['"][^'"]+['"];?/, 'const fieldError = (form, key) => form.errors[key]; const toastFormErrors = () => {};');
};
const layoutSource = compile('layouts/SettingsLayout.vue')
    .replace(/import AppLayout from ['"][^'"]+['"];?/, 'const AppLayout = { inheritAttrs: false, props: ["breadcrumbs"], setup: (props, { slots }) => () => { globalThis.inventorySettingsTest.state.breadcrumbs = props.breadcrumbs; return [slots["settings-nav"]?.(), slots.default?.()]; } };')
    .replace(/import SidebarNavItem from ['"][^'"]+['"];?/, 'const SidebarNavItem = globalThis.inventorySettingsTest.button;');
const shellSource = compile('components/settings/EventEditShell.vue')
    .replace(/import SettingsLayout from ['"][^'"]+['"];?/, `import SettingsLayout from '${encode(layoutSource)}';`);
const locationsSource = compile('pages/Settings/Events/Locations.vue')
    .replace(/import EventEditShell from ['"][^'"]+['"];?/, `import EventEditShell from '${encode(shellSource)}';`);
const { default: Locations } = await import(encode(locationsSource));
const props = { event: { id: 7, name: 'Festival', is_active: true, is_read_only: false }, forPrimary: true,
    locations: [{ id: 9, name: 'Main Gate', type: 'stage' }], canAddLocations: true, canRenameLocations: true, canDeleteLocations: false, canEditLocationTypes: false };
const mount = (overrides = {}, admin = false) => {
    state.page.props.auth.user.is_admin = admin;
    state.forms = []; state.calls = [];
    const app = createApp(Locations, { ...props, ...overrides });
    app.config.globalProperties.$t = translate;
    app.mount(document.getElementById('app'));
    return app;
};
const clickText = (text) => { const button = [...document.querySelectorAll('button')].find((button) => button.textContent.trim() === text); assert.ok(button, text); button.click(); };

test('manager Settings and event shell expose Locations and Account without admin links', () => {
    const app = mount();
    try {
        const links = [...document.querySelectorAll('a')].map((link) => link.getAttribute('href'));
        assert.ok(links.includes('/settings/locations'));
        assert.ok(links.includes('/settings/account'));
        assert.ok(links.includes('/dashboard'));
        assert.ok(!links.some((href) => href === '/settings/events' || href === '/settings/roles' || href === '/settings/team' || href.endsWith('/edit')));
        assert.ok(!state.breadcrumbs.some((crumb) => crumb.href === '/settings/events'));
        assert.match(document.body.textContent, /Manage locations for Festival, your current event/);
        assert.equal(document.querySelector('button[aria-label="Delete"]'), null);
    } finally { app.unmount(); }
});

test('manager add and rename submit only names, preserving existing location types', async () => {
    const app = mount();
    try {
        clickText('Add location'); await nextTick();
        assert.equal(document.querySelector(`[data-field="${locale['setup.locations.type']}"]`), null);
        state.forms[0].name = 'West Gate'; state.forms[0].type = 'unsent';
        document.querySelector('form').dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
        assert.deepEqual(state.calls[0], { method: 'post', url: '/settings/events/7/locations', data: { name: 'West Gate' } });
        document.querySelector('button[aria-label="Edit"]').click(); await nextTick();
        assert.equal(document.querySelector(`[data-field="${locale['setup.locations.type']}"]`), null);
        state.forms[1].name = 'Renamed Gate';
        document.querySelectorAll('form')[1].dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
        assert.deepEqual(state.calls[1], { method: 'put', url: '/settings/events/7/locations/9', data: { name: 'Renamed Gate' } });
    } finally { app.unmount(); }
});

test('locked manager Locations hides every write control', () => {
    const app = mount({ event: { ...props.event, is_read_only: true, is_locked: true }, canAddLocations: false, canRenameLocations: false, canDeleteLocations: false });
    try {
        assert.ok(![...document.querySelectorAll('button')].some((button) => button.textContent.trim() === 'Add location'));
        assert.equal(document.querySelector('button[aria-label="Edit"]'), null);
        assert.equal(document.querySelector('button[aria-label="Delete"]'), null);
        assert.equal(document.querySelector('form'), null);
        assert.deepEqual(state.calls, []);
    } finally { app.unmount(); }
});

test('admins retain event Details links, optional types, and delete controls', async () => {
    const app = mount({ canDeleteLocations: true, canEditLocationTypes: true }, true);
    try {
        assert.ok(document.querySelector('a[href="/settings/events"]'));
        assert.ok(document.querySelector('button[aria-label="Delete"]'));
        clickText('Add location'); await nextTick();
        assert.ok(document.querySelector(`[data-field="${locale['setup.locations.type']}"]`));
        state.forms[0].name = 'New stage'; state.forms[0].type = 'stage';
        document.querySelector('form').dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
        assert.deepEqual(state.calls[0].data, { name: 'New stage', type: 'stage' });
    } finally { app.unmount(); }
});
