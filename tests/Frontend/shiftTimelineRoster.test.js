import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { JSDOM } from 'jsdom';
import { parse, compileScript } from '@vue/compiler-sfc';
import * as timeline from '../../resources/js/lib/scheduleTimeline.js';
import * as breakHelpers from '../../resources/js/lib/shiftBreaks.js';
import * as slotHelpers from '../../resources/js/lib/shiftRoleSlots.js';
import {
    scheduleRosterRows,
    validAssignmentHours,
    resizedAssignment,
    translatedAssignment,
    draftRoster,
} from '../../resources/js/lib/shiftAssignments.js';
import {
    labelTokens,
    fallbackLabelToken,
} from '../../resources/js/lib/labelTokens.js';
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
const { createApp, h, reactive, nextTick } = await import('vue');
const require = createRequire(import.meta.url);
const text = (key, params = {}) =>
    key +
    ' ' +
    Object.entries(params)
        .map(([k, v]) => `${k}=${v}`)
        .join(' ');
const box = (tag) => ({
    setup:
        (_, { attrs, slots }) =>
        () =>
            h(tag, attrs, slots.default?.()),
});
const writes = [];
let form;
const deps = {
    ...timeline,
    scheduleRosterRows,
    validAssignmentHours,
    resizedAssignment,
    translatedAssignment,
    draftRoster,
    labelTokens,
    fallbackLabelToken,
    trans: text,
    Table: box('table'),
    TableHeader: box('thead'),
    TableBody: box('tbody'),
    TableRow: box('tr'),
    TableHead: box('th'),
    TableCell: box('td'),
    CardTitle: box('h2'),
    Badge: box('span'),
    Button: box('button'),
    Icon: box('i'),
    Avatar: { props: ['name'], setup: (p) => () => h('span', p.name) },
    IconButton: {
        props: ['label', 'disabled'],
        setup:
            (p, { attrs }) =>
            () =>
                h(
                    'button',
                    { type: 'button', ...attrs, title: p.label, disabled: p.disabled },
                    p.label,
                ),
    },
    Dialog: {
        props: ['confirmDisabled', 'busy', 'open'],
        setup:
            (p, { slots, emit }) =>
            () =>
                p.open
                    ? h('div', [
                          slots.default?.(),
                          h(
                              'button',
                              { id: 'cancel', onClick: () => emit('cancel') },
                              'Cancel',
                          ),
                          h(
                              'button',
                              {
                                  id: 'save',
                                  disabled: p.confirmDisabled || p.busy,
                                  onClick: () => emit('confirm'),
                              },
                              'Save',
                          ),
                      ])
                    : null,
    },
    Checkbox: {
        props: ['modelValue'],
        setup:
            (p, { emit }) =>
            () =>
                h('input', {
                    type: 'checkbox',
                    checked: p.modelValue,
                    onChange: (e) =>
                        emit('update:modelValue', e.target.checked),
                }),
    },
    Input: {
        props: ['modelValue'],
        setup:
            (p, { attrs, emit }) =>
            () =>
                h('input', {
                    ...attrs,
                    value: p.modelValue,
                    onInput: (e) => emit('update:modelValue', e.target.value),
                }),
    },
    FormField: {
        props: ['error'],
        setup:
            (p, { slots }) =>
            () =>
                h('label', [
                    slots.default?.({ id: 'field', invalid: !!p.error }),
                    p.error,
                ]),
    },
    ShiftOverlapWarnings: {
        props: ['overlaps'],
        setup: (p) => () =>
            h(
                'div',
                p.overlaps.map((o) =>
                    text('overlap', {
                        name: o.shift_name,
                        minutes: o.overlap_minutes,
                    }),
                ),
            ),
    },
    useFlashToast: () => ({ showFormError: () => {} }),
    useForm: (data) => {
        form = reactive({ ...data, errors: {}, processing: false });
        let transform = (data) => data;
        const keys = Object.keys(data);
        let defaults = JSON.stringify(data);
        Object.defineProperty(form, 'isDirty', {
            get: () =>
                JSON.stringify(
                    Object.fromEntries(keys.map((key) => [key, form[key]])),
                ) !== defaults,
        });
        form.defaults = () => {
            defaults = JSON.stringify(
                Object.fromEntries(keys.map((key) => [key, form[key]])),
            );
        };
        form.clearErrors = () => {
            form.errors = {};
        };
        form.transform = (cb) => {
            transform = cb;
            return form;
        };
        form.put = (url, options) =>
            writes.push({ url, data: transform(form), options });
        return form;
    },
};
globalThis.__shiftTimelineTest = deps;
let compileId = 0;
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
        (statement, names, from) =>
            from.startsWith('file:')
                ? statement
                : `const ${names.startsWith('{') ? names : `{ ${names} }`} = globalThis.__shiftTimelineTest;`,
    );
    return (
        await import(
            `data:text/javascript;base64,${Buffer.from(source).toString('base64')}#${++compileId}`
        )
    ).default;
}
const Roster = await compile('ShiftTimelineRoster');
const Hours = await compile('ShiftAssignmentHoursDialog');
function mount(component, props) {
    const app = createApp(component, props);
    app.config.globalProperties.$t = text;
    app.mount('#app');
    return app;
}
const shift = {
    id: 7,
    name: 'Night',
    location: 'Gate',
    color: 'teal',
    starts_at: '2026-10-01T21:00',
    ends_at: '2026-10-02T02:00',
    filled_count: 1,
    total_needs: 2,
    extra_count: 1,
    slots: [
        {
            id: 3,
            role_id: 4,
            role_name: 'Crew',
            needed: 2,
            assigned_count: 1,
            open_count: 1,
        },
    ],
    assignments: [
        {
            id: 8,
            name: 'Person with very long name',
            role_id: 4,
            role_name: 'Crew',
            shift_role_slot_id: 3,
            starts_at: '2026-10-01T23:30',
            ends_at: '2026-10-02T00:00',
            is_extra: false,
            overlaps: [
                {
                    shift_id: 9,
                    shift_name: 'Other',
                    starts_at: '2026-10-01T23:45',
                    ends_at: '2026-10-02T01:00',
                    overlap_minutes: 15,
                },
            ],
        },
        {
            id: 10,
            name: 'Extra',
            role_id: 4,
            role_name: 'Crew',
            shift_role_slot_id: null,
            starts_at: '2026-10-01T21:00',
            ends_at: '2026-10-02T02:00',
            is_extra: true,
            overlaps: [],
        },
    ],
};

