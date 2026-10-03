import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { JSDOM } from 'jsdom';
import { parse, compileScript } from '@vue/compiler-sfc';

const dom = new JSDOM('<div id="app"></div>');
for (const key of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node']) {
    globalThis[key] = dom.window[key];
}
const { createApp, h, nextTick } = await import('vue');
const require = createRequire(import.meta.url);
const calls = [];
const rows = [
    { id: 10, name: 'Unused', active: true, people_count: 0 },
    { id: 11, name: 'Held', active: true, people_count: 1 },
];
let reloads = 0;
const Button = {
    props: ['disabled', 'type', 'loading', 'variant', 'size'],
    setup: (props, { slots }) => () => h('button', { disabled: props.disabled, type: props.type }, slots.default?.()),
};
const dependencies = {
    SettingsLayout: { setup: (_, { slots }) => () => h('main', slots.default?.()) },
    Button,
    Icon: { setup: () => () => null },
    Badge: { setup: (_, { slots }) => () => h('span', slots.default?.()) },
    Input: { setup: () => () => h('input') },
    DataTable: {
        setup: (_, { slots, expose }) => {
            expose({ reload: () => reloads++ });
            return () => h('div', rows.map((rowData) => h('div', { 'data-role': rowData.id }, slots.openCell({ rowData }))));
        },
    },
    Link: { setup: (_, { slots }) => () => h('a', slots.default?.()) },
    router: { delete: (url, options) => calls.push({ url, options }), get: () => {}, visit: () => {} },
    trans: (key) => key,
    transChoice: (key) => key,
    useFlashToast: () => ({ showFormError: () => {} }),
    rolesQuery: () => ({}),
    roleColumns: () => [],
    navigateDataTableRow: () => {},
    cn: (...values) => values.filter(Boolean).join(' '),
};
globalThis.roleDeletionDependencies = dependencies;

async function component(path) {
    const source = readFileSync(new URL(`../../resources/js/${path}`, import.meta.url), 'utf8');
    const { descriptor } = parse(source);
    const compiled = compileScript(descriptor, { id: path, inlineTemplate: true });
    const code = compiled.content.replace(/^import ([\s\S]*?) from ['"]([^'"]+)['"];?$/gm, (_, names, module) => {
        if (module === 'vue') return `import ${names} from '${pathToFileURL(require.resolve('vue')).href}';`;
        return `const ${names.trim()} = globalThis.roleDeletionDependencies${names.trim().startsWith('{') ? '' : '.' + names.trim()};`;
    });
    return (await import(`data:text/javascript;base64,${Buffer.from(code).toString('base64')}`)).default;
}

dependencies.Dialog = await component('components/ui/dialog/Dialog.vue');
const Roles = await component('pages/Settings/Roles.vue');

test('role deletion requires confirmation, Cancel makes no request, and held roles stay disabled', async () => {
    const app = createApp(Roles, {
        roles: { data: rows }, filters: { search: '', status: 'all' }, hasAnyRoles: true, canManageRoles: true,
    });
    app.config.globalProperties.$t = (key) => key;
    app.mount(document.getElementById('app'));
    try {
        const unused = document.querySelector('[data-role="10"] button');
        const held = document.querySelector('[data-role="11"] button');
        assert.equal(held.disabled, true);
        assert.equal(held.parentElement.title, 'settings.roles.delete.in_use');
        held.click();
        await nextTick();
        assert.equal(document.querySelector('[role="alertdialog"]'), null);
        unused.click();
        await nextTick();
        assert.equal(calls.length, 0);
        let dialog = document.querySelector('[role="alertdialog"]');
        assert.equal(dialog.querySelector('h2').textContent, 'settings.roles.delete.title');
        dialog.querySelector('button').click();
        await nextTick();
        assert.equal(document.querySelector('[role="alertdialog"]'), null);
        assert.equal(calls.length, 0);
        unused.click();
        await nextTick();
        dialog = document.querySelector('[role="alertdialog"]');
        dialog.querySelectorAll('button')[1].click();
        await nextTick();
        assert.equal(calls.length, 1);
        assert.equal(calls[0].url, '/settings/roles/10');
        assert.equal(held.disabled, true);
        await calls[0].options.onSuccess();
        calls[0].options.onFinish();
        await nextTick();
        assert.equal(document.querySelector('[role="alertdialog"]'), null);
        assert.equal(reloads, 1);
    } finally {
        app.unmount();
        delete globalThis.roleDeletionDependencies;
    }
});
