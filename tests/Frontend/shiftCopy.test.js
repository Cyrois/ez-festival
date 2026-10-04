import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { JSDOM } from 'jsdom';
import { parse, compileScript } from '@vue/compiler-sfc';
import * as copy from '../../resources/js/lib/shiftCopy.js';
import * as slots from '../../resources/js/lib/shiftRoleSlots.js';
import * as breaks from '../../resources/js/lib/shiftBreaks.js';
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
const { createApp, h, reactive, nextTick, provide, inject, computed } =
    await import('vue');
const require = createRequire(import.meta.url);
const strings = JSON.parse(
    readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'),
);
const trans = (key, values = {}) =>
    Object.entries(values).reduce(
        (s, [key, value]) => s.replaceAll(`:${key}`, value),
        strings[key] ?? key,
    );
const box = (tag) => ({
    setup:
        (_, { attrs, slots }) =>
        () =>
            h(tag, attrs, slots.default?.()),
});
let form;
const writes = [];
const requests = [];
const toasts = [];
const page = reactive({
    url: '/team/scheduling?tab=templates',
    props: { locale: 'en' },
});
let previewResponse = {
    ok: true,
    status: 200,
    json: async () => ({ data: { overlaps: [[], [], []] } }),
};
globalThis.fetch = async (url, options) => {
    requests.push({ url, ...options });
    return previewResponse;
};
const deps = {
    ...copy,
    ...slots,
    ...breaks,
    ...timeline,
    trans,
    AppLayout: box('main'),
    Card: box('article'),
    CardTitle: box('h2'),
    Badge: box('span'),
    Icon: box('i'),
    Avatar: { props: ['name'], setup: (p) => () => h('span', p.name) },
    Button: {
        props: ['href', 'disabled'],
        setup:
            (p, { attrs, slots }) =>
            () =>
                h(
                    p.href ? 'a' : 'button',
                    { ...attrs, href: p.href, disabled: p.disabled },
                    slots.default?.(),
                ),
    },
    IconButton: {
        props: ['label', 'disabled'],
        setup:
            (p, { attrs }) =>
            () =>
                h(
                    'button',
                    {
                        ...attrs,
                        type: 'button',
                        'aria-label': p.label,
                        disabled: p.disabled,
                    },
                    p.label,
                ),
    },
    FormField: {
        props: ['label', 'error'],
        setup:
            (p, { slots }) =>
            () =>
                h('label', [
                    p.label,
                    slots.default?.({ id: undefined, invalid: !!p.error }),
                    p.error,
                ]),
    },
    Input: {
        props: ['modelValue', 'disabled'],
        setup:
            (p, { attrs, emit }) =>
            () =>
                h('input', {
                    ...attrs,
                    value: p.modelValue,
                    disabled: p.disabled,
                    onInput: (e) => emit('update:modelValue', e.target.value),
                }),
    },
    CustomDropdown: {
        props: ['modelValue', 'items', 'disabled'],
        setup:
            (p, { emit }) =>
            () =>
                h(
                    'select',
                    {
                        value: p.modelValue,
                        disabled: p.disabled,
                        onChange: (e) =>
                            emit(
                                'update:modelValue',
                                p.items.find(
                                    (i) => String(i.value) === e.target.value,
                                )?.value,
                            ),
                    },
                    p.items.map((i) =>
                        h('option', { value: i.value }, i.title),
                    ),
                ),
    },
    ShiftOverlapWarnings: {
        props: ['overlaps'],
        setup: (p) => () =>
            h('p', p.overlaps.map((o) => `Overlap ${o.shift_name}`).join(', ')),
    },
    ShiftRoleSlots: box('section'),
    ShiftRoster: box('section'),
    ColorPicker: box('section'),
    ShiftBreaks: {
        setup: (_, { expose }) => {
            expose({ validate: () => true });
            return () => h('section');
        },
    },
    ShiftTimelineRoster: box('section'),
    ShiftAssignmentHoursDialog: box('section'),
    ShiftAssignDialog: box('section'),
    Dialog: box('section'),
    LocationScheduleGrid: box('section'),
    LocationRosterSchedule: box('section'),
    DataTable: box('section'),
    SegmentedControl: box('section'),
    EmptyState: box('section'),
    Link: box('a'),
    TabList: box('nav'),
    Tabs: {
        props: ['modelValue'],
        setup: (p, { slots }) => {
            provide(
                'copyTestTab',
                computed(() => p.modelValue),
            );
            return () => h('div', slots.default?.());
        },
    },
    Tab: {
        props: ['value'],
        setup:
            (p, { slots }) =>
            () =>
                h('button', { 'data-tab': p.value }, slots.default?.()),
    },
    TabPanel: {
        props: ['value'],
        setup: (p, { slots }) => {
            const active = inject('copyTestTab');
            return () =>
                active.value === p.value
                    ? h('div', { 'data-panel': p.value }, slots.default?.())
                    : null;
        },
    },
    fieldError: (f, name) => f.errors[name],
    toastFormErrors: () => {},
    navigateDataTableRow: () => {},
    shiftColumns: () => [],
    usePage: () => page,
    useFlashToast: () => ({
        showError: (e) => toasts.push(e),
        showFormError: (e) => toasts.push(e),
    }),
    router: {
        replace: () => {},
        get: () => {},
        delete: () => {
            throw new Error('Copy must not delete from source');
        },
        reload: () => {},
    },
    useForm: (data) => {
        form = reactive({
            ...data,
            errors: {},
            processing: false,
            isDirty: false,
        });
        let transform = (d) => d;
        form.data = () =>
            Object.fromEntries(
                Object.entries(form).filter(
                    ([key, value]) =>
                        typeof value !== 'function' &&
                        !['errors', 'processing', 'isDirty'].includes(key),
                ),
            );
        form.transform = (cb) => {
            transform = cb;
            return form;
        };
        form.post = (url, options) =>
            writes.push({ url, data: transform(form.data()), options });
        form.put = () => {
            throw new Error('Copy must not update source');
        };
        form.clearErrors = () => {
            form.errors = {};
        };
        form.defaults = () => {};
        return form;
    },
};
globalThis.__copyTest = deps;
async function compile(path) {
    const { descriptor } = parse(
        readFileSync(
            new URL(`../../resources/js/${path}.vue`, import.meta.url),
            'utf8',
        ),
    );
    const source = compileScript(descriptor, { id: path, inlineTemplate: true })
        .content.replaceAll(
            /from ['"]vue['"]/g,
            `from '${pathToFileURL(require.resolve('vue')).href}'`,
        )
        .replace(
            /import\s+([\s\S]*?)\s+from\s+['"]([^'"]+)['"];?/g,
            (statement, names, from) =>
                from.startsWith('file:')
                    ? statement
                    : `const ${names.startsWith('{') ? names : `{ ${names} }`} = globalThis.__copyTest;`,
        );
    return (
        await import(
            `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
        )
    ).default;
}
deps.CopiedShiftRoster = await compile('components/team/CopiedShiftRoster');
const Create = await compile('pages/Team/CreateShift');
const Shift = await compile('pages/Team/Shift');
const Scheduling = await compile('pages/Team/Scheduling');
const prefill = {
    copy: 47,
    name: 'Gate',
    color: 'teal',
    location_id: 9,
    starts_at: '2026-10-31T22:00',
    ends_at: '2026-11-01T04:00',
    slots: [
        { role_id: 3, role_name: 'Gate crew', needed: 1 },
        { role_id: 6, role_name: 'Runner', needed: 2 },
    ],
    breaks: [{ duration_minutes: 15, starts_at: '2026-11-01T00:30' }],
    assignments: [
        {
            team_engagement_id: 10,
            slot_index: 0,
            name: 'Alpha',
            role_name: 'Gate crew',
            hours_mode: 'full_shift',
            starts_at: '2026-10-31T22:00',
            ends_at: '2026-11-01T04:00',
            overlaps: [{ shift_id: 47, shift_name: 'Gate' }],
            error: null,
        },
        {
            team_engagement_id: 20,
            slot_index: 1,
            name: 'Beta',
            role_name: 'Runner',
            hours_mode: 'custom',
            starts_at: '2026-11-01T00:00',
            ends_at: '2026-11-01T03:00',
            overlaps: [],
            error: null,
        },
        {
            team_engagement_id: 30,
            slot_index: 0,
            name: 'Gamma',
            role_name: 'Gate crew',
            hours_mode: 'full_shift',
            starts_at: '2026-10-31T22:00',
            ends_at: '2026-11-01T04:00',
            overlaps: [],
            error: null,
        },
    ],
};
const baseProps = () => ({
    event: { id: 7, name: 'Festival', is_locked: false },
    locations: [{ id: 9, name: 'Gate' }],
    roles: [
        { id: 3, name: 'Gate crew' },
        { id: 6, name: 'Runner' },
    ],
    labelColors: ['teal'],
    breakOptions: { durations: [15, 30, 45, 60], default_duration: 15 },
    prefill: structuredClone(prefill),
});
function mount(component, props) {
    writes.length = 0;
    requests.length = 0;
    toasts.length = 0;
    const app = createApp(component, props);
    app.config.globalProperties.$t = trans;
    app.mount('#app');
    return app;
}
const submit = () =>
    document
        .querySelector('form')
        .dispatchEvent(
            new window.Event('submit', { bubbles: true, cancelable: true }),
        );
const waitPreview = async () => {
    await nextTick();
    await new Promise((resolve) => setTimeout(resolve, 300));
    await nextTick();
};

test('copied drafts strip saved ids, keep source untouched, and map slots by stable identity', () => {
    const source = structuredClone(prefill);
    const draft = copy.copiedShiftDraft(source);
    assert.equal(draft.slots[0].id, null);
    assert.equal(draft.breaks[0].id, null);
    assert.deepEqual(source, prefill);
    const payload = copy.copiedAssignmentPayload(
        draft.assignments,
        draft.slots.slice(1),
    );
    assert.deepEqual(
        payload.map((r) => r.slot_index),
        [null, 0, null],
    );
    assert.deepEqual(Object.keys(payload[0]), [
        'team_engagement_id',
        'slot_index',
        'hours_mode',
    ]);
    const errors = copy.copiedAssignmentErrors(draft.assignments, {
        'assignments.1.ends_at': ['Outside shift'],
        'assignments.2.team_engagement_id': 'Duplicate',
    });
    assert.equal(errors[draft.assignments[1]._key].ends_at, 'Outside shift');
});

test('event-local movement crosses midnight and DST without changing relative custom hours or break lengths', () => {
    const draft = {
        ...prefill,
        ...copy.copiedShiftDraft(prefill),
        starts_at: '2026-11-01T22:00',
        ends_at: '2026-11-02T05:00',
    };
    const moved = copy.moveCopiedShift(draft, prefill.starts_at);
    assert.equal(moved.assignments[0].ends_at, '2026-11-02T05:00');
    assert.equal(moved.assignments[1].starts_at, '2026-11-02T00:00');
    assert.equal(moved.assignments[1].ends_at, '2026-11-02T03:00');
    assert.equal(moved.breaks[0].starts_at, '2026-11-02T00:30');
    assert.equal(moved.breaks[0]._day, '2026-11-02');
    assert.equal(moved.breaks[0].duration_minutes, 15);
    assert.equal(
        copy.copiedHoursValid(
            moved.assignments[1],
            draft.starts_at,
            '2026-11-02T01:00',
        ),
        false,
    );
});

test('Copy is next to Delete, only shown for writable shifts, and retains return context', async () => {
    for (const [canManage, locked, shown] of [
        [true, false, true],
        [false, false, false],
        [true, true, false],
    ]) {
        const props = baseProps();
        props.event.is_locked = locked;
        props.shift = {
            ...prefill,
            id: 47,
            slots: [],
            breaks: [],
            assignments: [],
            assignment_count: 0,
        };
        props.canManage = canManage;
        props.returnContext = { return_tab: 'list', return_date: '2026-10-31' };
        const app = mount(Shift, props);
        const link = [...document.querySelectorAll('header a')].find((el) =>
            el.textContent.includes('Copy'),
        );
        assert.equal(!!link, shown);
        if (shown) {
            assert.equal(
                new URL(link.href, 'http://localhost').searchParams.get('copy'),
                '47',
            );
            assert.equal(
                new URL(link.href, 'http://localhost').searchParams.get(
                    'return_tab',
                ),
                'list',
            );
            assert.match(link.parentElement.textContent, /Delete shift/);
        }
        app.unmount();
        await nextTick();
    }
});

test('draft people render fully, removal writes nothing, and Create posts all remaining people in one request', async () => {
    const app = mount(Create, baseProps());
    assert.equal(document.querySelectorAll('[data-copy-person]').length, 3);
    assert.match(document.body.textContent, /Overlap Gate/);
    assert.match(document.body.textContent, /Extra/);
    assert.equal(writes.length, 0);
    document
        .querySelector('[aria-label="Remove Gamma from this shift"]')
        .click();
    await nextTick();
    assert.equal(writes.length, 0);
    submit();
    assert.equal(writes.length, 1);
    assert.equal(writes[0].url, '/team/events/7/shifts');
    assert.deepEqual(
        writes[0].data.assignments.map((row) => row.team_engagement_id),
        [10, 20],
    );
    assert.equal(writes[0].data.assignments[1].slot_index, 1);
    assert.equal(writes[0].data.assignments[1].starts_at, '2026-11-01T00:00');
    assert.equal(writes[0].data.slots[0].id, null);
    assert.equal(writes[0].data.breaks[0].id, undefined);
    app.unmount();
});

test('changing shift dates moves roster and breaks, updates warnings once, and ignores metadata-only mutations', async () => {
    const app = mount(Create, baseProps());
    form.starts_at = '2026-11-01T22:00';
    form.ends_at = '2026-11-02T04:00';
    await waitPreview();
    assert.equal(form.assignments[1].starts_at, '2026-11-02T00:00');
    assert.equal(form.breaks[0].starts_at, '2026-11-02T00:30');
    assert.equal(requests.length, 1);
    assert.equal(
        JSON.parse(requests[0].body).assignments[1].ends_at,
        '2026-11-02T03:00',
    );
    form.assignments[0].overlaps = [{ shift_name: 'New warning' }];
    await waitPreview();
    assert.equal(requests.length, 1);
    app.unmount();
});

test('out-of-bounds people show inline errors and cannot save until their own hours are corrected', async () => {
    const app = mount(Create, baseProps());
    form.ends_at = '2026-11-01T02:00';
    await nextTick();
    assert.match(
        document.querySelector('[data-copy-person="copy-person-1"]')
            .textContent,
        /must be/,
    );
    submit();
    assert.equal(writes.length, 0);
    const inputs = document
        .querySelector('[data-copy-person="copy-person-1"]')
        .querySelectorAll('input');
    inputs[1].value = '2026-11-01T01:30';
    inputs[1].dispatchEvent(new window.Event('input', { bubbles: true }));
    await nextTick();
    submit();
    assert.equal(writes.length, 1);
    app.unmount();
});

test('eligibility errors stay on a person row and removing that person allows creation', async () => {
    const props = baseProps();
    props.prefill.assignments[1].error =
        'Choose a hired Team member in this event.';
    const app = mount(Create, props);
    assert.match(
        document.querySelector('[data-copy-person="copy-person-1"]')
            .textContent,
        /hired/,
    );
    submit();
    assert.equal(writes.length, 0);
    document
        .querySelector('[aria-label="Remove Beta from this shift"]')
        .click();
    await nextTick();
    submit();
    assert.equal(writes.length, 1);
    app.unmount();
});

test('Templates is absent and a templates deep link opens Schedule even after List', async () => {
    const props = baseProps();
    props.scheduleDate = '2026-10-31';
    props.canManage = true;
    page.url = '/team/scheduling?tab=list';
    const app = mount(Scheduling, props);
    assert.ok(document.querySelector('[data-panel="list"]'));
    page.url = '/team/scheduling?tab=templates';
    await nextTick();
    assert.ok(document.querySelector('[data-panel="schedule"]'));
    assert.deepEqual(
        [...document.querySelectorAll('[data-tab]')].map(
            (node) => node.dataset.tab,
        ),
        ['schedule', 'list'],
    );
    app.unmount();
});
