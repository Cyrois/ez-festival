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

const navigation = async (permissions, collapsed = false, isAdmin = false) => {
    globalThis.kitchenNavigationCollapsed = collapsed;
    globalThis.kitchenNavigationPage = {
        url: permissions['meals.report.view'] ? '/reports' : '/meals',
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
