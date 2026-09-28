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
    assert.match(
        layout,
        /:compact="sidebarCompact"[\s\S]*@toggle="toggleSidebar"/,
    );
});

test('fixed action bars follow the current desktop sidebar width', () => {
    assert.match(layout, /lg:\[--app-sidebar-width:4rem\]/);
    assert.match(layout, /lg:\[--app-sidebar-width:14rem\]/);

    for (const page of actionBarPages) {
        assert.match(page, /lg:left-\[var\(--app-sidebar-width\)\]/);
        assert.doesNotMatch(page, /lg:left-56/);
    }
});