test('geometry continues through midnight, clips intervals, merges conflict segments and preserves fractional bounds', () => {
    assert.deepEqual(
        timeline.timelineIntersection(shift.assignments[0], shift),
        { start: 150, end: 180 },
    );
    assert.equal(
        timeline.timelineIntersection(
            { starts_at: '2026-10-02T02:00', ends_at: '2026-10-02T03:00' },
            shift,
        ),
        null,
    );
    assert.deepEqual(
        timeline.shiftOverlapIntervals(shift.assignments[0], shift),
        [{ start: 165, end: 180 }],
    );
    assert.deepEqual(
        timeline.shiftOverlapIntervals(
            {
                ...shift.assignments[1],
                overlaps: [
                    {
                        starts_at: '2026-10-01T23:00',
                        ends_at: '2026-10-02T01:00',
                    },
                    {
                        starts_at: '2026-10-02T00:00',
                        ends_at: '2026-10-02T01:30',
                    },
                ],
            },
            shift,
        ),
        [{ start: 120, end: 270 }],
    );
    assert.ok(
        timeline
            .shiftTimelineTicks(shift)
            .find((t) => t.label === '00:00' && t.date === '2026-10-02'),
    );
    const fractional = timeline.shiftTimelineTicks({
        ...shift,
        starts_at: '2026-10-01T21:15',
        ends_at: '2026-10-01T23:10',
    });
    assert.equal(fractional[0].label, '21:15');
    assert.equal(fractional.at(-1).label, '23:10');
    assert.equal(fractional.at(-1).position, 100);
});

