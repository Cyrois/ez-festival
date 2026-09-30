import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) =>
    readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

const lang = JSON.parse(read('lang/en.json'));
const listPage = read('resources/js/pages/Settings/Team.vue');
const addPage = read('resources/js/pages/Settings/Team/Create.vue');
const personPage = read('resources/js/pages/Settings/Team/Show.vue');
const invitationPage = read(
    'resources/js/pages/Auth/SetInvitedPassword.vue',
);
const http = read('resources/js/lib/http.js');
const appBlade = read('resources/views/app.blade.php');
const memberPage = read('resources/js/pages/Team/Member.vue');
const settingsLayout = read('resources/js/layouts/SettingsLayout.vue');
const eventEditShell = read(
    'resources/js/components/settings/EventEditShell.vue',
);

test('Global Team add uses the locked defaults and enables login by default', () => {
    assert.match(addPage, /status: 'applied'/);
    assert.match(addPage, /v-model="form\.phone"/);
    assert.match(addPage, /settings\.team\.fields\.phone/);
    assert.match(addPage, /event\.locked \|\| form\.processing/);
    assert.match(addPage, /settings\.team\.access\.locked/);
    assert.match(addPage, /can_log_in: true/);
    assert.match(addPage, /v-model="form\.can_log_in"/);
    assert.match(addPage, /settings\.team\.login\.add_hint/);
    assert.equal(
        lang['settings.team.login.add_hint'],
        'Turning this on sends them an invite email.',
    );
});

test('Global Team add warns and links instead of merging an existing person', () => {
    assert.match(addPage, /lookup\.value\?\.exists/);
    assert.match(addPage, /lookup\?\.exists/);
    assert.match(addPage, /settings\.team\.add\.existing_contact/);
    assert.match(addPage, /settings\.team\.add\.open_existing/);
    assert.match(addPage, /`\/settings\/team\/\$\{lookup\.person\.id\}`/);
    assert.equal(
        lang['settings.team.validation.email_exists'],
        'This email already belongs to an existing person. Open their person page to make changes.',
    );
});

test('Global Team person page saves login access with the main form', () => {
    assert.match(personPage, /can_log_in: props\.person\.can_log_in/);
    assert.match(personPage, /v-model="form\.can_log_in"/);
    assert.match(personPage, /person\.login_disable_reason/);
    assert.doesNotMatch(memberPage, /can_log_in|settings\.team\.login/);
});

test('Global Team login tools follow the saved switch state', () => {
    assert.match(
        personPage,
        /props\.person\.can_log_in && form\.can_log_in/,
    );
    assert.match(personPage, /inviteWillBeSent/);
    assert.match(personPage, /person\.has_set_password/);
    assert.match(personPage, /temporary-password/);
    assert.match(personPage, /navigator\.clipboard\.writeText/);
    assert.match(personPage, /inviteCancelledLocally\.value = true/);
});

test('temporary password requests use the refreshed XSRF cookie', () => {
    assert.match(personPage, /'X-XSRF-TOKEN': xsrfToken\(\)/);
    assert.match(http, /document\.cookie/);
    assert.match(http, /XSRF-TOKEN=/);
    assert.doesNotMatch(personPage, /meta\[name="csrf-token"\]/);
    assert.doesNotMatch(appBlade, /name="csrf-token"/);
});

test('signed-in invitation visitors get a sign-out state', () => {
    assert.match(invitationPage, /v-if="authenticated"/);
    assert.match(invitationPage, /auth\.invitation\.signed_in_title/);
    assert.match(invitationPage, /signOutForm\.post\('\/logout'\)/);
});

test('temporary passwords are never included in the normal person page props', () => {
    assert.doesNotMatch(personPage, /props\.person\.temporary_password/);
});

test('Global Team list renders the Login column from server data', () => {
    assert.match(listPage, /#loginCell/);
    assert.match(listPage, /settings\.team\.login\.enabled/);
    assert.match(listPage, /settings\.team\.login\.disabled/);
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

test('Global Team save panels align to their page containers', () => {
    const teamActionLayout =
        /container mx-auto px-4 md:px-6[\s\S]*mx-auto flex max-w-5xl items-center justify-between/;

    assert.match(addPage, /container mx-auto max-w-5xl pb-24/);
    assert.match(personPage, /container mx-auto max-w-5xl pb-24/);
    assert.match(addPage, teamActionLayout);
    assert.match(personPage, teamActionLayout);
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
