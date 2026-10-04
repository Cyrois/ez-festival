import { onMounted, onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

export function useUnsavedNavigation(isDirty) {
    const open = ref(false);
    let pending;

    const continueNavigation = () => {
        if (!pending) return;
        const navigate = pending;
        pending = null;
        open.value = false;
        navigate();
    };
    const cancel = () => {
        pending = null;
        open.value = false;
    };
    const onClick = (event) => {
        if (
            !isDirty.value ||
            event.defaultPrevented ||
            event.button !== 0 ||
            event.ctrlKey ||
            event.metaKey ||
            event.shiftKey ||
            event.altKey
        )
            return;
        const link = event.target.closest?.('a[href]');
        if (
            !link?.closest('[data-unsaved-navigation]') ||
            (link.target && link.target !== '_self') ||
            link.hasAttribute('download')
        )
            return;

        // Only sidebar and breadcrumb clicks opt into the warning. Cancel,
        // browser history, and other page links keep their normal behavior.
        event.preventDefault();
        event.stopPropagation();
        const destination = link.href;
        pending = () => router.visit(destination);
        open.value = true;
    };
    onMounted(() => document.addEventListener('click', onClick, true));
    onUnmounted(() => document.removeEventListener('click', onClick, true));

    return { open, continueNavigation, cancel };
}
