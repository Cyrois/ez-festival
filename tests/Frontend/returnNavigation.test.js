import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import test from 'node:test';
import { parse, compileScript } from '@vue/compiler-sfc';
import { JSDOM } from 'jsdom';
import { withReturnTo, returnFormOptions } from '../../resources/js/lib/returnNavigation.js';

const dom = new JSDOM('<main id="app"></main>', { url: 'http://localhost' });
for (const name of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node', 'Option', 'DocumentFragment', 'getComputedStyle']) globalThis[name] = dom.window[name];
Object.defineProperty(globalThis, 'navigator', { value: dom.window.navigator, configurable: true });
window.performance.getEntriesByType = () => [];
const { createApp, h, ref, reactive, watch, nextTick } = await import('vue');
const require = createRequire(import.meta.url);
const vue = pathToFileURL(require.resolve('vue')).href;
const encode = (source) => `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
const locale = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'));
const translate = (key, values = {}) => Object.entries(values).reduce((text, [name, value]) => text.replaceAll(`:${name}`, String(value)), locale[key] ?? key);
const page = reactive({ url: '/credentials/passes', props: { auth: { user: { id: 7 } }, activeEvent: { id: 11 } } });
const destinations = [];
globalThis.returnTestPage = page;
globalThis.returnTestRouter = { visit: (href) => destinations.push(href) };
globalThis.returnTestLink = { setup: (_, { attrs, slots }) => () => h('a', { ...attrs, onClick: (event) => { if (!event.defaultPrevented) { event.preventDefault(); destinations.push(attrs.href); } } }, slots.default?.()) };
globalThis.returnTestDialog = {
    props: ['open', 'title'],
    setup: (props, { slots, emit }) => () => props.open ? h('div', { role: 'alertdialog', 'aria-label': props.title }, [h('button', { onClick: () => emit('cancel') }, 'Stay'), slots.default?.()]) : null,
};
const javascript = (path, replacements = []) => {
    let source = readFileSync(new URL(`../../resources/js/${path}`, import.meta.url), 'utf8')
        .replace(/from ['"]vue['"]/g, `from '${vue}'`);
    for (const [pattern, replacement] of replacements) source = source.replace(pattern, replacement);
    return encode(source);
};
const compile = (path, replacements = []) => {
    const { descriptor } = parse(readFileSync(new URL(`../../resources/js/${path}.vue`, import.meta.url), 'utf8'));
    let source = compileScript(descriptor, { id: path, inlineTemplate: true }).content.replaceAll(/from ['"]vue['"]/g, `from '${vue}'`);
    for (const [pattern, replacement] of replacements) source = source.replace(pattern, replacement);
    return encode(source);
};
const guard = javascript('composables/useUnsavedNavigation.js', [[/import \{ router \} from ['"].*?['"];?/, 'const router = globalThis.returnTestRouter;']]);
const button = compile('components/ui/button/Button', [
    [/import \{ Link \} from ['"].*?['"];?/, 'const Link = globalThis.returnTestLink;'],
    [/import \{ cn \} from ['"].*?['"];?/, 'const cn = (...values) => values.filter(Boolean).join(" ");'],
    [/import \{ buttonVariants \} from ['"].*?['"];?/, 'const buttonVariants = () => "";'],
]);
const dialog = compile('components/ui/unsaved-changes-dialog/UnsavedChangesDialog', [
    [/import \{ Button \} from ['"].*?['"];?/, `import Button from '${button}';`],
    [/import \{ Dialog \} from ['"].*?['"];?/, 'const Dialog = globalThis.returnTestDialog;'],
    [/from ['"][^'"]*useUnsavedNavigation['"]/, `from '${guard}'`],
]);
const back = compile('components/ui/back-link/BackLink', [
    [/import \{ Button \} from ['"].*?['"];?/, `import Button from '${button}';`],
    [/import \{ Icon \} from ['"].*?['"];?/, `const Icon = { render: () => h('span', {'aria-hidden': 'true'}) }; import { h } from '${vue}';`],
    [/import \{ UnsavedChangesDialog \} from ['"].*?['"];?/, `import UnsavedChangesDialog from '${dialog}';`],
    [/import \{ usePage \} from ['"].*?['"];?/, 'const usePage = () => globalThis.returnTestPage;'],
]);
const { default: BackLink } = await import(back);
const stateModule = javascript('composables/useLandingState.js', [[/import \{ usePage \} from ['"].*?['"];?/, 'const usePage = () => globalThis.returnTestPage;']]);
const { useLandingState } = await import(stateModule);
const tick = async () => { await nextTick(); await nextTick(); };
const mountBack = (props, onSave) => {
    const app = createApp({ render: () => h(BackLink, { href: '/artists/advancing', page: 'Artists', ...props, onSave }) });
    app.config.globalProperties.$t = translate;
    app.mount(document.getElementById('app'));
    return app;
};
const clickText = (text) => [...document.querySelectorAll('a, button')].find((element) => element.textContent.trim() === text).click();

test('return targets preserve existing person, anchors and scheduling context without changing the destination', () => {
    const navigation = { href: '/check-in?type=artist&search=River' };
    const url = new URL(withReturnTo('/check-in/artists/3?person=19#passes', navigation), 'http://localhost');
    assert.equal(url.pathname, '/check-in/artists/3');
    assert.equal(url.searchParams.get('person'), '19');
    assert.equal(url.hash, '#passes');
    assert.equal(url.searchParams.get('return_to'), '/check-in?type=artist&search=River&restore=1');
    assert.equal(withReturnTo('https://example.test/', navigation), 'https://example.test/');
    assert.equal(withReturnTo('/team/members/3', null), '/team/members/3');
    assert.equal(new URL(withReturnTo('http://localhost/team/members/3', navigation), 'http://localhost').searchParams.get('return_to'), '/check-in?type=artist&search=River&restore=1');
});

test('read-only Back is a named focusable link with a decorative arrow and respects restricted Events destinations', async () => {
    page.props.returnNavigation = { href: '/check-in?status=complete', page: 'check_in.title' };
    const app = mountBack();
    try {
        const link = document.querySelector('a');
        assert.equal(link.textContent.trim(), 'Back to Check-in');
        assert.equal(link.getAttribute('href'), '/check-in?status=complete');
        assert.equal(link.querySelector('[aria-hidden]').getAttribute('aria-hidden'), 'true');
        link.focus();
        assert.equal(document.activeElement, link);
        assert.equal(document.querySelector('[role="alertdialog"]'), null);
    } finally { app.unmount(); }
    const events = mountBack({ href: '/settings/events', page: 'Events', restrictContext: true });
    try { assert.equal(document.querySelector('a').getAttribute('href'), '/settings/events'); }
    finally { events.unmount(); delete page.props.returnNavigation; }
});

test('dirty Back supports staying, discarding without a write, failed save, and successful save before returning', async () => {
    destinations.length = 0;
    const writes = [];
    const form = reactive({ isDirty: true, processing: false, defaults() { this.isDirty = false; } });
    const app = mountBack({ form }, (afterSave) => writes.push(returnFormOptions(form, afterSave)));
    try {
        clickText('Back to Artists'); await tick();
        assert.ok(document.querySelector('[role="alertdialog"]'));
        assert.equal(destinations.length, 0);
        clickText('Stay'); await tick();
        assert.equal(document.querySelector('[role="alertdialog"]'), null);
        clickText('Back to Artists'); await tick();
        clickText('Continue'); await tick();
        assert.equal(writes.length, 0);
        assert.equal(new URL(destinations.pop()).pathname, '/artists/advancing');
        clickText('Back to Artists'); await tick();
        clickText('Save & continue');
        assert.equal(writes.length, 1);
        assert.equal(destinations.length, 0);
        // A validation error never calls the successful-save callback.
        form.errors = { name: 'Required' }; await tick();
        assert.ok(document.querySelector('[role="alertdialog"]'));
        clickText('Save & continue');
        await writes.at(-1).onSuccess({ props: { flash: {} } }); await tick();
        assert.equal(destinations.length, 1);
        assert.equal(form.isDirty, false);
        assert.equal(document.querySelector('[role="alertdialog"]'), null);
    } finally { app.unmount(); }
});

test('a partial-save warning keeps the dialog open and in-flight writes disable Back', async () => {
    destinations.length = 0;
    const form = reactive({ isDirty: true, processing: false, defaults() {} });
    let options;
    const app = mountBack({ form }, (next) => { options = returnFormOptions(form, next); });
    try {
        clickText('Back to Artists'); await tick(); clickText('Save & continue');
        await options.onSuccess({ props: { flash: { warning: 'A note could not be saved' } } }); await tick();
        assert.equal(destinations.length, 0);
        assert.ok(document.querySelector('[role="alertdialog"]'));
        form.processing = true; await tick();
        assert.equal(document.querySelector('a').getAttribute('aria-disabled'), 'true');
    } finally { app.unmount(); }
});

// Exercise the real DataTable rather than only comparing persistence options.
globalThis.returnTableCore = (await import('datatables.net-dt')).default;
globalThis.returnTableVue = (await import('datatables.net-vue3')).default.default;
globalThis.returnTranslate = translate;
const tableUrl = compile('components/ui/data-table/DataTable', [
    [/import DataTablesCore from ['"].*?['"];?/, 'const DataTablesCore = globalThis.returnTableCore;'],
    [/import DataTablesVue from ['"].*?['"];?/, 'const DataTablesVue = globalThis.returnTableVue;'],
    [/import \{[^}]*\} from ['"]laravel-vue-i18n['"];?/, 'const currentLocale = "en", isLoaded = () => true, getActiveLanguage = () => "en", loadLanguageAsync = async () => {}; const trans = globalThis.returnTranslate;'],
    [/import \{ cn \} from ['"].*?['"];?/, 'const cn = (...values) => values.filter(Boolean).join(" ");'],
]);
const { default: DataTable } = await import(tableUrl);
let filter;
const mountList = () => {
    const app = createApp({ setup() {
        const table = ref(null);
        filter = ref('');
        const options = useLandingState('round-trip', { filter }, () => table.value?.search(filter.value, false));
        watch(filter, (value) => table.value?.search(value));
        return () => h('section', [h('input', { 'data-filter': '', value: filter.value, onInput: (event) => { filter.value = event.target.value; } }), h(DataTable, {
            ref: table,
            columns: [{ title: 'Name', data: 'name' }],
            data: Array.from({ length: 37 }, (_, index) => ({ name: `Crew ${String(index + 1).padStart(2, '0')}` })),
            options: { ...options, pageLength: 10, lengthChange: false, order: [[0, 'desc']], layout: { topStart: null, topEnd: null } },
        })]);
    } });
    app.config.globalProperties.$t = translate;
    app.mount(document.getElementById('app'));
    return app;
};

test('returning restores real table search, sort and page; direct visits and other events start fresh', async () => {
    window.sessionStorage.clear();
    page.url = '/credentials/passes';
    let app = mountList();
    try {
        await tick();
        const input = document.querySelector('[data-filter]');
        input.value = 'Crew'; input.dispatchEvent(new window.Event('input', { bubbles: true })); await tick();
        document.querySelector('button[data-dt-idx="next"]').click(); await tick();
        document.querySelector('button[data-dt-idx="next"]').click(); await tick();
        assert.equal(document.querySelector('tbody td').textContent, 'Crew 17');
    } finally { app.unmount(); }
    page.url = '/credentials/passes?restore=1';
    app = mountList();
    try {
        await tick();
        assert.equal(document.querySelector('[data-filter]').value, 'Crew');
        assert.equal(document.querySelector('tbody td').textContent, 'Crew 17');
        assert.equal(document.querySelectorAll('tbody tr').length, 10);
    } finally { app.unmount(); }
    page.props.activeEvent.id = 12;
    app = mountList();
    try { await tick(); assert.equal(filter.value, ''); assert.equal(document.querySelector('tbody td').textContent, 'Crew 37'); }
    finally { app.unmount(); page.props.activeEvent.id = 11; }
    page.url = '/credentials/passes';
    app = mountList();
    try { await tick(); assert.equal(filter.value, ''); assert.equal(document.querySelector('tbody td').textContent, 'Crew 37'); }
    finally { app.unmount(); }
});

test('numeric dropdown selections restore and table state is loaded only once per return visit', async () => {
    window.sessionStorage.clear();
    page.url = '/vendors/advancing';
    let selectedType;
    let options;
    const mount = () => {
        const app = createApp({ setup() {
            selectedType = ref('');
            options = useLandingState('vendor-selection', { selectedType });
            return () => null;
        } });
        app.mount(document.getElementById('app'));
        return app;
    };
    let app = mount();
    selectedType.value = 7;
    options.stateSaveCallback(null, { start: 25 });
    await tick();
    app.unmount();
    page.url = '/vendors/advancing?restore=1';
    app = mount();
    try {
        assert.equal(selectedType.value, 7);
        assert.deepEqual(options.stateLoadCallback(), { start: 25 });
        assert.equal(options.stateLoadCallback(), null);
    } finally { app.unmount(); }
    page.props.auth.user.id = 8;
    app = mount();
    try { assert.equal(selectedType.value, ''); assert.equal(options.stateLoadCallback(), null); }
    finally { app.unmount(); page.props.auth.user.id = 7; }
});
