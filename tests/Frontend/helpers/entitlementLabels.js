import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { parse, compileScript } from '@vue/compiler-sfc';

const require = createRequire(import.meta.url);
const root = new URL('../../../resources/js/', import.meta.url);
const locale = JSON.parse(readFileSync(new URL('../../../lang/en.json', import.meta.url), 'utf8'));
globalThis.entitlementLabelTranslate = (key, values = {}) => Object.entries(values).reduce((text, [name, value]) => text.replaceAll(`:${name}`, value), locale[key] ?? key);
const compile = (path, replacements = []) => {
    const { descriptor } = parse(readFileSync(new URL(path, root), 'utf8'));
    let source = compileScript(descriptor, { id: path, inlineTemplate: true }).content
        .replaceAll(/from ['"]vue['"]/g, `from '${pathToFileURL(require.resolve('vue')).href}'`)
        .replace(/import \{ cn \} from ['"].*?['"];?/, 'const cn = (...values) => values.flat().filter(Boolean).join(" ");')
        .replace(/import \{ trans \} from ['"].*?['"];?/, 'const trans = globalThis.entitlementLabelTranslate;')
        .replace(/import \{ Icon \} from ['"].*?['"];?/, 'const Icon = { render: () => null };');
    for (const [pattern, replacement] of replacements) source = source.replace(pattern, replacement);
    return `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
};
export const tagUrl = compile('components/ui/tag/Tag.vue', [
    [/from ['"].*?lib\/labelTokens['"]/, `from '${new URL('lib/labelTokens.js', root).href}'`],
]);
export const labelsUrl = compile('components/ui/entitlement-labels/EntitlementLabels.vue', [
    [/import \{ Tag \} from ['"].*?['"];?/, `import Tag from '${tagUrl}';`],
    [/from ['"]\.\/fitLabels['"]/, `from '${new URL('components/ui/entitlement-labels/fitLabels.js', root).href}'`],
]);
export const nameUrl = compile('components/ui/entitlement-labels/EntitlementItemName.vue', [
    [/from ['"]\.\/EntitlementLabels.vue['"]/, `from '${labelsUrl}'`],
]);
export const labelImports = [
    /import \{ EntitlementItemName \} from ['"].*?['"];?/,
    `import EntitlementItemName from '${nameUrl}';`,
];
export function desktopLabelEnvironment() {
    window.matchMedia ??= () => ({ matches: false, addEventListener() {}, removeEventListener() {} });
}
