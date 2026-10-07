import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { JSDOM } from 'jsdom';
import { parse, compileScript } from '@vue/compiler-sfc';
import { draftShiftMeals, removeMealRecipient, shiftMealPayload, shiftMealOptions, shiftMealErrors } from '../../resources/js/lib/shiftMeals.js';

const dom = new JSDOM('<div id="app"></div>');
for (const name of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node']) globalThis[name] = dom.window[name];
const { createApp, h, ref, nextTick } = await import('vue');
const require = createRequire(import.meta.url);
const vueUrl = pathToFileURL(require.resolve('vue')).href;
const strings = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'));
const trans = (key, values = {}) => Object.entries(values).reduce((text, [name, value]) => text.replaceAll(`:${name}`, value), strings[key] ?? key);
globalThis.shiftMealTestTranslate = trans;
const compile = async (path, replacements = []) => {
    const { descriptor } = parse(readFileSync(new URL(path, import.meta.url), 'utf8'));
    let source = compileScript(descriptor, { id: path, inlineTemplate: true }).content
        .replaceAll(/from ['"]vue['"]/g, `from '${vueUrl}'`)
        .replace(/import \{ cn \} from ['"].*?['"];?/, 'const cn = (...values) => values.filter(Boolean).join(" ");')
        .replace(/import \{ Icon \} from ['"].*?['"];?/, 'const Icon = { render: () => null };')
        .replace(/import \{ trans \} from ['"].*?['"];?/, 'const trans = globalThis.shiftMealTestTranslate;')
        .replace(/import \{ getActiveLanguage, trans \} from ['"].*?['"];?/, 'const getActiveLanguage = () => "en"; const trans = globalThis.shiftMealTestTranslate;');
    for (const [pattern, replacement] of replacements) source = source.replace(pattern, replacement);
    return `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
};
const input = await compile('../../resources/js/components/ui/input/Input.vue');
const field = await compile('../../resources/js/components/ui/form-field/FormField.vue');
const tag = await compile('../../resources/js/components/ui/tag/Tag.vue', [[/from ['"].*?lib\/labelTokens['"]/, `from '${new URL('../../resources/js/lib/labelTokens.js', import.meta.url).href}'`]]);
const checkbox = await compile('../../resources/js/components/ui/checkbox/Checkbox.vue');
const avatar = await compile('../../resources/js/components/ui/avatar/Avatar.vue', [[/from ['"]class-variance-authority['"]/, `from '${pathToFileURL(require.resolve('class-variance-authority').replace('/dist/index.js', '/dist/index.mjs')).href}'`]]);
const dropdown = await compile('../../resources/js/components/ui/custom-dropdown/CustomDropdown.vue', [
    [/import \{ Input \} from ['"].*?['"];?/, `import Input from '${input}';`],
    [/import \{ Tag \} from ['"].*?['"];?/, `import Tag from '${tag}';`],
    [/import \{ Checkbox \} from ['"].*?['"];?/, `import Checkbox from '${checkbox}';`],
    [/import \{ Avatar \} from ['"].*?['"];?/, `import Avatar from '${avatar}';`],
]);
globalThis.shiftMealTestIconButton = { props: ['label', 'disabled'], setup: (p, { attrs }) => () => h('button', { ...attrs, disabled: p.disabled, 'aria-label': p.label }, p.label) };
const mealsUrl = await compile('../../resources/js/components/team/ShiftMeals.vue', [
    [/import \{ Avatar \} from ['"].*?['"];?/, `import Avatar from '${avatar}';`],
    [/import \{ CardTitle \} from ['"].*?['"];?/, "const CardTitle = 'h2';"],
    [/import \{ Button \} from ['"].*?['"];?/, "const Button = 'button';"],
    [/import \{ IconButton \} from ['"].*?['"];?/, 'const IconButton = globalThis.shiftMealTestIconButton;'],
    [/import \{ Tag \} from ['"].*?['"];?/, `import Tag from '${tag}';`],
    [/import \{ CustomDropdown \} from ['"].*?['"];?/, `import CustomDropdown from '${dropdown}';`],
    [/import \{ FormField \} from ['"].*?['"];?/, `import FormField from '${field}';`],
    [/from ['"].*?lib\/shiftMeals['"]/, `from '${new URL('../../resources/js/lib/shiftMeals.js', import.meta.url).href}'`],
    [/from ['"].*?lib\/mealDates['"]/, `from '${new URL('../../resources/js/lib/mealDates.js', import.meta.url).href}'`],
]);
const { default: Meals } = await import(mealsUrl);
const person = (id, name) => ({ id, name, role_name: 'Volunteer', starts_at: '2026-10-01T12:00', ends_at: '2026-10-01T22:00' });
const meal = (id, name, starts_at = '17:30', ends_at = '19:30', date = '2026-10-01') => ({ id, name, date, starts_at, ends_at, meal_type: { name: 'Dinner' } });
const options = [meal(1, 'Dinner'), meal(2, 'Late dinner', '20:00', '21:30')];
const tick = async () => { await nextTick(); await nextTick(); };
const mount = (overrides = {}) => {
    const rows = ref(overrides.modelValue ?? []);
    const people = ref([person(8, 'Ava Lee'), person(9, 'Ben Cruz')]);
    const app = createApp({ setup: () => () => h(Meals, { options, startsAt: '2026-10-01T12:00', endsAt: '2026-10-01T22:00', ...overrides, people: people.value, modelValue: rows.value, 'onUpdate:modelValue': (value) => { rows.value = value; } }) });
    app.config.globalProperties.$t = trans;
    app.mount(document.getElementById('app'));
    return { app, rows, people };
};
const trigger = (index = 0) => document.querySelectorAll('[aria-haspopup="listbox"]')[index];
const add = () => [...document.querySelectorAll('button')].find((button) => button.textContent.trim() === 'Add meal');
const pickMeal = async () => { trigger().click(); await tick(); document.querySelector('[role="option"]').click(); await tick(); };

test('suggestions use overlap, whole-span midpoint, earlier/name ties, and event-local days', () => {
    const rows = [meal(1, 'Zulu', '16:00'), meal(2, 'Alpha', '16:00'), meal(3, 'Evening', '18:00'), meal(4, 'Morning', '08:00', '09:00'), meal(5, 'Other day', '17:00', '18:00', '2026-10-02')];
    const result = shiftMealOptions(rows, '2026-10-01T12:00', '2026-10-01T22:00');
    assert.deepEqual(result.suggested.map((row) => row.id), [2, 1, 3]);
    assert.deepEqual(result.other.map((row) => row.id), [4]);
    assert.deepEqual(shiftMealOptions(rows, '2026-10-01T12:00', '2026-10-01T22:00', [2]).suggested.map((row) => row.id), [1, 3]);
    const overnight = [meal(6, 'Midnight', '23:30', '01:30'), meal(7, 'Early', '00:30', '01:30', '2026-10-02')];
    assert.deepEqual(shiftMealOptions(overnight, '2026-10-01T23:00', '2026-10-02T03:00').suggested.map((row) => row.id), [7, 6]);
    assert.deepEqual(shiftMealOptions(overnight, '2026-10-01T23:00', '2026-10-02T00:00').days, ['2026-10-01']);
    const boundary = shiftMealOptions([meal(8, 'Before', '10:00', '12:00'), meal(9, 'After', '22:00', '23:00')], '2026-10-01T12:00', '2026-10-01T22:00');
    assert.equal(boundary.suggested.length, 0);
    assert.equal(boundary.other.length, 2);
    assert.deepEqual(shiftMealOptions(rows, '', '').days, []);
});

test('adding snapshots current recipients without an automatic selection or later grants', async () => {
    const { app, rows, people } = mount();
    try {
        assert.equal(rows.value.length, 0);
        assert.equal(trigger(1).textContent.trim(), 'Pick people');
        assert.ok(trigger(1).classList.contains('h-10'));
        assert.equal(trigger(1).querySelector('button[aria-label]'), null);
        assert.equal(document.querySelector('button[aria-label="Remove Ava Lee"]'), null);
        assert.equal(trigger().textContent.trim(), 'Pick a meal');
        assert.doesNotMatch(document.body.textContent, /Everyone on the shift will be entitled/);
        people.value = []; await tick();
        people.value = [person(8, 'Ava Lee'), person(9, 'Ben Cruz')]; await tick();
        assert.equal(document.querySelector('button[aria-label="Remove Ava Lee"]'), null);
        trigger(1).click(); await tick();
        for (const option of document.querySelectorAll('[role="option"]')) {
            assert.equal(option.getAttribute('aria-selected'), 'false');
        }
        const selectAll = [...document.querySelectorAll('button')].find((button) => button.textContent.trim() === 'Select all');
        assert.ok(selectAll);
        selectAll.click(); await tick();
        for (const option of document.querySelectorAll('[role="option"]')) {
            assert.equal(option.getAttribute('aria-selected'), 'true');
        }
        trigger(1).click(); await tick();
        await pickMeal();
        add().click(); await tick();
        assert.deepEqual(rows.value[0].assignment_keys, [8, 9]);
        assert.equal(document.querySelector('button[aria-label="Remove Ava Lee"]').closest('.bg-page'), null);
        people.value = [...people.value, person(10, 'Cara Finn')]; await tick();
        assert.deepEqual(rows.value[0].assignment_keys, [8, 9]);
        trigger().click(); await tick();
        assert.equal(document.querySelector('input[type="search"]'), null);
        assert.equal(document.querySelectorAll('[role="option"]').length, 2);
        assert.deepEqual(shiftMealPayload(rows.value), [{ meal_id: 1, assignment_keys: [8, 9] }]);
    } finally { app.unmount(); }
});

test('recipient search shows names and roles without avatars or hours; adds merge with existing recipients', async () => {
    const row = draftShiftMeals([{ meal_id: 1, meal: options[0], assignment_keys: [8] }]);
    const { app, rows, people } = mount({ modelValue: row });
    try {
        assert.equal(document.querySelectorAll('[aria-haspopup="listbox"]').length, 2);
        people.value = [...people.value, person(11, 'Dee'), person(12, 'Eli'), person(13, 'Finn')]; await tick();
        trigger(1).click(); await tick();
        const search = document.querySelector('input[type="search"]');
        assert.equal(search.placeholder, 'Search people on this shift');
        assert.equal(document.querySelectorAll('[role="listbox"] [data-ui="avatar"]').length, 0);
        assert.match(document.querySelector('[role="option"]').textContent, /Volunteer/);
        assert.doesNotMatch(document.querySelector('[role="option"]').textContent, /12:00|22:00/);
        const action = [...document.querySelectorAll('button')].find((button) => button.textContent.includes('Select everyone on the shift (2)'));
        assert.equal(action, undefined);
        people.value = [...people.value, person(10, 'Cara Finn')]; await tick();
        assert.deepEqual(rows.value[0].assignment_keys, [8]);
        search.value = 'Cara'; search.dispatchEvent(new window.Event('input', { bubbles: true })); await tick();
        assert.equal(document.querySelectorAll('[role="option"]').length, 1);
        search.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true })); await tick();
        assert.equal(document.activeElement.getAttribute('role'), 'option');
        document.activeElement.dispatchEvent(new window.KeyboardEvent('keydown', { key: ' ', bubbles: true })); await tick();
        assert.deepEqual(rows.value[0].assignment_keys, [8]);
        document.activeElement.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true })); await tick();
        assert.equal(document.activeElement.getAttribute('aria-haspopup'), 'listbox');
        assert.equal(document.querySelector('[role="listbox"]'), null);
        await pickMeal();
        add().click(); await tick();
        assert.equal(rows.value.length, 1);
        assert.deepEqual(rows.value[0].assignment_keys, [8, 10]);
        const chip = [...document.querySelectorAll('button[aria-label="Remove Cara Finn"]')].at(-1);
        chip.click(); await tick();
        assert.deepEqual(rows.value[0].assignment_keys, [8]);
    } finally { app.unmount(); }
});

test('removing the last recipient keeps an invalid row for correction or removal, with errors mapped to stable keys', async () => {
    const rows = draftShiftMeals([{ id: 12, meal_id: 1, meal: options[0], assignment_ids: [8] }]);
    const cleared = removeMealRecipient(rows, 8);
    assert.equal(cleared.length, 1);
    assert.deepEqual(cleared[0].assignment_keys, []);
    assert.deepEqual(rows[0].assignment_keys, [8]);
    const errors = shiftMealErrors(cleared, { 'meals.0.assignment_keys.0': 'Not on this shift' });
    assert.equal(errors['saved-meal-12'].assignment_keys, 'Not on this shift');
    const { app } = mount({ modelValue: cleared });
    try { assert.match(document.body.textContent, /Pick who gets this meal, or remove it/); }
    finally { app.unmount(); }
});

test('empty roster disables adding; read-only rows have no recipient controls or trash; copy only shows its warning', async () => {
    let state = mount();
    state.people.value = []; await tick();
    assert.equal(add().disabled, true);
    assert.equal(add().parentElement.title, 'Add people to the shift first.');
    state.app.unmount();
    state = mount({ editable: false, modelValue: draftShiftMeals([{ meal_id: 1, meal: options[0], assignment_ids: [8] }]), disabledReason: 'View only' });
    assert.equal(add().disabled, true);
    assert.equal(document.querySelectorAll('[aria-haspopup="listbox"]').length, 2);
    assert.equal(document.querySelector('button[aria-label="Remove Dinner"]'), null);
    state.app.unmount();
    state = mount({ copying: true });
    assert.match(document.querySelector('[role="status"]').textContent, /Meals can[’']t be copied/);
    assert.equal(document.querySelector('[aria-haspopup="listbox"]'), null);
    assert.equal(add(), undefined);
    assert.doesNotMatch(document.body.textContent, /Choose who|Add meal adds/);
    state.app.unmount();
});
