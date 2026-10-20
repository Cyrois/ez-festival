import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import { parse, compileScript } from '@vue/compiler-sfc';
import { JSDOM } from 'jsdom';
import { buttonVariants } from '../../resources/js/components/ui/button/buttonVariants.js';

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
const mount = () => {
    requests = [];
    toasts = [];
    page = reactive({
        props: {
            activeEvent: { id: 11, name: 'Sunrise Folk Fest' },
            permissions: { 'team.hours_report.view': true },
        },
    });
    globalThis.fetch = (url, options) =>
        new Promise((resolve, reject) => {
            requests.push({ url, options, resolve, reject });
        });
    const app = createApp(Report);
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
                range: { start_date: null, end_date: null },
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
        assert.equal(scheduledTotal(), '12h00');
        assert.equal(document.querySelectorAll('tbody tr').length, 12);
        assert.deepEqual(
            [...document.querySelectorAll('thead th')].map(
                (cell) => cell.textContent,
            ),
            ['Team group', 'Scheduled', 'Approved', 'Unapproved'],
        );
        assert.deepEqual(
            [...document.querySelector('tbody tr').cells].map((cell) =>
                cell.textContent.trim(),
            ),
            ['<img src=x onerror=alert(1)>', '1h00', '—', '—'],
        );
        assert.equal(
            document.querySelector('img, select, .dt-paging, .dt-search'),
            null,
        );
        assert.equal(document.querySelectorAll('input').length, 2);
        assert.equal(document.querySelector('button, a'), null);
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
        assert.equal(scheduledTotal(), '0h00');
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
        assert.equal(scheduledTotal(), '0h00');
        assert.equal(document.querySelectorAll('tbody tr').length, 1);
        assert.match(document.querySelector('tbody').textContent, /Unused/);
        assert.doesNotMatch(document.body.textContent, /No Team groups yet/);
        await setDate('hours-start-date', '');
        await setDate('hours-end-date', '');
        assert.equal(requests.length, 2);
        assert.equal(scheduledTotal(), '1h30');
        await setDate('hours-start-date', '2027-10-05');
        await setDate('hours-end-date', '2027-10-06');
        assert.equal(requests.length, 2);
        assert.equal(scheduledTotal(), '0h00');
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
        assert.equal(scheduledTotal(), '2h00');
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
        assert.equal(scheduledTotal(), '3h00');
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
        assert.equal(scheduledTotal(), '1h30');
    } finally {
        app.unmount();
    }
});
