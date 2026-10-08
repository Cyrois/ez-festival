import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { JSDOM } from 'jsdom';
import { parse, compileScript } from '@vue/compiler-sfc';
import { mealDateLabel, mealNextDayLabel } from '../../resources/js/lib/mealDates.js';

const dom = new JSDOM('<div id="app"></div>');
for (const key of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node']) {
    globalThis[key] = dom.window[key];
}
const { createApp, reactive, nextTick } = await import('vue');
const require = createRequire(import.meta.url);
const locale = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'));
globalThis.mealTestTranslate = (key, values = {}) => Object.entries(values).reduce(
    (text, [name, value]) => text.replaceAll(`:${name}`, String(value)), locale[key] ?? key,
);
const compile = async (path, replacements = []) => {
    const { descriptor } = parse(readFileSync(new URL(path, import.meta.url), 'utf8'));
    let source = compileScript(descriptor, { id: path, inlineTemplate: true }).content
        .replaceAll(/from ['"]vue['"]/g, `from '${pathToFileURL(require.resolve('vue')).href}'`)
        .replace(/import \{ cn \} from ['"].*?['"];?/, 'const cn = (...values) => values.filter(Boolean).join(" ");')
        .replace(/import \{ (?:getActiveLanguage, )?trans \} from ['"]laravel-vue-i18n['"];?/, 'const trans = globalThis.mealTestTranslate; const getActiveLanguage = () => "en";')
        .replace(/import \{ Icon \} from ['"].*?['"];?/, 'const Icon = { render: () => null };');
    for (const [pattern, replacement] of replacements) source = source.replace(pattern, replacement);
    return `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
};
const input = await compile('../../resources/js/components/ui/input/Input.vue');
const field = await compile('../../resources/js/components/ui/form-field/FormField.vue');
const title = await compile('../../resources/js/components/ui/card/CardTitle.vue');
const card = await compile('../../resources/js/components/ui/card/Card.vue', [
    [/import CardTitle from ['"].*?['"];?/, `import CardTitle from '${title}';`],
]);
const dropdownTag = await compile('../../resources/js/components/ui/tag/Tag.vue', [
    [/from ['"].*?lib\/labelTokens['"]/, `from '${new URL('../../resources/js/lib/labelTokens.js', import.meta.url).href}'`],
    [/import \{ Icon \} from ['"].*?['"];?/, 'const Icon = { render: () => null };'],
]);
const dropdownCheckbox = await compile('../../resources/js/components/ui/checkbox/Checkbox.vue');
const dropdownAvatar = await compile('../../resources/js/components/ui/avatar/Avatar.vue', [
    [/from ['" ]class-variance-authority['"]/, `from '${pathToFileURL(require.resolve('class-variance-authority').replace('/dist/index.js', '/dist/index.mjs')).href}'`],
    [/import \{ Icon \} from ['"].*?['"];?/, 'const Icon = { render: () => null };'],
]);
const dropdown = await compile('../../resources/js/components/ui/custom-dropdown/CustomDropdown.vue', [
        [/import \{ Tag \} from ['"].*?['"];?/, `import Tag from '${dropdownTag}';`],
        [/import \{ Checkbox \} from ['"].*?['"];?/, `import Checkbox from '${dropdownCheckbox}';`],
        [/import \{ Avatar \} from ['"].*?['"];?/, `import Avatar from '${dropdownAvatar}';`],
    [/import \{ Input \} from ['"].*?['"];?/, `import Input from '${input}';`],
]);
const button = await compile('../../resources/js/components/ui/button/Button.vue', [
    [/import \{ Link \} from ['"].*?['"];?/, "const Link = 'a';"],
    [/import \{ buttonVariants \} from ['"].*?['"];?/, 'const buttonVariants = () => "";'],
]);
const editor = await compile('../../resources/js/pages/Kitchen/MealEditor.vue', [
    [/import AppLayout from ['"].*?['"];?/, 'const AppLayout = { inheritAttrs: false, setup: (props, { slots }) => () => slots.default?.() };'],
    [/import \{ Button \} from ['"].*?['"];?/, `import Button from '${button}';`],
    [/import \{ Card, CardTitle \} from ['"].*?['"];?/, `import Card from '${card}'; import CardTitle from '${title}';`],
    [/import \{ CustomDropdown \} from ['"].*?['"];?/, `import CustomDropdown from '${dropdown}';`],
    [/import \{ FormField \} from ['"].*?['"];?/, `import FormField from '${field}';`],
    [/import \{ Input \} from ['"].*?['"];?/, `import Input from '${input}';`],
    [/import \{ useForm \} from ['"].*?['"];?/, 'const useForm = (initial) => globalThis.mealTestUseForm(initial);'],
    [/import \{ useFlashToast \} from ['"].*?['"];?/, 'const useFlashToast = () => ({ showError: () => {}, showFormError: () => {} });'],
    [/import \{ toastFormErrors \} from ['"].*?['"];?/, 'const toastFormErrors = () => {};'],
    [/from ['"]\.\.\/\.\.\/lib\/mealDates['"]/, `from '${new URL('../../resources/js/lib/mealDates.js', import.meta.url).href}'`],
]);
const { default: MealEditor } = await import(editor);
const tick = async () => { await nextTick(); await nextTick(); };
const types = [
    { id: 4, name: 'Lunch', starts_at: '11:00', ends_at: '14:00' },
    { id: 7, name: 'Dinner', starts_at: '17:00', ends_at: '20:00' },
];
const mount = (meal = null, canWrite = true) => {
    let form;
    const submissions = [];
    globalThis.mealTestUseForm = (initial) => {
        form = reactive({ ...initial, processing: false, errors: {},
            post: (url) => submissions.push({ method: 'post', url }),
            put: (url) => submissions.push({ method: 'put', url }),
        });
        return form;
    };
    const app = createApp(MealEditor, {
        event: { id: 8, name: 'Festival', starts_on: '2027-07-10', ends_on: '2027-07-12', is_locked: !canWrite },
        meal, mealTypes: types, canWrite,
    });
    app.config.globalProperties.$t = globalThis.mealTestTranslate;
    app.mount(document.getElementById('app'));
    return { app, form, submissions };
};
const choose = async (name) => {
    document.querySelector('[aria-haspopup="listbox"]').click();
    await tick();
    [...document.querySelectorAll('[role="option"]')].find((option) => option.textContent.includes(name)).click();
    await tick();
};
const enter = async (selector, value) => {
    const input = document.querySelector(selector);
    input.value = value;
    input.dispatchEvent(new window.Event('input', { bubbles: true }));
    await tick();
};

test('Add copies the selected type window and replaces typed times on the next type selection', async () => {
    const { app, form, submissions } = mount();
    try {
        await choose('Dinner');
        assert.equal(form.starts_at, '17:00');
        assert.equal(form.ends_at, '20:00');
        await enter('input[type="time"]', '18:30');
        await choose('Lunch');
        assert.equal(form.starts_at, '11:00');
        assert.equal(form.ends_at, '14:00');
        assert.match(document.body.textContent, /Filled in from Lunch/);
        await enter('input[type="date"]', '2027-06-30');
        assert.equal(form.date, '2027-06-30');
        assert.equal(document.querySelector('input[type="date"]').validity.rangeUnderflow, false);
        document.querySelector('form').dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
        assert.deepEqual(submissions, [{ method: 'post', url: '/events/8/meals' }]);
    } finally { app.unmount(); }
});

test('Edit retains the saved window when its type changes, updates the hint and submits the same meal', async () => {
    const { app, form, submissions } = mount({ id: 15, name: 'Fri Dinner', meal_type_id: 7, date: '2027-07-10', starts_at: '17:30', ends_at: '19:30' });
    try {
        await choose('Lunch');
        assert.equal(form.starts_at, '17:30');
        assert.equal(form.ends_at, '19:30');
        assert.match(document.body.textContent, /Lunch’s window is 11:00–14:00/);
        assert.match(document.body.textContent, /Changes apply to this meal/);
        assert.equal(document.querySelector('input[type="date"]').value, '2027-07-10');
        await enter('input[type="date"]', '2027-10-07');
        assert.equal(form.date, '2027-10-07');
        assert.equal(document.querySelector('input[type="date"]').validity.rangeOverflow, false);
        document.querySelector('form').dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
        assert.deepEqual(submissions, [{ method: 'put', url: '/events/8/meals/15' }]);
    } finally { app.unmount(); }
});

test('locked editor disables every field and refuses submission', () => {
    const { app, submissions } = mount(null, false);
    try {
        assert.ok([...document.querySelectorAll('input, [aria-haspopup="listbox"], button[type="submit"]')].every((control) => control.disabled));
        document.querySelector('form').dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
        assert.deepEqual(submissions, []);
    } finally { app.unmount(); }
});

test('calendar labels keep the event day and the next-day weekday across month, year and DST boundaries', () => {
    assert.equal(mealDateLabel('2026-12-31', 'en'), 'Thu, Dec 31');
    assert.equal(mealNextDayLabel('2026-12-31', 'en'), 'Fri');
    assert.equal(mealNextDayLabel('2026-03-07', 'en'), 'Sun');
});

 test('assigned meal editor explains the restriction and refuses edits', () => {
    const { app, submissions } = mount({ id: 15, name: 'Fri Dinner', meal_type_id: 7, date: '2027-07-10', starts_at: '17:30', ends_at: '19:30', assigned_to_shifts: true }, false);
    try {
        assert.match(document.body.textContent, /Remove it from all shifts first/);
        assert.ok([...document.querySelectorAll('input, button[type="submit"]')].every((control) => control.disabled));
        document.querySelector('form').dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
        assert.deepEqual(submissions, []);
    } finally { app.unmount(); }
});
