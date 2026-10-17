export function withReturnTo(href, navigation) {
    if (!href || !navigation?.href) return href;
    const base =
        typeof window !== 'undefined' && /^https?:/.test(window.location.origin)
            ? window.location.origin
            : 'http://navigation.local';
    const destination = new URL(href, base);
    if (destination.origin !== base) return href;
    const origin = new URL(navigation.href, base);
    if (origin.origin !== base) return href;
    origin.searchParams.set('restore', '1');
    destination.searchParams.set('return_to', origin.pathname + origin.search);
    return destination.pathname + destination.search + destination.hash;
}

export function returnFormOptions(form, afterSave, options = {}) {
    return {
        ...options,
        onSuccess: async (page) => {
            await options.onSuccess?.(page);
            form.defaults();
            if (
                typeof afterSave === 'function' &&
                !page?.props?.flash?.warning
            ) {
                afterSave();
            }
        },
    };
}
