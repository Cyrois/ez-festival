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
                    { ...attrs, title: p.label, disabled: p.disabled },
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
            `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
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
    slots: [{ id: 3, role_id: 4, role_name: 'Crew', open_count: 1 }],
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
            '10%',
        );
        assert.ok(
            Math.abs(
                parseFloat(
                    first
                        .querySelector('[data-overlap-hatch]')
                        .style.getPropertyValue('--bar-start'),
                ) - 55,
            ) < 1e-8,
        );
        assert.ok(first.querySelector('[data-short-label]'));
        assert.match(first.textContent, /23:30–2026-10-02 00:00/);
        assert.match(first.textContent, /name=Other minutes=15/);
        assert.match(
            document.querySelector('[data-roster-row="person-10"]').textContent,
            /assignments.full_shift/,
        );
        document.querySelector('[data-open-bar] button').click();
        first.querySelectorAll('button')[0].click();
        first.querySelectorAll('button')[1].click();
        assert.deepEqual(events, [3, 8, -8]);
    } finally {
        app.unmount();
    }
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
            const button = document.querySelector('[data-open-bar] button');
            assert.ok(button.disabled);
            assert.equal(button.title, 'Save or read only');
            for (const b of document.querySelectorAll('button')) b.click();
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
        assert.ok(document.querySelector('[data-roster-summary]'));
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

test('shift page guards absent confirmation data and confirms removal before one DELETE', async () => {
    const deletions = [];
    Object.assign(deps, {
        ...breakHelpers,
        ...slotHelpers,
        ColorPicker: box('div'),
        ShiftRoleSlots: box('div'),
        ShiftBreaks: box('div'),
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
    const strictText = (key, params = {}) => {
        for (const value of Object.values(params))
            assert.notEqual(
                value,
                undefined,
                `undefined translation replacement for ${key}`,
            );
        return text(key, params);
    };
    const Page = await compile('Shift', 'pages/Team');
    const app = createApp(Page, {
        shift: { ...shift, breaks: [], assignment_count: 2 },
        event: { id: 2, is_locked: false },
        canManage: true,
        roles: [],
        locations: [],
        labelColors: [],
        breakOptions: { lengths: [] },
    });
    app.config.globalProperties.$t = strictText;
    app.mount('#app');
    try {
        assert.equal(document.querySelector('#save'), null);
        const first = document.querySelector('[data-roster-row="person-8"]');
        first.querySelectorAll('button')[1].click();
        await nextTick();
        assert.equal(deletions.length, 0);
        document.querySelector('#cancel').click();
        await nextTick();
        assert.equal(deletions.length, 0);
        assert.equal(document.querySelector('#save'), null);
        first.querySelectorAll('button')[1].click();
        await nextTick();
        document.querySelector('#save').click();
        assert.equal(deletions.length, 1);
        assert.match(deletions[0].url, /assignments\/8$/);
        await nextTick();
        assert.equal(
            document.querySelector('[data-open-bar] button').disabled,
            true,
        );
        deletions[0].options.onSuccess();
        deletions[0].options.onFinish();
        await nextTick();
        assert.equal(document.querySelector('#save'), null);
    } finally {
        app.unmount();
    }
});
