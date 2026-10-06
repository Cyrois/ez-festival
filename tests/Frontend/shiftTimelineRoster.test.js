import * as personalBreakHelpers from '../../resources/js/lib/personalBreaks.js';
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
import { cn } from '../../resources/js/lib/utils.js';
import {
    scheduleRosterRows,
    validAssignmentHours,
    resizedAssignment,
    translatedAssignment,
    draftRoster,
    assignmentCandidatesUrl,
    assignmentOverlapUrl,
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
window.performance.getEntriesByType = () => [];
const { router: navigationRouter } = await import('@inertiajs/vue3');
const { useUnsavedNavigation } = await import('../../resources/js/composables/useUnsavedNavigation.js');
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
    ...personalBreakHelpers,
    ...breakHelpers,
    cn,
    useUnsavedNavigation,
    ...timeline,
    scheduleRosterRows,
    validAssignmentHours,
    resizedAssignment,
    translatedAssignment,
    draftRoster,
    assignmentCandidatesUrl,
    assignmentOverlapUrl,
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
        form.clearErrors = (...keys) => {
            if (!keys.length) form.errors = {};
            else for (const key of keys) delete form.errors[key];
        };
        form.transform = (cb) => {
            transform = cb;
            return form;
        };
        form.put = (url, options) =>
            writes.push({ url, data: transform(form), options, method: 'put' });
        form.post = (url, options) =>
            writes.push({ url, data: transform(form), options, method: 'post' });
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
deps.CustomDropdown = {props: ['modelValue', 'items'], setup: (p, {emit}) => () => h('select', {value: p.modelValue, onChange: (e) => emit('update:modelValue', e.target.value)}, p.items.map((row) => h('option', {value: row.value}, row.title)))};
deps.ShiftBreaks = await compile('ShiftBreaks');
const Hours = await compile('ShiftAssignmentHoursDialog');
deps.UnsavedChangesDialog = await compile('UnsavedChangesDialog', 'components/ui/unsaved-changes-dialog');
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
test('Edit hours updates previews without stale responses and stages hours only; Cancel never writes', async () => {
    const requests = [];
    globalThis.fetch = (url, options) =>
        new Promise((resolve) => requests.push({ url, options, resolve }));
    writes.length = 0;
    const events = [];
    const app = mount(Hours, {
        shift,
        assignment: shift.assignments[0],
        enabled: true,
        onClose: () => events.push('close'),
        onChanged: (change) => events.push(change),
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
        assert.equal(writes.length, 0);
        assert.deepEqual(events[0], {
            hours_mode: 'custom',
            starts_at: '2026-10-01T22:00',
            ends_at: '2026-10-02T01:00',
            breaks: [],
            overlaps: [{ shift_name: 'New overlap', overlap_minutes: 60 }],
            other_shifts: [],
        });
        form.errors.ends_at = 'Server rejected these hours';
        await nextTick();
        assert.match(document.body.textContent, /Server rejected/);
        assert.equal(events[1], 'close');
        document.querySelector('#cancel').click();
        assert.equal(writes.length, 0);
    } finally {
        app.unmount();
    }
});

test('full-shift Edit hours omits timestamps; preview failure permits Save but invalid hours do not', async () => {
    globalThis.fetch = async () => ({ ok: false });
    writes.length = 0;
    const changes = [];
    const app = mount(Hours, {
        shift,
        assignment: shift.assignments[1],
        onChanged: change => changes.push(change),
        enabled: true,
    });
    try {
        assert.equal(form.hours_mode, 'full_shift');
        document.querySelector('#save').click();
        assert.equal(writes.length, 0);
        assert.deepEqual(changes[0], { hours_mode: 'full_shift', breaks: [], overlaps: [], other_shifts: [] });
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
    const Page = await compile('ShiftEditor');
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
    assert.deepEqual(move('2026-10-02T00:08'), { starts_at: '2026-10-02T00:15', ends_at: '2026-10-02T00:45', breaks: [] });
    assert.deepEqual(move('2026-10-01T23:53'), { starts_at: '2026-10-02T00:00', ends_at: '2026-10-02T00:30', breaks: [] });
    assert.deepEqual(move('2026-10-01T20:00'), { starts_at: shift.starts_at, ends_at: '2026-10-01T21:30', breaks: [] });
    assert.deepEqual(move('2026-10-02T03:00'), { starts_at: '2026-10-02T01:30', ends_at: shift.ends_at, breaks: [] });
    assert.deepEqual(translatedAssignment(shift, shift, timeline.timelineMinute('2026-10-02T00:00')), { starts_at: shift.starts_at, ends_at: shift.ends_at, breaks: [] });
    const offGrid = { starts_at: '2026-10-01T23:32', ends_at: '2026-10-02T00:09' };
    assert.deepEqual(translatedAssignment(shift, offGrid, timeline.timelineMinute('2026-10-02T01:57')), { starts_at: '2026-10-02T01:15', ends_at: '2026-10-02T01:52', breaks: [] });
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
        assert.deepEqual(events[0].hours, { starts_at: '2026-10-01T23:45', ends_at: '2026-10-02T00:15', breaks: [] });
        await nextTick();
        handle.dispatchEvent(new dom.window.KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true }));
        assert.deepEqual(events[1].hours, { starts_at: '2026-10-01T23:15', ends_at: '2026-10-01T23:45', breaks: [] });
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

test('hours dialog always emits a draft without issuing a PUT', async () => {
    writes.length = 0;
    const changes = [];
    const app = mount(Hours, {
        shift,
        assignment: shift.assignments[0],
        eventId: 2,
        enabled: true,
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
    const Page = await compile('ShiftEditor');
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

test('only sidebar and breadcrumb links warn; Continue discards and Save waits for success', async () => {
    const originalVisit = navigationRouter.visit;
    const originalDialog = deps.Dialog;
    const originalWarning = deps.UnsavedChangesDialog;
    const destinations = [];
    navigationRouter.visit = (url) => destinations.push(new URL(url));
    const navigation = document.createElement('div');
    navigation.innerHTML = '<nav data-unsaved-navigation><a href="https://example.com/dashboard"><span>Sidebar</span></a></nav><nav data-unsaved-navigation><a href="https://example.com/team/scheduling?day=2026-10-02">Breadcrumb</a></nav><a href="https://example.com/team/scheduling">Cancel</a>';
    document.body.append(navigation);
    // Simulate the normal link handler without asking JSDOM to load another document.
    navigation.addEventListener('click', (event) => {
        event.allowedByGuard = !event.defaultPrevented;
        event.preventDefault();
    });
    const attempt = (index = 0, options = {}) => {
        const link = navigation.querySelectorAll('a')[index];
        const event = new dom.window.MouseEvent('click', { bubbles: true, cancelable: true, button: 0, ...options });
        (link.querySelector('span') ?? link).dispatchEvent(event);
        return { defaultPrevented: !event.allowedByGuard };
    };
    const unload = () => {
        const event = new dom.window.Event('beforeunload', { cancelable: true });
        window.dispatchEvent(event);
        return event;
    };
    deps.Dialog = await compile('Dialog', 'components/ui/dialog');
    deps.UnsavedChangesDialog = await compile('UnsavedChangesDialog', 'components/ui/unsaved-changes-dialog');
    const Page = await compile('ShiftEditor');
    const app = mount(Page, {
        shift: { ...shift, breaks: [], assignment_count: 2 },
        event: { id: 1, is_locked: false }, canManage: true,
        locations: [], labelColors: [], breakOptions: {},
    });
    const button = (key) => [...document.querySelectorAll('[role="alertdialog"] button')].find((item) => item.textContent.trim() === key);
    writes.length = 0;
    try {
        assert.equal(attempt().defaultPrevented, false);
        form.color = 'danger';
        await nextTick();
        assert.equal(attempt(2).defaultPrevented, false);
        assert.equal(attempt(0, { metaKey: true }).defaultPrevented, false);
        assert.equal(attempt(1, { ctrlKey: true }).defaultPrevented, false);
        assert.equal(unload().defaultPrevented, false);
        window.dispatchEvent(new dom.window.PopStateEvent('popstate'));
        await nextTick();
        assert.equal(document.querySelector('[role="alertdialog"]'), null);
        assert.equal(attempt().defaultPrevented, true);
        await nextTick();
        assert.ok(document.querySelector('[role="alertdialog"]'));
        document.querySelector('[aria-label^="ui.dialog.close"]').click();
        await nextTick();
        assert.equal(document.querySelector('[role="alertdialog"]'), null);
        assert.equal(form.color, 'danger');
        attempt();
        await nextTick();
        button('ui.unsaved_changes.continue').click();
        await nextTick();
        assert.equal(destinations.length, 1);
        assert.equal(destinations[0].pathname, '/dashboard');
        assert.equal(writes.length, 0);
        assert.equal(document.querySelector('[role="alertdialog"]'), null);
        destinations.length = 0;
        assert.equal(attempt(1).defaultPrevented, true);
        await nextTick();
        button('ui.unsaved_changes.save_continue').click();
        assert.equal(writes.length, 1);
        assert.equal(destinations.length, 0);
        form.errors.name = 'Required';
        writes[0].options.onError(form.errors);
        await nextTick();
        assert.ok(document.querySelector('[role="alertdialog"]'));
        assert.equal(destinations.length, 0);
        assert.equal(form.color, 'danger');
        button('ui.unsaved_changes.save_continue').click();
        assert.equal(writes.length, 2);
        writes[1].options.onSuccess();
        await nextTick();
        assert.equal(destinations.length, 1);
        assert.equal(destinations[0].search, '?day=2026-10-02');
        assert.equal(document.querySelector('[role="alertdialog"]'), null);
        assert.equal(attempt().defaultPrevented, false);
        form.name = 'Changed again';
        await nextTick();
        assert.equal(attempt().defaultPrevented, true);
    } finally {
        app.unmount();
        deps.Dialog = originalDialog;
        deps.UnsavedChangesDialog = originalWarning;
        navigationRouter.visit = originalVisit;
    }
    assert.equal(attempt().defaultPrevented, false);
    assert.equal(unload().defaultPrevented, false);
    navigation.remove();
});


test('create page shares the draft timeline, stages hours and removal, and POSTs its roster without delete or save reminder', async () => {
    writes.length = 0;
    const requests = [];
    globalThis.fetch = async (url) => {
        requests.push(new URL(url, 'http://localhost'));
        return { ok: true, json: async () => ({ data: [], other_shifts: [] }) };
    };
    deps.ShiftEditor = await compile('ShiftEditor');
    const Page = await compile('CreateShift', 'pages/Team');
    const app = mount(Page, {
        event: { id: 2, is_locked: false }, canManage: true,
        prefill: { starts_at: shift.starts_at, ends_at: shift.ends_at, location_id: 1 },
        locations: [{ id: 1, name: 'Gate' }], roles: [{ id: 4, name: 'Crew' }], labelColors: [], breakOptions: {},
    });
    const editorForm = form;
    try {
        assert.equal(editorForm.isDirty, false);
        assert.equal([...document.querySelectorAll('button')].some(button => button.textContent.trim() === 'team.scheduling.actions.delete'), false);
        editorForm.name = 'Draft shift';
        editorForm.slots.push({ ...slotHelpers.newShiftSlot(), role_id: 4, role_name: 'Crew' });
        await nextTick();
        const slot = editorForm.slots[0];
        assert.equal(document.querySelector('[data-open-assign]').disabled, false);
        document.querySelector('[data-open-assign]').click();
        await nextTick();
        document.querySelector('#draft-confirm').click();
        await nextTick();
        assert.equal(editorForm.assignment_additions.length, 1);
        assert.equal(editorForm.assignment_additions[0].slot_key, slot._key);
        let person = document.querySelector('[data-roster-row="person--1"]');
        assert.match(person.textContent, /Draft person/);
        assert.equal(document.querySelector('[data-save-reminder]'), null);
        person.querySelector('[role="slider"]:last-of-type').dispatchEvent(new dom.window.KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true }));
        await nextTick();
        await new Promise(resolve => setTimeout(resolve, 0));
        assert.equal(editorForm.assignment_additions[0].hours_mode, 'custom');
        assert.equal(requests[0].pathname, '/team/events/2/shifts/assignment-overlaps');
        assert.equal(requests[0].searchParams.get('team_engagement_id'), '20');
        assert.equal(writes.length, 0);
        person.querySelector('button[title*="remove_person"]').click();
        await nextTick();
        assert.equal(editorForm.assignment_additions.length, 0);
        assert.equal(editorForm.assignment_removals.length, 0);
        assert.equal(writes.length, 0);
        assert.ok(document.querySelector('[data-open-assign]'));
        document.querySelector('[data-open-assign]').click();
        await nextTick();
        document.querySelector('#draft-confirm').click();
        await nextTick();
        document.querySelector('#create-shift-form').dispatchEvent(new dom.window.Event('submit', { bubbles: true, cancelable: true }));
        assert.equal(writes.length, 1);
        assert.equal(writes[0].method, 'post');
        assert.equal(writes[0].url, '/team/events/2/shifts');
        assert.equal(writes[0].data.slots[0].client_key, slot._key);
        assert.equal(writes[0].data.assignment_additions[0].slot_key, slot._key);
        assert.equal('_key' in writes[0].data.assignment_additions[0], false);
        assert.equal(document.querySelector('[data-save-reminder]'), null);
    } finally { app.unmount(); }
});

test('create page handles missing dates and retains the navigation warning until saving succeeds', async () => {
    const originalVisit = navigationRouter.visit;
    const originalDialog = deps.Dialog;
    const destinations = [];
    navigationRouter.visit = url => destinations.push(url);
    deps.Dialog = await compile('Dialog', 'components/ui/dialog');
    deps.UnsavedChangesDialog = await compile('UnsavedChangesDialog', 'components/ui/unsaved-changes-dialog');
    deps.ShiftEditor = await compile('ShiftEditor');
    const Page = await compile('CreateShift', 'pages/Team');
    const navigation = document.createElement('nav');
    navigation.dataset.unsavedNavigation = '';
    navigation.innerHTML = '<a href="https://example.com/dashboard">Dashboard</a>';
    document.body.append(navigation);
    const app = mount(Page, {
        event: { id: 2, is_locked: false }, canManage: true,
        locations: [{ id: 1, name: 'Gate' }], roles: [], labelColors: [], breakOptions: {},
    });
    const attempt = () => navigation.querySelector('a').dispatchEvent(new dom.window.MouseEvent('click', { bubbles: true, cancelable: true, button: 0 }));
    const save = () => [...document.querySelectorAll('[role="alertdialog"] button')].find(button => button.textContent.trim() === 'ui.unsaved_changes.save_continue');
    writes.length = 0;
    try {
        assert.match(document.body.textContent, /roster.enter_hours/);
        form.name = 'Draft';
        await nextTick();
        assert.equal(attempt(), false);
        await nextTick();
        assert.ok(document.querySelector('[role="alertdialog"]'));
        save().click();
        assert.equal(writes[0].method, 'post');
        writes[0].options.onError({ starts_at: 'Required' });
        await nextTick();
        assert.ok(document.querySelector('[role="alertdialog"]'));
        assert.equal(destinations.length, 0);
        form.starts_at = shift.starts_at;
        form.ends_at = shift.ends_at;
        save().click();
        writes[1].options.onSuccess();
        await nextTick();
        assert.equal(destinations.length, 1);
        assert.equal(document.querySelector('[role="alertdialog"]'), null);
        assert.equal(document.querySelector('[data-save-reminder]'), null);
    } finally {
        app.unmount(); navigation.remove();
        navigationRouter.visit = originalVisit;
        deps.Dialog = originalDialog;
    }
});

test('timeline shows only personal break blocks with shift borders across midnight, preserves them read-only and hides empty legend', async () => {
    const source = {id: 91, duration_minutes: 60, starts_at: '2026-10-02T00:00'};
    const props = reactive({shift: {...shift, breaks: [source], assignments: shift.assignments.map((row, i) => ({...row, breaks: i === 0 ? [{duration_minutes: 15, starts_at: row.starts_at}] : []}))}, enabled: false, canManage: false});
    const app = mount(Roster, props);
    try {
        assert.equal(document.querySelector('[data-break-header]'), null);
        assert.equal(document.querySelector('[data-break-marker]'), null);
        const hatch = document.querySelector('[data-person-break-hatch]');
        assert.ok(hatch.classList.contains('border-y'));
        assert.ok(hatch.classList.contains('border-label-teal/20'));
        props.shift.color = 'warning';
        await nextTick();
        assert.ok(hatch.classList.contains('border-warning/20'));
        assert.equal(document.querySelector('[data-break-summary]'), null);
        assert.ok(document.querySelector('[data-person-break-hatch] i'));
        assert.equal(document.querySelectorAll('tbody tr').length, document.querySelectorAll('[data-roster-row]').length);
        assert.equal(document.querySelectorAll('[data-person-break-hatch]').length, 1);
        assert.ok(document.querySelector('[data-break-legend]'));
        assert.equal(document.querySelectorAll('[role="slider"]').length, 0);
        const initial = hatch.style.getPropertyValue('--bar-start');
        props.shift.breaks = [{...source, starts_at: '2026-10-02T00:30', duration_minutes: 15}];
        await nextTick();
        assert.equal(hatch.style.getPropertyValue('--bar-start'), initial);
        props.shift.assignments[0].breaks[0].starts_at = '2026-10-01T23:45';
        await nextTick();
        assert.notEqual(document.querySelector('[data-person-break-hatch]').style.getPropertyValue('--bar-start'), initial);
        props.shift.breaks = [];
        await nextTick();
        assert.ok(document.querySelector('[data-break-legend]'));
        props.shift.assignments = props.shift.assignments.map((row) => ({...row, breaks: []}));
        await nextTick();
        assert.equal(document.querySelector('[data-break-legend]'), null);
    } finally {app.unmount();}
});

test('Edit hours stages invalid breaks without writing, clears manually edited provenance and Cancel leaves the person intact', async () => {
    deps.ShiftBreaks = await compile('ShiftBreaks');
    const saved = {id: 91, shift_break_id: 51, duration_minutes: 15, starts_at: '2026-10-02T00:00'};
    const person = {...shift.assignments[0], breaks: [saved]};
    const events = [];
    writes.length = 0;
    const app = mount(Hours, {shift, assignment: person, enabled: true, breakOptions: {durations: [15,30,45,60], default_duration: 15}, onChanged: (row) => events.push(row)});
    try {
        const time = document.querySelector('[data-break-row="saved-personal-break-91"] input[type="time"]');
        time.value = '';
        time.dispatchEvent(new window.Event('input', {bubbles: true}));
        await nextTick();
        assert.equal(document.querySelector('#save').disabled, false);
        document.querySelector('#save').click();
        assert.equal(events.length, 1);
        assert.equal(events[0].breaks[0].starts_at, '');
        assert.equal(events[0].breaks[0].shift_break_id, null);
        assert.equal(saved.starts_at, '2026-10-02T00:00');
        assert.equal(saved.shift_break_id, 51);
        assert.equal(writes.length, 0);
        document.querySelector('#cancel').click();
        assert.equal(saved.starts_at, '2026-10-02T00:00');
    } finally {app.unmount();}
});

test('mass add has only the pending panel, blocks every person on conflict and saves independent personal breaks', async () => {
    deps.ShiftBreaks = await compile('ShiftBreaks');
    deps.ShiftAssignmentHoursDialog = Hours;
    deps.ShiftTimelineRoster = Roster;
    const Page = await compile('ShiftEditor');
    const source = {id: 91, duration_minutes: 15, starts_at: '2026-10-01T23:45', sort_order: 0};
    const app = mount(Page, {shift: {...shift, breaks: [source], assignment_count: 2, assignments: shift.assignments.map((row, i) => ({...row, breaks: i ? [] : [{id: 92, shift_break_id: 91, duration_minutes: 15, starts_at: source.starts_at}]}))}, event:{id:2,is_locked:false},canManage:true,locations:[],roles:[],labelColors:[],breakOptions:{durations:[15,30,45,60],default_duration:15}});
    writes.length = 0;
    try {
        assert.equal(document.querySelectorAll('[data-break-row]').length, 1);
        assert.equal(document.querySelector('[data-break-row] input[type="checkbox"]'), null);
        const time = document.querySelector('[data-break-row] input[type="time"]');
        const add = () => Array.from(document.querySelectorAll('[data-break-row] button')).find((el) => el.textContent.includes('breaks.add')).click();
        const change = async (value) => {time.value = value; time.dispatchEvent(new window.Event('input',{bubbles:true})); await nextTick();};
        await change('23:45');
        add();
        await nextTick();
        assert.match(document.body.textContent, /breaks.errors.mass_conflict/);
        assert.equal(form.assignment_updates.length, 0);
        assert.equal(form.break_operations.length, 0);
        await change('23:30'); // Adjacent to the existing break is allowed.
        add();
        await nextTick();
        assert.equal(form.assignment_updates.length, 2);
        assert.equal(document.querySelectorAll('[data-break-row]').length, 1);
        assert.equal(form.assignment_updates[0].breaks.length, 2);
        assert.equal(form.assignment_updates[1].breaks.length, 1);
        document.querySelector('#shift-details-form').dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));
        assert.equal(writes.length, 1);
        const data = writes[0].data;
        assert.equal(data.assignment_updates[1].breaks[0].shift_break_id, null);
        assert.deepEqual(data.break_operations, [{type: 'mass', assignment_keys: [8, 10], break: {duration_minutes: 15, starts_at: '2026-10-01T23:30'}}]);
        assert.equal(data.breaks[0].starts_at, source.starts_at);
        form.errors = {'break_operations.0': 'Someone already has a break overlapping this time. No breaks were added.', name: 'Keep this error'};
        writes[0].options.onError({'break_operations.0': form.errors['break_operations.0']});
        await nextTick();
        assert.match(document.body.textContent, /Someone already has a break overlapping this time/);
        // Editing the pending time clears the mass error without clearing other form errors.
        document.querySelector('[data-break-row] input[type="time"]').value = '23:00';
        document.querySelector('[data-break-row] input[type="time"]').dispatchEvent(new window.Event('input',{bubbles:true}));
        await nextTick();
        assert.equal(form.errors['break_operations.0'], undefined);
        assert.equal(form.errors.name, 'Keep this error');
    } finally {app.unmount();}
});

test('break stripes retain the assignment outline at either boundary', () => {
    const app = mount(Roster, {shift: {...shift, assignments: [{...shift.assignments[1], breaks: [
        {duration_minutes: 15, starts_at: shift.starts_at},
        {duration_minutes: 15, starts_at: '2026-10-01T23:00'},
        {duration_minutes: 15, starts_at: '2026-10-02T01:45'},
    ]}]}});
    try {
        const stripes = document.querySelectorAll('[data-person-break-hatch]');
        assert.equal(stripes.length, 3);
        assert.equal(stripes[0].classList.contains('rounded-l-lg'), true);
        assert.equal(stripes[0].classList.contains('border-l'), true);
        assert.equal(stripes[1].classList.contains('rounded-l-lg'), false);
        assert.equal(stripes[1].classList.contains('rounded-r-lg'), false);
        assert.equal(stripes[2].classList.contains('rounded-r-lg'), true);
        assert.equal(stripes[2].classList.contains('border-r'), true);
    } finally {app.unmount();}
});

test('grid shrink stages excluded breaks as removals in the atomic Save payload', async () => {
    deps.ShiftTimelineRoster = Roster;
    const Page = await compile('ShiftEditor');
    const source = {id: 91, duration_minutes: 15, starts_at: '2026-10-01T23:45'};
    const app = mount(Page, {shift: {...shift, breaks: [source], assignments: shift.assignments.map((row, i) => ({...row, breaks: i ? [] : [{...source, id: 92, shift_break_id: 91}]}))}, event:{id:2,is_locked:false},canManage:true,locations:[],roles:[],labelColors:[],breakOptions:{durations:[15,30,45,60],default_duration:15}});
    writes.length = 0;
    try {
        const handles = document.querySelector('[data-roster-row="person-8"]').querySelectorAll('[role="slider"]:not([data-move-handle])');
        handles[1].dispatchEvent(new window.KeyboardEvent('keydown', {key:'ArrowLeft', bubbles:true}));
        await nextTick();
        assert.deepEqual(form.assignment_updates[0].breaks, []);
        assert.equal(document.querySelector('[data-person-break-hatch]'), null);
        assert.equal(writes.length, 0);
        document.querySelector('#shift-details-form').dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));
        assert.deepEqual(writes[0].data.assignment_updates[0].breaks, []);
        assert.deepEqual(writes[0].data.break_operations.at(-1).breaks, []);
        assert.equal(writes[0].data.breaks.length, 1);
    } finally {app.unmount();}
});


test('break dragging previews with 15-minute snapping and commits only the personal break on release', async () => {
    const events = [];
    const hoursEvents = [];
    const person = {...shift.assignments[1], breaks: [
        {id: 92, duration_minutes: 15, starts_at: '2026-10-01T23:45', shift_break_id: 91},
        {id: 93, duration_minutes: 15, starts_at: '2026-10-02T01:00', shift_break_id: null},
    ]};
    const before = JSON.stringify(person);
    const app = mount(Roster, {shift: {...shift, assignments: [person]}, enabled: true, canManage: true,
        onMoveBreak: (event) => events.push(event), onResize: (event) => hoursEvents.push(event)});
    try {
        const handle = document.querySelector('[data-person-break-hatch]');
        const canvas = handle.closest('[data-roster-canvas]');
        canvas.getBoundingClientRect = () => ({width: 600}); // Six hours including timeline padding.
        const begin = () => handle.dispatchEvent(new window.MouseEvent('pointerdown', {clientX: 100, button: 0, bubbles: true}));
        const initial = handle.style.getPropertyValue('--bar-start');
        begin();
        window.dispatchEvent(new window.MouseEvent('pointermove', {clientX: 126}));
        await nextTick();
        assert.notEqual(handle.style.getPropertyValue('--bar-start'), initial);
        assert.match(handle.getAttribute('aria-valuetext'), /00:00/);
        assert.equal(events.length, 0);
        window.dispatchEvent(new window.MouseEvent('pointerup', {clientX: 126}));
        await nextTick();
        assert.equal(events.length, 1);
        assert.equal(events[0].breaks[0].starts_at, '2026-10-02T00:00');
        assert.equal(events[0].breaks[0].id, 92);
        assert.equal(events[0].breaks[0].duration_minutes, 15);
        assert.equal(events[0].breaks[0].shift_break_id, null);
        assert.deepEqual(events[0].breaks[1], person.breaks[1]);
        assert.equal(hoursEvents.length, 0);
        assert.equal(JSON.stringify(person), before);
        // Escape and pointer cancellation restore the source state; a plain click has no effect.
        for (const cancellation of ['Escape', 'pointercancel']) {
            begin();
            window.dispatchEvent(new window.MouseEvent('pointermove', {clientX: 126}));
            window.dispatchEvent(cancellation === 'Escape'
                ? new window.KeyboardEvent('keydown', {key: 'Escape'})
                : new window.MouseEvent('pointercancel'));
            window.dispatchEvent(new window.MouseEvent('pointerup', {clientX: 126}));
            await nextTick();
            assert.equal(handle.style.getPropertyValue('--bar-start'), initial);
            assert.equal(events.length, 1);
        }
        begin();
        window.dispatchEvent(new window.MouseEvent('pointerup', {clientX: 100}));
        assert.equal(events.length, 1);
        handle.dispatchEvent(new window.KeyboardEvent('keydown', {key: 'ArrowRight', bubbles: true}));
        assert.equal(events.length, 2);
        assert.equal(events[1].breaks[0].starts_at, '2026-10-02T00:00');
        // The second break cannot move onto the first.
        const second = document.querySelectorAll('[data-person-break-hatch]')[1];
        second.dispatchEvent(new window.MouseEvent('pointerdown', {clientX: 100, button: 0, bubbles: true}));
        window.dispatchEvent(new window.MouseEvent('pointerup', {clientX: -25}));
        assert.equal(events.length, 2);
    } finally {app.unmount();}
});

test('break move controls cannot stage changes when read-only or disabled', async () => {
    for (const canManage of [true, false]) {
        const events = [];
        const person = {...shift.assignments[1], breaks: [{duration_minutes: 15, starts_at: '2026-10-01T23:45'}]};
        const app = mount(Roster, {shift: {...shift, assignments: [person]}, enabled: false, canManage, onMoveBreak: (event) => events.push(event)});
        try {
            const hatch = document.querySelector('[data-person-break-hatch]');
            assert.equal(hatch.tagName, canManage ? 'BUTTON' : 'SPAN');
            if (canManage) assert.equal(hatch.disabled, true);
            hatch.dispatchEvent(new window.MouseEvent('pointerdown', {clientX: 100, button: 0, bubbles: true}));
            window.dispatchEvent(new window.MouseEvent('pointerup', {clientX: 126}));
            hatch.dispatchEvent(new window.KeyboardEvent('keydown', {key: 'ArrowRight', bubbles: true}));
            await nextTick();
            assert.equal(events.length, 0);
        } finally {app.unmount();}
    }
});

test('moving a break stages its independent time in Save without changing person hours or shift defaults', async () => {
    deps.ShiftTimelineRoster = Roster;
    const Page = await compile('ShiftEditor');
    const source = {id: 91, duration_minutes: 15, starts_at: '2026-10-01T23:45'};
    const app = mount(Page, {shift: {...shift, breaks: [source], assignments: shift.assignments.map((row, i) => ({...row, breaks: i ? [] : [{...source, id: 92, shift_break_id: 91}]}))}, event:{id:2,is_locked:false},canManage:true,locations:[],roles:[],labelColors:[],breakOptions:{durations:[15,30,45,60],default_duration:15}});
    writes.length = 0;
    try {
        document.querySelector('[data-person-break-hatch]').dispatchEvent(new window.KeyboardEvent('keydown', {key:'ArrowLeft', bubbles:true}));
        await nextTick();
        assert.equal(writes.length, 0);
        assert.equal(form.assignment_updates[0].starts_at, shift.assignments[0].starts_at);
        assert.equal(form.assignment_updates[0].ends_at, shift.assignments[0].ends_at);
        assert.equal(form.assignment_updates[0].breaks[0].starts_at, '2026-10-01T23:30');
        document.querySelector('#shift-details-form').dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));
        const payload = writes[0].data;
        assert.deepEqual(payload.assignment_updates[0].breaks, [{id: 92, duration_minutes: 15, starts_at: '2026-10-01T23:30', shift_break_id: null}]);
        assert.deepEqual(payload.breaks, [{id: 91, duration_minutes: 15, starts_at: source.starts_at}]);
        assert.equal(payload.break_operations.at(-1).type, 'person');
        assert.equal(payload.break_operations.at(-1).assignment_key, 8);
    } finally {app.unmount();}
});
