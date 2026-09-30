import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { emphasisParts } from '../../resources/js/lib/emphasisParts.js';
import { roleMatchHint } from '../../resources/js/pages/Settings/roleMatchHint.js';
import {
    DEFAULT_ROLE_STATUS,
    rolesQuery,
} from '../../resources/js/pages/Settings/rolesFilters.js';

const read = (path) =>
    readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

const lang = JSON.parse(read('lang/en.json'));
const settingsLayout = read('resources/js/layouts/SettingsLayout.vue');
const eventEditShell = read(
    'resources/js/components/settings/EventEditShell.vue',
);
const rolesPage = read('resources/js/pages/Settings/Roles.vue');
const roleColumns = read('resources/js/pages/Settings/roleColumns.js');

// Minimal stand-in for laravel-vue-i18n's trans(): swaps :placeholders.
const translate = (key, replacements = {}) =>
    Object.entries(replacements).reduce(
        (text, [name, value]) => text.replaceAll(`:${name}`, value),
        lang[key] ?? key,
    );

test('the roles filter starts on On and keeps defaults out of the URL', () => {
    assert.equal(DEFAULT_ROLE_STATUS, 'on');
    assert.deepEqual(rolesQuery({ search: '', status: 'on' }), {});
    assert.deepEqual(rolesQuery({ search: '  stage ', status: 'all' }), {
        search: 'stage',
        status: 'all',
    });
    assert.deepEqual(rolesQuery({ status: 'off' }), { status: 'off' });
    assert.deepEqual(rolesQuery({ status: 'bogus' }), {});
});

test('the turn off popup text bolds the role name and people count', () => {
    const parts = emphasisParts(translate, 'settings.roles.turn_off.body', {
        name: 'Box office lead',
        people: '2 people',
    });

    assert.deepEqual(
        parts.filter((part) => part.emphasis).map((part) => part.text),
        ['Box office lead', '2 people'],
    );
    assert.equal(
        parts.map((part) => part.text).join(''),
        'Box office lead will leave every role picker and grant nothing, at every event. The 2 people who have it keep it on record, shown as off. You can turn it back on anytime.',
    );
});

test('emphasised values are inserted as text, never as markup', () => {
    const parts = emphasisParts(translate, 'settings.roles.form.match_hint', {
        typed: '"<b>staff</b>"',
        existing: 'Staff',
    });

    assert.deepEqual(parts, [
        { text: '"<b>staff</b>"', emphasis: true },
        { text: ' matches ', emphasis: false },
        { text: 'Staff', emphasis: true },
        { text: '.', emphasis: false },
    ]);
});

test('Roles sits in Global Settings and not in Event Settings', () => {
    const organizationItems = settingsLayout.slice(
        settingsLayout.indexOf('const organizationItems'),
        settingsLayout.indexOf('const personalItems'),
    );
    const eventNavItems = settingsLayout.slice(
        settingsLayout.indexOf('const eventNavItems'),
        settingsLayout.indexOf('const isEventNavActive'),
    );

    assert.match(
        organizationItems,
        /key: 'team'[\s\S]*?key: 'roles', href: '\/settings\/roles'/,
    );
    assert.doesNotMatch(eventNavItems, /roles/);
    assert.doesNotMatch(settingsLayout, /events\\\/\\d\+\\\/roles/);
});

test('the event settings page has no Roles tab', () => {
    assert.doesNotMatch(eventEditShell, /roles/i);
    assert.equal(lang['settings.events.tabs.roles'], undefined);
    assert.doesNotMatch(lang['settings.events.primary_missing'], /Roles/);
});

test('every settings.roles key used by the page exists in en.json', () => {
    const keys = new Set(
        [...rolesPage.matchAll(/'(settings\.roles\.[a-z_.]+)'/g)].map(
            (match) => match[1],
        ),
    );

    assert.ok(keys.size > 10);
    for (const key of keys) {
        assert.ok(key in lang, `${key} is missing from lang/en.json`);
    }
});

test('the roles page has no delete action', () => {
    assert.doesNotMatch(rolesPage, /\.delete\(|'trash'|destroy/);
});

test('the match hint shows only the last submitted name', () => {
    assert.deepEqual(
        roleMatchHint({
            match: 'Staff',
            submitted: ' staff ',
            current: ' staff ',
        }),
        { typed: '" staff "', existing: 'Staff' },
    );
});

test('the match hint goes away once the name is edited', () => {
    assert.equal(
        roleMatchHint({
            match: 'Staff',
            submitted: ' staff ',
            current: 'stage crew',
        }),
        null,
    );
    assert.equal(
        roleMatchHint({ match: 'Staff', submitted: null, current: 'staff' }),
        null,
    );
    assert.equal(
        roleMatchHint({ match: '', submitted: 'staff', current: 'staff' }),
        null,
    );
});

test('the page snapshots the submitted name for the match hint', () => {
    assert.match(rolesPage, /submittedName\.value = form\.name;/);
    assert.match(rolesPage, /submitted: submittedName\.value/);
    assert.doesNotMatch(rolesPage, /typed: `"\$\{form\.name\}"`/);
});

test('phones get role cards and md+ uses the server-side DataTable', () => {
    const cardsStart = rolesPage.indexOf(
        '<div class="flex flex-col gap-3 md:hidden">',
    );
    const tableWrapper = rolesPage.indexOf('<div class="hidden md:block">');
    const table = rolesPage.indexOf('<DataTable');

    assert.notEqual(cardsStart, -1);
    assert.ok(tableWrapper > cardsStart);
    assert.ok(table > tableWrapper);
    assert.match(rolesPage, /serverSide: true/);
    assert.match(rolesPage, /:ajax="dataTableUrl"/);
    assert.match(rolesPage, /\/settings\/roles\/data/);
    assert.match(roleColumns, /render: \{ display: '#roleCell' \}/);

    const cards = rolesPage.slice(cardsStart, tableWrapper);
    assert.match(cards, /v-for="role in roles\.data"/);
    assert.match(cards, /openRename\(role\)/);
    assert.match(cards, /turningOff = role/);
    assert.match(cards, /setActive\(role, true\)/);
    assert.match(cards, /min-h-11/);
});
