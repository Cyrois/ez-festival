import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import { parse, compileScript } from '@vue/compiler-sfc';
import { JSDOM } from 'jsdom';
import { buttonVariants } from '../../resources/js/components/ui/button/buttonVariants.js';
import { cva } from 'class-variance-authority';

const dom = new JSDOM('<div id="app"></div>', { url: 'http://localhost' });
for (const key of [
    'window',
    'document',
    'Element',
    'HTMLElement',
    'SVGElement',
    'Node',
    'Option',
    'DocumentFragment',
    'getComputedStyle',
])
    globalThis[key] = dom.window[key];
Object.defineProperty(globalThis, 'navigator', {
    value: dom.window.navigator,
    configurable: true,
});
dom.window.performance.getEntriesByType = () => [];
const { createApp, h, nextTick, reactive } = await import('vue');
const require = createRequire(import.meta.url);
const locale = JSON.parse(
    readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'),
);
const translate = (key, values = {}) =>
    Object.entries(values).reduce(
        (text, [name, value]) => text.replaceAll(':' + name, value),
        locale[key] ?? key,
    );
let page, requests, toasts;
const deps = (globalThis.__teamHoursTest = {
    router: {
        replace: (options) => {
            page.props = options.props(page.props);
            page.url = options.url;
        },
    },
    cva,
    FontAwesomeIcon: (await import('@fortawesome/vue-fontawesome'))
        .FontAwesomeIcon,
    activateDataTableRow: (
        await import('../../resources/js/lib/dataTableRowNavigation.js')
    ).activateDataTableRow,
    navigateDataTableRow: (
        await import('../../resources/js/lib/dataTableRowNavigation.js')
    ).navigateDataTableRow,
    trans: translate,
    cn: (...values) => values.filter(Boolean).join(' '),
    AppLayout: {
        setup:
            (_, { slots }) =>
            () =>
                h('main', slots.default?.()),
    },
    usePage: () => page,
    useFlashToast: () => ({ showFormError: (errors) => toasts.push(errors) }),
    DataTablesCore: (await import('datatables.net-dt')).default,
    DataTablesVue: (await import('datatables.net-vue3')).default.default,
    currentLocale: 'en',
    getActiveLanguage: () => 'en',
    isLoaded: () => true,
    loadLanguageAsync: async () => {},
    Link: 'a',
    buttonVariants,
});
await import('../../resources/js/icons.js');
const compile = async (path) => {
    const { descriptor } = parse(
        readFileSync(
            new URL('../../resources/js/' + path, import.meta.url),
            'utf8',
        ),
    );
    let source = compileScript(descriptor, { id: path, inlineTemplate: true })
        .content.replaceAll(
            /from ['"]vue['"]/g,
            "from '" + pathToFileURL(require.resolve('vue')).href + "'",
        )
        .replace(
            /import\s+([\s\S]*?)\s+from\s+['"]([^'"]+)['"];?/g,
            (statement, names, from) => {
                if (from.startsWith('file:')) return statement;
                return names.startsWith('{')
                    ? 'const ' + names + ' = globalThis.__teamHoursTest;'
                    : 'const ' +
                          names +
                          ' = globalThis.__teamHoursTest.' +
                          names +
                          ';';
            },
        );
    return (
        await import(
            'data:text/javascript;base64,' +
                Buffer.from(source).toString('base64')
        )
    ).default;
};
for (const [name, path] of [
    ['CardTitle', 'components/ui/card/CardTitle.vue'],
    ['Card', 'components/ui/card/Card.vue'],
    ['Input', 'components/ui/input/Input.vue'],
    ['Icon', 'components/ui/icon/Icon.vue'],
    ['Badge', 'components/ui/badge/Badge.vue'],
    ['CustomDropdown', 'components/ui/custom-dropdown/CustomDropdown.vue'],
    ['FormField', 'components/ui/form-field/FormField.vue'],
    ['Button', 'components/ui/button/Button.vue'],
    ['DataTable', 'components/ui/data-table/DataTable.vue'],
    ['EmptyState', 'components/ui/empty-state/EmptyState.vue'],
])
    deps[name] = await compile(path);
const Report = await compile('pages/Team/Reports.vue');
const tick = async () => {
    await new Promise((resolve) => setTimeout(resolve, 10));
    await nextTick();
};
const mount = ({
    filters = {},
    groupOptions = [],
    url = '/team/reports',
    canViewTeam = false,
} = {}) => {
    requests = [];
    toasts = [];
    page = reactive({
        url,
        props: {
            filters,
            groupOptions,
            activeEvent: { id: 11, name: 'Sunrise Folk Fest' },
            permissions: {
                'team.hours_report.view': true,
                'team.view': canViewTeam,
            },
        },
    });
    globalThis.fetch = (url, options) =>
        new Promise((resolve, reject) => {
            requests.push({ url, options, resolve, reject });
        });
    const app = createApp(Report, { filters, groupOptions });
    app.config.globalProperties.$t = translate;
    app.mount(document.getElementById('app'));
    return app;
};
const group = (name = 'Gate', minutes = 90) => ({
    group_id: 1,
    name,
    scheduled_minutes: minutes,
    approved_minutes: null,
    unapproved_minutes: null,
});
const respond = async (index, groups = [group()], eventId = 11) => {
    requests[index].resolve({
        ok: true,
        status: 200,
        json: async () => ({
            data: {
                event_id: eventId,
                range: {
                    start_date: new URL(
                        requests[index].url,
                        'http://localhost',
                    ).searchParams.get('start_date'),
                    end_date: new URL(
                        requests[index].url,
                        'http://localhost',
                    ).searchParams.get('end_date'),
                },
                totals: {
                    scheduled_minutes: groups.reduce(
                        (sum, row) => sum + row.scheduled_minutes,
                        0,
                    ),
                    approved_minutes: null,
                    unapproved_minutes: null,
                },
                groups,
            },
        }),
    });
    await tick();
};
const setDate = async (id, value) => {
    const input = document.getElementById(id);
    input.value = value;
    input.dispatchEvent(new dom.window.Event('input', { bubbles: true }));
    await tick();
};
const scheduledTotal = () => document.querySelector('dd').textContent.trim();
const person = (name = 'Ava Chen', minutes = 90, type = 'volunteer') => ({
    team_engagement_id: 1,
    person_id: 1,
    name,
    employment_type: type,
    member_url: null,
    scheduled_minutes: minutes,
    approved_minutes: null,
    unapproved_minutes: null,
});
const respondPeople = async (index, people = [person()], overrides = {}) => {
    const url = new URL(requests[index].url, 'http://localhost');
    requests[index].resolve({
        ok: true,
        status: 200,
        json: async () => ({
            data: {
                event_id: 11,
                range: {
                    start_date: url.searchParams.get('start_date'),
                    end_date: url.searchParams.get('end_date'),
                },
                group: {
                    group_id: Number(url.pathname.split('/')[4]),
                    name: 'Gate',
                },
                totals: {
                    scheduled_minutes: people.reduce(
                        (sum, row) => sum + row.scheduled_minutes,
                        0,
                    ),
                    approved_minutes: null,
                    unapproved_minutes: null,
                },
                people,
                ...overrides,
            },
        }),
    });
    await tick();
};
const buttonWithText = (text) =>
    [...document.querySelectorAll('button')].find(
        (button) => button.textContent.trim() === text,
    );
const pickGroup = async (name) => {
    document.getElementById('hours-group').click();
    await tick();
    [...document.querySelectorAll('[role="option"]')]
        .find((option) => option.textContent.trim() === name)
        .click();
    await tick();
};

test('first load shows loading, then every group with three hours columns and no later controls', async () => {
    const app = mount();
    try {
        assert.equal(requests.length, 1);
        assert.equal(
            new URL(requests[0].url, 'http://localhost').searchParams.size,
            0,
        );
        assert.match(
            document.querySelector('[role="status"]').textContent,
            /Loading group hours/,
        );
        assert.equal(
            document.querySelector('[aria-busy="true"]')?.tagName,
            'SECTION',
        );
        const groups = Array.from({ length: 12 }, (_, i) => ({
            ...group(
                i === 0 ? '<img src=x onerror=alert(1)>' : 'Group ' + i,
                60,
            ),
            group_id: i + 1,
        }));
        await respond(0, groups);
        assert.equal(document.querySelector('[role="status"]'), null);
        assert.equal(scheduledTotal(), '12 h 00');
        assert.equal(document.querySelectorAll('tbody tr').length, 12);
        assert.deepEqual(
            [...document.querySelectorAll('thead th')].map(
                (cell) => cell.textContent,
            ),
            ['Team group', 'Scheduled', 'Approved', 'Unapproved', ''],
        );
        assert.deepEqual(
            [...document.querySelector('tbody tr').cells].map((cell) =>
                cell.textContent.trim(),
            ),
            ['<img src=x onerror=alert(1)>', '1 h 00', '—', '—', ''],
        );
        assert.equal(
            document.querySelector('img, select, .dt-paging, .dt-search'),
            null,
        );
        assert.equal(document.querySelectorAll('input').length, 2);
        assert.equal(document.querySelectorAll('tbody button').length, 12);
        assert.equal(
            document.querySelector('tbody button').getAttribute('aria-label'),
            'View hours for <img src=x onerror=alert(1)>',
        );
        assert.doesNotMatch(
            document.body.textContent,
            /No group|By shift|Export CSV|Back to All groups|Paid|Volunteer/,
        );
        assert.match(document.body.textContent, /Sunrise Folk Fest/);
    } finally {
        app.unmount();
    }
});

test('a failed response shows Retry, repeated clicks send one request, and zero groups show empty totals', async () => {
    const app = mount();
    try {
        requests[0].resolve({ ok: false, status: 500 });
        await tick();
        assert.match(
            document.querySelector('[role="alert"]').textContent,
            /Couldn’t load group hours/,
        );
        assert.equal(document.querySelector('table'), null);
        const retry = document.querySelector('button');
        retry.click();
        retry.click();
        assert.equal(requests.length, 2);
        await tick();
        assert.match(
            document.querySelector('[role="status"]').textContent,
            /Loading group hours/,
        );
        await respond(1, []);
        assert.equal(document.querySelector('[role="alert"], table'), null);
        assert.match(document.body.textContent, /No Team groups yet/);
        assert.equal(scheduledTotal(), '0 h 00');
    } finally {
        app.unmount();
    }
});

test('paired free dates validate before fetching, retain zero rows, and cache successful ranges for the page visit', async () => {
    const app = mount();
    try {
        await respond(0);
        for (const input of document.querySelectorAll('input')) {
            assert.equal(input.hasAttribute('min'), false);
            assert.equal(input.hasAttribute('max'), false);
            assert.equal(input.value, '');
        }
        await setDate('hours-start-date', '2027-10-05');
        assert.equal(requests.length, 1);
        assert.match(
            document.body.textContent,
            /Choose both a start date and an end date/,
        );
        await setDate('hours-end-date', '2027-10-01');
        assert.equal(requests.length, 1);
        assert.match(document.body.textContent, /end date must be on or after/);
        await setDate('hours-end-date', '2027-10-06');
        assert.equal(requests.length, 2);
        const params = new URL(requests[1].url, 'http://localhost')
            .searchParams;
        assert.equal(params.get('start_date'), '2027-10-05');
        assert.equal(params.get('end_date'), '2027-10-06');
        await respond(1, [group('Unused', 0)]);
        assert.equal(scheduledTotal(), '0 h 00');
        assert.equal(document.querySelectorAll('tbody tr').length, 1);
        assert.match(document.querySelector('tbody').textContent, /Unused/);
        assert.doesNotMatch(document.body.textContent, /No Team groups yet/);
        await setDate('hours-start-date', '');
        await setDate('hours-end-date', '');
        assert.equal(requests.length, 2);
        assert.equal(scheduledTotal(), '1 h 30');
        await setDate('hours-start-date', '2027-10-05');
        await setDate('hours-end-date', '2027-10-06');
        assert.equal(requests.length, 2);
        assert.equal(scheduledTotal(), '0 h 00');
    } finally {
        app.unmount();
    }
});

test('late results cannot replace the selected range or event, and unmount aborts requests', async () => {
    const app = mount();
    try {
        await setDate('hours-start-date', '2026-09-01');
        assert.equal(requests[0].options.signal.aborted, true);
        await setDate('hours-end-date', '2026-09-02');
        await respond(1, [group('Selected range', 120)]);
        await respond(0, [group('Stale range', 900)]);
        assert.equal(scheduledTotal(), '2 h 00');
        assert.doesNotMatch(document.body.textContent, /Stale range/);
        page.props.activeEvent = { id: 22, name: 'Other festival' };
        await tick();
        assert.equal(requests.length, 3);
        await respond(2, [group('Wrong event', 900)], 11);
        assert.match(
            document.querySelector('[role="alert"]').textContent,
            /Couldn’t load/,
        );
        assert.doesNotMatch(
            document.body.textContent,
            /Wrong event|Selected range/,
        );
        document.querySelector('button').click();
        await respond(3, [group('Other event', 180)], 22);
        assert.equal(scheduledTotal(), '3 h 00');
        await setDate('hours-end-date', '2026-09-03');
        assert.equal(requests.length, 5);
    } finally {
        app.unmount();
    }
    assert.equal(requests[4].options.signal.aborted, true);
    await respond(4, [group('Unmounted', 500)], 22);
    assert.equal(document.getElementById('app').textContent, '');
});

test('server validation is shown on the field and through the shared error toast', async () => {
    const app = mount();
    try {
        requests[0].resolve({
            ok: false,
            status: 422,
            json: async () => ({
                errors: { end_date: ['Choose a valid end date.'] },
            }),
        });
        await tick();
        assert.equal(
            document
                .getElementById('hours-end-date')
                .getAttribute('aria-invalid'),
            'true',
        );
        assert.match(document.body.textContent, /Choose a valid end date/);
        assert.deepEqual(toasts, [{ end_date: 'Choose a valid end date.' }]);
        assert.equal(document.querySelector('button, table'), null);
        await setDate('hours-start-date', '2026-10-01');
        await setDate('hours-end-date', '2026-10-02');
        assert.equal(requests.length, 2);
        await respond(1);
        assert.equal(scheduledTotal(), '1 h 30');
    } finally {
        app.unmount();
    }
});

test('group rows open the complete people list directly with badges, group totals and no drill-down actions', async () => {
    const app = mount();
    try {
        await respond(0, [group(), { ...group('Stage', 300), group_id: 2 }]);
        assert.equal(requests.length, 1);
        const row = document.querySelector('tbody tr');
        const selection = window.getSelection();
        selection.selectAllChildren(row.cells[0]);
        row.click();
        await tick();
        assert.equal(
            requests.length,
            1,
            'selected text must not activate a row',
        );
        selection.removeAllRanges();
        row.click();
        row.click();
        await tick();
        assert.equal(requests.length, 2);
        assert.equal(
            new URL(requests[1].url, 'http://localhost').pathname,
            '/team/reports/groups/1/people',
        );
        assert.match(
            document.querySelector('[role="status"]').textContent,
            /Loading people’s hours/,
        );
        assert.ok(buttonWithText('Back to All groups'));
        const people = Array.from({ length: 32 }, (_, i) => ({
            ...person(
                i === 0 ? '<img src=x onerror=alert(1)>' : `Person ${i}`,
                i === 0 ? 90 : 0,
                i === 0 ? 'paid' : 'volunteer',
            ),
            team_engagement_id: i + 1,
            person_id: i + 1,
        }));
        await respondPeople(1, people);
        assert.equal(scheduledTotal(), '1 h 30');
        assert.deepEqual(
            [...document.querySelectorAll('thead th')].map(
                (cell) => cell.textContent,
            ),
            ['Person', 'Scheduled', 'Approved', 'Unapproved'],
        );
        assert.equal(document.querySelectorAll('tbody tr').length, 32);
        assert.match(
            document.querySelector('tbody tr').textContent,
            /<img src=x onerror=alert\(1\)>\s*Paid1 h 30/,
        );
        assert.match(
            document.querySelectorAll('tbody tr')[1].textContent,
            /Volunteer0 h 00/,
        );
        assert.equal(
            document.querySelector(
                'tbody button, tbody a, img, .dt-paging, .dt-search',
            ),
            null,
        );
        assert.doesNotMatch(
            document.body.textContent,
            /By shift|Export CSV|No group|hourly_pay/,
        );
        assert.equal(document.querySelector('select'), null);
        assert.equal(
            document
                .getElementById('hours-group')
                .getAttribute('aria-haspopup'),
            'listbox',
        );
    } finally {
        app.unmount();
    }
});

test('switching groups and Back reuse successful results without clearing other groups or changing the range', async () => {
    const app = mount();
    try {
        await respond(0, [group(), { ...group('Stage'), group_id: 2 }]);
        await setDate('hours-start-date', '2026-09-01');
        await setDate('hours-end-date', '2026-09-02');
        await respond(1, [group(), { ...group('Stage'), group_id: 2 }]);
        document.querySelector('tbody button').click();
        await tick();
        await respondPeople(2, [person('Gate person', 90)]);
        await pickGroup('Stage');
        assert.equal(requests.length, 4);
        await respondPeople(3, [person('Stage person', 120)]);
        await pickGroup('Gate');
        assert.equal(requests.length, 4);
        assert.match(
            document.querySelector('tbody').textContent,
            /Gate person/,
        );
        buttonWithText('Back to All groups').click();
        await tick();
        assert.equal(requests.length, 4);
        assert.equal(
            document.getElementById('hours-start-date').value,
            '2026-09-01',
        );
        assert.equal(
            document.getElementById('hours-end-date').value,
            '2026-09-02',
        );
        document
            .querySelectorAll('tbody button')[1]
            .dispatchEvent(
                new dom.window.MouseEvent('click', { bubbles: true }),
            );
        await tick();
        assert.equal(requests.length, 4);
        assert.match(
            document.querySelector('tbody').textContent,
            /Stage person/,
        );
    } finally {
        app.unmount();
    }
});

test('returning to an in-flight group shares its request and late results never replace the active group', async () => {
    const app = mount();
    try {
        await respond(0, [group(), { ...group('Stage'), group_id: 2 }]);
        document.querySelector('tbody button').click();
        await tick();
        await pickGroup('Stage');
        await pickGroup('Gate');
        assert.equal(requests.length, 3);
        assert.equal(requests[1].options.signal.aborted, false);
        await respondPeople(2, [person('Stage person', 300)]);
        assert.match(
            document.querySelector('[role="status"]').textContent,
            /Loading people/,
        );
        assert.doesNotMatch(document.body.textContent, /Stage person/);
        await respondPeople(1, [person('Gate person', 90)]);
        assert.equal(scheduledTotal(), '1 h 30');
        await pickGroup('Stage');
        assert.equal(requests.length, 3);
        assert.equal(scheduledTotal(), '5 h 00');
    } finally {
        app.unmount();
    }
});

test('failed groups retry once while pending and successful empty groups are remembered', async () => {
    const app = mount();
    try {
        await respond(0, [group(), { ...group('Empty'), group_id: 2 }]);
        document.querySelector('tbody button').click();
        await tick();
        requests[1].resolve({ ok: false, status: 500 });
        await tick();
        assert.match(
            document.querySelector('[role="alert"]').textContent,
            /Couldn’t load Gate’s people/,
        );
        assert.equal(
            scheduledTotal(),
            '1 h 30',
            'known same-range group totals remain visible after a people fetch fails',
        );
        const retry = buttonWithText('Retry');
        retry.click();
        retry.click();
        assert.equal(requests.length, 3);
        await respondPeople(2);
        await pickGroup('Empty');
        await respondPeople(3, []);
        assert.match(document.body.textContent, /No people in this group/);
        assert.equal(scheduledTotal(), '0 h 00');
        assert.equal(document.querySelector('table'), null);
        await pickGroup('Gate');
        await pickGroup('Empty');
        assert.equal(requests.length, 4);
        assert.match(document.body.textContent, /No people in this group/);
    } finally {
        app.unmount();
    }
});

test('selected-group ranges fetch only that group, retain old ranges and discard stale event and range responses', async () => {
    const app = mount();
    try {
        await respond(0);
        document.querySelector('tbody button').click();
        await tick();
        await respondPeople(1);
        await setDate('hours-start-date', '2027-01-01');
        assert.equal(requests.length, 2);
        await setDate('hours-end-date', '2027-01-02');
        assert.equal(requests.length, 3);
        assert.match(requests[2].url, /^\/team\/reports\/groups\/1\/people\?/);
        await setDate('hours-end-date', '2027-01-03');
        assert.equal(requests[2].options.signal.aborted, true);
        await respondPeople(3, [person('Selected range', 120)]);
        await respondPeople(2, [person('Old range', 900)]);
        assert.match(
            document.querySelector('tbody').textContent,
            /Selected range/,
        );
        await setDate('hours-start-date', '');
        await setDate('hours-end-date', '');
        assert.equal(requests.length, 4);
        assert.equal(scheduledTotal(), '1 h 30');
        await setDate('hours-start-date', '2027-01-01');
        await setDate('hours-end-date', '2027-01-03');
        assert.equal(requests.length, 4);
        assert.equal(scheduledTotal(), '2 h 00');
        await setDate('hours-end-date', '2027-01-04');
        page.props.activeEvent = { id: 22, name: 'Other festival' };
        await tick();
        assert.equal(requests[4].options.signal.aborted, true);
        assert.equal(requests.length, 6);
        assert.equal(document.getElementById('hours-group'), null);
        await respondPeople(4, [person('Old event', 999)]);
        await respond(5, [group('Other event', 60)], 22);
        assert.doesNotMatch(
            document.body.textContent,
            /Old event|Selected range/,
        );
        assert.equal(scheduledTotal(), '1 h 00');
        document.querySelector('tbody button').click();
        await tick();
        assert.equal(requests.length, 7);
    } finally {
        app.unmount();
    }
    assert.equal(requests[6].options.signal.aborted, true);
    await respondPeople(6);
    assert.equal(document.getElementById('app').textContent, '');
});

test('wrong-group responses fail without caching and selected-group validation uses field errors and toast', async () => {
    const app = mount();
    try {
        await respond(0);
        document.querySelector('tbody button').click();
        await tick();
        await respondPeople(1, [person('Wrong group')], {
            group: { group_id: 2, name: 'Stage' },
        });
        assert.match(
            document.querySelector('[role="alert"]').textContent,
            /Couldn’t load/,
        );
        assert.doesNotMatch(document.body.textContent, /Wrong group/);
        buttonWithText('Retry').click();
        requests[2].resolve({
            ok: false,
            status: 422,
            json: async () => ({
                errors: { end_date: ['Choose a valid end date.'] },
            }),
        });
        await tick();
        assert.equal(
            document
                .getElementById('hours-end-date')
                .getAttribute('aria-invalid'),
            'true',
        );
        assert.deepEqual(toasts, [{ end_date: 'Choose a valid end date.' }]);
        await setDate('hours-start-date', '2026-09-01');
        await setDate('hours-end-date', '2026-09-02');
        await respondPeople(3);
        assert.equal(scheduledTotal(), '1 h 30');
    } finally {
        app.unmount();
    }
});

test('member names and chevrons use same-tab member links only with Team view, with the summary mobile layout', async () => {
    const app = mount({ canViewTeam: true });
    try {
        await respond(0);
        document.querySelector('tbody button').click();
        await tick();
        await respondPeople(1, [
            { ...person(), member_url: '/team/members/87' },
        ]);
        const links = [...document.querySelectorAll('tbody a')];
        assert.equal(links.length, 2);
        assert.equal(links[0].textContent, 'Ava Chen');
        for (const link of links) {
            assert.equal(link.getAttribute('href'), '/team/members/87');
            assert.equal(link.getAttribute('target'), null);
        }
        assert.equal(
            links[1].getAttribute('aria-label'),
            'View Team member Ava Chen',
        );
        assert.equal(
            document.querySelector('table').classList.contains('dt-summary'),
            true,
        );
        assert.equal(
            document.querySelector('tbody tr').hasAttribute('data-row-link'),
            true,
        );
        assert.match(
            document.body.textContent,
            /A person’s name opens their Team member page/,
        );
        assert.equal(
            requests.length,
            2,
            'rendering a member link must not fetch shifts',
        );
        assert.equal(page.url, '/team/reports?group_id=1');
    } finally {
        app.unmount();
    }
});

test('the report address and Inertia history props restore the same group and dates after a member visit', async () => {
    let app = mount({ canViewTeam: true });
    let saved;
    try {
        await respond(0);
        await setDate('hours-start-date', '2027-01-01');
        await setDate('hours-end-date', '2027-01-03');
        await respond(1);
        document.querySelector('tbody button').click();
        await tick();
        await respondPeople(2, [
            { ...person(), member_url: '/team/members/87' },
        ]);
        assert.equal(
            page.url,
            '/team/reports?group_id=1&start_date=2027-01-01&end_date=2027-01-03',
        );
        saved = {
            url: page.url,
            filters: { ...page.props.filters },
            groupOptions: [...page.props.groupOptions],
            canViewTeam: true,
        };
    } finally {
        app.unmount();
    }
    app = mount(saved);
    try {
        assert.equal(
            document.getElementById('hours-start-date').value,
            '2027-01-01',
        );
        assert.equal(
            document.getElementById('hours-end-date').value,
            '2027-01-03',
        );
        assert.match(
            document.getElementById('hours-group').textContent,
            /Gate/,
        );
        assert.equal(requests.length, 1);
        assert.equal(
            requests[0].url,
            '/team/reports/groups/1/people?start_date=2027-01-01&end_date=2027-01-03',
        );
        await respondPeople(0, [
            { ...person(), member_url: '/team/members/87' },
        ]);
        assert.equal(
            document.querySelector('tbody a').getAttribute('href'),
            '/team/members/87',
        );
    } finally {
        app.unmount();
    }
});

test('deferred history updates retain the filters belonging to their own address', async () => {
    const replace = deps.router.replace;
    const historyUpdates = [];
    deps.router.replace = (options) => {
        historyUpdates.push(options);
        replace(options);
    };
    const app = mount();
    try {
        await respond(0, [group(), { ...group('Stage'), group_id: 2 }]);
        document.querySelector('tbody button').click();
        await tick();
        const gateHistory = historyUpdates.at(-1);
        await pickGroup('Stage');
        assert.equal(page.url, '/team/reports?group_id=2');
        assert.equal(gateHistory.url, '/team/reports?group_id=1');
        assert.deepEqual(gateHistory.props({}).filters, {
            group_id: 1,
            start_date: null,
            end_date: null,
        });
    } finally {
        app.unmount();
        deps.router.replace = replace;
    }
});
