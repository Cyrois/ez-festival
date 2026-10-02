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
const source = readFileSync(
    new URL(
        '../../resources/js/components/ui/radio/Radio.vue',
        import.meta.url,
    ),
    'utf8',
);
const { descriptor } = parse(source);
const compiled = compileScript(descriptor, {
    id: 'radio-test',
    inlineTemplate: true,
});
const moduleSource = compiled.content
    .replaceAll(
        /from ['"]vue['"]/g,
        `from '${pathToFileURL(require.resolve('vue')).href}'`,
    )
    .replace(
        /import \{ cn \} from ['"].*?['"];?/,
        'const cn = (...values) => values.filter(Boolean).join(" ");',
    );
const { default: Radio } = await import(
    `data:text/javascript;base64,${Buffer.from(moduleSource).toString('base64')}`
);

test('radio selection survives keyed row moves, new rows and repeated clicks', async () => {
    const rows = ref(['a', 'b']);
    const selected = ref('');
    const app = createApp({
        setup() {
            return () =>
                h(
                    'div',
                    [...rows.value]
                        .sort(
                            (a, b) =>
                                Number(b === selected.value) -
                                Number(a === selected.value),
                        )
                        .map((value) =>
                            h(Radio, {
                                key: value,
                                name: 'selection',
                                value,
                                label: value,
                                modelValue: selected.value,
                                'onUpdate:modelValue': (value) => {
                                    selected.value = value;
                                },
                            }),
                        ),
                );
        },
    });
    app.mount(document.getElementById('app'));
    try {
        const choose = async (value) => {
            document.querySelector(`input[value="${value}"]`).click();
            await nextTick();
            assert.equal(selected.value, value);
            assert.deepEqual(
                [...document.querySelectorAll('input:checked')].map(
                    (input) => input.value,
                ),
                [value],
            );
        };
        await choose('a');
        await choose('b');
        await choose('a');
        rows.value.push('c');
        await nextTick();
        await choose('c');
        await choose('b');
        await choose('c');
    } finally {
        app.unmount();
    }
});