test('complete roster shows exact bars, open rows, Extras and hours; writable controls emit the correct IDs', () => {
    const events = [];
    const app = mount(Roster, {
        shift,
        enabled: true,
        canManage: true,
        onAssign: (s) => events.push(s.id),
        onEdit: (a) => events.push(a.id),
        onRemove: (a) => events.push(-a.id),
    });
    try {
        assert.equal(document.querySelectorAll('[data-roster-row]').length, 3);
        const first = document.querySelector('[data-roster-row="person-8"]');
        assert.equal(
            first
                .querySelector('[data-person-bar]')
                .style.getPropertyValue('--bar-start'),
            '50%',
        );
        assert.equal(
            first
                .querySelector('[data-person-bar]')
                .style.getPropertyValue('--bar-width'),
            `${(30 / 360) * 100}%`,
        );
        assert.ok(
            Math.abs(
                parseFloat(
                    first
                        .querySelector('[data-overlap-hatch]')
                        .style.getPropertyValue('--bar-start'),
                ) -
                    (195 / 360) * 100,
            ) < 1e-8,
        );
        assert.equal(first.querySelector('[data-short-label]'), null);
        assert.match(first.textContent, /23:30–2026-10-02 00:00/);
        assert.match(first.textContent, /name=Other minutes=15/);
        const tooltip = first.querySelector('[role="tooltip"]');
        assert.match(tooltip.textContent, /name=Other minutes=15.*23:45–2026-10-02 01:00/);
        assert.equal(tooltip.parentElement.getAttribute('tabindex'), '0');
        assert.equal(tooltip.parentElement.getAttribute('aria-describedby'), tooltip.id);
        for (const cell of document.querySelectorAll('[data-roster-row] td:first-child')) {
            const labels = cell.cloneNode(true);
            labels.querySelector('[role="tooltip"]')?.remove();
            assert.doesNotMatch(labels.textContent, /assignments.full_shift|\d{2}:\d{2}/);
        }
        document.querySelector('[data-open-assign]').click();
        first.querySelector('button[title*="edit_hours"]').click();
        first.querySelector('button[title*="remove_person"]').click();
        assert.deepEqual(events, [3, 8, -8]);
    } finally {
        app.unmount();
    }
});

test('shift names appear only with a visible other shift and enough room beside the duration', () => {
    const other = { shift_id: 21, shift_name: 'Before', color: 'danger', starts_at: shift.starts_at, ends_at: shift.ends_at };
    for (const scenario of [
        { others: [], starts_at: shift.starts_at, ends_at: shift.ends_at, visible: false },
        { others: [{ ...other, starts_at: '2026-10-02T04:00', ends_at: '2026-10-02T05:00' }], starts_at: shift.starts_at, ends_at: shift.ends_at, visible: false },
        { others: [other], starts_at: shift.starts_at, ends_at: shift.ends_at, visible: true },
        { others: [other], starts_at: '2026-10-01T23:30', ends_at: '2026-10-02T00:00', visible: false },
    ]) {
        const app = mount(Roster, {
            shift: { ...shift, assignments: [{ ...shift.assignments[0], starts_at: scenario.starts_at, ends_at: scenario.ends_at, overlaps: [], other_shifts: scenario.others }] },
        });
        try {
            const bar = document.querySelector('[data-person-bar]');
            assert.equal(bar.textContent.includes(shift.name), scenario.visible);
            assert.ok(bar.querySelector('[data-assignment-duration]').textContent);
            assert.equal(document.querySelector('[data-short-label]'), null);
            if (scenario.others.length && scenario.others[0] === other) {
                assert.match(document.querySelector('[data-other-shift]').textContent, /Before/);
            }
        } finally { app.unmount(); }
    }
});

test('other shifts render their full clipped span and color, hiding narrow names without any controls', () => {
    const events = [];
    const app = mount(Roster, {
        shift: { ...shift, assignments: [{ ...shift.assignments[0], overlaps: [], other_shifts: [
            { shift_id: 21, shift_name: 'Before', color: 'danger', starts_at: '2026-10-01T20:00', ends_at: '2026-10-01T21:15' },
            { shift_id: 22, shift_name: 'Later', color: 'violet', starts_at: '2026-10-02T00:00', ends_at: '2026-10-02T01:00' },
            { shift_id: 23, shift_name: 'Outside', color: 'teal', starts_at: '2026-10-02T03:00', ends_at: '2026-10-02T04:00' },
        ] }] },
        enabled: true, canManage: true, onResize: event => events.push(event),
    });
    try {
        const bars = document.querySelectorAll('[data-other-shift]');
        assert.equal(bars.length, 2);
        assert.doesNotMatch(bars[0].textContent, /Before/);
        assert.match(bars[0].title, /Before/);
        assert.match(bars[0].textContent, /duration_both hours=1 minutes=15/);
        assert.match(bars[0].className, /bg-danger/);
        assert.equal(bars[0].style.getPropertyValue('--bar-start'), '0%');
        assert.equal(bars[0].style.getPropertyValue('--bar-width'), '12.5%');
        assert.doesNotMatch(bars[1].textContent, /Later/);
        assert.match(bars[1].title, /Later/);
        assert.match(bars[1].textContent, /duration_hours hours=1/);
        assert.match(bars[1].className, /bg-label-violet/);
        for (const bar of bars) {
            assert.equal(bar.querySelector('button, [role="slider"]'), null);
            bar.click();
            bar.dispatchEvent(new dom.window.MouseEvent('pointerdown', { clientX: 100, button: 0, bubbles: true }));
        }
        window.dispatchEvent(new dom.window.MouseEvent('pointerup', { clientX: 145 }));
        assert.deepEqual(events, []);
        assert.equal(document.querySelectorAll('[data-move-handle]').length, 1);
    } finally { app.unmount(); }
});

