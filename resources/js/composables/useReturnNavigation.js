import { usePage } from '@inertiajs/vue3';
import { withReturnTo } from '../lib/returnNavigation';

export function useReturnNavigation() {
    const page = usePage();
    return {
        returnUrl: (href) => {
            const navigation = page.props.returnNavigation;
            return withReturnTo(
                href,
                navigation?.landing
                    ? { ...navigation, href: page.url }
                    : navigation,
            );
        },
    };
}
