import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { JSDOM } from 'jsdom';
import { parse, compileScript } from '@vue/compiler-sfc';
import { labelTokens } from '../../resources/js/lib/labelTokens.js';
import * as timeline from '../../resources/js/lib/scheduleTimeline.js';

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
const { createApp, h, ref, nextTick } = await import('vue');
let animationId = 0;
const animations = new Map();
globalThis.requestAnimationFrame = (callback) => {
    animations.set(++animationId, callback);
    return animationId;
};
globalThis.cancelAnimationFrame = (id) => animations.delete(id);
const Link = {
    setup:
        (_, { attrs, slots }) =>
        () =>
            h('a', attrs, slots.default?.()),
};
const Button = {
    setup:
        (_, { attrs, slots }) =>
        () =>
            h('button', attrs, slots.default?.()),
};
let gridProps;
const dependencies = {
    ...timeline,
    labelTokens,
    Link,
    Button,
    Icon: {
        props: ['name'],
        setup: (props) => () => h('i', { 'data-icon': props.name?.[1] }),
    },
    ScheduleTimeline: {
        props: ['rows', 'date', 'empty', 'loading', 'hasMore'],
        setup:
            (props, { emit }) =>
            () => {
                gridProps = props;
                return h('button', {
                    id: 'more',
                    onClick: () => emit('load-more'),
                });
            },
    },
};
globalThis.__scheduleTest = dependencies;
const require = createRequire(import.meta.url);
async function compile(name, folder = 'team') {
    const { descriptor } = parse(
        readFileSync(
            new URL(
                `../../resources/js/components/${folder}/${name}.vue`,
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
            return `const ${names.startsWith('{') ? names : `{ ${names} }`} = globalThis.__scheduleTest;`;
        },
    );
    return (
        await import(
            `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
        )
    ).default;
}
const Timeline = await compile('ScheduleTimeline');
const Grid = await compile('LocationScheduleGrid');
const ColorPicker = await compile('ColorPicker', 'ui/color-picker');
const date = '2026-09-26';
const shifts = [
    {
        id: 1,
        name: 'Show run',
        color: 'violet',
        starts_at: `${date}T14:00`,
        ends_at: `${date}T22:00`,
        assignment_count: 2,
        filled_count: 2,
        total_needs: 2,
    },
    {
        id: 2,
        name: '<script>unsafe</script>',
        color: 'rose',
        starts_at: `${date}T15:00`,
        ends_at: `${date}T18:00`,
        assignment_count: 0,
        filled_count: 0,
        total_needs: 3,
    },
    {
        id: 3,
        name: 'Extra only',
        starts_at: `${date}T18:00`,
        ends_at: `${date}T18:01`,
        assignment_count: 1,
        filled_count: 0,
        total_needs: 0,
    },
    {
        id: 4,
        name: 'Partially filled',
        color: 'teal',
        starts_at: `${date}T19:00`,
        ends_at: `${date}T20:00`,
        assignment_count: 1,
        filled_count: 1,
        total_needs: 3,
    },
];
const settle = async () => {
    await new Promise((resolve) => setTimeout(resolve, 0));
    await nextTick();
};
const pointer = (element, type, x, overrides = {}) => {
    const event = new window.Event(type, { bubbles: true, cancelable: true });
    Object.assign(event, {
        clientX: x,
        button: 0,
        pointerId: 4,
        isPrimary: true,
        ...overrides,
    });
    element.dispatchEvent(event);
};
async function mountTimeline(canCreate = true) {
    const writable = ref(canCreate);
    const firstMinute = ref(null);
    const received = [];
    const app = createApp({
        setup: () => () =>
            h(Timeline, {
                rows: [{ id: 19, name: 'Main stage', shifts }],
                date,
                canCreate: writable.value,
                firstShiftMinute: firstMinute.value,
                onCreate: (value) => received.push(value),
            }),
    });
    app.config.globalProperties.$t = (key, data = {}) =>
        `${key} ${JSON.stringify(data)}`;
    app.mount(document.querySelector('#app'));
    await nextTick();
    const canvas = document.querySelector('[data-location-id]');
    canvas.getBoundingClientRect = () => ({ left: 0 });
    return { app, canvas, received, writable, firstMinute };
}

test('dismissing the empty hint applies only to the current day and location filter', async () => {
    const selectedDate = ref(date);
    const location = ref('');
    const app = createApp({
        setup: () => () =>
            h(Timeline, {
                rows: [{ id: 19, name: 'Main stage', shifts: [] }],
                date: selectedDate.value,
                locationId: location.value,
                empty: true,
                canCreate: true,
            }),
    });
    app.config.globalProperties.$t = (key) => key;
    app.mount(document.querySelector('#app'));
    const hint = () => document.querySelector('[data-icon="arrow-pointer"]');
    const dismiss = async () => {
        document.querySelector('button').click();
        await nextTick();
        assert.equal(hint(), null);
    };
    try {
        assert.ok(hint());
        await dismiss();
        selectedDate.value = '2026-09-27';
        await nextTick();
        assert.ok(hint());
        await dismiss();
        location.value = 19;
        await nextTick();
        assert.ok(hint());
        await dismiss();
        location.value = '';
        await nextTick();
        assert.ok(hint());
    } finally {
        app.unmount();
    }
});

test('grid starts at the first shift, or 06:00 when no shift exists', async () => {
    assert.equal(timeline.scheduleInitialScroll(null), 672);
    assert.equal(timeline.scheduleInitialScroll(0), 0);
    assert.equal(timeline.scheduleInitialScroll(555), 980);
    const { app, firstMinute } = await mountTimeline();
    try {
        const viewport = document.querySelector('[role="region"]');
        assert.equal(viewport.scrollLeft, 672);
        firstMinute.value = 555;
        await settle();
        assert.equal(viewport.scrollLeft, 980);
        firstMinute.value = null;
        await settle();
        assert.equal(viewport.scrollLeft, 672);
    } finally {
        app.unmount();
    }
});

test('wall-clock date arithmetic and labels survive timezone and DST differences', () => {
    assert.equal(timeline.scheduleDay('2026-03-08', 1), '2026-03-09');
    assert.equal(timeline.scheduleDay('2026-01-01', -1), '2025-12-31');
    assert.equal(
        timeline.scheduleDateLabel(date, 'en-US', { year: 'numeric' }),
        'Sat, Sep 26, 2026',
    );
    assert.equal(timeline.scheduleTimestamp(date, 1440), '2026-09-27T00:00');
});

test('overnight clipping, touching boundaries and arbitrary minutes remain accurate', () => {
    assert.deepEqual(
        timeline.scheduleInterval(
            { starts_at: '2026-09-25T23:00', ends_at: `${date}T02:15` },
            date,
        ),
        { start: 0, end: 135 },
    );
    assert.deepEqual(
        timeline.scheduleInterval(
            { starts_at: `${date}T23:45`, ends_at: '2026-09-27T02:00' },
            date,
        ),
        { start: 1425, end: 1440 },
    );
    assert.equal(
        timeline.scheduleInterval(
            { starts_at: '2026-09-25T23:00', ends_at: `${date}T00:00` },
            date,
        ),
        null,
    );
    assert.equal(
        timeline.scheduleInterval(
            { starts_at: '2026-09-27T00:00', ends_at: '2026-09-27T02:00' },
            date,
        ),
        null,
    );
});

test('overlapping shifts get stable lanes and adjacent shifts reuse a lane', () => {
    const values = [
        ...shifts.slice(0, 2),
        { id: 4, starts_at: `${date}T22:00`, ends_at: `${date}T23:00` },
    ];
    assert.deepEqual(
        timeline
            .scheduleLanes(values.reverse(), date)
            .map(({ id, lane }) => [id, lane]),
        [
            [1, 0],
            [2, 1],
            [4, 0],
        ],
    );
    assert.equal(timeline.scheduleSlot(20, -540), 10);
    assert.equal(timeline.scheduleSlot(-100, 0), 0);
    assert.equal(timeline.scheduleSlot(10000, 0), 47);
});

test('creation URLs distinguish header context from dragged values and encode names safely', () => {
    const header = new URL(
        timeline.scheduleCreateHref(date),
        'http://localhost',
    );
    assert.equal(header.searchParams.get('location_id'), null);
    const selection = timeline.scheduleSelection(date, 19, 47, 47);
    const drag = new URL(
        timeline.scheduleCreateHref(date, 'schedule', selection),
        'http://localhost',
    );
    assert.equal(drag.searchParams.get('ends_at'), '2026-09-27T00:00');
    assert.equal(drag.searchParams.get('location_id'), '19');
});

test('shift and return URLs retain the originating tab and selected day', () => {
    for (const tab of ['schedule', 'list']) {
        const shift = new URL(
            timeline.scheduleShiftHref(7, date, tab),
            'http://localhost',
        );
        assert.equal(shift.pathname, '/team/shifts/7');
        assert.equal(shift.searchParams.get('return_tab'), tab);
        assert.equal(shift.searchParams.get('schedule_date'), date);
        const back = new URL(
            timeline.scheduleReturnHref({
                return_tab: tab,
                schedule_date: date,
            }),
            'http://localhost',
        );
        assert.equal(back.pathname, '/team/scheduling');
        assert.equal(back.searchParams.get('tab'), tab);
        assert.equal(back.searchParams.get('date'), date);
    }
});

test('mounted timeline emits the same half-hour range for forward and reverse drags', async () => {
    const { app, canvas, received } = await mountTimeline();
    try {
        const cell = canvas.querySelector('[data-schedule-cell]');
        assert.ok(cell.classList.contains('hover:bg-page'));
        pointer(cell, 'pointerdown', 28 * 56 + 4);
        pointer(canvas, 'pointermove', 31 * 56 + 10);
        pointer(canvas, 'pointerup', 31 * 56 + 10);
        pointer(canvas, 'pointerdown', 28 * 56 + 4);
        pointer(canvas, 'pointermove', 31 * 56 + 10);
        pointer(canvas, 'pointerup', 31 * 56 + 10);
        pointer(canvas, 'pointerdown', 31 * 56 + 10);
        pointer(canvas, 'pointerup', 28 * 56 + 4);
        assert.equal(received.length, 3);
        assert.deepEqual(received[0], received[1]);
        assert.deepEqual(received[1], received[2]);
        assert.equal(received[0].starts_at, `${date}T14:00`);
        assert.equal(received[0].ends_at, `${date}T16:00`);
        assert.equal(received[0].location_id, 19);
        assert.equal(animations.size, 0);
    } finally {
        app.unmount();
    }
});

test('clicks, shift links, Escape, cancellation and permission loss never create shifts', async () => {
    const { app, canvas, received, writable } = await mountTimeline();
    try {
        pointer(canvas, 'pointerdown', 100);
        pointer(canvas, 'pointerup', 102);
        pointer(canvas.querySelector('a'), 'pointerdown', 100);
        pointer(canvas, 'pointerup', 500);
        pointer(canvas, 'pointerdown', 100);
        pointer(canvas, 'pointermove', 500);
        window.dispatchEvent(
            new window.KeyboardEvent('keydown', { key: 'Escape' }),
        );
        pointer(canvas, 'pointerup', 500);
        pointer(canvas, 'pointerdown', 100);
        pointer(canvas, 'pointercancel', 500);
        pointer(canvas, 'pointerup', 500);
        pointer(canvas, 'pointerdown', 100);
        pointer(canvas, 'pointermove', 500);
        writable.value = false;
        await nextTick();
        pointer(canvas, 'pointerup', 500);
        pointer(canvas, 'pointerdown', 100);
        pointer(canvas, 'pointerup', 500);
        assert.equal(received.length, 0);
        const shiftUrl = new URL(
            canvas.querySelector('a').getAttribute('href'),
            'http://localhost',
        );
        assert.equal(shiftUrl.searchParams.get('return_tab'), 'schedule');
        assert.equal(shiftUrl.searchParams.get('schedule_date'), date);
        assert.equal(document.querySelector('script'), null);
        assert.match(
            canvas.querySelectorAll('a')[1].textContent,
            /<script>unsafe<\/script>/,
        );
        assert.equal(canvas.querySelectorAll('[data-icon="check"]').length, 1);
        const filledCheck = canvas.querySelector('[data-icon="check"]');
        assert.ok(filledCheck.classList.contains('absolute'));
        assert.ok(filledCheck.classList.contains('right-2'));
        assert.ok(canvas.querySelectorAll('a')[0].classList.contains('pr-7'));
        assert.ok(
            canvas
                .querySelectorAll('a')[0]
                .classList.contains('bg-label-violet'),
        );
        assert.ok(
            canvas
                .querySelectorAll('a')[1]
                .classList.contains('text-label-rose'),
        );
        assert.ok(
            canvas.querySelectorAll('a')[1].classList.contains('border-dashed'),
        );
        assert.ok(
            canvas
                .querySelectorAll('a')[1]
                .classList.contains('hover:border-solid'),
        );
        assert.ok(
            canvas
                .querySelectorAll('a')[1]
                .classList.contains('hover:bg-label-rose/30'),
        );
        const extra = canvas.querySelectorAll('a')[2];
        assert.ok(extra.classList.contains('border-dashed'));
        assert.ok(parseFloat(extra.style.getPropertyValue('--bar-width')) > 0);
        const warning = canvas.querySelector(
            '[data-icon="circle-exclamation"]',
        );
        assert.equal(warning.classList.contains('text-warning'), true);
        const partial = canvas.querySelectorAll('a')[3];
        assert.ok(partial.classList.contains('border-dashed'));
        assert.ok(partial.classList.contains('hover:bg-label-teal/30'));
        assert.equal(
            partial.querySelector('[data-icon="circle-exclamation"]') !== null,
            true,
        );
        assert.equal(
            canvas.querySelectorAll('[data-icon="circle-exclamation"]').length,
            3,
        );
    } finally {
        app.unmount();
    }
});

test('the shared color picker selects a standard color and respects read-only state', async () => {
    const selected = ref('teal');
    const disabled = ref(false);
    const app = createApp({
        setup: () => () =>
            h(ColorPicker, {
                modelValue: selected.value,
                colors: Object.keys(labelTokens),
                disabled: disabled.value,
                'onUpdate:modelValue': (color) => {
                    selected.value = color;
                },
            }),
    });
    app.config.globalProperties.$t = (key) => key;
    app.mount(document.querySelector('#app'));
    try {
        assert.equal(document.querySelectorAll('button').length, 10);
        document.querySelector('[aria-label="labels.colors.violet"]').click();
        await nextTick();
        assert.equal(selected.value, 'violet');
        assert.equal(
            document
                .querySelector('[aria-label="labels.colors.violet"]')
                .getAttribute('aria-pressed'),
            'true',
        );
        disabled.value = true;
        await nextTick();
        document.querySelector('[aria-label="labels.colors.rose"]').click();
        await nextTick();
        assert.equal(selected.value, 'violet');
    } finally {
        app.unmount();
    }
});

test('date changes ignore stale requests and location continuation remains server-paged', async () => {
    const requests = [];
    globalThis.fetch = (url, options) =>
        new Promise((resolve) => requests.push({ url, options, resolve }));
    const selected = ref(date);
    const app = createApp({
        setup: () => () => h(Grid, { date: selected.value, eventId: 4 }),
    });
    app.config.globalProperties.$t = (key) => key;
    app.mount(document.querySelector('#app'));
    const response = (rows, page = 1, last = 1) => ({
        ok: true,
        json: async () => ({
            data: rows,
            schedule: { shift_count: 1 },
            meta: { current_page: page, last_page: last },
        }),
    });
    try {
        selected.value = '2026-09-27';
        await nextTick();
        assert.equal(requests[0].options.signal.aborted, true);
        requests[1].resolve(response([{ id: 7, shifts: [] }], 1, 2));
        await settle();
        requests[0].resolve(response([{ id: 99, shifts: [] }]));
        await settle();
        assert.deepEqual(
            gridProps.rows.map((row) => row.id),
            [7],
        );
        assert.equal(gridProps.empty, false);
        document.querySelector('#more').click();
        await nextTick();
        assert.match(requests[2].url, /page=2/);
        requests[2].resolve(response([{ id: 8, shifts: [] }], 2, 2));
        await settle();
        assert.deepEqual(
            gridProps.rows.map((row) => row.id),
            [7, 8],
        );
        assert.equal(gridProps.hasMore, false);
    } finally {
        app.unmount();
    }
});

test('location changes cancel stale loads, restart paging and clear the filter', async () => {
    const requests = [];
    globalThis.fetch = (url, options) =>
        new Promise((resolve) => requests.push({ url, options, resolve }));
    const location = ref('');
    const app = createApp({
        setup: () => () =>
            h(Grid, { date, eventId: 4, locationId: location.value }),
    });
    app.config.globalProperties.$t = (key) => key;
    app.mount(document.querySelector('#app'));
    const response = (id) => ({
        ok: true,
        json: async () => ({
            data: [{ id, shifts: [] }],
            schedule: { shift_count: 0, first_shift_minute: null },
            meta: { current_page: 1, last_page: 1 },
        }),
    });
    try {
        location.value = 9;
        await nextTick();
        assert.equal(requests[0].options.signal.aborted, true);
        assert.match(requests[1].url, /location_id=9/);
        assert.match(requests[1].url, /page=1/);
        requests[1].resolve(response(9));
        await settle();
        requests[0].resolve(response(99));
        await settle();
        assert.deepEqual(
            gridProps.rows.map((row) => row.id),
            [9],
        );
        location.value = '';
        await nextTick();
        assert.equal(
            new URL(requests[2].url, 'http://localhost').searchParams.has(
                'location_id',
            ),
            false,
        );
        requests[2].resolve(response(1));
        await settle();
        assert.deepEqual(
            gridProps.rows.map((row) => row.id),
            [1],
        );
    } finally {
        app.unmount();
    }
});

test('a failed grid request does not show the empty state and can retry', async () => {
    let fail = true;
    globalThis.fetch = async () => ({
        ok: !fail,
        json: async () => ({
            data: [{ id: 1, shifts: [] }],
            schedule: { shift_count: 0 },
            meta: { current_page: 1, last_page: 1 },
        }),
    });
    const app = createApp({ setup: () => () => h(Grid, { date, eventId: 4 }) });
    app.config.globalProperties.$t = (key) => key;
    app.mount(document.querySelector('#app'));
    try {
        await settle();
        assert.equal(gridProps.empty, false);
        assert.ok(document.querySelector('[role="alert"]'));
        fail = false;
        document.querySelector('[role="alert"] button').click();
        await settle();
        assert.equal(gridProps.empty, true);
        assert.equal(document.querySelector('[role="alert"]'), null);
    } finally {
        app.unmount();
    }
});

test('schedule summaries count extras as assigned without changing required headcount or role occupancy', async () => {
    const app = createApp({
        setup: () => () => h(Timeline, {
            rows: [{ id: 19, name: 'Gate', shifts: [{ ...shifts[0], assignment_count: 3, filled_count: 2, total_needs: 3 }] }],
            date,
            canCreate: false,
        }),
    });
    app.config.globalProperties.$t = (key, data = {}) => `${key} ${JSON.stringify(data)}`;
    app.mount(document.querySelector('#app'));
    try {
        await nextTick();
        assert.match(document.body.textContent, /"filled":3,"needed":3/);
        assert.equal(timeline.scheduleShiftIsFilled({ assignment_count: 3, filled_count: 2, total_needs: 3 }), true);
        assert.equal(timeline.scheduleShiftIsFilled({ assignment_count: 1, filled_count: 0, total_needs: 0 }), false);
    } finally { app.unmount(); }
});