test('view-only and locked roster omits Actions and explains disabled Assign; dirty controls do not emit', () => {
    for (const canManage of [false, true]) {
        const events = [];
        const app = mount(Roster, {
            shift,
            canManage,
            enabled: false,
            disabledReason: 'Save or read only',
            onAssign: () => events.push(1),
            onEdit: () => events.push(2),
        });
        try {
            assert.equal(
                !!document.querySelector('[data-roster-actions]'),
                canManage,
            );
            const button = document.querySelector('[data-open-assign]');
            if (canManage) {
                assert.ok(button.disabled);
                assert.equal(button.parentElement.title, 'Save or read only');
            } else {
                assert.equal(button, null);
            }
            for (const b of document.querySelectorAll('button')) b.click();
            for (const handle of document.querySelectorAll('[data-move-handle]')) {
                assert.equal(handle.disabled, true);
                handle.dispatchEvent(new dom.window.KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
                handle.dispatchEvent(new dom.window.MouseEvent('pointerdown', { clientX: 100, button: 0, bubbles: true }));
            }
            window.dispatchEvent(new dom.window.MouseEvent('pointerup', { clientX: 125 }));
            assert.deepEqual(events, []);
        } finally {
            app.unmount();
        }
    }
    const app = mount(Roster, {
        shift: {
            ...shift,
            slots: [],
            assignments: [],
            filled_count: 0,
            total_needs: 0,
            extra_count: 0,
        },
    });
    try {
        assert.equal(document.querySelector('[data-roster-summary]'), null);
        assert.match(document.body.textContent, /empty_shift/);
        assert.doesNotMatch(document.body.textContent, /legend_hours/);
    } finally {
        app.unmount();
    }
});
const settle = () => new Promise((resolve) => setTimeout(resolve, 280));
test('Edit hours updates previews without stale responses and saves hours only; Cancel never writes', async () => {
    const requests = [];
    globalThis.fetch = (url, options) =>
        new Promise((resolve) => requests.push({ url, options, resolve }));
    writes.length = 0;
    const events = [];
    const app = mount(Hours, {
        shift,
        assignment: shift.assignments[0],
        eventId: 2,
        enabled: true,
        returnContext: { return_tab: 'schedule' },
        onClose: () => events.push('close'),
        onBusy: (busy) => events.push(busy),
    });
    try {
        assert.equal(form.hours_mode, 'custom');
        form.starts_at = '2026-10-01T22:00';
        await nextTick();
        await settle();
        assert.equal(requests.length, 1);
        assert.match(document.body.textContent, /preview_loading/);
        form.ends_at = '2026-10-02T01:00';
        await nextTick();
        await settle();
        assert.equal(requests[0].options.signal.aborted, true);
        requests[1].resolve({
            ok: true,
            json: async () => ({
                data: [{ shift_name: 'New overlap', overlap_minutes: 60 }],
            }),
        });
        await nextTick();
        await new Promise((r) => setTimeout(r, 0));
        requests[0].resolve({
            ok: true,
            json: async () => ({
                data: [{ shift_name: 'Stale', overlap_minutes: 99 }],
            }),
        });
        await new Promise((r) => setTimeout(r, 0));
        assert.match(document.body.textContent, /New overlap/);
        assert.doesNotMatch(document.body.textContent, /Stale/);
        document.querySelector('#save').click();
        assert.equal(writes.length, 1);
        assert.deepEqual(writes[0].data, {
            return_tab: 'schedule',
            hours_mode: 'custom',
            starts_at: '2026-10-01T22:00',
            ends_at: '2026-10-02T01:00',
        });
        form.errors.ends_at = 'Server rejected these hours';
        await nextTick();
        assert.match(document.body.textContent, /Server rejected/);
        writes[0].options.onSuccess();
        writes[0].options.onFinish();
        assert.deepEqual(events, [true, 'close', false]);
        document.querySelector('#cancel').click();
        assert.equal(writes.length, 1);
    } finally {
        app.unmount();
    }
});

test('full-shift Edit hours omits timestamps; preview failure permits Save but invalid hours do not', async () => {
    globalThis.fetch = async () => ({ ok: false });
    writes.length = 0;
    const app = mount(Hours, {
        shift,
        assignment: shift.assignments[1],
        eventId: 2,
        enabled: true,
    });
    try {
        assert.equal(form.hours_mode, 'full_shift');
        document.querySelector('#save').click();
        assert.deepEqual(writes[0].data, { hours_mode: 'full_shift' });
        form.hours_mode = 'custom';
        form.starts_at = '2026-10-01T22:00';
        await nextTick();
        await settle();
        await nextTick();
        assert.match(document.body.textContent, /preview_failed/);
        assert.equal(document.querySelector('#save').disabled, false);
        form.ends_at = '2026-10-03T01:00';
        await nextTick();
        assert.equal(document.querySelector('#save').disabled, true);
    } finally {
        app.unmount();
    }
});

test('shift page stages removal without a popup or DELETE and writes roster changes only on page Save', async () => {
    const deletions = [];
    writes.length = 0;
    Object.assign(deps, {
        ...breakHelpers,
        ...slotHelpers,
        ColorPicker: box('div'),
        ShiftRoleSlots: box('div'),
        ShiftBreaks: {
            setup: (_, { expose }) => {
                expose({ validate: () => true });
                return () => h('div');
            },
        },
        Card: box('div'),
        CustomDropdown: box('div'),
        AppLayout: box('main'),
        ShiftTimelineRoster: Roster,
        ShiftAssignmentHoursDialog: Hours,
        ShiftAssignDialog: box('div'),
        fieldError: (form, key) => form.errors[key],
        toastFormErrors: () => {},
        router: { delete: (url, options) => deletions.push({ url, options }) },
    });
    const Page = await compile('Shift', 'pages/Team');
    const app = mount(Page, {
        shift: { ...shift, breaks: [], assignment_count: 2 },
        event: { id: 2, is_locked: false },
        canManage: true,
        roles: [],
        locations: [],
        labelColors: [],
        breakOptions: { lengths: [] },
    });
    try {
        assert.equal(document.querySelector('[data-save-reminder]'), null);
        const first = document.querySelector('[data-roster-row="person-8"]');
        globalThis.fetch = async () => ({ ok: true, json: async () => ({ data: [] }) });
        first.querySelector('[role="slider"]:not([data-move-handle])').dispatchEvent(
            new dom.window.KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }),
        );
        await nextTick();
        assert.equal(writes.length, 0);
        assert.equal(form.assignment_updates[0].starts_at, '2026-10-01T23:45');
        assert.ok(document.querySelector('[data-save-reminder]'));
        first.querySelector('[data-move-handle]').dispatchEvent(
            new dom.window.KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true }),
        );
        await nextTick();
        assert.equal(writes.length, 0);
        assert.equal(form.assignment_updates[0].starts_at, '2026-10-01T23:30');
        assert.equal(form.assignment_updates[0].ends_at, '2026-10-01T23:45');
        first.querySelector('button[title*="remove_person"]').click();
        await nextTick();
        assert.equal(deletions.length, 0);
        assert.equal(writes.length, 0);
        assert.equal(document.querySelector('#save'), null);
        assert.equal(
            document.querySelector('[data-roster-row="person-8"]'),
            null,
        );
        assert.deepEqual([...form.assignment_removals], [8]);
        assert.equal(form.assignment_updates.length, 0);
        assert.ok(document.querySelector('[data-save-reminder]'));
        assert.equal(document.querySelectorAll('[data-open-bar]').length, 2);
        document.querySelector('#shift-details-form').dispatchEvent(
            new dom.window.Event('submit', {
                bubbles: true,
                cancelable: true,
            }),
        );
        assert.equal(writes.length, 1);
        assert.deepEqual([...writes[0].data.assignment_removals], [8]);
        assert.match(writes[0].url, /shifts\/7$/);
        form.errors['assignment_removals.0'] = 'Roster changed';
        writes[0].options.onError(form.errors);
        await nextTick();
        assert.ok(document.querySelector('[data-save-reminder]'));
        assert.deepEqual([...form.assignment_removals], [8]);
        writes[0].options.onSuccess();
        await nextTick();
        assert.equal(document.querySelector('[data-save-reminder]'), null);
    } finally {
        app.unmount();
    }
});

