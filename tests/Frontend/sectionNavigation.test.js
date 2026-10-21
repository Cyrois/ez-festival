import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import { parse, compileScript } from '@vue/compiler-sfc';
import { JSDOM } from 'jsdom';
import { getAppNavigation, isNavigationItemActive } from '../../resources/js/lib/appNavigation.js';

const allPermissions = Object.fromEntries(['team.view', 'scheduling.view', 'forms.view', 'team.hours_report.view', 'meals.view', 'meals.report.view', 'credentials.passes.edit', 'credentials.entitlements.edit', 'artists.view', 'vendors.view', 'patrons.view', 'checkin.view', 'checkin.edit', 'inventory.manage'].map((key) => [key, true]));
const navigation = (options = {}) => getAppNavigation({ permissions: allPermissions, isAdmin: true, ...options });

test('Team opens its first permitted page and omits inaccessible tabs', () => {
    for (const [permission, href, keys] of [
        ['team.view', '/team/advancement', ['team.advancement', 'team.configure']],
        ['scheduling.view', '/team/scheduling', ['team.scheduling']],
        ['forms.view', '/team/forms', ['team.forms']],
        ['team.hours_report.view', '/team/reports', ['team.reports']],
    ]) {
        const team = navigation({ permissions: { [permission]: true } }).find((item) => item.key === 'team');
        assert.equal(team.href, href);
        assert.deepEqual(team.tabs.map((tab) => tab.key), keys);
    }
    assert.equal(navigation({ permissions: {} }).some((item) => item.key === 'team'), false);
    assert.equal(navigation({ features: { team: false } }).find((item) => item.key === 'team').enabled, false);
});

test('detail and edit routes keep exactly one section tab selected', () => {
    const cases = [
        ['/check-in?status=complete', 'check_in', 'check_in.people'],
        ['/check-in/artists/12?person=53', 'check_in', 'check_in.people'],
        ['/check-in/team/12', 'check_in', 'check_in.people'],
        ['/check-in/my-stock', 'check_in', 'check_in.my_stock'],
        ['/check-in/inventory', 'check_in', 'check_in.inventory'],
        ['/check-in/inventory/holders/53/stock', 'check_in', 'check_in.inventory'],
        ['/team/members/create', 'team', 'team.advancement'],
        ['/team/members/53?tab=shifts', 'team', 'team.advancement'],
        ['/team/shifts/12/copy?return_tab=schedule', 'team', 'team.scheduling'],
        ['/team/shifts/create', 'team', 'team.scheduling'],
        ['/team/forms/12/edit', 'team', 'team.forms'],
        ['/team/reports', 'team', 'team.reports'],
        ['/team/configure', 'team', 'team.configure'],
        ['/credentials/passes/12/edit', 'credentials', 'credentials.passes'],
        ['/credentials/entitlements/12/edit', 'credentials', 'credentials.entitlements'],
        ['/meals/people/12', 'meals', 'meals.index'],
        ['/meals/settings/meals/12/edit', 'meals', 'meals.settings'],
        ['/reports?event=12', 'meals', 'meals.report'],
    ];
    for (const [url, sectionKey, tabKey] of cases) {
        const sections = navigation().filter((item) => item.tabs && isNavigationItemActive(item, url));
        assert.deepEqual(sections.map((item) => item.key), [sectionKey], url);
        assert.deepEqual(sections[0].tabs.filter((tab) => isNavigationItemActive(tab, url)).map((tab) => tab.key), [tabKey], url);
    }
    assert.equal(navigation().some((item) => isNavigationItemActive(item, '/team/scheduling-other')), false);
    assert.equal(navigation().some((item) => item.tabs && isNavigationItemActive(item, '/settings/team')), false);
});

