import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import { compileScript, parse } from '@vue/compiler-sfc';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { JSDOM } from 'jsdom';

const require = createRequire(import.meta.url);
const vueUrl = pathToFileURL(require.resolve('vue')).href;
const locale = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'));
const encode = (source) => `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
const compile = (path) => {
    const { descriptor } = parse(readFileSync(new URL(path, import.meta.url), 'utf8'));
    return compileScript(descriptor, { id: path, inlineTemplate: true }).content
        .replaceAll(/from ['"]vue['"]/g, `from '${vueUrl}'`);
};
const sidebarItem = encode(compile('../../resources/js/components/navigation/SidebarNavItem.vue')
    .replace(/import \{ Link \} from ['"].*?['"];?/, 'const Link = "a";')
    .replace(/import \{ Icon \} from ['"].*?['"];?/, 'const Icon = { render: () => null };')
    .replace(/from ['"]\.\.\/\.\.\/lib\/utils['"]/, `from '${new URL('../../resources/js/lib/utils.js', import.meta.url).href}'`));
const compileTab = (name) => encode(compile(`../../resources/js/components/ui/tabs/${name}.vue`)
    .replace(/import \{ Link \} from ['"].*?['"];?/, 'const Link = "a";')
    .replace(/from ['"][^'"]*lib\/utils['"]/, `from '${new URL('../../resources/js/lib/utils.js', import.meta.url).href}'`)
    .replace(/from ['"]\.\/tabsContext['"]/, `from '${new URL('../../resources/js/components/ui/tabs/tabsContext.js', import.meta.url).href}'`));
const appNavigationUrl = new URL('../../resources/js/lib/appNavigation.js', import.meta.url).href;
const sectionNavigation = encode(compile('../../resources/js/components/navigation/SectionNavigation.vue')
    .replace(/import \{ Tab, TabList, Tabs \} from ['"].*?['"];?/, `import Tab from '${compileTab('Tab')}'; import TabList from '${compileTab('TabList')}'; import Tabs from '${compileTab('Tabs')}';`)
    .replace(/from ['"]\.\.\/\.\.\/lib\/appNavigation['"]/, `from '${appNavigationUrl}'`));
let source = compile('../../resources/js/layouts/AppLayout.vue');
for (const name of ['Toast', 'Icon', 'SidebarCollapseButton', 'UserAccountControls']) {
    source = source.replace(new RegExp(`import (?:\\{ ${name} \\}|${name}) from ['"].*?['"];?`), `const ${name} = { render: () => null };`);
}
source = source
    .replace(/from ['"]\.\.\/lib\/appNavigation['"]/, `from '${appNavigationUrl}'`)
    .replace(/import SectionNavigation from ['"].*?['"];?/, `import SectionNavigation from '${sectionNavigation}';`)
    .replace(/import SidebarNavItem from ['"].*?['"];?/, `import SidebarNavItem from '${sidebarItem}';`)
    .replace(/import \{ useInertiaErrorToast \} from ['"].*?['"];?/, 'const useInertiaErrorToast = () => {};')
    .replace(/import \{[^}]*\} from ['"]\.\.\/composables\/useSidebarCollapsed['"];?/, 'const useSidebarCollapsed = () => ref(globalThis.kitchenNavigationCollapsed); const shouldUseCompactSidebar = (collapsed, hovered) => collapsed && !hovered;')
    .replace(/import \{ Head, usePage \} from ['"].*?['"];?/, 'const Head = { render: () => null }; const usePage = () => globalThis.kitchenNavigationPage;');
const { default: AppLayout } = await import(encode(source));

const navigation = async (permissions, collapsed = false, isAdmin = false, url = permissions['meals.report.view'] ? '/reports' : '/meals') => {
    globalThis.kitchenNavigationCollapsed = collapsed;
    globalThis.kitchenNavigationPage = {
        url,
        props: { permissions, auth: { user: { name: 'Reader', is_admin: isAdmin } } },
    };
    const app = createSSRApp({ render: () => h(AppLayout, { title: 'Report' }) });
    app.config.globalProperties.$t = (key) => locale[key] ?? key;
    const dom = new JSDOM(await renderToString(app));
    try {
        const links = (selector) => [...dom.window.document.querySelectorAll(selector)].map((link) => ({
            label: link.textContent.trim(),
            href: link.getAttribute('href'),
            current: link.getAttribute('aria-current'),
        }));
        return { sidebar: links('aside nav a'), tabs: links('header nav a') };
    } finally {
        dom.window.close();
    }
};

for (const collapsed of [false, true]) {
    test(`Kitchen navigation respects independent report access (collapsed: ${collapsed})`, async () => {
        for (const [viewMeals, viewReport] of [[false, false], [true, false], [false, true], [true, true]]) {
            const { sidebar, tabs } = await navigation({ 'meals.view': viewMeals, 'meals.report.view': viewReport }, collapsed);
            const kitchen = sidebar.find((link) => link.label === locale['nav.meals']);
            assert.equal(Boolean(kitchen), viewMeals || viewReport);
            if (kitchen) {
                assert.equal(kitchen.href, viewMeals ? '/meals' : '/reports');
                assert.equal(kitchen.current, 'page');
            }
            for (const key of ['nav.meals.index', 'nav.meals.report', 'nav.meals.settings']) {
                assert.equal(sidebar.some((link) => link.label === locale[key]), false);
            }
            assert.equal(tabs.some((link) => link.label === locale['nav.meals.index']), viewMeals);
            assert.equal(tabs.some((link) => link.href === '/meals/settings'), viewMeals);
            const report = tabs.find((link) => link.label === locale['nav.meals.report']);
            assert.equal(Boolean(report), viewReport);
            if (report) {
                assert.equal(report.href, '/reports');
                assert.equal(report.current, 'page');
            }
        }
    });
}

test('admins see every Kitchen tab through their shared permission map', async () => {
    const { sidebar, tabs } = await navigation({ 'meals.view': true, 'meals.report.view': true }, false, true);
    assert.ok(sidebar.some((link) => link.label === locale['nav.meals']));
    for (const key of ['nav.meals.index', 'nav.meals.report', 'nav.meals.settings']) {
        assert.ok(tabs.some((link) => link.label === locale[key]));
    }
});

for (const collapsed of [false, true]) {
    test(`Team navigation respects independent hours report access (collapsed: ${collapsed})`, async () => {
        for (const [viewTeam, viewReport] of [[false, false], [true, false], [false, true], [true, true]]) {
            const permissions = { 'team.view': viewTeam, 'team.hours_report.view': viewReport };
            const url = viewReport ? '/team/reports' : viewTeam ? '/team/advancement' : '/dashboard';
            const { sidebar, tabs } = await navigation(permissions, collapsed, false, url);
            const team = sidebar.find((link) => link.label === locale['nav.team']);
            assert.equal(Boolean(team), viewTeam || viewReport);
            if (team) {
                assert.equal(team.href, viewTeam ? '/team/advancement' : '/team/reports');
                assert.equal(team.current, 'page');
            }
            assert.equal(sidebar.some((link) => link.label === 'Reports'), false);
            assert.equal(tabs.some((link) => link.href === '/team/reports'), viewReport);
            assert.equal(tabs.some((link) => link.href === '/team/advancement'), viewTeam);
            assert.equal(tabs.some((link) => link.href === '/team/configure'), viewTeam);
            if (viewReport) {
                assert.deepEqual(tabs.filter((link) => link.current === 'page').map((link) => link.href), ['/team/reports']);
            }
            if (viewReport && !viewTeam) {
                assert.deepEqual(tabs.map((link) => link.label), ['Reports']);
            }
        }
    });
}

test('admins see Reports in its agreed position through their shared permission map', async () => {
    const permissions = Object.fromEntries(['team.view', 'scheduling.view', 'forms.view', 'team.hours_report.view'].map((key) => [key, true]));
    const { sidebar, tabs } = await navigation(permissions, false, true, '/team/reports');
    assert.ok(sidebar.some((link) => link.label === locale['nav.team']));
    assert.equal(sidebar.some((link) => link.label === 'Reports'), false);
    assert.deepEqual(tabs.map((link) => link.label), ['Advancement', 'Scheduling', 'Forms', 'Reports', 'Configure']);
    assert.deepEqual(tabs.filter((link) => link.current === 'page').map((link) => link.href), ['/team/reports']);
});

for (const collapsed of [false, true]) {
    test(`Check-in header tabs respect Inventory access (collapsed: ${collapsed})`, async () => {
        for (const [permissions, url, labels, active] of [
            [{}, '/dashboard', [], null],
            [{ 'checkin.view': true }, '/check-in', ['Check-in'], '/check-in'],
            [{ 'checkin.view': true, 'checkin.edit': true }, '/check-in/team/12?person=53', ['Check-in', 'My stock'], '/check-in'],
            [{ 'checkin.view': true, 'checkin.edit': true }, '/check-in/my-stock', ['Check-in', 'My stock'], '/check-in/my-stock'],
            [{ 'checkin.view': true, 'checkin.edit': true, 'inventory.manage': true }, '/check-in/inventory', ['Check-in', 'My stock', 'Inventory'], '/check-in/inventory'],
        ]) {
            const { sidebar, tabs } = await navigation(permissions, collapsed, false, url);
            const checkIn = sidebar.find((link) => link.label === locale['nav.check_in']);
            assert.equal(Boolean(checkIn), Boolean(permissions['checkin.view']));
            assert.equal(sidebar.some((link) => ['My stock', 'Inventory'].includes(link.label)), false);
            assert.deepEqual(tabs.map((link) => link.label), labels);
            assert.deepEqual(tabs.filter((link) => link.current === 'page').map((link) => link.href), active ? [active] : []);
            if (checkIn) {
                assert.equal(checkIn.href, '/check-in');
                assert.equal(checkIn.current, 'page');
            }
        }
    });
}