test('resize snaps to clock quarter-hours, clamps to shift bounds, and crosses midnight without timezone conversion', () => {
    const person = shift.assignments[0];
    assert.deepEqual(
        resizedAssignment(
            shift,
            person,
            'start',
            timeline.timelineMinute('2026-10-01T23:38'),
        ),
        { starts_at: '2026-10-01T23:45', ends_at: person.ends_at },
    );
    assert.equal(
        resizedAssignment(
            shift,
            person,
            'end',
            timeline.timelineMinute('2026-10-02T01:53'),
        ).ends_at,
        shift.ends_at,
    );
    assert.equal(
        resizedAssignment(
            shift,
            person,
            'start',
            timeline.timelineMinute('2026-10-01T20:00'),
        ).starts_at,
        shift.starts_at,
    );
    assert.equal(
        resizedAssignment(
            shift,
            person,
            'end',
            timeline.timelineMinute('2026-10-01T21:00'),
        ).ends_at,
        '2026-10-01T23:45',
    );
    assert.equal(timeline.shiftTimelineGrid(shift).length, 11);
});

test('pointer resize previews locally, emits on release, supports keyboard steps and Escape cancellation', async () => {
    const events = [];
    const app = mount(Roster, {
        shift,
        enabled: true,
        canManage: true,
        onResize: (event) => events.push(event),
    });
    try {
        const row = document.querySelector('[data-roster-row="person-8"]');
        const canvas = row.querySelector('[data-person-bar]').parentElement;
        canvas.getBoundingClientRect = () => ({ width: 600 });
        const handles = row.querySelectorAll('[role="slider"]:not([data-move-handle])');
        handles[0].dispatchEvent(
            new dom.window.MouseEvent('pointerdown', {
                clientX: 100,
                button: 0,
                bubbles: true,
            }),
        );
        window.dispatchEvent(
            new dom.window.MouseEvent('pointermove', { clientX: 145 }),
        );
        await nextTick();
        assert.equal(events.length, 0);
        assert.match(row.textContent, /23:45/);
        window.dispatchEvent(
            new dom.window.MouseEvent('pointerup', { clientX: 145 }),
        );
        await nextTick();
        assert.equal(events.length, 1);
        assert.equal(events[0].hours.starts_at, '2026-10-01T23:45');
        handles[1].dispatchEvent(
            new dom.window.KeyboardEvent('keydown', {
                key: 'ArrowRight',
                bubbles: true,
            }),
        );
        assert.equal(events.length, 2);
        assert.equal(events[1].hours.ends_at, '2026-10-02T00:15');
        handles[0].dispatchEvent(
            new dom.window.MouseEvent('pointerdown', {
                clientX: 100,
                button: 0,
                bubbles: true,
            }),
        );
        window.dispatchEvent(
            new dom.window.MouseEvent('pointermove', { clientX: 145 }),
        );
        window.dispatchEvent(
            new dom.window.KeyboardEvent('keydown', { key: 'Escape' }),
        );
        await nextTick();
        assert.equal(events.length, 2);
        assert.match(row.textContent, /23:30/);
    } finally {
        app.unmount();
    }
});

