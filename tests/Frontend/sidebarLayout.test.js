import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const layout = readFileSync(
    new URL('../../resources/js/layouts/AppLayout.vue', import.meta.url),
    'utf8',
);
const accountControls = readFileSync(
    new URL(
        '../../resources/js/components/navigation/UserAccountControls.vue',
        import.meta.url,
    ),
    'utf8',
);
const sidebarNavItem = readFileSync(
    new URL(
        '../../resources/js/components/navigation/SidebarNavItem.vue',
        import.meta.url,
    ),
    'utf8',
);
const actionBarPages = [
    '../../resources/js/pages/Artists/View.vue',
    '../../resources/js/pages/Credentials/EditEntitlement.vue',
    '../../resources/js/pages/Team/FormEditor.vue',
    '../../resources/js/pages/Team/Member.vue',
    '../../resources/js/pages/Vendors/View.vue',
].map((path) => readFileSync(new URL(path, import.meta.url), 'utf8'));

test('desktop account controls remain in the app header', () => {
    const headerStart = layout.indexOf('<header');
    const headerEnd = layout.indexOf('</header>', headerStart);
    const header = layout.slice(headerStart, headerEnd);

    assert.notEqual(headerStart, -1);
    assert.notEqual(headerEnd, -1);
    assert.match(header, /<UserAccountControls\s+:user="user"\s+\/>/);
    assert.match(accountControls, /'ml-auto hidden lg:flex'/);
});

test('mobile account controls remain at the bottom of each sidebar', () => {
    const mobilePlacements = layout.match(
        /<UserAccountControls\s+:user="user"\s+placement="sidebar"\s+\/>/g,
    );

    assert.equal(mobilePlacements?.length, 2);
    assert.match(accountControls, /'flex[^']*lg:hidden'/);
    assert.match(accountControls, /min-h-11 min-w-11/);
});

test('settings remains in the main navigation above the collapse control', () => {
    const mainSidebarStart = layout.indexOf('<aside');
    const mainSidebarEnd = layout.indexOf('</aside>', mainSidebarStart);
    const mainSidebar = layout.slice(mainSidebarStart, mainSidebarEnd);
    const navEnd = mainSidebar.indexOf('</nav>');
    const settingsLink = mainSidebar.indexOf('href="/settings/events"');
    const collapseControl = mainSidebar.indexOf('<SidebarCollapseButton');

    assert.notEqual(mainSidebarStart, -1);
    assert.notEqual(mainSidebarEnd, -1);
    assert.ok(settingsLink > -1 && settingsLink < navEnd);
    assert.ok(collapseControl > navEnd);
});

test('sign out remains a neutral outlined icon button', () => {
    assert.match(accountControls, /variant: 'outline'/);
    assert.match(accountControls, /size: 'icon'/);
    assert.match(accountControls, /text-muted/);
    assert.doesNotMatch(accountControls, /text-primary|text-brand/);
});

test('a collapsed desktop sidebar expands while hovered', () => {
    assert.match(layout, /@mouseenter="sidebarHovered = true"/);
    assert.match(layout, /@mouseleave="sidebarHovered = false"/);
    assert.match(layout, /lg:fixed lg:top-0/);
    assert.match(
        layout,
        /:compact="sidebarCompact"[\s\S]*@toggle="toggleSidebar"/,
    );
    assert.match(
        layout,
        /sidebarCollapsed && !settingsNav \? 'lg:ml-16' : 'lg:ml-56'/,
    );
    assert.doesNotMatch(
        layout,
        /sidebarCompact && !settingsNav[\s\S]*--app-sidebar-width/,
    );
});

test('sidebar navigation stays vertically anchored during hover expansion', () => {
    assert.match(
        layout,
        /flex shrink-0 items-center justify-between[^"\n]*lg:h-16 lg:py-0/,
    );
    assert.match(
        layout,
        /ml-5 space-y-1 border-l border-line pl-3 transition-/,
    );
    assert.doesNotMatch(layout, /:key="`collapsed-\$\{child\.key\}`"/);
    assert.match(sidebarNavItem, /lg:h-7 lg:min-h-7/);
    assert.match(sidebarNavItem, /lg:h-8 lg:min-h-8/);
    assert.match(sidebarNavItem, /truncate/);
    assert.match(sidebarNavItem, /whitespace-nowrap/);
});

test('settings navigation uses the reduced main sidebar row height on desktop', () => {
    assert.match(
        sidebarNavItem,
        /if \(props\.density === 'settings'\) \{\s+return '[^']*lg:h-8 lg:min-h-8 lg:py-1\.5';\s+\}/,
    );
});

test('sidebar labels animate without replacing navigation rows', () => {
    assert.match(sidebarNavItem, /transition-\[max-width,opacity\]/);
    assert.match(sidebarNavItem, /lg:max-w-0 lg:opacity-0/);
    assert.match(layout, /overflow-x-hidden/);
    assert.match(layout, /duration-200 ease-in-out/);
});

test('fixed action bars follow the current desktop sidebar width', () => {
    assert.match(layout, /lg:\[--app-sidebar-width:4rem\]/);
    assert.match(layout, /lg:\[--app-sidebar-width:14rem\]/);

    for (const page of actionBarPages) {
        assert.match(page, /lg:left-\[var\(--app-sidebar-width\)\]/);
        assert.doesNotMatch(page, /lg:left-56/);
    }
});
