import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { JSDOM } from 'jsdom';
import { parse, compileScript } from '@vue/compiler-sfc';

const dom = new JSDOM('<div id="app"></div>', { url: 'http://localhost' });
for (const key of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node', 'Option', 'DocumentFragment', 'getComputedStyle']) globalThis[key] = dom.window[key];
const { createApp, h, nextTick, reactive } = await import('vue');
Object.defineProperty(globalThis, 'navigator', { value: dom.window.navigator, configurable: true });
window.performance.getEntriesByType = () => [];
globalThis.teamCheckInCore = (await import('datatables.net-dt')).default;
globalThis.teamCheckInTable = (await import('datatables.net-vue3')).default.default;
const require = createRequire(import.meta.url);
const locale = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'));
globalThis.teamCheckInTranslate = (key) => locale[key] ?? key;
globalThis.teamCheckInBox = (tag = 'div') => ({ setup: (_, { slots }) => () => h(tag, slots.default?.()) });
globalThis.teamCheckInEmpty = { props: ["title", "description"], setup: (props) => () => h("div", props.title) };
globalThis.teamCheckInConsumeDialog = {
    props: ['open', 'endpoint'],
    setup: (props) => () => h('div', { 'data-consume-open': String(props.open), 'data-endpoint': props.endpoint }),
};
globalThis.teamCheckInShifts = {
    props: ['checkInShifts'],
    setup: (props) => () => h('section', { 'data-check-in-shifts': true }, props.checkInShifts.map(shift => shift.location)),
};
const compile = (path, replacements = []) => {
    const { descriptor } = parse(readFileSync(new URL(path, import.meta.url), 'utf8'));
    let source = compileScript(descriptor, { id: path, inlineTemplate: true }).content
        .replaceAll(/from ['"]vue['"]/g, "from '" + pathToFileURL(require.resolve('vue')).href + "'");
    for (const [pattern, replacement] of replacements) source = source.replace(pattern, replacement);
    return 'data:text/javascript;base64,' + Buffer.from(source).toString('base64');
};
const button = compile('../../resources/js/components/ui/button/Button.vue', [
    [/import \{ Link \} from ['"].*?['"];?/, "const Link = 'a';"],
    [/import \{ cn \} from ['"].*?['"];?/, 'const cn = (...values) => values.filter(Boolean).join(" ");'],
    [/import \{ buttonVariants \} from ['"].*?['"];?/, 'const buttonVariants = () => "";'],
]);
const tooltip = compile('../../resources/js/components/ui/tooltip/Tooltip.vue');
const kit = ['Avatar', 'Badge', 'Icon', 'Tag'].map((name) => [
    new RegExp("import \\{ " + name + " \\} from ['\"].*?['\"];?"),
    'const ' + name + ' = globalThis.teamCheckInBox("span");',
]);
const dataTable = compile('../../resources/js/components/ui/data-table/DataTable.vue', [
    [/import DataTablesCore from ['"].*?['"];?/, 'const DataTablesCore = globalThis.teamCheckInCore;'],
    [/import DataTablesVue from ['"].*?['"];?/, 'const DataTablesVue = globalThis.teamCheckInTable;'],
    [/import \{[^}]*\} from ['"]laravel-vue-i18n['"];?/, 'const currentLocale = "en", getActiveLanguage = () => "en", isLoaded = () => true, loadLanguageAsync = async () => {}; const trans = globalThis.teamCheckInTranslate;'],
    [/import \{ cn \} from ['"].*?['"];?/, 'const cn = (...values) => values.filter(Boolean).join(" ");'],
]);
const iconButton = compile('../../resources/js/components/ui/icon-button/IconButton.vue', [
    ...kit,
    [/import \{ Button \} from ['"].*?['"];?/, "import Button from '" + button + "';"],
    [/import \{ cn \} from ['"].*?['"];?/, 'const cn = (...values) => values.filter(Boolean).join(" ");'],
]);
const rows = compile('../../resources/js/components/check-in/EntitlementTable.vue', [
    ...kit,
    [/import \{ DataTable \} from ['"].*?['"];?/, "import DataTable from '" + dataTable + "';"],
    [/import \{ getActiveLanguage, trans \} from ['"].*?['"];?/, 'const getActiveLanguage = () => "en", trans = globalThis.teamCheckInTranslate;'],
    [/import \{ useFlashToast \} from ['"].*?['"];?/, 'const useFlashToast = () => ({showError: () => {}});'],
    [/import \{ IconButton \} from ['"].*?['"];?/, "import IconButton from '" + iconButton + "';"],
    [/import \{ Tooltip \} from ['"].*?['"];?/, "import Tooltip from '" + tooltip + "';"],
]);
const page = compile('../../resources/js/pages/CheckIn/Team.vue', [
    ...kit,
    [/import \{ Button \} from ['"].*?['"];?/, "import Button from '" + button + "';"],
    [/import \{ Card \} from ['"].*?['"];?/, 'const Card = globalThis.teamCheckInBox();'],
    [/import TeamMemberShiftsCard from ['"].*?['"];?/, 'const TeamMemberShiftsCard = globalThis.teamCheckInShifts;'],
    [/import AppLayout from ['"].*?['"];?/, 'const AppLayout = globalThis.teamCheckInBox();'],
    [/import HiddenPersonalInfo from ['"].*?['"];?/, 'const HiddenPersonalInfo = { render: () => "Hidden for your role" };'],
    [/import \{ EmptyState \} from ['"].*?['"];?/, 'const EmptyState = globalThis.teamCheckInEmpty;'],
    [/import \{ trans \} from ['"].*?['"];?/, 'const trans = globalThis.teamCheckInTranslate;'],
    [/import EntitlementTable from ['"].*?['"];?/, "import EntitlementTable from '" + rows + "';"],
    [/import ConsumeEntitlementDialog from ['"].*?['"];?/, 'const ConsumeEntitlementDialog = globalThis.teamCheckInConsumeDialog;'],
]);
const { default: Show } = await import(page);
const person = (hasPass = true, entitlements = []) => ({
    id: 17, name: 'Maya', has_pass: hasPass, personal_info_hidden: true,
    passes: hasPass ? ['Crew pass'] : [], pass_labels: [], expected: entitlements.length,
    issued: 0, entitlements,
});
const pending = { id: 43, name: 'Wristband', status: 'pending', locations: [{ id: 2, name: 'Gate', in_stock: 2 }] };
const mount = async ({ holder = person(), canWrite = true, locked = false, memberUrl = null, canViewShifts = false, checkInShifts = [], groupName = null, roleName = null } = {}) => {
    globalThis.teamCheckInRequests = [];
    globalThis.fetch = async (url) => {
        globalThis.teamCheckInRequests.push(url);
        const draw = Number(new URL(url, 'http://localhost').searchParams.get('draw'));
        return {ok: true, json: async () => ({draw, recordsTotal: holder.entitlements.length, recordsFiltered: holder.entitlements.length, data: holder.entitlements})};
    };
    const app = createApp(Show, {
        engagement: { id: 8, type: 'team', name: 'Maya', people: [holder], group_name: groupName, role_name: roleName },
        event: { locked, timezone: 'America/Vancouver' }, canWrite, memberUrl, canViewShifts, checkInShifts,
    });
    app.config.globalProperties.$t = globalThis.teamCheckInTranslate;
    app.mount('#app');
    await new Promise(resolve => setTimeout(resolve, 20));
    await nextTick();
    return app;
};

test('Team no-pass banner appears only when no pass is held, including zero-line passes', async () => {
    for (const hasPass of [false, true]) {
        const app = await mount({ holder: person(hasPass) });
        try {
            const text = document.querySelector('#app').textContent;
            assert.doesNotMatch(text, /Team member|Hired|Select a person/);
            assert.equal(text.includes(locale['check_in.no_pass_banner']), !hasPass);
            assert.ok(text.includes(locale['artists.check_in.no_entitlements']));
            assert.ok(text.includes('Hidden for your role'));
            assert.ok(!text.includes(locale['artists.check_in.primary_contact']));
            assert.equal(document.querySelectorAll('button').length, 0);
        } finally { app.unmount(); }
    }
});

test('writable Team entitlement uses the shared issuance endpoint', async () => {
    const app = await mount({ holder: person(true, [pending]) });
    try {
        const consume = document.querySelector('button[aria-label="Consume"]');
        assert.equal(consume.disabled, false);
        consume.click();
        await nextTick();
        assert.equal(document.querySelector('[data-consume-open]').dataset.consumeOpen, 'true');
        assert.equal(document.querySelector('[data-endpoint]').dataset.endpoint, '/check-in/expected-entitlements/43/issues');
    } finally { app.unmount(); }
});

for (const locked of [false, true]) {
    test('read-only Team consumption has an accessible hover reason; locked: ' + locked, async () => {
        const app = await mount({ holder: person(true, [pending]), canWrite: false, locked });
        try {
            const consume = document.querySelector('button[aria-label="Consume"]');
            assert.equal(consume.disabled, true);
            const trigger = consume.parentElement;
            trigger.dispatchEvent(new window.FocusEvent('focus'));
            await nextTick();
            const reason = locked ? locale['artists.check_in.locked'] : locale['check_in.needs_edit_permission'];
            assert.equal(trigger.getAttribute('aria-label'), reason);
            assert.equal(document.querySelector('[role="tooltip"]').textContent.trim(), reason);
            consume.click();
            await nextTick();
            assert.equal(document.querySelector('[data-consume-open]').dataset.consumeOpen, 'false');
        } finally { app.unmount(); }
    });
}


test('Team layout puts gated member link in the header and email/phone in the left column', async () => {
    for (const allowed of [true, false]) {
        const holder = {...person(), personal_info_hidden: false, email: 'maya@example.test', phone: '555-1234'};
        const app = await mount({holder, memberUrl: allowed ? '/team/members/8' : null});
        try {
            const link = document.querySelector('header a');
            assert.equal(link?.getAttribute('href') ?? null, allowed ? '/team/members/8' : null);
            assert.match(document.querySelector('aside').textContent, /maya@example.test/);
            assert.match(document.querySelector('aside').textContent, /555-1234/);
            assert.doesNotMatch(document.querySelector('section').textContent, /maya@example.test|555-1234/);
            assert.doesNotMatch(document.querySelector('#app').textContent, /Team member|Hired/);
        } finally { app.unmount(); }
    }
});

test('next shifts section is gated and appears after entitlement details', async () => {
    for (const allowed of [true, false]) {
        const app = await mount({canViewShifts: allowed, checkInShifts: [{location:'Next gate'}]});
        try {
            const shifts = document.querySelector('[data-check-in-shifts]');
            assert.equal(Boolean(shifts), allowed);
            if (allowed) {
                assert.match(shifts.textContent, /Next gate/);
                assert.ok(document.querySelector('section').compareDocumentPosition(shifts) & window.Node.DOCUMENT_POSITION_FOLLOWING);
            }
        } finally { app.unmount(); }
    }
});


test('event group and role remain visible when personal details are hidden, with empty fallbacks', async () => {
    for (const assigned of [true, false]) {
        const app = await mount({groupName: assigned ? 'Gate group' : null, roleName: assigned ? 'Gate role' : null});
        try {
            const card = document.querySelector('aside');
            assert.match(card.textContent, /Hidden for your role/);
            assert.ok(card.textContent.includes(assigned ? 'Gate group' : locale['team.member.fields.group_none']));
            assert.ok(card.textContent.includes(assigned ? 'Gate role' : locale['team.member.role.no_role']));
            assert.doesNotMatch(card.textContent, /Email|Phone/);
        } finally {app.unmount();}
    }
});


test('entitlement table has the six requested columns and issued audit values; actions differ by status', async () => {
    const issued = {...pending, id:44, status:'issued', issued:{code:'WRIST-44', issued_by:'Gate operator <img>', issued_at:'2026-10-09T18:30:00Z', location:'Gate'}};
    const app = await mount({holder:person(true,[pending,issued])});
    try {
        assert.deepEqual([...document.querySelectorAll('th')].map(el=>el.textContent.trim()), ['Entitled To','Status','Code','Checked in By','Checked in at','Actions']);
        const rows = document.querySelectorAll('tbody tr');
        assert.match(rows[0].textContent, /Pending/);
        assert.equal(rows[0].querySelector('[aria-label="Details"], [aria-label="Remove"]'), null);
        assert.match(rows[1].textContent, /Issued|WRIST-44|Gate operator <img>/);
        assert.match(rows[1].textContent, /11:30/);
        assert.equal(rows[1].querySelector('img'), null);
        assert.equal(document.querySelector('button[aria-label="Details"]'), null);
        assert.ok(rows[1].querySelector('button[aria-label="Remove"]').disabled);
        for (const button of document.querySelectorAll('tbody button')) {
            assert.equal(button.title, button.getAttribute('aria-label'));
            assert.equal(button.textContent, '');
        }
        const params = new URL(globalThis.teamCheckInRequests[0], 'http://localhost').searchParams;
        assert.equal(params.get('length'),'-1');
        assert.equal(document.querySelector('.dt-search'), null);
        assert.equal(document.querySelector('.dt-paging'), null);
        assert.equal(params.get('person_id'),'17');
        assert.equal(params.get('engagement_id'),'8');
    } finally {app.unmount();}
});

test('successful consumption refreshes the entitlement table without resetting its page', async () => {
    const holder = reactive(person(true,[pending]));
    const app = await mount({holder});
    try {
        holder.entitlements = [{...pending, status:'issued', issued:{code:'NEW',issued_by:'Gate operator',issued_at:'2026-10-09T18:30:00Z'}}];
        holder.issued=1;
        await nextTick();
        await new Promise(resolve=>setTimeout(resolve,20));
        await nextTick();
        assert.equal(globalThis.teamCheckInRequests.length,2);
        assert.match(document.querySelector('tbody').textContent,/NEW/);
        assert.equal(document.querySelector('tbody [aria-label="Consume"]'), null);
    } finally {app.unmount();}
});