test('moving an assignment preserves duration, snaps its start, clamps both boundaries and crosses midnight', () => {
    const person = shift.assignments[0];
    const move = (minute) => translatedAssignment(shift, person, timeline.timelineMinute(minute));
    assert.deepEqual(move('2026-10-02T00:08'), { starts_at: '2026-10-02T00:15', ends_at: '2026-10-02T00:45' });
    assert.deepEqual(move('2026-10-01T23:53'), { starts_at: '2026-10-02T00:00', ends_at: '2026-10-02T00:30' });
    assert.deepEqual(move('2026-10-01T20:00'), { starts_at: shift.starts_at, ends_at: '2026-10-01T21:30' });
    assert.deepEqual(move('2026-10-02T03:00'), { starts_at: '2026-10-02T01:30', ends_at: shift.ends_at });
    assert.deepEqual(translatedAssignment(shift, shift, timeline.timelineMinute('2026-10-02T00:00')), { starts_at: shift.starts_at, ends_at: shift.ends_at });
    const offGrid = { starts_at: '2026-10-01T23:32', ends_at: '2026-10-02T00:09' };
    assert.deepEqual(translatedAssignment(shift, offGrid, timeline.timelineMinute('2026-10-02T01:57')), { starts_at: '2026-10-02T01:15', ends_at: '2026-10-02T01:52' });
});

