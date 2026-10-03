import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { JSDOM } from 'jsdom';
import { parse, compileScript } from '@vue/compiler-sfc';
import { labelTokens } from '../../resources/js/lib/labelTokens.js';
import * as timeline from '../../resources/js/lib/scheduleTimeline.js';
import { scheduleRosterRows } from '../../resources/js/lib/shiftAssignments.js';

const dom = new JSDOM('<div id="app"></div>');
for (const name of [
    'window',
    'document',
    'Element',
    'HTMLElement',
    'SVGElement',
    'Node',
])
    globalThis[name] = dom.window[name];
const { createApp, h, nextTick, ref, reactive } = await import('vue');
const require = createRequire(import.meta.url);
const simple = (tag) => ({
    setup:
        (_, { attrs, slots }) =>
        () =>
            h(tag, attrs, slots.default?.()),
});
const deps = {
    ...timeline,
    scheduleRosterRows,
    labelTokens,
    Link: simple('a'),
    Button: simple('button'),
    Badge: simple('span'),
    Icon: {
        props: ['name'],
        setup: (props) => () => h('i', { 'data-icon': props.name[1] }),
    },
    Avatar: {
        props: ['name'],
        setup: (props) => () => h('span', { 'data-avatar': props.name }),
    },
    ShiftAssignDialog: {
        props: ['shift', 'requirement', 'returnContext'],
        setup:
            (props, { emit }) =>
            () =>
                h('button', {
                    id: 'assign-dialog',
                    'data-shift': props.shift.id,
                    'data-slot': props.requirement.id,
                    'data-context': JSON.stringify(props.returnContext),
                    onClick: () => emit('assigned'),
                }),
    },
};
globalThis.__rosterTest = deps;
async function compile(name, folder = 'components/team') {
    const { descriptor } = parse(
        readFileSync(
            new URL(
                `../../resources/js/${folder}/${name}.vue`,
                import.meta.url,
            ),
            'utf8',
        ),
    );
    let source = compileScript(descriptor, {
        id: name,
        inlineTemplate: true,
    }).content.replaceAll(
        /from ['"]vue['"]/g,
        `from '${pathToFileURL(require.resolve('vue')).href}'`,
    );
    source = source.replace(
        /import\s+([\s\S]*?)\s+from\s+['"]([^'"]+)['"];?/g,
        (statement, names, from) => {
            if (from.startsWith('file:')) return statement;
            return `const ${names.startsWith('{') ? names : `{ ${names} }`} = globalThis.__rosterTest;`;
        },
    );
    return (
        await import(
            `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
        )
    ).default;
}
deps.ScheduleTimeline = await compile('ScheduleTimeline');
deps.ScheduleShiftRoster = await compile('ScheduleShiftRoster');
const Feed = await compile('LocationRosterSchedule');
const date = '2026-10-01';
const shift = {
    id: 17,
    name: 'Morning',
    color: 'teal',
    location_id: 9,
    starts_at: `${date}T10:00`,
    ends_at: `${date}T14:00`,
    filled_count: 1,
    total_needs: 4,
    slots: [
        { id: 1, role_id: 2, role_name: 'Volunteer', open_count: 0 },
        { id: 3, role_id: 2, role_name: 'Gate crew', open_count: 3 },
    ],
    assignments: [
        {
            id: 4,
            name: 'First person',
            role_id: 2,
            role_name: 'Volunteer',
            shift_role_slot_id: 1,
            starts_at: `${date}T10:00`,
            ends_at: `${date}T13:00`,
            is_extra: false,
            overlaps: [
                {
                    shift_id: 20,
                    shift_name: 'Show run',
                    starts_at: `${date}T12:30`,
                    ends_at: `${date}T14:00`,
                    overlap_minutes: 30,
                },
            ],
        },
        {
            id: 5,
            name: 'Extra person',
            role_id: 2,
            role_name: 'Volunteer',
            shift_role_slot_id: 1,
            starts_at: `${date}T10:00`,
            ends_at: `${date}T14:00`,
            is_extra: true,
            overlaps: [],
        },
        {
            id: 6,
            name: 'Removed-slot person',
            role_id: 2,
            role_name: 'Volunteer',
            shift_role_slot_id: null,
            starts_at: `${date}T10:00`,
            ends_at: `${date}T14:00`,
            is_extra: true,
            overlaps: [],
        },
    ],
};
const geometry = (start, end) => ({
    '--bar-start': `${start}px`,
    '--bar-width': `${end - start}px`,
    '--bar-end': `${end}px`,
});
const translate = (key, params = {}) =>
    key +
    ' ' +
    Object.entries(params)
        .map(([key, value]) => `${key}=${value}`)
        .join(' ');
function mount(component, props) {
    const app = createApp({
        setup: () => () =>
            h(component, typeof props === 'function' ? props() : props),
    });
    app.config.globalProperties.$t = translate;
    app.mount(document.querySelector('#app'));
    return app;
}
const settle = async () => {
    await new Promise((resolve) => setTimeout(resolve, 0));
    await nextTick();
};
const baseProps = { shift, date, locationId: 9, geometry, slots: [0, 1] };

test('roster orders slots, assigned people, Open rows, then removed-slot Extras', () => {
    assert.deepEqual(
        scheduleRosterRows(shift).map((row) => row.key),
        [
            'person-4',
            'person-5',
            'open-3-0',
            'open-3-1',
            'open-3-2',
            'person-6',
        ],
    );
});

test('mounted roster folds at four rows and expands and collapses with correct Extras, hours and hatching', async () => {
    const app = mount(deps.ScheduleShiftRoster, baseProps);
    try {
        assert.equal(document.querySelectorAll('[data-roster-row]').length, 4);
        const first = document.querySelector('[data-roster-row="person-4"]');
        assert.match(first.textContent, /Volunteer · 10:00–13:00/);
        assert.ok(first.classList.contains('bg-warning/10'));
        assert.equal(
            first
                .querySelector('[data-overlap-hatch]')
                .style.getPropertyValue('--bar-width'),
            '30px',
        );
        assert.match(first.textContent, /name=Show run minutes=30/);
        const buttons = [...document.querySelectorAll('button')];
        const expand = buttons.find((button) =>
            button.hasAttribute('aria-expanded'),
        );
        assert.match(expand.textContent, /count=2/);
        expand.click();
        await nextTick();
        assert.equal(document.querySelectorAll('[data-roster-row]').length, 6);
        assert.match(
            document.querySelector('[data-roster-row="person-6"]').textContent,
            /assignments.extra/,
        );
        assert.match(
            document.querySelector('[data-roster-row="person-5"]').textContent,
            /full_shift/,
        );
        expand.click();
        await nextTick();
        assert.equal(document.querySelectorAll('[data-roster-row]').length, 4);
        assert.equal(
            new URL(
                document.querySelector('a').getAttribute('href'),
                'http://localhost',
            ).searchParams.get('schedule_location_id'),
            '9',
        );
    } finally {
        app.unmount();
    }
});

test('Open rows explain read-only permissions and emit the matching slot only when enabled', async () => {
    const allowed = ref(false);
    const emitted = [];
    const app = mount(deps.ScheduleShiftRoster, () => ({
        ...baseProps,
        canAssign: allowed.value,
        disabledReason: 'Read only',
        onAssign: (slot) => emitted.push(slot.id),
    }));
    try {
        const assign = document.querySelector(
            '[data-roster-row="open-3-0"] button',
        );
        assert.equal(assign.disabled, true);
        assert.equal(assign.title, 'Read only');
        assign.click();
        assert.deepEqual(emitted, []);
        allowed.value = true;
        await nextTick();
        assign.click();
        assert.deepEqual(emitted, [3]);
    } finally {
        app.unmount();
    }
});

test('overnight assignments retain rows without a bar on the other day and clip overlapping bars', () => {
    const overnight = {
        ...shift,
        starts_at: '2026-10-01T23:00',
        ends_at: '2026-10-02T02:00',
        slots: [],
        assignments: [
            {
                ...shift.assignments[2],
                starts_at: '2026-10-01T23:00',
                ends_at: '2026-10-01T23:30',
            },
        ],
    };
    const app = mount(deps.ScheduleShiftRoster, {
        ...baseProps,
        shift: overnight,
        date: '2026-10-02',
    });
    try {
        assert.ok(document.querySelector('[data-roster-row="person-6"]'));
        assert.match(document.body.textContent, /23:00–23:30/);
        assert.equal(
            document.querySelector(
                '[data-roster-row="person-6"] [title*="23:00"]',
            ),
            null,
        );
        assert.deepEqual(timeline.scheduleInterval(overnight, '2026-10-02'), {
            start: 0,
            end: 120,
        });
    } finally {
        app.unmount();
    }
    assert.deepEqual(
        timeline.scheduleOverlapIntervals(
            {
                starts_at: '2026-10-01T23:00',
                ends_at: '2026-10-02T02:00',
                overlaps: [
                    {
                        starts_at: '2026-10-01T23:30',
                        ends_at: '2026-10-02T01:00',
                    },
                ],
            },
            '2026-10-02',
        ),
        [{ start: 0, end: 60 }],
    );
});

test('location controls roster mode through URLs and survives shift return links and clearing', () => {
    const locations = [
        { id: 9, name: 'Stage' },
        { id: 10, name: 'Gate' },
    ];
    const href = timeline.schedulePageHref(date, 'schedule', 9);
    assert.equal(timeline.scheduleLocationFromUrl(href, locations), 9);
    assert.equal(
        timeline.scheduleLocationFromUrl(
            timeline.schedulePageHref(date, 'schedule'),
            locations,
        ),
        '',
    );
    assert.equal(
        timeline.scheduleLocationFromUrl(
            '/team/scheduling?location_id=999',
            locations,
        ),
        '',
    );
    const detail = new URL(
        timeline.scheduleShiftHref(17, date, 'schedule', 9),
        'http://localhost',
    );
    const back = timeline.scheduleReturnHref(
        Object.fromEntries(detail.searchParams),
    );
    assert.equal(timeline.scheduleLocationFromUrl(back, locations), 9);
    assert.equal(
        new URL(back, 'http://localhost').searchParams.get('date'),
        date,
    );
});

test('roster fetch discards stale location loads and refreshes after Assign with the current location', async () => {
    const requests = [];
    globalThis.fetch = (url, options) =>
        new Promise((resolve) => requests.push({ url, options, resolve }));
    const locationId = ref(9);
    const app = mount(Feed, () => ({
        date,
        eventId: 3,
        locationId: locationId.value,
        locationName: 'Stage',
        canAssign: true,
    }));
    const response = (shifts, page = 1, lastPage = 1) => ({
        ok: true,
        json: async () => ({
            data: shifts,
            schedule: { first_shift_minute: 600 },
            meta: { current_page: page, last_page: lastPage },
        }),
    });
    try {
        locationId.value = 10;
        await nextTick();
        assert.equal(requests[0].options.signal.aborted, true);
        assert.match(requests[1].url, /location_id=10/);
        requests[1].resolve(response([shift]));
        await settle();
        requests[0].resolve(
            response([{ ...shift, id: 99, name: 'Stale shift' }]),
        );
        await settle();
        assert.doesNotMatch(document.body.textContent, /Stale shift/);
        document.querySelector('[data-roster-row="open-3-0"] button').click();
        await nextTick();
        const dialog = document.querySelector('#assign-dialog');
        assert.equal(dialog.dataset.shift, '17');
        assert.equal(dialog.dataset.slot, '3');
        assert.deepEqual(JSON.parse(dialog.dataset.context), {
            return_tab: 'schedule',
            schedule_date: date,
            schedule_location_id: 10,
            return_to_schedule: true,
            schedule_view: 'location_shifts',
        });
        dialog.click();
        await nextTick();
        assert.equal(document.querySelector('#assign-dialog'), null);
        assert.match(requests[2].url, /location_id=10/);
        const updated = {
            ...shift,
            slots: shift.slots.map((slot) => ({ ...slot, open_count: 0 })),
        };
        requests[2].resolve(response([updated]));
        await settle();
        assert.equal(
            document.querySelector('[data-roster-row="open-3-0"]'),
            null,
        );
    } finally {
        app.unmount();
    }
});

test('joined view selector keeps the day and location and persists both modes through URLs', async () => {
    const page = reactive({
        url: `/team/scheduling?date=${date}&tab=schedule`,
        props: { locale: 'en-US' },
    });
    Object.assign(deps, {
        cn: (...classes) => classes.filter(Boolean).join(' '),
        usePage: () => page,
        trans: (key) => key,
        router: {
            replace: ({ url }) => {
                page.url = url;
            },
        },
        shiftColumns: () => [],
        navigateDataTableRow: () => {},
        AppLayout: simple('main'),
        DataTable: simple('div'),
        EmptyState: simple('div'),
        Tabs: simple('div'),
        TabList: simple('div'),
        Tab: simple('span'),
        TabPanel: simple('div'),
        Input: simple('input'),
        CustomDropdown: {
            props: ['modelValue', 'items'],
            setup:
                (props, { emit }) =>
                () =>
                    h(
                        'select',
                        {
                            id: 'location',
                            value: props.modelValue,
                            onChange: (event) =>
                                emit(
                                    'update:modelValue',
                                    event.target.value === ''
                                        ? ''
                                        : Number(event.target.value),
                                ),
                        },
                        props.items.map((item) =>
                            h('option', { value: item.value }, item.title),
                        ),
                    ),
        },
        LocationScheduleGrid: {
            props: ['locationId'],
            setup: (props) => () =>
                h('div', {
                    id: 'grid-view',
                    'data-location': props.locationId,
                }),
        },
        LocationRosterSchedule: {
            props: ['locationId'],
            setup: (props) => () =>
                h('div', {
                    id: 'roster-view',
                    'data-location': props.locationId,
                }),
        },
    });
    deps.SegmentedControl = await compile(
        'SegmentedControl',
        'components/ui/segmented-control',
    );
    const Scheduling = await compile('Scheduling', 'pages/Team');
    const pageProps = {
        event: { id: 3, is_locked: false },
        locations: [
            { id: 9, name: 'Gate' },
            { id: 10, name: 'Stage' },
        ],
        scheduleDate: date,
        canManage: true,
    };
    let app = mount(Scheduling, pageProps);
    try {
        const selector = document.querySelector('[role="radiogroup"]');
        assert.ok(selector.classList.contains('divide-x'));
        assert.ok(selector.classList.contains('border-line'));
        const buttons = [...selector.querySelectorAll('button')];
        assert.match(buttons[0].textContent, /views.all_locations/);
        assert.match(buttons[1].textContent, /views.location_shifts/);
        buttons[1].click();
        await nextTick();
        await nextTick();
        assert.equal(
            document.querySelector('#roster-view').dataset.location,
            '9',
        );
        assert.equal(
            document.querySelector('#location option[value=""]'),
            null,
        );
        const dropdown = document.querySelector('#location');
        dropdown.value = '10';
        dropdown.dispatchEvent(new window.Event('change', { bubbles: true }));
        await nextTick();
        await nextTick();
        buttons[0].click();
        await nextTick();
        await nextTick();
        assert.equal(document.querySelector('#roster-view'), null);
        assert.equal(
            document.querySelector('#grid-view').dataset.location,
            '10',
        );
        assert.ok(document.querySelector('#location option[value=""]'));
        assert.equal(
            timeline.scheduleViewFromUrl(page.url, pageProps.locations),
            'all_locations',
        );
        assert.equal(
            new URL(page.url, 'http://localhost').searchParams.get('date'),
            date,
        );
        app.unmount();
        app = mount(Scheduling, pageProps);
        assert.equal(
            document
                .querySelector('[role="radio"]')
                .getAttribute('aria-checked'),
            'true',
        );
        assert.equal(
            document.querySelector('#grid-view').dataset.location,
            '10',
        );
        for (const view of ['all_locations', 'location_shifts']) {
            const detail = new URL(
                timeline.scheduleShiftHref(17, date, 'schedule', 10, view),
                'http://localhost',
            );
            const back = timeline.scheduleReturnHref(
                Object.fromEntries(detail.searchParams),
            );
            assert.equal(
                timeline.scheduleViewFromUrl(back, pageProps.locations),
                view,
            );
            assert.equal(
                timeline.scheduleLocationFromUrl(back, pageProps.locations),
                10,
            );
        }
    } finally {
        app.unmount();
    }
});
