import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { JSDOM } from 'jsdom';
import { parse, compileScript } from '@vue/compiler-sfc';
import {
    assignmentPayload,
    validAssignmentHours,
    requirementRoster,
    assignmentOverlapDetails,
    assignmentDurationLabel,
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
    name: 'Morning crew',
    starts_at: '2026-10-01T10:00',
    ends_at: '2026-10-01T14:00',
};
const harness = {
    writes: [],
    requests: [],
    errors: [],
    page: 0,
    failSearch: false,
    total: 21,
    overlapMinutes: 30,
    onlyOnShift: false,
    noRoleMatches: false,
};
const Box = {
    setup:
        (_, { slots }) =>
        () =>
            h('div', slots.default?.()),
};
const copy = JSON.parse(
    readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'),
);
const translate = (key, params = {}) =>
    Object.entries(params).reduce(
        (text, [name, value]) => text.replaceAll(`:${name}`, value),
        copy[key] ?? key,
    );
const dependencies = {
    assignmentPayload,
    validAssignmentHours,
    trans: translate,
    assignmentOverlapDetails,
    assignmentDurationLabel,
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
                    slots.subtitle?.(),
                    slots.default?.(),
                    slots['footer-hint']?.(),
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
                        props.confirmLabel,
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
            (props, { emit, attrs }) =>
            () =>
                h('label', [
                    h('input', {
                        ...attrs,
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
    Avatar: {
        props: ['name'],
        setup: (props) => () =>
            h('span', { 'data-ui': 'avatar' }, props.name.slice(0, 2)),
    },
    Tag: { props: ['name'], setup: (props) => () => h('span', props.name) },
    SegmentedControl: {
        props: ['modelValue', 'options'],
        setup:
            (props, { emit }) =>
            () =>
                h(
                    'div',
                    props.options.map((option) =>
                        h(
                            'button',
                            {
                                'data-filter': option.value,
                                'aria-checked':
                                    props.modelValue === option.value,
                                onClick: () =>
                                    emit('update:modelValue', option.value),
                            },
                            option.label,
                        ),
                    ),
                ),
    },
    ShiftOverlapWarnings: {
        props: ['overlaps'],
        setup: (props) => () => h('span', JSON.stringify(props.overlaps)),
    },
    DataTable: {
        props: ['ajax', 'options', 'columns'],
        setup: (props, { slots, expose }) => {
            const rows = ref([]);
            const root = ref(null);
            let search = '';
            let total = 0;
            const api = () => ({
                table: () => ({
                    node: () => root.value.querySelector('table'),
                    container: () => root.value,
                }),
                page: {
                    info: () => ({
                        pages: Math.ceil(total / props.options.pageLength),
                    }),
                },
            });
            const load = () =>
                props.ajax(
                    {
                        draw: 1,
                        start: harness.page * props.options.pageLength,
                        length: props.options.pageLength,
                        search: { value: search },
                    },
                    async (response) => {
                        total = response.recordsFiltered;
                        rows.value = response.data;
                        await nextTick();
                        root.value
                            .querySelectorAll('tbody tr[data-id]')
                            .forEach((row, index) =>
                                props.options.createdRow(
                                    row,
                                    rows.value[index],
                                ),
                            );
                        props.options.drawCallback.call({ api });
                    },
                );
            expose({
                reload: load,
                search: (value) => {
                    search = value;
                    harness.page = 0;
                    return load();
                },
            });
            harness.reload = load;
            onMounted(load);
            return () =>
                h('div', { ref: root }, [
                    h('table', [
                        h(
                            'tbody',
                            rows.value.length
                                ? rows.value.map((row) =>
                                      h(
                                          'tr',
                                          { key: row.id, 'data-id': row.id },
                                          Object.values(slots).map((slot) =>
                                              h('td', slot({ rowData: row })),
                                          ),
                                      ),
                                  )
                                : h('tr', [h('td', { class: 'dt-empty' })]),
                        ),
                    ]),
                    h('div', { class: 'dt-paging' }, 'Previous Next'),
                ]);
        },
    },
};
globalThis.__shiftDialogTest = dependencies;
globalThis.fetch = async (url) => {
    const request = new URL(url, 'http://localhost');
    harness.requests.push(request);
    const candidates = [
        {
            id: 8,
            name: 'Alpha',
            role_name: 'Crew',
            group_name: 'Stage',
            suggested: true,
            on_shift: false,
            overlaps: harness.overlapMinutes
                ? [
                      {
                          shift_id: 20,
                          shift_name: 'Gate close',
                          starts_at: '2026-10-01T11:00',
                          ends_at: '2026-10-01T13:00',
                          overlap_minutes: harness.overlapMinutes,
                      },
                  ]
                : [],
        },
        {
            id: 9,
            name: 'Beta',
            role_name: 'Sound',
            suggested: false,
            on_shift: false,
            overlaps: [],
        },
        {
            id: 10,
            name: 'Already assigned',
            role_name: 'Crew',
            suggested: true,
            on_shift: true,
            overlaps: [],
        },
    ];
    const selectedId = request.searchParams.get('selected_id');
    const search = request.searchParams.get('search').toLowerCase();
    const filtered = candidates.filter(
        (candidate) =>
            (!harness.onlyOnShift || candidate.on_shift) &&
            (!harness.noRoleMatches ||
                request.searchParams.get('role_filter') !== 'has_role') &&
            (!selectedId || candidate.id === Number(selectedId)) &&
            (!search || candidate.name.toLowerCase().includes(search)) &&
            (request.searchParams.get('role_filter') !== 'has_role' ||
                candidate.suggested),
    );
    return {
        ok: !harness.failSearch,
        json: async () => ({
            data:
                !selectedId && harness.page > 0
                    ? filtered.filter((row) => row.id === 9)
                    : filtered,
            meta: {
                total: selectedId || search ? filtered.length : harness.total,
            },
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
    harness.total = 21;
    harness.overlapMinutes = 30;
    harness.onlyOnShift = false;
    harness.noRoleMatches = false;
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
    app.config.globalProperties.$t = translate;
    harness.closed = false;
    app.mount(document.getElementById('app'));
    await settle();
    return app;
};
const choose = async (name) => {
    document.querySelector(`input[type=radio][aria-label="${name}"]`).click();
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
            document.querySelector('input[aria-label="Already assigned"]')
                .disabled,
            true,
        );
        assert.ok(document.body.textContent.includes('Crew'));
        assert.ok(document.body.textContent.includes('Stage'));
        assert.ok(
            document.body.textContent.includes('Overlaps Gate close · 30 min'),
        );
        assert.equal(harness.requests[0].searchParams.get('per_page'), '25');
        await choose('Alpha');
        harness.page = 1;
        await harness.reload();
        await settle();
        document.querySelector('#assign').click();
        assert.equal(harness.writes[0].data.team_engagement_id, 8);
        assert.equal(
            harness.requests
                .find((request) => request.searchParams.get('page') === '2')
                .searchParams.get('page'),
            '2',
        );
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
        assert.equal(
            document.querySelectorAll('input[type=datetime-local]').length,
            0,
        );
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
            document.querySelectorAll('input[type=datetime-local]').length ===
                0,
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
                copy['team.scheduling.assignments.search_failed'],
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

test('popup opens on Has this role with subtitle, hidden single-page pager, full-shift text and disabled footer hint', async () => {
    const app = await mount();
    try {
        assert.equal(
            document
                .querySelector('[data-filter="has_role"]')
                .getAttribute('aria-checked'),
            'true',
        );
        assert.equal(
            harness.requests[0].searchParams.get('role_filter'),
            'has_role',
        );
        assert.ok(
            document.body.textContent.includes(
                'Morning crew · Open Crew slot · 10:00–14:00',
            ),
        );
        assert.ok(
            document.body.textContent.includes('Pick a person to assign'),
        );
        assert.ok(
            document.body.textContent.includes('10:00–14:00 (full shift)'),
        );
        assert.equal(
            document.querySelectorAll('input[type=datetime-local]').length,
            0,
        );
        assert.equal(
            document.querySelector('.dt-paging').classList.contains('hidden'),
            true,
        );
        harness.total = 26;
        await harness.reload();
        await settle();
        assert.equal(
            document.querySelector('.dt-paging').classList.contains('hidden'),
            false,
        );
    } finally {
        app.unmount();
    }
});

test('clicking anywhere on a row selects with a teal edge, and overlapping candidates remain assignable', async () => {
    const app = await mount();
    try {
        document.querySelector('tr[data-id="8"] td:last-child').click();
        await nextTick();
        const row = document.querySelector('tr[data-id="8"]');
        assert.ok(row.classList.contains('bg-primary-soft'));
        assert.ok(
            row.classList.contains('[&>td:first-child]:border-l-primary'),
        );
        assert.equal(
            document.querySelector('input[aria-label="Alpha"]').checked,
            true,
        );
        assert.equal(
            document.querySelector('#assign').textContent,
            'Assign Alpha',
        );
        assert.equal(document.querySelector('#assign').disabled, false);
        assert.ok(
            document.body.textContent.includes(
                'Overlaps Gate close by 30 min. You can still assign them.',
            ),
        );
        const chip = document.querySelector('[title^="Also on"]');
        assert.ok(
            chip.title.includes('Also on Gate close\n2026-10-01 11:00–13:00'),
        );
        assert.ok(
            chip.title.includes('Overlaps this shift 11:00–13:00 (30 min)'),
        );
        document.querySelector('tr[data-id="10"]').click();
        await nextTick();
        assert.equal(
            document.querySelector('#assign').textContent,
            'Assign Alpha',
        );
    } finally {
        app.unmount();
    }
});

test('Everyone shows nonmatching people with blank roles; filtering or searching a picked person away clears selection', async () => {
    const app = await mount();
    try {
        document.querySelector('[data-filter="everyone"]').click();
        await settle();
        const beta = document.querySelector('tr[data-id="9"]');
        assert.equal(beta.children[1].textContent, '');
        beta.click();
        await nextTick();
        assert.equal(
            document.querySelector('#assign').textContent,
            'Assign Beta',
        );
        document.querySelector('[data-filter="has_role"]').click();
        await settle();
        assert.equal(document.querySelector('#assign').disabled, true);
        assert.ok(
            document.body.textContent.includes('Pick a person to assign'),
        );
        await choose('Alpha');
        const search = document.querySelector('input[type=search]');
        search.value = 'Nobody';
        search.dispatchEvent(new window.Event('input', { bubbles: true }));
        await new Promise((resolve) => setTimeout(resolve, 320));
        await settle();
        assert.equal(document.querySelector('#assign').disabled, true);
        assert.ok(
            document.body.textContent.includes(
                'No hired Team members match ‘Nobody’.',
            ),
        );
    } finally {
        app.unmount();
    }
});

test('hours changes refresh a selected candidate even when they are on another page', async () => {
    const app = await mount();
    try {
        await choose('Alpha');
        harness.page = 1;
        harness.overlapMinutes = 0;
        document.querySelector('input[type=checkbox]').click();
        await new Promise((resolve) => setTimeout(resolve, 270));
        await settle();
        assert.ok(
            harness.requests.some(
                (request) => request.searchParams.get('selected_id') === '8',
            ),
        );
        assert.equal(
            document.querySelector('#assign').textContent,
            'Assign Alpha',
        );
        assert.equal(document.querySelector('#assign').disabled, false);
        assert.ok(
            !document.body.textContent.includes('You can still assign them.'),
        );
    } finally {
        app.unmount();
    }
});

test('tooltip includes every overlap and crosses midnight without browser-timezone conversion', () => {
    const details = assignmentOverlapDetails(
        [
            {
                shift_name: 'Load-out',
                starts_at: '2026-10-01T21:00',
                ends_at: '2026-10-02T00:00',
                overlap_minutes: 60,
            },
            {
                shift_name: 'Gate close',
                starts_at: '2026-10-01T21:30',
                ends_at: '2026-10-01T22:00',
                overlap_minutes: 30,
            },
        ],
        { starts_at: '2026-10-01T14:00', ends_at: '2026-10-01T22:00' },
        translate,
    );
    assert.ok(details.includes('2026-10-01 21:00–00:00'));
    assert.ok(details.includes('Overlaps this shift 21:00–22:00 (1 h)'));
    assert.ok(details.includes('Also on Gate close'));
    assert.equal(assignmentDurationLabel(90, translate), '1 h 30 min');
});

test('an empty role gives the switch hint and all-on-shift candidates stay disabled', async () => {
    const app = await mount();
    try {
        harness.noRoleMatches = true;
        await harness.reload();
        await settle();
        assert.ok(
            document.body.textContent.includes(
                'No one has the Crew role yet. Switch to Everyone to pick anyone.',
            ),
        );
        assert.equal(document.querySelector('#assign').disabled, true);
        harness.noRoleMatches = false;
        harness.onlyOnShift = true;
        await harness.reload();
        await settle();
        assert.ok(
            [...document.querySelectorAll('input[type=radio]')].every(
                (input) => input.disabled,
            ),
        );
        document.querySelector('tbody tr').click();
        await nextTick();
        assert.equal(document.querySelector('#assign').disabled, true);
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
    const requirement = {
        id: 'draft-12',
        role_id: 3,
        role_name: 'Crew',
        needed: 1,
        assigned_count: 0,
    };
    const app = await mount({
        deferred: true,
        requirement,
        onAssigned: (draft) => drafts.push(draft),
    });
    try {
        await choose('Alpha');
        const query = harness.requests.at(-1).searchParams;
        assert.equal(query.get('role_id'), '3');
        assert.equal(query.has('shift_role_slot_id'), false);
        document.querySelector('#assign').click();
        assert.equal(harness.writes.length, 0);
        assert.equal(drafts[0].slot_key, 'draft-12');
        assert.equal('shift_role_slot_id' in drafts[0], false);
    } finally {
        app.unmount();
    }
});