test('middle drag previews both times, commits only on release, supports keyboard movement and cancels without writes', async () => {
    const events = [];
    const app = mount(Roster, { shift, enabled: true, canManage: true, onResize: event => events.push(event) });
    try {
        const row = document.querySelector('[data-roster-row="person-8"]');
        row.querySelector('[data-person-bar]').parentElement.getBoundingClientRect = () => ({ width: 600 });
        const handle = row.querySelector('[data-move-handle]');
        const begin = () => handle.dispatchEvent(new dom.window.MouseEvent('pointerdown', { clientX: 100, button: 0, bubbles: true }));
        begin();
        window.dispatchEvent(new dom.window.MouseEvent('pointermove', { clientX: 125 }));
        await nextTick();
        assert.equal(events.length, 0);
        assert.match(row.querySelector('[data-person-bar]').title, /23:45–2026-10-02 00:15/);
        assert.match(row.querySelector('[data-assignment-duration]').textContent, /minutes=30/);
        window.dispatchEvent(new dom.window.MouseEvent('pointerup', { clientX: 125 }));
        assert.deepEqual(events[0].hours, { starts_at: '2026-10-01T23:45', ends_at: '2026-10-02T00:15' });
        await nextTick();
        handle.dispatchEvent(new dom.window.KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true }));
        assert.deepEqual(events[1].hours, { starts_at: '2026-10-01T23:15', ends_at: '2026-10-01T23:45' });
        begin();
        window.dispatchEvent(new dom.window.MouseEvent('pointermove', { clientX: 125 }));
        window.dispatchEvent(new dom.window.KeyboardEvent('keydown', { key: 'Escape' }));
        await nextTick();
        assert.equal(events.length, 2);
        assert.match(row.querySelector('[data-person-bar]').title, /23:30–2026-10-02 00:00/);
        begin();
        window.dispatchEvent(new dom.window.MouseEvent('pointercancel'));
        window.dispatchEvent(new dom.window.MouseEvent('pointerup', { clientX: 125 }));
        begin();
        window.dispatchEvent(new dom.window.MouseEvent('pointerup', { clientX: 100 }));
        document.querySelector('[data-roster-row="person-10"] [data-move-handle]').dispatchEvent(new dom.window.KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
        assert.equal(events.length, 2);
    } finally { app.unmount(); }
});

test('deferred hours dialog emits a draft without issuing a PUT', async () => {
    writes.length = 0;
    const changes = [];
    const app = mount(Hours, {
        shift,
        assignment: shift.assignments[0],
        eventId: 2,
        enabled: true,
        deferred: true,
        onChanged: (change) => changes.push(change),
    });
    try {
        document.querySelector('#save').click();
        assert.equal(writes.length, 0);
        assert.equal(changes.length, 1);
        assert.equal(changes[0].hours_mode, 'custom');
    } finally {
        app.unmount();
    }
});


