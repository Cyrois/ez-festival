import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) =>
    readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

const lang = JSON.parse(read('lang/en.json'));
const listPage = read('resources/js/pages/Settings/Team.vue');
const addPage = read('resources/js/pages/Settings/Team/Create.vue');
const personPage = read('resources/js/pages/Settings/Team/Show.vue');
const memberPage = read('resources/js/pages/Team/Member.vue');
const settingsLayout = read('resources/js/layouts/SettingsLayout.vue');
const eventEditShell = read(
    'resources/js/components/settings/EventEditShell.vue',
);

test('Global Team add uses the locked status defaults and optional phone field', () => {
    assert.match(addPage, /status: 'applied'/);
    assert.match(addPage, /v-model="form\.phone"/);
    assert.match(addPage, /settings\.team\.fields\.phone/);
    assert.match(addPage, /event\.locked \|\| form\.processing/);
    assert.match(addPage, /settings\.team\.access\.locked/);
    assert.doesNotMatch(addPage, /Can log in|back office login/i);
});

test('new event access starts as Hired and only appears for a new role', () => {
    assert.match(personPage, /originalRoles\[event\.id\] === ''/);
    assert.match(personPage, /const wasNoAccess = access\.role_id === ''/);
    assert.match(personPage, /access\.status = 'hired'/);
    assert.match(
        personPage,
        /v-if="[\s\S]*?newlyGranted\([\s\S]*?form\.event_access\[index\][\s\S]*?\)[\s\S]*?"/,
    );
});

test('role pickers use CustomDropdown and preserve visible off roles', () => {
    assert.match(addPage, /<CustomDropdown/);
    assert.match(personPage, /<CustomDropdown/);
    assert.match(memberPage, /<CustomDropdown/);
    assert.match(personPage, /event\.role && !event\.role\.active/);
    assert.match(memberPage, /engagement\.role && !engagement\.role\.active/);
    assert.equal(
        lang['settings.team.access.off_role_hint'],
        "This role is off. It's kept for history and grants nothing.",
    );
});

test('Global Team uses the shared server-side DataTable with person links', () => {
    assert.match(listPage, /import \{ DataTable \}/);
    assert.match(listPage, /<DataTable/);
    assert.match(listPage, /ajax="\/settings\/team\/data"/);
    assert.match(listPage, /serverSide: true/);
    assert.match(listPage, /table\.value\?\.search\(value\)/);
    assert.match(listPage, /`\/settings\/team\/\$\{rowData\.id\}`/);
    assert.doesNotMatch(listPage, /people\.links\.(prev|next)/);
});

test('Global Team save panels match the Vendors action layout', () => {
    const vendorActionLayout =
        /container mx-auto px-4 md:px-6[\s\S]*mx-auto flex max-w-6xl items-center justify-between/;

    assert.match(addPage, vendorActionLayout);
    assert.match(personPage, vendorActionLayout);
});

test('the obsolete event Users surfaces are absent', () => {
    assert.doesNotMatch(settingsLayout, /key: 'users'|\/settings\/users/);
    assert.doesNotMatch(eventEditShell, /value="users"|\/users/);
    assert.equal(lang['settings.events.tabs.users'], undefined);
    assert.equal(lang['settings.nav.items.users'], undefined);
    assert.doesNotMatch(lang['settings.events.primary_missing'], /Users/);
});

test('every settings.team translation key used by the pages exists', () => {
    const sources = [listPage, addPage, personPage, memberPage].join('\n');
    const keys = new Set(
        [...sources.matchAll(/['`](settings\.team\.[a-z_.]+)['`]/g)].map(
            (match) => match[1],
        ),
    );

    assert.ok(keys.size > 20);
    for (const key of keys) {
        assert.ok(key in lang, `${key} is missing from lang/en.json`);
    }
});
