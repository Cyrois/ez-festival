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
const { createApp, h, nextTick, reactive, ref } = await import('vue');
const require = createRequire(import.meta.url);
const locale = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'));
const translate = (key, values = {}) => Object.entries(values).reduce((text, [name, value]) => text.replaceAll(`:${name}`, value), locale[key] ?? key);
const state = { page: reactive({ props: { permissions: {}, activeEvent: { id: 3 }, auth: { user: { is_admin: false } } }, url: '/credentials/entitlements' }), calls: [], forms: [], breadcrumbs: [] };
const box = (tag = 'div') => ({ setup: (_, { slots }) => () => h(tag, slots.default?.({ id: 'field', invalid: false })) });
const button = { props: ['href', 'disabled', 'enabled'], setup: (props, { slots, attrs }) => () => h(props.href ? 'a' : 'button', { ...attrs, href: props.href, disabled: props.disabled || props.enabled === false }, slots.default?.()) };
const dialog = { props: ['open', 'title', 'busy', 'confirmLabel'], emits: ['confirm', 'accept'], setup: (props, { slots, emit }) => () => props.open ? h('section', { 'data-dialog': props.title }, [slots.default?.(), h('button', { 'data-confirm': '', disabled: props.busy, onClick: () => { emit('confirm'); emit('accept'); } }, props.confirmLabel ?? 'Confirm')]) : null };
const form = (values) => {
    const keys = Object.keys(values);
    const result = reactive({ ...JSON.parse(JSON.stringify(values)), processing: false, errors: {},
        reset() { Object.assign(result, JSON.parse(JSON.stringify(values))); }, clearErrors() { result.errors = {}; }, setError(key, value) { result.errors[key] = value; },
        post(url) { state.calls.push({ method: 'post', url, data: Object.fromEntries(keys.map((key) => [key, result[key]])) }); },
        put(url) { state.calls.push({ method: 'put', url }); }, delete(url) { state.calls.push({ method: 'delete', url }); },
    });
    state.forms.push(result);
    return result;
};
const ui = (name) => {
    if (name === 'Button') return button;
    if (['Popup', 'Dialog'].includes(name)) return dialog;
    if (name === 'Input') return { props: ['modelValue', 'disabled'], setup: (props, { attrs }) => () => h('input', { ...attrs, value: props.modelValue, disabled: props.disabled }) };
    if (name === 'LabelCombobox') return { inheritAttrs: false, props: ['disabled'], setup: (props) => () => h('input', { 'data-label-picker': '', disabled: props.disabled }) };
    if (name === 'CustomDropdown') return { setup: (_, { attrs }) => () => h('div', { ...attrs, 'data-dropdown': '' }) };
    if (name === 'DataTable') return { props: ['options', 'ajax'], setup: (props, { slots }) => () => h('div', { 'data-server-side': String(props.options?.serverSide ?? false), 'data-ajax': props.ajax }, slots.actionsCell?.({ rowData: { id: 8, name: 'Wristband' } })) };
    if (name === 'Icon') return { render: () => null };
    return box(name === 'CardTitle' ? 'h2' : 'div');
};
const sectionNavigation = { props: ['section'], setup: (props) => () => h('nav', props.section.tabs.map((tab) => h(button, { href: tab.href, enabled: tab.enabled }, () => translate(`nav.${tab.key}`)))) };
globalThis.credentialsTest = { state, box, button, form, ui, translate, ref, sectionNavigation };
const compile = async (path, layout = false) => {
    const { descriptor } = parse(readFileSync(new URL(`../../resources/js/${path}`, import.meta.url), 'utf8'));
    let source = compileScript(descriptor, { id: path, inlineTemplate: true }).content
        .replaceAll(/from ['"]vue['"]/g, `from '${pathToFileURL(require.resolve('vue')).href}'`)
        .replace(/import \{([^}]+)\} from ['"]@inertiajs\/vue3['"];?/g, (_, names) => names.split(',').map((part) => {
            const name = part.trim();
            if (name === 'usePage') return 'const usePage = () => globalThis.credentialsTest.state.page;';
            if (name === 'useForm') return 'const useForm = globalThis.credentialsTest.form;';
            return `const ${name} = globalThis.credentialsTest.button;`;
        }).join('\n'))
        .replace(/import \{[^}]+\} from ['"]laravel-vue-i18n['"];?/, 'const trans = globalThis.credentialsTest.translate;')
        .replace(/import \{\s*([^}]+)\s*\} from ['"][^'"]*components\/ui\/[^'"]+['"];?/g, (_, names) => names.split(',').map((name) => name.trim()).filter(Boolean).map((name) => `const ${name.trim()} = globalThis.credentialsTest.ui('${name.trim()}');`).join('\n'))
        .replace(/import \{ useFlashToast \} from ['"][^'"]+['"];?/, 'const useFlashToast = () => ({ showError() {}, showFormError() {} });')
        .replace(/import \{ toastFormErrors \} from ['"][^'"]+['"];?/, 'const toastFormErrors = () => {};')
        .replace(/import \{ credentialsHome \} from ['"][^'"]+['"];?/, `import { credentialsHome } from '${new URL('../../resources/js/lib/credentialsNavigation.js', import.meta.url).href}';`)
        .replace(/import \{ navigateDataTableRow \} from ['"][^'"]+['"];?/, 'const navigateDataTableRow = () => {};')
        .replace(/import \{ entitlementColumns \} from ['"][^'"]+['"];?/, 'const entitlementColumns = () => [];')
        .replace(/import AppLayout from ['"][^'"]+['"];?/, 'const AppLayout = { inheritAttrs: false, props: ["breadcrumbs"], setup: (props, { slots }) => () => { globalThis.credentialsTest.state.breadcrumbs = props.breadcrumbs; return slots.default?.(); } };');
    if (layout) {
        source = source
            .replace(/from ['"]\.\.\/lib\/appNavigation['"]/, `from '${new URL('../../resources/js/lib/appNavigation.js', import.meta.url).href}'`)
            .replace(/import SectionNavigation from ['"][^'"]+['"];?/, 'const SectionNavigation = globalThis.credentialsTest.sectionNavigation;')
            .replace(/import ([A-Za-z]+) from ['"][^'"]+['"];?/g, (_, name) => `const ${name} = globalThis.credentialsTest.${name === 'SidebarNavItem' ? 'button' : 'box()'};`)
            .replace(/import \{ useInertiaErrorToast \} from ['"][^'"]+['"];?/, 'const useInertiaErrorToast = () => {};')
            .replace(/import \{[^}]+\} from ['"][^'"]*useSidebarCollapsed['"];?/, 'const useSidebarCollapsed = () => globalThis.credentialsTest.ref(false); const shouldUseCompactSidebar = () => false;');
    }
    return (await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`)).default;
};
const Entitlements = await compile('pages/Credentials/Entitlements.vue');
const Item = await compile('pages/Credentials/EditEntitlement.vue');
const Pass = await compile('pages/Credentials/CreatePass.vue');
const Layout = await compile('layouts/AppLayout.vue', true);
const mount = (component, props) => {
    state.calls = []; state.forms = [];
    const app = createApp(component, props);
    app.config.globalProperties.$t = translate;
    app.mount(document.getElementById('app'));
    return app;
};
const clickText = (text) => { const target = [...document.querySelectorAll('button')].find((button) => button.textContent.trim() === text); assert.ok(target, text); target.click(); };
const itemProps = { event: { id: 3 }, item: { id: 8, name: 'Wristband', labels: [] }, stats: { in_stock: 2, expected: 0, issued: 0 }, locations: [], locations_for_adjust: [], issued_log_url: '/credentials/entitlements/8/issued' };

test('item-only creation omits opening stock controls and uses a readable row and breadcrumb', async () => {
    state.page.props.permissions = { 'credentials.entitlements.edit': true };
    const app = mount(Entitlements, { canWrite: true, canEditItems: true, canAdjustStock: false });
    try {
        assert.equal(state.breadcrumbs[1].href, '/credentials/entitlements');
        assert.equal(document.querySelector('a').getAttribute('href'), '/credentials/entitlements/8');
        clickText('Add item'); await nextTick();
        const popup = document.querySelector('[data-dialog]');
        assert.ok(popup);
        assert.doesNotMatch(popup.textContent, /Opening amount|Opening balance|Location/);
        popup.querySelector('[data-confirm]').click();
        assert.equal(state.calls[0].data.opening_balance, 0);
    } finally { app.unmount(); }
});

test('combined creation shows opening stock controls and stock-only cannot create', async () => {
    let app = mount(Entitlements, { canWrite: true, canEditItems: true, canAdjustStock: true, locations: [{ id: 2, name: 'Gate' }] });
    try {
        clickText('Add item'); await nextTick();
        assert.ok(document.querySelector('[data-dialog] input[type="number"]'));
        assert.ok(document.querySelector('[data-dialog] [data-dropdown]'));
    } finally { app.unmount(); }
    app = mount(Entitlements, { canWrite: true, canEditItems: false, canAdjustStock: true });
    try { assert.ok(!document.body.textContent.includes('Add item')); } finally { app.unmount(); }
});

test('stock-only item disables details but permits the adjustment deep link and hides admin locations link', async () => {
    state.page.props.permissions = { 'credentials.stock.adjust': true };
    const app = mount(Item, { ...itemProps, canEditItems: false, canAdjustStock: true, open_adjust: true });
    try {
        assert.ok(document.querySelector('#entitlement-details input').disabled);
        assert.ok(document.querySelector('[data-label-picker]').disabled);
        assert.ok(document.querySelector('[data-dialog]'));
        assert.equal(document.querySelector('a[href="/settings/locations"]'), null);
        assert.doesNotMatch(document.body.textContent, /Delete item/);
        assert.equal(document.querySelector('[form="entitlement-details"]'), null);
        assert.equal(document.querySelector('[data-server-side="true"]').dataset.ajax, itemProps.issued_log_url);
    } finally { app.unmount(); }
});

test('item-only and locked pages cannot open adjustments and delete requires confirmation', async () => {
    let app = mount(Item, { ...itemProps, canEditItems: true, canAdjustStock: false, open_adjust: true, adjust_location_id: 2 });
    try {
        assert.equal(document.querySelector('[data-dialog]'), null);
        assert.ok(!document.querySelector('#entitlement-details input').disabled);
        clickText('Delete item'); await nextTick();
        assert.equal(state.calls.length, 0);
        document.querySelector('[data-confirm]').click();
        assert.deepEqual(state.calls, [{ method: 'delete', url: '/events/3/credentials/entitlements/8' }]);
    } finally { app.unmount(); }
    app = mount(Item, { ...itemProps, canEditItems: true, canAdjustStock: true, is_read_only: true, open_adjust: true });
    try {
        assert.equal(document.querySelector('[data-dialog]'), null);
        assert.ok(document.querySelector('#entitlement-details input').disabled);
        assert.equal(document.querySelector('[form="entitlement-details"]'), null);
        assert.doesNotMatch(document.body.textContent, /Delete item/);
    } finally { app.unmount(); }
});

test('pass delete uses a confirmation dialog and the existing destroy endpoint', async () => {
    const app = mount(Pass, { event: { id: 3 }, pass: { id: 9, name: 'Artist', label_ids: [], entitlement_item_ids: [] }, labelColors: [], canWrite: true });
    try {
        assert.doesNotMatch(document.body.textContent, /Associated products/);
        clickText('Delete pass'); await nextTick();
        assert.equal(state.calls.length, 0);
        document.querySelector('[data-confirm]').click();
        assert.deepEqual(state.calls, [{ method: 'delete', url: '/events/3/credentials/passes/9' }]);
    } finally { app.unmount(); }
});

test('locked pass form is read-only and drops delete/save controls after the event locks', async () => {
    const props = reactive({ event: { id: 3 }, pass: { id: 9, name: 'Artist', label_ids: [], entitlement_item_ids: [8] }, labelColors: [], entitlementItems: [{ id: 8, name: 'Wristband' }], canWrite: true });
    state.calls = []; state.forms = [];
    const app = createApp({ setup: () => () => h(Pass, props) });
    app.config.globalProperties.$t = translate;
    app.mount(document.getElementById('app'));
    try {
        clickText('Delete pass'); await nextTick();
        assert.ok(document.querySelector('[data-dialog]'));
        props.canWrite = false;
        await nextTick();
        assert.equal(document.querySelector('[data-dialog]'), null);
        assert.doesNotMatch(document.body.textContent, /Delete pass/);
        assert.equal(document.querySelector('button[type="submit"]'), null);
        assert.ok([...document.querySelectorAll('form input')].every((input) => input.disabled));
        assert.equal(document.querySelector('a[href="/credentials/passes"]').textContent.trim(), 'Cancel');
        assert.match(document.body.textContent, /This event is locked/);
        document.querySelector('form').dispatchEvent(new dom.window.Event('submit', { bubbles: true, cancelable: true }));
        assert.equal(state.calls.length, 0);
    } finally { app.unmount(); }
});

test('Credentials sidebar destination and header tabs respect independent permissions', async () => {
    for (const [permissions, admin, expected] of [
        [{}, false, []],
        [{ 'credentials.passes.edit': true }, false, ['Passes']],
        [{ 'credentials.entitlements.edit': true }, false, ['Entitlements']],
        [{ 'credentials.stock.adjust': true }, false, ['Entitlements']],
        [{ 'inventory.manage': true }, false, ['Entitlements']],
        [{ 'inventory.pass_shift': true }, false, []],
        [{ 'inventory.pass_group': true }, false, []],
        [{ 'credentials.passes.edit': true, 'credentials.entitlements.edit': true, 'credentials.stock.adjust': true }, true, ['Products', 'Passes', 'Entitlements']],
    ]) {
        state.page.props.permissions = permissions;
        state.page.props.auth.user.is_admin = admin;
        state.page.url = permissions['credentials.passes.edit'] ? '/credentials/passes' : '/credentials/entitlements';
        const app = mount(Layout, { title: 'Test' });
        try {
            const nav = document.querySelector('aside nav');
            const settings = [...nav.querySelectorAll('a')].find((link) => link.textContent.trim() === 'Settings');
            assert.equal(Boolean(settings), admin || Boolean(permissions['inventory.manage']));
            if (settings) assert.equal(settings.getAttribute('href'), admin ? '/settings/events' : '/settings/locations');
            const header = document.querySelector('header');
            for (const name of ['Products', 'Passes', 'Entitlements']) {
                assert.equal(nav.textContent.includes(name), false);
                assert.equal(header.textContent.includes(name), expected.includes(name));
            }
            const parent = [...nav.querySelectorAll('a')].find((link) => link.textContent.includes('Credentials'));
            if (!expected.length) assert.equal(parent, undefined);
            else assert.equal(parent.getAttribute('href'), permissions['credentials.passes.edit'] ? '/credentials/passes' : '/credentials/entitlements');
        } finally { app.unmount(); }
    }
});

test('inventory manager item view cannot edit, adjust, delete, or open a deep-linked stock popup', async () => {
    state.page.props.permissions = { 'inventory.manage': true };
    state.page.props.auth.user.is_admin = false;
    const app = mount(Item, { ...itemProps, canEditItems: false, canAdjustStock: false, open_adjust: true, adjust_location_id: 2 });
    try {
        assert.equal(state.breadcrumbs[1].href, '/credentials/entitlements');
        assert.ok(document.querySelector('#entitlement-details input').disabled);
        assert.ok(document.querySelector('[data-label-picker]').disabled);
        assert.equal(document.querySelector('[form="entitlement-details"]'), null);
        assert.equal(document.querySelector('[data-dialog]'), null);
        assert.doesNotMatch(document.body.textContent, /Delete item|Adjust stock/);
        assert.equal(document.querySelector('[data-server-side="true"]').dataset.ajax, itemProps.issued_log_url);
        assert.deepEqual(state.calls, []);
    } finally { app.unmount(); }
});

test('inventory managers who can adjust stock can reach Locations from an empty stock popup', async () => {
    state.page.props.permissions = { 'inventory.manage': true, 'credentials.stock.adjust': true };
    state.page.props.auth.user.is_admin = false;
    const app = mount(Item, { ...itemProps, canEditItems: false, canAdjustStock: true, open_adjust: true });
    try {
        assert.ok(document.querySelector('[data-dialog] a[href="/settings/locations"]'));
    } finally { app.unmount(); }
});