test('color and Headcount edits keep the roster active; draft roles assign before Save and quantities respect pending people', async () => {
    writes.length = 0;
    Object.assign(deps, {
        ...slotHelpers,
        CustomDropdown: {
            props: ['items', 'modelValue', 'disabled'],
            setup: (p, { emit }) => () => h('select', {
                disabled: p.disabled, value: p.modelValue,
                onChange: event => emit('update:modelValue', Number(event.target.value)),
            }, [h('option', { value: '' }, ''), ...p.items.map(item => h('option', { value: item.value }, item.title))]),
        },
    });
    deps.QuantityInput = await compile('QuantityInput', 'components/ui/quantity-input');
    deps.ShiftRoleSlots = await compile('ShiftRoleSlots');
    deps.ShiftAssignDialog = {
        props: ['requirement', 'shift'],
        setup: (p, { emit }) => () => h('button', {
            id: 'draft-confirm',
            onClick: () => {
                emit('assigned', {
                    candidate: { id: 20, name: 'Draft person', overlaps: [] },
                    slot: p.requirement,
                    slot_key: p.requirement.id,
                    team_engagement_id: 20,
                    hours_mode: 'full_shift',
                });
                emit('close');
            },
        }, 'Assign draft'),
    };
    const Page = await compile('Shift', 'pages/Team');
    const app = mount(Page, {
        shift: { ...shift, breaks: [], assignment_count: 2 },
        event: { id: 2, is_locked: false }, canManage: true,
        roles: [{ id: 4, name: 'Crew' }, { id: 5, name: 'Sound' }],
        locations: [], labelColors: [], breakOptions: { lengths: [] },
    });
    try {
        form.color = 'warning';
        await nextTick();
        assert.equal(document.querySelector('[data-open-assign]').disabled, false);
        const picker = document.querySelector('select:has(option[value="5"])');
        picker.value = '5';
        picker.dispatchEvent(new dom.window.Event('change', { bubbles: true }));
        const pendingQty = picker.parentElement.parentElement.querySelector('input[type="number"]');
        pendingQty.value = '2';
        pendingQty.dispatchEvent(new dom.window.Event('input', { bubbles: true }));
        await nextTick();
        [...document.querySelectorAll('button')].find(button => button.textContent.trim().startsWith('team.scheduling.slots.add')).click();
        await nextTick();
        const slot = form.slots.at(-1);
        assert.equal(slot.role_id, 5);
        assert.equal(slot.needed, 2);
        const open = document.querySelector(`[data-roster-row="open-${slot._key}-0"] [data-open-assign]`);
        assert.equal(open.disabled, false);
        assert.equal(writes.length, 0);
        open.click();
        await nextTick();
        document.querySelector('#draft-confirm').click();
        await nextTick();
        assert.equal(form.assignment_additions[0].slot_key, slot._key);
        assert.match(document.body.textContent, /Draft person/);
        const controls = document.querySelector(`[data-headcount-row="${slot._key}"]`);
        const minus = controls.querySelector('button[title^="ui.quantity.decrease"]');
        minus.click();
        await nextTick();
        assert.equal(form.slots.at(-1).needed, 1);
        const removeRole = controls.querySelector('button[title="team.scheduling.slots.remove role=Sound"]');
        assert.equal(removeRole.disabled, true);
        const tooltip = controls.querySelector('[role="tooltip"]');
        assert.match(tooltip.textContent, /slots.assigned_slot_tooltip/);
        assert.equal(removeRole.parentElement.getAttribute('aria-describedby'), tooltip.id);
        assert.equal(controls.querySelectorAll('button').length, 2);
        const qty = controls.querySelector('input[type="number"]');
        qty.value = '0';
        qty.dispatchEvent(new dom.window.Event('input', { bubbles: true }));
        await nextTick();
        assert.equal(Number(form.slots.at(-1).needed), 1);
        assert.equal(qty.value, '1');
        controls.querySelector('button[title^="ui.quantity.increase"]').click();
        await nextTick();
        assert.equal(form.slots.at(-1).needed, 2);
        document.querySelector('#shift-details-form').dispatchEvent(new dom.window.Event('submit', { bubbles: true, cancelable: true }));
        assert.equal(writes.length, 1);
        assert.equal(writes[0].data.slots.at(-1).client_key, slot._key);
        assert.equal('_key' in writes[0].data.slots.at(-1), false);
        assert.equal(writes[0].data.assignment_additions[0].slot_key, slot._key);
        const draftRow = [...document.querySelectorAll('[data-roster-row]')].find(row => row.textContent.includes('Draft person'));
        draftRow.querySelector('button[title*="remove_person"]').click();
        await nextTick();
        const decrease = controls.querySelector('button[title^="ui.quantity.decrease"]');
        assert.equal(decrease.disabled, false);
        decrease.click();
        await nextTick();
        const removeEmpty = controls.querySelector('button[title="team.scheduling.slots.remove role=Sound"]');
        assert.equal(removeEmpty.disabled, false);
        removeEmpty.click();
        await nextTick();
        assert.equal(form.slots.length, 1);
        assert.equal(form.assignment_additions.length, 0);
        assert.equal(document.querySelector(`[data-roster-row="open-${slot._key}-0"]`), null);
    } finally { app.unmount(); }
});
