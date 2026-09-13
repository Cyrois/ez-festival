import { onUnmounted, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { useFlashToast } from './useFlashToast';

/**
 * Shared flash.error watcher + Inertia invalid handler for authenticated shells.
 */
export function useInertiaErrorToast() {
    const page = usePage();
    const { showError } = useFlashToast();

    watch(
        () => page.props.flash?.error,
        (error) => {
            if (error) {
                showError(error);
            }
        },
        { immediate: true },
    );

    const removeInvalidListener = router.on('invalid', (event) => {
        event.preventDefault();
        showError(trans('errors.generic'));
    });

    onUnmounted(() => {
        removeInvalidListener();
    });

    return { showError };
}
