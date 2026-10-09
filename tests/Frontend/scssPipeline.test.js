import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import postcss from 'postcss';
import { createServer } from 'vite';

test('Vite compiles SCSS partials before resolving Tailwind styles', async () => {
    const root = fileURLToPath(new URL('../../', import.meta.url));
    const server = await createServer({
        root,
        configFile: false,
        logLevel: 'silent',
        server: { middlewareMode: true, hmr: false },
    });

    try {
        const result = await server.transformRequest(
            '/resources/scss/app.scss?direct',
        );
        const css = postcss.parse(result.code);
        const rules = [];
        css.walkRules((rule) => rules.push(rule));

        css.walkAtRules((rule) => {
            assert.ok(
                ![
                    'apply',
                    'utility',
                    'variant',
                    'theme',
                    'config',
                    'source',
                ].includes(rule.name),
                `Unresolved Tailwind directive: @${rule.name}`,
            );
        });

        const hasDeclaration = (selector, property, value) =>
            rules.some(
                (rule) =>
                    rule.selector === selector &&
                    rule.nodes.some(
                        (node) =>
                            node.type === 'decl' &&
                            node.prop === property &&
                            node.value === value,
                    ),
            );

        assert.ok(hasDeclaration('.content-body', 'padding-inline', '1em'));
        assert.ok(
            hasDeclaration(
                'div.dt-container .dt-search input::placeholder',
                'color',
                'var(--color-muted)',
            ),
        );
        assert.ok(
            hasDeclaration(
                'div.dt-container .dt-paging .dt-paging-button.disabled',
                'cursor',
                'not-allowed',
            ),
        );
        assert.match(result.code, /@media \(width >= 48rem\)/);
        assert.match(result.code, /@media \(width < 64rem\)/);
        assert.match(
            result.code,
            /--dt-header_padding: calc\(var\(--spacing\)/,
        );

        const module = server.moduleGraph.getModuleById(
            `${root}resources/scss/app.scss?direct`,
        );
        for (const dependencyPath of [
            'resources/scss/_bootstrap.scss',
            'resources/scss/layout/_content-body.scss',
            'resources/scss/integrations/_datatables.scss',
            'resources/js/layouts/AppLayout.vue',
        ]) {
            const dependency = `${root}${dependencyPath}`;
            assert.ok(
                [
                    ...(server.moduleGraph.getModulesByFile(dependency) ?? []),
                ].some((dependencyModule) =>
                    dependencyModule.importers.has(module),
                ),
                `Stylesheet dependency is not tracked for hot reload: ${dependencyPath}`,
            );
        }
    } finally {
        await server.close();
    }
});
