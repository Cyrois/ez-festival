import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import test from 'node:test';
import { parse, compileScript } from '@vue/compiler-sfc';
import { baseParse } from '@vue/compiler-dom';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

const root = new URL('../../resources/js/', import.meta.url);
const require = createRequire(import.meta.url);
const vueUrl = pathToFileURL(require.resolve('vue')).href;
const encode = (source) =>
    `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
const compile = (path) => {
    const { descriptor } = parse(readFileSync(new URL(path, root), 'utf8'));
    return compileScript(descriptor, {
        id: path,
        inlineTemplate: true,
    }).content.replaceAll(/from ['"]vue['"]/g, `from '${vueUrl}'`);
};
const titleUrl = encode(compile('components/ui/card/CardTitle.vue'));
const { default: CardTitle } = await import(titleUrl);
const { default: Card } = await import(
    encode(
        compile('components/ui/card/Card.vue')
            .replace("from './CardTitle.vue'", `from '${titleUrl}'`)
            .replace(
                "from '../../../lib/utils'",
                `from '${new URL('lib/utils.js', root).href}'`,
            ),
    )
);
const render = async (component, props, slots) =>
    (
        await renderToString(
            createSSRApp({ render: () => h(component, props, slots) }),
        )
    ).replace(/<!--[\s\S]*?-->/g, '');
const typography = 'm-0 text-xl font-bold text-muted';

test('card titles render slot content, heading levels, IDs and caller spacing', async () => {
    for (const as of ['h2', 'h3', 'h4']) {
        const html = await render(
            CardTitle,
            { as, id: 'details', class: 'mb-4' },
            {
                default: () => [
                    'Details',
                    h('span', { class: 'text-danger' }, '*'),
                ],
            },
        );
        assert.match(
            html,
            new RegExp(`^<${as} class="${typography} mb-4" id="details">`),
        );
        assert.match(html, /Details<span class="text-danger">\*<\/span>/);
    }
});

test('built-in and custom card titles both default to h2 with identical typography', async () => {
    const builtIn = await render(Card, { title: 'Form' });
    const custom = await render(
        Card,
        {},
        {
            header: () => h(CardTitle, {}, () => 'Fields'),
        },
    );
    assert.match(builtIn, new RegExp(`<h2 class="${typography}">Form</h2>`));
    assert.match(custom, new RegExp(`<h2 class="${typography}">Fields</h2>`));
    assert.doesNotMatch(await render(Card, {}), /<h[234]/);
});

test('Custom Fields parent and nested editor titles share the visual hierarchy', () => {
    const source = readFileSync(
        new URL('pages/Settings/CustomFields.vue', root),
        'utf8',
    );
    assert.match(
        source,
        /<CardTitle>\s*\{\{ \$t\(`settings\.custom_fields\.target/,
    );
    assert.match(source, /<template #header>\s*<CardTitle as="h3">/);
});

test('card section headings use CardTitle and callers do not override typography', () => {
    // These cards display data row names, rather than section headings.
    const rowCards = new Set([
        'components/credentials/PassRow.vue',
        'pages/Settings/FeatureFlags.vue',
    ]);
    // These panels provide the header inside a Card owned by their parent page.
    const panels = new Set([
        'components/credentials/PassAssignmentsPanel.vue',
        'components/people/EngagementPeoplePanel.vue',
        'components/team/ShiftRoleSlots.vue',
        'components/team/ShiftRoster.vue',
        'components/team/ShiftTimelineRoster.vue',
        'components/team/ShiftBreaks.vue',
        'components/team/TeamPassAssignmentsPanel.vue',
    ]);
    for (const path of readdirSync(root, { recursive: true }).filter((path) =>
        path.endsWith('.vue'),
    )) {
        const { descriptor } = parse(readFileSync(new URL(path, root), 'utf8'));
        if (!descriptor.template) continue;
        const walk = (node, inCard = false) => {
            const inside = inCard || node.tag === 'Card';
            if (
                node.tag === 'h2' &&
                (inside || panels.has(path)) &&
                !rowCards.has(path)
            ) {
                assert.fail(
                    `${path}:${node.loc.start.line}: card section heading must use CardTitle`,
                );
            }
            if (node.tag === 'CardTitle') {
                for (const prop of node.props) {
                    if (prop.name === 'class' && prop.value) {
                        assert.doesNotMatch(
                            prop.value.content,
                            /(?:^|\s)(?:\S+:)*(?:text-|font-|tracking-|leading-)/,
                            `${path}: title typography belongs in CardTitle`,
                        );
                    }
                }
            }
            for (const child of node.children ?? []) walk(child, inside);
        };
        walk(baseParse(descriptor.template.content));
    }
});