const dom = new JSDOM('<div id="app"></div>', { url: 'http://localhost' });
for (const key of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node']) globalThis[key] = dom.window[key];
Object.defineProperty(globalThis, 'navigator', { value: dom.window.navigator, configurable: true });
const { createApp, h, nextTick, ref } = await import('vue');
const require = createRequire(import.meta.url);
const destinations = [];
globalThis.navigationTestLink = { setup: (_, { attrs, slots }) => () => h('a', { ...attrs, onClick: (event) => { event.preventDefault(); destinations.push(attrs.href); } }, slots.default?.()) };
globalThis.navigationTestRouter = { visit: (url) => destinations.push(url) };
const locale = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'));
const compile = (path, replacements = []) => {
    const { descriptor } = parse(readFileSync(new URL(path, import.meta.url), 'utf8'));
    let source = compileScript(descriptor, { id: path, inlineTemplate: true }).content
        .replaceAll(/from ['"]vue['"]/g, `from '${pathToFileURL(require.resolve('vue')).href}'`)
        .replace(/import \{ cn \} from ['"].*?['"];?/, 'const cn = (...values) => values.filter(Boolean).join(" ");')
        .replace(/from ['"]\.\/tabsContext['"]/, `from '${new URL('../../resources/js/components/ui/tabs/tabsContext.js', import.meta.url).href}'`)
        .replace(/import \{ Link \} from ['"].*?['"];?/, 'const Link = globalThis.navigationTestLink;');
    for (const [pattern, replacement] of replacements) source = source.replace(pattern, replacement);
    return `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
};
const tabsUrl = compile('../../resources/js/components/ui/tabs/Tabs.vue');
const listUrl = compile('../../resources/js/components/ui/tabs/TabList.vue');
const tabUrl = compile('../../resources/js/components/ui/tabs/Tab.vue');
const sectionUrl = compile('../../resources/js/components/navigation/SectionNavigation.vue', [
    [/import \{ Tab, TabList, Tabs \} from ['"].*?['"];?/, `import Tab from '${tabUrl}'; import TabList from '${listUrl}'; import Tabs from '${tabsUrl}';`],
    [/from ['"]\.\.\/\.\.\/lib\/appNavigation['"]/, `from '${new URL('../../resources/js/lib/appNavigation.js', import.meta.url).href}'`],
]);
const { default: SectionNavigation } = await import(sectionUrl);
const unsavedSource = readFileSync(new URL('../../resources/js/composables/useUnsavedNavigation.js', import.meta.url), 'utf8')
    .replace(/from ['"]vue['"]/, `from '${pathToFileURL(require.resolve('vue')).href}'`)
    .replace(/import \{ router \} from ['"].*?['"];?/, 'const router = globalThis.navigationTestRouter;');
const { useUnsavedNavigation } = await import(`data:text/javascript;base64,${Buffer.from(unsavedSource).toString('base64')}`);

const mountSection = (key, url, options = {}) => {
    const section = navigation(options).find((item) => item.key === key);
    const app = createApp({ setup: () => () => h(SectionNavigation, { section, url }) });
    app.config.globalProperties.$t = (key) => locale[key] ?? key;
    app.mount(document.getElementById('app'));
    return app;
};

test('section tabs are real keyboard-focusable links with one current destination', async () => {
    const app = mountSection('team', '/team/shifts/53');
    try {
        await nextTick();
        const nav = document.querySelector('nav[aria-label="Personnel"]');
        assert.ok(nav.hasAttribute('data-unsaved-navigation'));
        assert.deepEqual([...nav.querySelectorAll('a')].map((link) => link.textContent.trim()), ['Advancement', 'Scheduling', 'Forms', 'Reports', 'Configure']);
        assert.equal(nav.querySelectorAll('[aria-current="page"]').length, 1);
        assert.equal(nav.querySelector('[aria-current="page"]').getAttribute('href'), '/team/scheduling');
        assert.equal(nav.querySelectorAll('[role="tab"], [aria-controls], [tabindex="-1"]').length, 0);
        nav.querySelector('a[href="/team/forms"]').click();
        assert.equal(destinations.at(-1), '/team/forms');
        // The current destination only changes after the next server page arrives.
        assert.equal(nav.querySelector('[aria-current="page"]').getAttribute('href'), '/team/scheduling');
    } finally { app.unmount(); }
});

test('unavailable Products stays disabled beside the Credentials links', () => {
    const app = mountSection('credentials', '/credentials/entitlements/53');
    try {
        const disabled = document.querySelector('nav [aria-disabled="true"]');
        assert.equal(disabled.tagName, 'SPAN');
        assert.equal(disabled.textContent.trim(), 'Products');
        assert.equal(disabled.hasAttribute('href'), false);
        assert.equal(document.querySelectorAll('nav a').length, 2);
    } finally { app.unmount(); }
});

test('section links preserve the unsaved changes warning, Cancel, and Continue', async () => {
    let guard;
    const section = navigation().find((item) => item.key === 'team');
    const app = createApp({ setup() {
        guard = useUnsavedNavigation(ref(true));
        return () => h(SectionNavigation, { section, url: '/team/shifts/53' });
    } });
    app.config.globalProperties.$t = (key) => locale[key] ?? key;
    app.mount(document.getElementById('app'));
    try {
        const before = destinations.length;
        document.querySelector('nav a[href="/team/forms"]').click();
        await nextTick();
        assert.equal(guard.open.value, true);
        assert.equal(destinations.length, before);
        guard.cancel();
        assert.equal(guard.open.value, false);
        assert.equal(destinations.length, before);
        document.querySelector('nav a[href="/team/forms"]').click();
        guard.continueNavigation();
        assert.equal(destinations.at(-1), 'http://localhost/team/forms');
        assert.equal(guard.open.value, false);
    } finally { app.unmount(); }
});

test('in-page tabs keep their tab semantics and keyboard selection', async () => {
    const { default: Tabs } = await import(tabsUrl);
    const { default: TabList } = await import(listUrl);
    const { default: Tab } = await import(tabUrl);
    const selected = ref('schedule');
    const app = createApp({ setup: () => () => h(Tabs, { modelValue: selected.value, 'onUpdate:modelValue': (value) => selected.value = value }, {
        default: () => h(TabList, {}, { default: () => ['schedule', 'list'].map((value) => h(Tab, { value }, () => value)) }),
    }) });
    app.mount(document.getElementById('app'));
    try {
        document.querySelector('[role="tab"]').focus();
        document.querySelector('[role="tab"]').dispatchEvent(new dom.window.KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
        await nextTick();
        assert.equal(selected.value, 'list');
        assert.equal(document.querySelector('[aria-selected="true"]').textContent, 'list');
        assert.ok(document.querySelector('[role="tablist"]'));
        assert.ok(document.querySelector('[aria-controls]'));
    } finally { app.unmount(); }
});
