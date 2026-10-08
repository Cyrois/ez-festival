import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { JSDOM } from 'jsdom';
import { parse, compileScript } from '@vue/compiler-sfc';
import {
    newShiftBreak,
    draftShiftBreaks,
    updateShiftBreak,
    shiftBreakDays,
    shiftBreakPayload,
    shiftBreakErrors,
    validateShiftBreaks,
    wallMinutes,
} from '../../resources/js/lib/shiftBreaks.js';

const options = { durations: [15, 30, 45, 60], default_duration: 15 };
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
const { createApp, h, ref, nextTick } = await import('vue');
const require = createRequire(import.meta.url);
const vueUrl = pathToFileURL(require.resolve('vue')).href;
const strings = JSON.parse(
    readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'),
);
globalThis.breakTestTranslate = (key, values = {}) =>
    Object.entries(values).reduce(
        (copy, [name, value]) => copy.replaceAll(`:${name}`, value),
        strings[key] ?? key,
    );
const compile = async (path, replacements = []) => {
    const { descriptor } = parse(
        readFileSync(new URL(path, import.meta.url), 'utf8'),
    );
    let source = compileScript(descriptor, { id: path, inlineTemplate: true })
        .content.replaceAll(/from ['"]vue['"]/g, `from '${vueUrl}'`)
        .replace(
            /import \{ cn \} from ['"].*?['"];?/,
            'const cn = (...values) => values.filter(Boolean).join(" ");',
        )
        .replace(
            /import \{ trans \} from ['"].*?['"];?/,
            'const trans = globalThis.breakTestTranslate;',
        )
        .replace(
            /import \{ Icon \} from ['"].*?['"];?/,
            'const Icon = { render: () => null };',
        );
    for (const [pattern, replacement] of replacements)
        source = source.replace(pattern, replacement);
    return `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
};
const buttonUrl = await compile(
    '../../resources/js/components/ui/button/Button.vue',
    [
        [/import \{ Link \} from ['"].*?['"];?/, "const Link = 'a';"],
        [
            /from ['"]\.\/buttonVariants['"]/,
            `from '${new URL('../../resources/js/components/ui/button/buttonVariants.js', import.meta.url).href}'`,
        ],
    ],
);
const inputUrl = await compile(
    '../../resources/js/components/ui/input/Input.vue',
);
const fieldUrl = await compile(
    '../../resources/js/components/ui/form-field/FormField.vue',
);
const dropdownTag = await compile('../../resources/js/components/ui/tag/Tag.vue', [
    [/from ['"].*?lib\/labelTokens['"]/, `from '${new URL('../../resources/js/lib/labelTokens.js', import.meta.url).href}'`],
    [/import \{ Icon \} from ['"].*?['"];?/, 'const Icon = { render: () => null };'],
]);
const dropdownCheckbox = await compile('../../resources/js/components/ui/checkbox/Checkbox.vue');
const dropdownAvatar = await compile('../../resources/js/components/ui/avatar/Avatar.vue', [
    [/from ['" ]class-variance-authority['"]/, `from '${pathToFileURL(require.resolve('class-variance-authority').replace('/dist/index.js', '/dist/index.mjs')).href}'`],
    [/import \{ Icon \} from ['"].*?['"];?/, 'const Icon = { render: () => null };'],
]);
const dropdownUrl = await compile(
    '../../resources/js/components/ui/custom-dropdown/CustomDropdown.vue',
    [
        [/import \{ Tag \} from ['"].*?['"];?/, `import Tag from '${dropdownTag}';`],
        [/import \{ Checkbox \} from ['"].*?['"];?/, `import Checkbox from '${dropdownCheckbox}';`],
        [/import \{ Avatar \} from ['"].*?['"];?/, `import Avatar from '${dropdownAvatar}';`],
        [
            /import \{ Input \} from ['"].*?['"];?/,
            `import Input from '${inputUrl}';`,
        ],
    ],
);
const iconButtonUrl = await compile(
    '../../resources/js/components/ui/icon-button/IconButton.vue',
    [
        [
            /import \{ Button \} from ['"].*?['"];?/,
            `import Button from '${buttonUrl}';`,
        ],
    ],
);
const cardTitleUrl = await compile(
    '../../resources/js/components/ui/card/CardTitle.vue',
);
const checkboxUrl = await compile('../../resources/js/components/ui/checkbox/Checkbox.vue');
const componentUrl = await compile(
    '../../resources/js/components/team/ShiftBreaks.vue',
    [
        [/from ['"].*?lib\/personalBreaks['"]/, `from '${new URL('../../resources/js/lib/personalBreaks.js', import.meta.url).href}'`],
        [/import \{ Checkbox \} from ['"].*?['"];?/, `import Checkbox from '${checkboxUrl}';`],
        [
            /import \{ CardTitle \} from ['"].*?['"];?/,
            `import CardTitle from '${cardTitleUrl}';`,
        ],
        [
            /import \{ Button \} from ['"].*?['"];?/,
            `import Button from '${buttonUrl}';`,
        ],
        [
            /import \{ Input \} from ['"].*?['"];?/,
            `import Input from '${inputUrl}';`,
        ],
        [
            /import \{ FormField \} from ['"].*?['"];?/,
            `import FormField from '${fieldUrl}';`,
        ],
        [
            /import \{ CustomDropdown \} from ['"].*?['"];?/,
            `import CustomDropdown from '${dropdownUrl}';`,
        ],
        [
            /import \{ IconButton \} from ['"].*?['"];?/,
            `import IconButton from '${iconButtonUrl}';`,
        ],
        [
            /from ['"].*?lib\/shiftBreaks['"]/,
            `from '${new URL('../../resources/js/lib/shiftBreaks.js', import.meta.url).href}'`,
        ],
    ],
);
const { default: ShiftBreaks } = await import(componentUrl);
const tick = async () => {
    await nextTick();
    await nextTick();
};
const mount = (
    initial = [],
    start = '2026-10-03T14:00',
    end = '2026-10-03T22:00',
) => {
    const rows = ref(initial);
    const bounds = ref({ start, end });
    const editable = ref(true);
    const busy = ref(false);
    const errors = ref({});
    const editor = ref(null);
    const app = createApp({
        setup: () => () =>
            h(ShiftBreaks, {
                ref: editor,
                modelValue: rows.value,
                options,
                startsAt: bounds.value.start,
                endsAt: bounds.value.end,
                editable: editable.value,
                busy: busy.value,
                errors: errors.value,
                disabledReason: 'Scheduling is read-only.',
                'onUpdate:modelValue': (value) => {
                    rows.value = value;
                },
                'onClear-error': (key, field) => {
                    if (errors.value[key]) delete errors.value[key][field];
                },
                'onClear-containment-errors': () => {
                    for (const row of Object.values(errors.value))
                        delete row.starts_at;
                },
            }),
    });
    app.config.globalProperties.$t = globalThis.breakTestTranslate;
    app.mount(document.getElementById('app'));
    return { app, rows, bounds, editable, busy, errors, editor };
};
const rowAt = (index) => document.querySelectorAll('[data-break-row]')[index];
const fill = async (element, value) => {
    element.value = value;
    element.dispatchEvent(new window.Event('input', { bubbles: true }));
    await tick();
};
const pick = async (row, label, option) => {
    row.querySelector(`[aria-label="${label}"]`).click();
    await tick();
    const button = [...document.querySelectorAll('[role="option"]')].find(
        (item) => item.textContent.trim() === option,
    );
    assert.ok(button, `Option ${option} should be available`);
    button.click();
    await tick();
};
const add = async () => {
    [...document.querySelectorAll('button')]
        .find((item) => item.textContent.trim() === 'Add break')
        .click();
    await tick();
};

test('floating dates support overnight and multi-day shifts without browser timezone or calendar rollover', () => {
    const previousTimezone = process.env.TZ;
    try {
        for (const timezone of ['America/Vancouver', 'Asia/Tokyo']) {
            process.env.TZ = timezone;
            assert.deepEqual(
                shiftBreakDays('2026-10-03T23:00', '2026-10-04T02:00'),
                ['2026-10-03', '2026-10-04'],
            );
            assert.deepEqual(
                shiftBreakDays('2026-10-03T23:00', '2026-10-04T00:00'),
                ['2026-10-03'],
            );
            assert.deepEqual(
                shiftBreakDays('2026-10-03T14:00', '2026-10-05T22:00'),
                ['2026-10-03', '2026-10-04', '2026-10-05'],
            );
            assert.equal(
                wallMinutes('2026-03-08T03:30') -
                    wallMinutes('2026-03-08T01:30'),
                120,
            );
        }
    } finally {
        if (previousTimezone === undefined) delete process.env.TZ;
        else process.env.TZ = previousTimezone;
    }
    assert.ok(Number.isNaN(wallMinutes('2026-02-30T10:00')));
    assert.deepEqual(shiftBreakDays('', ''), []);
});

test('draft serialization strips UI state and binds failed-save messages to submitted identities', () => {
    const saved = draftShiftBreaks([
        {
            id: 22,
            duration_minutes: 30,
            starts_at: '2026-10-04T00:30',
            sort_order: 2,
        },
        {
            id: 21,
            duration_minutes: 15,
            starts_at: '2026-10-03T23:30',
            sort_order: 0,
        },
    ]);
    const snapshot = [...saved];
    const errors = shiftBreakErrors(snapshot, {
        'breaks.1.starts_at': 'Server error',
    });
    saved.shift();
    assert.equal(errors[saved[0]._key].starts_at, 'Server error');
    assert.deepEqual(shiftBreakPayload(saved), [
        {
            id: 22,
            duration_minutes: 30,
            starts_at: '2026-10-04T00:30',
        },
    ]);
    const draft = updateShiftBreak(
        newShiftBreak(15, '2026-10-04'),
        '_time',
        '01:00',
    );
    assert.deepEqual(shiftBreakPayload([draft]), [
        { duration_minutes: 15, starts_at: '2026-10-04T01:00' },
    ]);
});

test('the editor validates, adds, edits and removes rows, rejects earlier overlaps and allows adjacent breaks', async () => {
    const { app, rows, editor } = mount();
    try {
        assert.match(document.body.textContent, /No breaks yet/);
        assert.equal(
            rowAt(0).querySelector('[aria-label="Length"]').textContent.trim(),
            '15 min',
        );
        assert.equal(
            rowAt(0).querySelector('input[type="time"]').value,
            '14:00',
        );
        await fill(rowAt(0).querySelector('input[type="time"]'), '');
        await add();
        assert.equal(rows.value.length, 0);
        assert.match(document.body.textContent, /Enter a valid break start/);
        await fill(rowAt(0).querySelector('input[type="time"]'), '15:30');
        await pick(rowAt(0), 'Length', '30 min');
        await add();
        assert.equal(rows.value.length, 1);
        assert.equal(rows.value[0].starts_at, '2026-10-03T15:30');
        assert.equal(rows.value[0].duration_minutes, 30);
        assert.equal(
            rowAt(0).querySelector('input[type="time"]').value,
            '14:00',
        );
        await fill(rowAt(1).querySelector('input[type="time"]'), '');
        assert.equal(editor.value.validate(), false);
        await fill(rowAt(1).querySelector('input[type="time"]'), '15:30');
        assert.equal(editor.value.validate(), true);
        await fill(rowAt(0).querySelector('input[type="time"]'), '15:15');
        await pick(rowAt(0), 'Length', '30 min');
        await add();
        assert.equal(rows.value.length, 1);
        assert.match(rowAt(0).textContent, /Breaks cannot overlap/);
        await fill(rowAt(0).querySelector('input[type="time"]'), '16:00');
        await add();
        assert.equal(rows.value.length, 2);
        rowAt(1).querySelector('button[aria-label^="Remove break"]').click();
        await tick();
        assert.equal(rows.value.length, 1);
        assert.equal(rows.value[0].starts_at, '2026-10-03T16:00');
    } finally {
        app.unmount();
    }
});

test('a shift start entered later defaults the pending break and preserves edited and added times', async () => {
    const { app, rows, bounds } = mount([], '', '');
    try {
        assert.equal(rowAt(0).querySelector('input[type="time"]').value, '');
        bounds.value = { start: '2026-10-03T23:00', end: '2026-10-04T02:00' };
        await tick();
        assert.equal(
            rowAt(0).querySelector('input[type="time"]').value,
            '23:00',
        );
        await add();
        assert.equal(rows.value[0].starts_at, '2026-10-03T23:00');
        assert.equal(
            rowAt(0).querySelector('input[type="time"]').value,
            '23:00',
        );
        await fill(rowAt(0).querySelector('input[type="time"]'), '23:30');
        bounds.value = { start: '2026-10-03T22:00', end: '2026-10-04T02:00' };
        await tick();
        assert.equal(
            rowAt(0).querySelector('input[type="time"]').value,
            '23:30',
        );
        assert.equal(rows.value[0].starts_at, '2026-10-03T23:00');
    } finally {
        app.unmount();
    }
});

test('overnight selection is explicit and changing shift dates preserves an invalid draft until corrected', async () => {
    const { app, rows, bounds, editor } = mount(
        [],
        '2026-10-03T23:00',
        '2026-10-04T02:00',
    );
    try {
        await fill(rowAt(0).querySelector('input[type="time"]'), '00:15');
        await add();
        assert.equal(rows.value.length, 0);
        assert.match(document.body.textContent, /whole break must fit/);
        await pick(rowAt(0), 'Day', '2026-10-04');
        await add();
        assert.equal(rows.value[0].starts_at, '2026-10-04T00:15');
        bounds.value = { start: '2026-10-03T14:00', end: '2026-10-03T22:00' };
        await tick();
        assert.equal(rows.value[0].starts_at, '2026-10-04T00:15');
        assert.equal(editor.value.validate(), false);
        assert.equal(
            rowAt(1).querySelector('[aria-label="Day"]').textContent.trim(),
            '2026-10-04',
        );
        await pick(rowAt(1), 'Day', '2026-10-03');
        await fill(rowAt(1).querySelector('input[type="time"]'), '15:30');
        assert.equal(editor.value.validate(), true);
        assert.equal(rows.value[0].starts_at, '2026-10-03T15:30');
        assert.equal(rowAt(1).querySelector('[aria-label="Day"]'), null);
    } finally {
        app.unmount();
    }
});

test('failed-save errors preserve rows and clear on correction; busy and readonly states expose no write path', async () => {
    const initial = draftShiftBreaks([
        {
            id: 1,
            duration_minutes: 15,
            starts_at: '2026-10-03T15:30',
            sort_order: 0,
        },
    ]);
    const { app, rows, errors, busy, editable } = mount(initial);
    try {
        errors.value = shiftBreakErrors([...rows.value], {
            'breaks.0.starts_at': 'Server rejected this time.',
        });
        await tick();
        assert.match(rowAt(1).textContent, /Server rejected this time/);
        assert.equal(rowAt(1).querySelector('input').value, '15:30');
        await fill(rowAt(1).querySelector('input'), '16:00');
        assert.doesNotMatch(
            document.body.textContent,
            /Server rejected this time/,
        );
        busy.value = true;
        await tick();
        assert.ok(
            [...document.querySelectorAll('input, button')].every(
                (item) => item.disabled,
            ),
        );
        await fill(rowAt(1).querySelector('input'), '17:00');
        assert.equal(rows.value[0].starts_at, '2026-10-03T16:00');
        busy.value = false;
        editable.value = false;
        await tick();
        assert.equal(document.querySelectorAll('input').length, 0);
        assert.equal(document.querySelectorAll('button').length, 1);
        assert.equal(document.querySelector('button').disabled, true);
        assert.match(document.body.textContent, /4:00/);
        assert.equal(
            document.querySelector('[tabindex="0"]').title,
            'Scheduling is read-only.',
        );
        assert.equal(
            document
                .querySelector('[tabindex="0"]')
                .getAttribute('aria-describedby'),
            document.querySelector('.sr-only').id,
        );
    } finally {
        app.unmount();
    }
});

test('the editor offers longer lengths without name controls and serializes them', async () => {
    const { app, rows } = mount();
    try {
        assert.equal(document.querySelector('[aria-label="Name"]'), null);
        await fill(rowAt(0).querySelector('input[type="time"]'), '15:00');
        await pick(rowAt(0), 'Length', '45 min');
        await add();
        await fill(rowAt(0).querySelector('input[type="time"]'), '16:00');
        await pick(rowAt(0), 'Length', '1 hour');
        await add();
        assert.deepEqual(shiftBreakPayload(rows.value), [
            { duration_minutes: 45, starts_at: '2026-10-03T15:00' },
            { duration_minutes: 60, starts_at: '2026-10-03T16:00' },
        ]);
        await pick(rowAt(1), 'Length', '1 hour');
        assert.equal(rows.value[0].duration_minutes, 60);
    } finally {
        app.unmount();
    }
});

test('invalid edited lengths, partial-end breaks and missing fields are found before page submission', () => {
    const row = updateShiftBreak(
        newShiftBreak(15, '2026-10-03'),
        '_time',
        '21:50',
    );
    assert.equal(
        validateShiftBreaks(
            [row],
            '2026-10-03T14:00',
            '2026-10-03T22:00',
            options.durations,
        )[row._key].starts_at,
        'team.scheduling.breaks.errors.containment',
    );
    row.duration_minutes = 90;
    assert.equal(
        validateShiftBreaks(
            [row],
            '2026-10-03T14:00',
            '2026-10-03T22:00',
            options.durations,
        )[row._key].duration_minutes,
        'team.scheduling.breaks.errors.duration',
    );
});


test('an invalid staged date renders safely during the read-only state after a refused Save', async () => {
    const {app, editable} = mount([newShiftBreak(15, '2026-10-03', '')]);
    try {
        editable.value = false;
        await tick();
        assert.match(document.body.textContent, /Enter a valid break start time and day/);
    } finally {app.unmount();}
});
