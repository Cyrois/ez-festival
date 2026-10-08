import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { JSDOM } from 'jsdom';
import { parse, compileScript } from '@vue/compiler-sfc';

const dom = new JSDOM('<div id="app"></div>');
for (const key of [
    'window',
    'document',
    'Element',
    'HTMLElement',
    'SVGElement',
    'Node',
]) {
    globalThis[key] = dom.window[key];
}
const { createApp, h, ref, nextTick } = await import('vue');
const require = createRequire(import.meta.url);
const vueUrl = pathToFileURL(require.resolve('vue')).href;
const compile = async (path, replacements = []) => {
    const { descriptor } = parse(
        readFileSync(new URL(path, import.meta.url), 'utf8'),
    );
    let source = compileScript(descriptor, { id: path, inlineTemplate: true })
        .content.replaceAll(/from ['"]vue['"]/g, `from '${vueUrl}'`)
        .replace(
            /import \{ cn \} from ['"].*?['"];?/,
            'const cn = (...values) => values.filter(Boolean).join(" ");',
        );
    for (const [pattern, replacement] of replacements)
        source = source.replace(pattern, replacement);
    return `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
};
const inputUrl = await compile(
    '../../resources/js/components/ui/input/Input.vue',
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
        [
            /import \{ Icon \} from ['"].*?['"];?/,
            'const Icon = { render: () => null };',
        ],
        [
            /import \{ trans \} from ['"].*?['"];?/,
            'const trans = (key) => key;',
        ],
    ],
);
const { default: CustomDropdown } = await import(dropdownUrl);
const tick = async () => {
    await nextTick();
    await nextTick();
};
const mount = (count, modal = false) => {
    const items = ref(
        Array.from({ length: count }, (_, index) => ({
            value: index,
            title: `Option ${index}`,
            description: index === 5 ? 'Special description' : '',
            disabled: index === 0,
        })),
    );
    const selected = ref(2);
    const app = createApp({
        setup: () => () =>
            h('div', modal ? { 'aria-modal': 'true' } : {}, [
                h(CustomDropdown, {
                    items: items.value,
                    modelValue: selected.value,
                    'onUpdate:modelValue': (value) => {
                        selected.value = value;
                    },
                }),
            ]),
    });
    app.mount(document.getElementById('app'));
    return { app, items, selected, trigger: document.querySelector('button') };
};
const search = async (value) => {
    const input = document.querySelector('input');
    input.value = value;
    input.dispatchEvent(new window.Event('input', { bubbles: true }));
    await tick();
    return input;
};
const key = async (element, value) => {
    element.dispatchEvent(
        new window.KeyboardEvent('keydown', { key: value, bubbles: true }),
    );
    await tick();
};

test('four options keep the compact menu; five enable search and bounded scrolling', async () => {
    const { app, items, trigger } = mount(4);
    try {
        trigger.click();
        await tick();
        assert.equal(document.querySelector('input'), null);
        assert.equal(document.querySelector('.max-h-64'), null);
        assert.equal(document.activeElement.textContent.trim(), 'Option 2');
        trigger.click();
        await tick();
        items.value.push({ value: 4, title: 'Fifth option' });
        await tick();
        trigger.click();
        await tick();
        assert.equal(document.activeElement, document.querySelector('input'));
        assert.ok(document.querySelector('.max-h-64'));
        assert.ok(
            document
                .querySelector('[role="listbox"]')
                .classList.contains('overflow-y-auto'),
        );
    } finally {
        app.unmount();
    }
});

test('search filters titles and descriptions, shows no matches, and resets on reopening', async () => {
    const { app, selected, trigger } = mount(6);
    try {
        trigger.click();
        await tick();
        await search('  SPECIAL  ');
        assert.equal(document.querySelectorAll('[role="option"]').length, 1);
        assert.equal(selected.value, 2);
        await search('missing');
        assert.equal(document.querySelectorAll('[role="option"]').length, 0);
        assert.match(document.body.textContent, /dropdown.no_results/);
        const input = await search('OPTION 4');
        await key(input, 'ArrowDown');
        assert.equal(document.activeElement.textContent.trim(), 'Option 4');
        await key(document.activeElement, 'Enter');
        assert.equal(selected.value, 4);
        assert.equal(document.querySelector('[role="listbox"]'), null);
        trigger.click();
        await tick();
        assert.equal(document.querySelector('input').value, '');
        assert.equal(document.querySelectorAll('[role="option"]').length, 6);
    } finally {
        app.unmount();
    }
});

test('dialog search stays in the teleported menu and keyboard navigation skips disabled options', async () => {
    const { app, selected, trigger } = mount(7, true);
    try {
        trigger.click();
        await tick();
        const input = document.querySelector('input');
        assert.equal(
            document.querySelector('[aria-modal="true"]').contains(input),
            false,
        );
        await key(input, 'ArrowDown');
        assert.equal(document.activeElement.textContent.trim(), 'Option 1');
        await key(document.activeElement, 'ArrowUp');
        assert.equal(document.activeElement, input);
        await search('Option 0');
        await key(input, 'Enter');
        assert.equal(selected.value, 2);
        assert.ok(document.querySelector('[role="listbox"]'));
        await key(input, 'Escape');
        assert.equal(document.querySelector('[role="listbox"]'), null);
        assert.equal(document.activeElement, trigger);
        trigger.click();
        await tick();
        document.body.dispatchEvent(
            new window.Event('pointerdown', { bubbles: true }),
        );
        await tick();
        assert.equal(document.querySelector('[role="listbox"]'), null);
    } finally {
        app.unmount();
    }
});
