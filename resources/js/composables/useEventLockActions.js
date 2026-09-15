import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

export function useEventLockActions() {
    const lockOpen = ref(false);
    const unlockOpen = ref(false);
    const actionBusy = ref(false);
    const targetEvent = ref(null);

    const openLock = (event) => {
        targetEvent.value = event;
        lockOpen.value = true;
    };

    const openUnlock = (event) => {
        targetEvent.value = event;
        unlockOpen.value = true;
    };

    const reset = () => {
        actionBusy.value = false;
        lockOpen.value = false;
        unlockOpen.value = false;
        targetEvent.value = null;
    };

    const confirmLock = () => {
        if (!targetEvent.value || actionBusy.value) {
            return;
        }

        actionBusy.value = true;
        router.post(
            `/events/${targetEvent.value.id}/lock`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    reset();
                },
                onFinish: () => {
                    actionBusy.value = false;
                },
            },
        );
    };

    const confirmUnlock = () => {
        if (!targetEvent.value || actionBusy.value) {
            return;
        }

        actionBusy.value = true;
        router.post(
            `/events/${targetEvent.value.id}/unlock`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    reset();
                },
                onFinish: () => {
                    actionBusy.value = false;
                },
            },
        );
    };

    return {
        lockOpen,
        unlockOpen,
        actionBusy,
        targetEvent,
        openLock,
        openUnlock,
        confirmLock,
        confirmUnlock,
    };
}
