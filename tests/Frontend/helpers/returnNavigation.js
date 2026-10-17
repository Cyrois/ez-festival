import { parse } from '@vue/compiler-sfc';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';

const require = createRequire(import.meta.url);
const vue = pathToFileURL(require.resolve('vue')).href;
const options = new URL('../../../resources/js/lib/returnNavigation.js', import.meta.url).href;

// Existing component suites isolate navigation. Its real UI and behavior have a
// dedicated integration suite in returnNavigation.test.js.
export function parseWithReturnNavigation(source, ...args) {
    source = source
        .replace(/import \{ BackLink \} from ['"].*?['"];?/, `import { h as returnNavigationH } from '${vue}'; const BackLink = { props: ['href', 'page'], setup: (props) => () => returnNavigationH('a', {href: props.href}, props.page) };`)
        .replace(/import \{ useReturnNavigation \} from ['"].*?['"];?/, 'const useReturnNavigation = () => ({ returnUrl: (href) => href });')
        .replace(/import \{ useLandingState \} from ['"].*?['"];?/, 'const useLandingState = () => ({});')
        .replace(/from ['"][^'"]*lib\/returnNavigation['"]/, `from '${options}'`);
    return parse(source, ...args);
}
