import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { JSDOM } from 'jsdom';
import { parse, compileScript } from '@vue/compiler-sfc';
import {
    assignmentPayload,
    assignmentCandidatesUrl,
    validAssignmentHours,
    requirementRoster,
} from '../../resources/js/lib/shiftAssignments.js';

const dom = new JSDOM('<div id="app"></div>');
for (const key of [
    'window',
    'document',
    'Element',
    'HTMLElement',
    'SVGElement',
    'Node',
])
    globalThis[key] = dom.window[key];
const { createApp, h, ref, reactive, nextTick, onMounted } =
    await import('vue');
const shift = {
    id: 3,
    starts_at: '2026-10-01T10:00',
    ends_at: '2026-10-01T14:00',
};
const harness = {
    writes: [],
    requests: [],
    errors: [],
    page: 0,
    failSearch: false,
};
const Box = {
    setup:
        (_, { slots }) =>
        () =>
            h('div', slots.default?.()),
};
const dependencies = {
    assignmentPayload,
    assignmentCandidatesUrl,
    validAssignmentHours,
    trans: (key) => key,
    useFlashToast: () => ({
        showError: (error) => harness.errors.push(error),
        showFormError: (error) => harness.errors.push(error),
    }),
    useForm: (data) => {
        const form = reactive({ ...data, errors: {}, processing: false });
        let transform = (data) => data;
        form.clearErrors = () => {
            form.errors = {};
        };
        form.transform = (callback) => {
            transform = callback;
            return form;
        };
        form.post = (url, options) => {
            harness.writes.push({ url, data: transform(form), options });
        };
        harness.form = form;
        return form;
    },
    Dialog: {
        props: ['confirmDisabled', 'confirmLabel'],
        setup:
            (props, { slots, emit }) =>
            () =>
                h('div', [
                    slots.default?.(),
                    h(
                        'button',
                        { id: 'cancel', onClick: () => emit('cancel') },
                        'Cancel',
                    ),
                    h(
                        'button',
                        {
                            id: 'assign',
                            disabled: props.confirmDisabled,
                            onClick: () => emit('confirm'),
                        },
                        'Assign',
                    ),
                ]),
    },
    Input: {
        props: ['modelValue'],
        setup:
            (props, { emit, attrs }) =>
            () =>
                h('input', {
                    ...attrs,
                    value: props.modelValue,
                    onInput: (event) =>
                        emit('update:modelValue', event.target.value),
                }),
    },
    FormField: {
        setup:
            (_, { slots }) =>
            () =>
                h('div', slots.default?.({ id: 'field', invalid: false })),
    },
    Radio: {
        props: ['modelValue', 'value', 'label', 'disabled'],
        setup:
            (props, { emit }) =>
            () =>
                h('label', [
                    h('input', {
                        type: 'radio',
                        checked: props.modelValue === props.value,
                        disabled: props.disabled,
                        onChange: () => emit('update:modelValue', props.value),
                    }),
                    props.label,
                ]),
    },
    Checkbox: {
        props: ['modelValue', 'disabled'],
        setup:
            (props, { emit }) =>
            () =>
                h('input', {
                    type: 'checkbox',
                    checked: props.modelValue,
                    disabled: props.disabled,
                    onChange: (event) =>
                        emit('update:modelValue', event.target.checked),
                }),
    },
    CustomDropdown: {
        props: ['modelValue', 'items'],
        setup:
            (props, { emit }) =>
            () =>
                h(
                    'select',
                    {
                        id: 'position',
                        value: props.modelValue,
                        onChange: (event) =>
                            emit(
                                'update:modelValue',
                                Number(event.target.value),
                            ),
                    },
                    [
                        h('option', { value: '' }, 'Choose'),
                        ...props.items.map((item) =>
                            h('option', { value: item.value }, item.title),
                        ),
                    ],
                ),
    },
    Icon: Box,
    Button: {
        setup:
            (_, { slots, attrs }) =>
            () =>
                h('button', attrs, slots.default?.()),
    },
    Badge: Box,
    ShiftOverlapWarnings: {
        props: ['overlaps'],
        setup: (props) => () => h('span', JSON.stringify(props.overlaps)),
    },
    DataTable: {
        props: ['ajax'],
        setup: (props, { slots, expose }) => {
            const rows = ref([]);
            const load = () =>
                props.ajax(
                    {
                        draw: 1,
                        start: harness.page * 5,
                        length: 5,
                        search: { value: '' },
                    },
                    (response) => {
                        rows.value = response.data;
                    },
                );
            expose({ reload: load, search: load });
            harness.reload = load;
            onMounted(load);
            return () =>
                h(
                    'div',
                    rows.value.map((row) =>
                        Object.values(slots).map((slot) =>
                            slot({ rowData: row }),
                        ),
                    ),
                );
        },
    },
};
globalThis.__shiftDialogTest = dependencies;
globalThis.fetch = async (url) => {
    harness.requests.push(new URL(url, 'http://localhost'));
    const name = harness.page === 0 ? 'Alpha' : 'Beta';
    return {
        ok: !harness.failSearch,
        json: async () => ({
            data: [
                {
                    id: harness.page === 0 ? 8 : 9,
                    name,
                    role_name: 'Crew',
                    group_name: 'Stage',
                    suggested: true,
                    on_shift: false,
                    overlaps: [{ shift_id: 20 }],
                },
                {
                    id: 10,
                    name: 'Already assigned',
                    on_shift: true,
                    overlaps: [],
                },
            ],
            meta: { total: 21 },
        }),
    };
};
const require = createRequire(import.meta.url);
const { descriptor } = parse(
    readFileSync(
        new URL(
            '../../resources/js/components/team/ShiftAssignDialog.vue',
            import.meta.url,
        ),
        'utf8',
    ),
);
const compiled = compileScript(descriptor, {
    id: 'shift-assignment-test',
    inlineTemplate: true,
});
let source = compiled.content.replaceAll(
    /from ['"]vue['"]/g,
    `from '${pathToFileURL(require.resolve('vue')).href}'`,
);
source = source.replace(
    /import\s+([\s\S]*?)\s+from\s+['"]([^'"]+)['"];?/g,
    (statement, names, from) => {
        if (from.startsWith('file:')) return statement;
        const bindings = names.startsWith('{') ? names : `{ ${names} }`;
        return `const ${bindings} = globalThis.__shiftDialogTest;`;
    },
);
const { default: AssignDialog } = await import(
    `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
);
const settle = async () => {
    await new Promise((resolve) => setTimeout(resolve, 5));
    await nextTick();
};
const mount = async (overrides = {}) => {
    harness.writes = [];
    harness.errors = [];
    harness.requests = [];
    harness.page = 0;
    harness.failSearch = false;
    const app = createApp({
        setup: () => () =>
            h(AssignDialog, {
                shift,
                eventId: 2,
                requirement: { id: 4, role_name: 'Crew' },
                ...overrides,
                onClose: () => {
                    harness.closed = true;
                },
            }),
    });
    app.config.globalProperties.$t = (key) => key;
    harness.closed = false;
    app.mount(document.getElementById('app'));
    await settle();
    return app;
};
const choose = async (name) => {
    [...document.querySelectorAll('label')]
        .find((label) => label.textContent === name)
        .querySelector('input')
        .click();
    await nextTick();
};

test('full/custom hours have bounded intervals and submit only assignment inputs', () => {
    assert.equal(validAssignmentHours(shift, 'full_shift', '', ''), true);
    assert.equal(
        validAssignmentHours(shift, 'custom', '', shift.ends_at),
        false,
    );
    assert.equal(
        validAssignmentHours(shift, 'custom', shift.ends_at, shift.starts_at),
        false,
    );
    assert.equal(
        validAssignmentHours(
            shift,
            'custom',
            '2026-10-01T09:00',
            shift.ends_at,
        ),
        false,
    );
    assert.deepEqual(
        assignmentPayload(4, 8, 'full_shift', 'unused', 'unused'),
        {
            shift_role_slot_id: 4,
            team_engagement_id: 8,
            hours_mode: 'full_shift',
        },
    );
    assert.equal(
        requirementRoster(
            {
                assignments: [
                    { role_id: 1, shift_role_slot_id: 4 },
                    { role_id: 1, shift_role_slot_id: 5 },
                    { role_id: 2, shift_role_slot_id: 4 },
                ],
            },
            { id: 4, role_id: 1 },
        ).length,
        1,
    );
});

test('dialog retains selection across pages, disables assigned people, and maps write errors', async () => {
    const app = await mount();
    try {
        assert.equal(document.querySelector('#assign').disabled, true);
        assert.equal(
            [...document.querySelectorAll('label')]
                .find((label) => label.textContent === 'Already assigned')
                .querySelector('input').disabled,
            true,
        );
        assert.ok(document.body.textContent.includes('Crew'));
        assert.ok(document.body.textContent.includes('Stage'));
        assert.ok(
            document.body.textContent.includes(
                'team.scheduling.assignments.not_available',
            ),
        );
        assert.equal(harness.requests[0].searchParams.get('per_page'), '5');
        await choose('Alpha');
        harness.page = 1;
        await harness.reload();
        await settle();
        document.querySelector('#assign').click();
        assert.equal(harness.writes[0].data.team_engagement_id, 8);
        assert.equal(harness.requests.at(-1).searchParams.get('page'), '2');
        harness.form.errors = { team_engagement_id: 'Already on shift' };
        harness.writes[0].options.onError(harness.form.errors);
        await settle();
        assert.ok(document.body.textContent.includes('Already on shift'));
        assert.deepEqual(harness.errors[0], {
            team_engagement_id: 'Already on shift',
        });
    } finally {
        app.unmount();
    }
});

test('changing hours refreshes search, invalid hours disable Assign, and cancel/remount resets selection', async () => {
    let app = await mount();
    try {
        await choose('Alpha');
        const checkbox = document.querySelector('input[type=checkbox]');
        checkbox.click();
        await nextTick();
        const inputs = [
            ...document.querySelectorAll('input[type=datetime-local]'),
        ];
        inputs[0].value = '2026-10-01T11:00';
        inputs[0].dispatchEvent(new window.Event('input', { bubbles: true }));
        inputs[1].value = '2026-10-01T12:00';
        inputs[1].dispatchEvent(new window.Event('input', { bubbles: true }));
        await new Promise((resolve) => setTimeout(resolve, 280));
        await settle();
        assert.equal(
            harness.requests.at(-1).searchParams.get('starts_at'),
            '2026-10-01T11:00',
        );
        document.querySelector('#assign').click();
        assert.deepEqual(harness.writes[0].data, {
            shift_role_slot_id: 4,
            team_engagement_id: 8,
            hours_mode: 'custom',
            starts_at: '2026-10-01T11:00',
            ends_at: '2026-10-01T12:00',
        });
        inputs[1].value = '';
        inputs[1].dispatchEvent(new window.Event('input', { bubbles: true }));
        await nextTick();
        assert.equal(document.querySelector('#assign').disabled, true);
        checkbox.click();
        await nextTick();
        assert.equal(harness.form.starts_at, shift.starts_at);
        assert.equal(harness.form.ends_at, shift.ends_at);
        assert.ok(inputs.every((input) => input.disabled));
        const count = harness.writes.length;
        document.querySelector('#cancel').click();
        assert.equal(harness.closed, true);
        assert.equal(harness.writes.length, count);
        app.unmount();
        app = await mount();
        assert.equal(document.querySelector('#assign').disabled, true);
        assert.equal(
            document.querySelector('input[type=checkbox]').checked,
            true,
        );
        assert.ok(
            [...document.querySelectorAll('input[type=datetime-local]')].every(
                (input) => input.disabled,
            ),
        );
    } finally {
        app.unmount();
    }
});

test('candidate errors prevent assignment until a successful refresh', async () => {
    const app = await mount();
    try {
        await choose('Alpha');
        harness.failSearch = true;
        await harness.reload();
        await settle();
        assert.equal(document.querySelector('#assign').disabled, true);
        assert.ok(
            document.body.textContent.includes(
                'team.scheduling.assignments.search_failed',
            ),
        );
        assert.equal(harness.writes.length, 0);
    } finally {
        app.unmount();
    }
});

test('overlap warnings render in a DataTable component without app-global translations', async () => {
    const { descriptor } = parse(
        readFileSync(
            new URL(
                '../../resources/js/components/team/ShiftOverlapWarnings.vue',
                import.meta.url,
            ),
            'utf8',
        ),
    );
    const compiled = compileScript(descriptor, {
        id: 'overlap-test',
        inlineTemplate: true,
    });
    const moduleSource = compiled.content
        .replaceAll(
            /from ['"]vue['"]/g,
            `from '${pathToFileURL(require.resolve('vue')).href}'`,
        )
        .replace(
            /import \{ trans \} from ['"]laravel-vue-i18n['"];?/,
            'const trans = (key, params) => key + JSON.stringify(params);',
        )
        .replace(
            /import \{ Icon \} from ['"].*?['"];?/,
            'const Icon = { render: () => null };',
        );
    const { default: Warning } = await import(
        `data:text/javascript;base64,${Buffer.from(moduleSource).toString('base64')}`
    );
    const app = createApp({
        setup: () => () =>
            h(Warning, {
                overlaps: [
                    {
                        shift_id: 2,
                        shift_name: 'Other shift',
                        starts_at: '2026-10-01T11:00',
                        ends_at: '2026-10-01T12:00',
                        overlap_minutes: 60,
                    },
                ],
            }),
    });
    app.mount(document.getElementById('app'));
    try {
        assert.ok(document.body.textContent.includes('Other shift'));
        assert.ok(document.body.textContent.includes('"minutes":60'));
        assert.ok(document.body.textContent.includes('2026-10-01 11:00'));
    } finally {
        app.unmount();
    }
});

test('header assignment chooses a Headcount position, permits a different Team role and clears a previous selection', async () => {
    const app = await mount({
        requirement: null,
        shift: {
            ...shift,
            slots: [
                { id: 4, role_name: 'Security', needed: 2, assigned_count: 1 },
                { id: 5, role_name: 'Crew', needed: 1, assigned_count: 1 },
            ],
        },
    });
    try {
        assert.equal(harness.requests.length, 0);
        assert.equal(document.querySelector('#assign').disabled, true);
        const select = document.querySelector('select');
        select.value = '4';
        select.dispatchEvent(new dom.window.Event('change'));
        await settle();
        assert.equal(
            harness.requests[0].searchParams.get('shift_role_slot_id'),
            '4',
        );
        await choose('Alpha');
        document.querySelector('#assign').click();
        assert.equal(harness.writes.length, 1);
        assert.deepEqual(harness.writes[0].data, {
            shift_role_slot_id: 4,
            team_engagement_id: 8,
            hours_mode: 'full_shift',
        });
        select.value = '5';
        select.dispatchEvent(new dom.window.Event('change'));
        await settle();
        assert.equal(
            harness.requests.at(-1).searchParams.get('shift_role_slot_id'),
            '5',
        );
        assert.equal(document.querySelector('#assign').disabled, true);
        assert.equal(
            document.querySelector('input[type=radio]').checked,
            false,
        );
    } finally {
        app.unmount();
    }
});

test('deferred assignment returns the selected person and position without writing', async () => {
    const drafts = [];
    const app = await mount({
        deferred: true,
        onAssigned: (draft) => drafts.push(draft),
    });
    try {
        await choose('Alpha');
        document.querySelector('#assign').click();
        assert.equal(harness.writes.length, 0);
        assert.equal(drafts.length, 1);
        assert.equal(drafts[0].candidate.id, 8);
        assert.equal(drafts[0].shift_role_slot_id, 4);
    } finally {
        app.unmount();
    }
});


test('unsaved Headcount searches by suggested role and emits a draft slot reference without writes', async () => {
    const drafts = [];
    const requirement = { id: 'draft-12', role_id: 3, role_name: 'Crew', needed: 1, assigned_count: 0 };
    const app = await mount({ deferred: true, requirement, onAssigned: draft => drafts.push(draft) });
    try {
        await choose('Alpha');
        const query = harness.requests.at(-1).searchParams;
        assert.equal(query.get('role_id'), '3');
        assert.equal(query.has('shift_role_slot_id'), false);
        document.querySelector('#assign').click();
        assert.equal(harness.writes.length, 0);
        assert.equal(drafts[0].slot_key, 'draft-12');
        assert.equal('shift_role_slot_id' in drafts[0], false);
    } finally { app.unmount(); }
});


test('create-shift candidates use event scope and proposed bounds, stage draft assignments, and disable pending people', async () => {
    const events = [];
    const draftSlot = { id: 'draft-1', role_id: 12, role_name: 'Crew' };
    const app = await mount({
        shift: { ...shift, id: undefined, slots: [draftSlot] },
        requirement: draftSlot,
        deferred: true,
        pendingMemberIds: [10],
        onAssigned: data => events.push(data),
    });
    try {
        const request = harness.requests[0];
        assert.equal(request.pathname, '/team/events/2/shifts/assignment-candidates');
        assert.equal(request.searchParams.get('role_id'), '12');
        assert.equal(request.searchParams.get('shift_starts_at'), shift.starts_at);
        assert.equal(request.searchParams.get('shift_ends_at'), shift.ends_at);
        assert.equal(request.searchParams.get('per_page'), '5');
        assert.equal(request.searchParams.has('shift_role_slot_id'), false);
        await choose('Alpha');
        document.querySelector('#assign').click();
        assert.equal(harness.writes.length, 0);
        assert.equal(events[0].slot_key, 'draft-1');
        assert.equal(events[0].team_engagement_id, 8);
        assert.equal(events[0].hours_mode, 'full_shift');
    } finally { app.unmount(); }
    const pending = await mount({
        shift: { ...shift, id: undefined, slots: [draftSlot] },
        requirement: draftSlot,
        deferred: true,
        pendingMemberIds: [8],
    });
    try {
        const label = [...document.querySelectorAll('label')].find(row => row.textContent === 'Alpha');
        assert.equal(label.querySelector('input').disabled, true);
        assert.equal(document.querySelector('#assign').disabled, true);
        assert.equal(harness.writes.length, 0);
    } finally { pending.unmount(); }
});
