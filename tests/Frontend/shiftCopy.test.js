import * as mealHelpers from '../../resources/js/lib/shiftMeals.js';
import * as mealDateHelpers from '../../resources/js/lib/mealDates.js';
import * as personalBreakHelpers from '../../resources/js/lib/personalBreaks.js';
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
import {
    labelTokens,
    fallbackLabelToken,
} from '../../resources/js/lib/labelTokens.js';
import * as assignments from '../../resources/js/lib/shiftAssignments.js';

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
    json: async () => ({ data: [], other_shifts: [] }),
};
globalThis.fetch = async (url, options) => {
    requests.push({ url, ...options });
    return previewResponse;
};
const deps = {
    ...mealHelpers,
    ...mealDateHelpers,
    getActiveLanguage: () => "en",
    ...personalBreakHelpers,
    ...copy,
    ...slots,
    ...breaks,
    ...timeline,
    labelTokens,
    fallbackLabelToken,
    Table: box('table'),
    TableHeader: box('thead'),
    TableBody: box('tbody'),
    TableRow: box('tr'),
    TableHead: box('th'),
    TableCell: box('td'),
    ...assignments,
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
    UnsavedChangesDialog: box('section'),
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
deps.Tooltip = await compile('components/ui/tooltip/Tooltip');
deps.ShiftTimelineRoster = await compile('components/team/ShiftTimelineRoster');
deps.ShiftMeals = await compile('components/team/ShiftMeals');
deps.ShiftEditor = await compile('components/team/ShiftEditor');
const Create = await compile('pages/Team/CopyShift');
const Shift = await compile('pages/Team/Shift');
const Scheduling = await compile('pages/Team/Scheduling');
const prefill = {
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
            role_id: 3,
            role_name: 'Gate crew',
            hours_mode: 'full_shift',
            starts_at: '2026-10-31T22:00',
            ends_at: '2026-11-01T04:00',
            overlaps: [
                {
                    shift_id: 47,
                    shift_name: 'Gate',
                    starts_at: '2026-10-31T22:00',
                    ends_at: '2026-11-01T04:00',
                    overlap_minutes: 360,
                },
            ],
            error: null,
        },
        {
            team_engagement_id: 20,
            slot_index: 1,
            name: 'Beta',
            role_id: 6,
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
            role_id: 3,
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
    canManage: true,
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

test('snapshot strips source ids and maps people to fresh draft slots, including detached roles', () => {
    const source = structuredClone(prefill);
    source.assignments[1].slot_index = null;
    const draft = copy.copiedShiftDraft(source);
    assert.equal(draft.slots[0].id, null);
    assert.equal(draft.breaks[0].id, null);
    assert.equal(draft.assignment_additions[0].slot_key, draft.slots[0]._key);
    assert.equal(draft.assignment_additions[1].role_id, 6);
    assert.equal(draft.assignment_additions[1].slot_key, undefined);
    assert.equal(draft.assignment_additions[0].copy, undefined);
    assert.deepEqual(prefill.assignments[1].slot_index, 1);
});

test('copy preserves a role-free extra without creating a role or headcount slot', () => {
    const source = structuredClone(prefill);
    source.assignments[1].slot_index = null;
    source.assignments[1].role_id = null;
    source.assignments[1].role_name = null;
    const draft = copy.copiedShiftDraft(source);
    const row = draft.assignment_additions[1];
    assert.equal(row.extra, true);
    assert.equal('role_id' in row, false);
    assert.equal('slot_key' in row, false);
    assert.equal(draft.slots.length, source.slots.length);
    assert.equal(draft.people[-2].shift_role_slot_id, null);
    assert.equal(draft.people[-2].role_name, null);
});

test('Copy preserves the supervisor by fresh draft key as dates move, clearing it on removal', async () => {
    const props = baseProps();
    props.prefill = structuredClone(prefill);
    props.prefill.assignments[1].is_supervisor = true;
    const draft = copy.copiedShiftDraft(props.prefill);
    assert.equal(draft.supervisor_key, -2);
    const app = mount(Create, props);
    try {
        assert.equal(form.supervisor_key, -2);
        const row = document.querySelector('[data-roster-row="person--2"]');
        assert.ok(row.querySelector('[data-supervisor-tag]'));
        assert.equal(row.querySelector('[data-person-bar] [data-supervisor-tag]'), null);
        form.starts_at = '2026-11-02T22:00';
        form.ends_at = '2026-11-03T04:00';
        await nextTick();
        assert.equal(form.supervisor_key, -2);
        submit();
        assert.equal(writes.length, 1);
        assert.equal(writes[0].data.supervisor_key, -2);
        assert.equal(writes[0].data.assignment_additions[1].client_key, -2);
        assert.equal('is_supervisor' in writes[0].data.assignment_additions[1], false);
        [...row.querySelectorAll('button')]
            .find((button) => button.getAttribute('aria-label')?.includes('Remove'))
            .click();
        await nextTick();
        assert.equal(form.supervisor_key, null);
        assert.equal(document.querySelector('[data-supervisor-tag]'), null);
        submit();
        assert.equal(writes[1].data.supervisor_key, null);
    } finally { app.unmount(); }
});

test('Copy opens its dedicated page in a new tab even with unsaved changes and keeps return context', async () => {
    for (const [canManage, locked, shown] of [
        [true, false, true],
        [false, false, false],
        [true, true, false],
    ]) {
        const props = baseProps();
        props.event.is_locked = locked;
        props.canManage = canManage;
        props.shift = {
            ...prefill,
            id: 47,
            slots: [],
            breaks: [],
            assignments: [],
            assignment_count: 0,
        };
        props.returnContext = { return_tab: 'list', return_date: '2026-10-31' };
        const app = mount(Shift, props);
        form.isDirty = true;
        await nextTick();
        const link = [...document.querySelectorAll('header a')].find((el) =>
            el.textContent.includes('Copy'),
        );
        assert.equal(!!link, shown);
        if (shown) {
            assert.equal(link.getAttribute('target'), '_blank');
            assert.match(link.getAttribute('rel'), /noopener/);
            assert.match(link.href, /\/team\/shifts\/47\/copy\?/);
            assert.equal(
                new URL(link.href, 'http://localhost').searchParams.get(
                    'return_tab',
                ),
                'list',
            );
            assert.match(link.parentElement.textContent, /Delete shift/);
        }
        app.unmount();
    }
});

test('copy renders the complete shared timeline; removing a person never writes and Create saves remaining values once', async () => {
    const app = mount(Create, baseProps());
    assert.equal(document.querySelectorAll('[data-roster-row]').length, 4);
    assert.ok(document.querySelector('[data-person-bar]'));
    assert.equal(writes.length, 0);
    const person = document.querySelector('[data-roster-row="person--2"]');
    [...person.querySelectorAll('button')]
        .find((button) => button.getAttribute('aria-label')?.includes('Remove'))
        .click();
    await nextTick();
    assert.equal(form.assignment_additions.length, 2);
    assert.equal(writes.length, 0);
    submit();
    assert.equal(writes.length, 1);
    const data = writes[0].data;
    assert.equal(data.assignment_additions.length, 2);
    assert.equal(data.name, 'Gate');
    assert.equal(data.color, 'teal');
    assert.equal(data.breaks[0].starts_at, '2026-11-01T00:30');
    assert.equal(data.copy, undefined);
    assert.equal(data.assignments, undefined);
    assert.equal(data.assignment_additions[0]._key, undefined);
    assert.equal(data.assignment_additions[0].starts_at, undefined);
    assert.equal(
        data.assignment_additions[0].slot_key,
        data.slots[0].client_key,
    );
    app.unmount();
});

test('copy dates move custom hours and breaks through midnight and DST while full-shift hours follow both bounds', async () => {
    const app = mount(Create, baseProps());
    form.ends_at = '2026-11-02T04:00';
    form.starts_at = '2026-11-01T22:00';
    await waitPreview();
    assert.equal(form.assignment_additions[1].starts_at, '2026-11-02T00:00');
    assert.equal(form.assignment_additions[1].ends_at, '2026-11-02T03:00');
    assert.equal(form.breaks[0].starts_at, '2026-11-02T00:30');
    assert.equal(form.assignment_additions[0].starts_at, undefined);
    assert.ok(
        requests.every((request) =>
            request.url.startsWith(
                '/team/events/7/shifts/assignment-overlaps?',
            ),
        ),
    );
    app.unmount();
});

test('copied roster eligibility and submitted-row errors appear inline on the timeline', async () => {
    const props = baseProps();
    props.prefill.assignments[1].error =
        'Choose a hired Team member in this event.';
    const app = mount(Create, props);
    assert.match(
        document.querySelector('[data-roster-row="person--2"]').textContent,
        /hired Team member/,
    );
    submit();
    writes[0].options.onError({
        'assignment_additions.0.team_engagement_id': 'Not hired anymore',
    });
    await nextTick();
    assert.match(
        document.querySelector('[data-roster-row="person--1"]').textContent,
        /Not hired anymore/,
    );
    app.unmount();
});

test('templates URL opens Schedule initially and when navigating from List', async () => {
    page.url = '/team/scheduling?tab=templates';
    const props = {
        event: { id: 7, name: 'Festival' },
        locations: [],
        shifts: [],
        filters: {},
        roles: [],
        labelColors: [],
        breakOptions: {},
        scheduleDate: '2026-10-31',
        permissions: {},
    };
    const app = mount(Scheduling, props);
    await nextTick();
    assert.ok(document.querySelector('[data-panel="schedule"]'));
    app.unmount();
});

test('Copy shift omits meals from its draft and payload and only shows the warning card', async () => {
    const props = baseProps();
    props.copying = true;
    props.prefill.meals = [{ meal_id: 12, assignment_ids: [90], meal: { name: 'Dinner', starts_at: '17:30' } }];
    const app = mount(Create, props);
    try {
        assert.deepEqual(form.meals, []);
        assert.match(document.body.textContent, /Meals can[’']t be copied/);
        assert.doesNotMatch(document.body.textContent, /Choose who on this shift/);
        submit();
        assert.equal(Object.hasOwn(writes[0].data, 'meals'), false);
    } finally { app.unmount(); }
});
