import { useFlashToast } from './useFlashToast';
import {
    applySnapshot,
    createPendingState,
    dropSettled,
    failMove,
    finishVisit,
    forgetSnapshot,
    movingIds as pendingMovingIds,
    overlayCounts,
    overlayItems,
    reconcileWithProps,
    startMove,
    startVisit,
    succeedMove,
} from '../lib/advancementPendingStatuses';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

export const useAdvancementBoard = ({
    indexUrl,
    statusUrl,
    items,
    serverCounts,
    filterPayload,
    canMove,
    itemsKey = 'engagements',
    countsKey = 'statusCounts',
}) => {
    const busy = ref(false);
    const pending = ref(createPendingState());
    const { showFormError } = useFlashToast();
    let lastRequestId = 0;
    let refreshing = false;

    const movingIds = computed(() => pendingMovingIds(pending.value));
    const engagementItems = computed(() =>
        overlayItems(pending.value, items()),
    );
    const localStatusCounts = computed(() =>
        overlayCounts(pending.value, items(), serverCounts()),
    );

    const trackedVisit = (requestId, { onSuccess, onError, refresh } = {}) => ({
        onSuccess: (page) => {
            onSuccess?.();
            pending.value = applySnapshot(
                pending.value,
                page.props[itemsKey].data,
                requestId,
            );
        },
        onError: (errors) => {
            onError?.(errors);
            pending.value = forgetSnapshot(pending.value);
        },
        onFinish: () => {
            const result = finishVisit(pending.value, requestId);
            pending.value = result.state;

            if (refresh) {
                refreshing = false;
                pending.value = dropSettled(pending.value);
            } else if (result.needsRefresh) {
                refreshItems();
            }
        },
    });

    const refreshItems = () => {
        if (refreshing) {
            return;
        }

        const requestId = ++lastRequestId;
        refreshing = true;
        pending.value = startVisit(pending.value, requestId);
        router.reload({
            only: [itemsKey, countsKey],
            async: true,
            ...trackedVisit(requestId, { refresh: true }),
        });
    };

    const applyFilters = () => {
        const requestId = ++lastRequestId;
        const callbacks = trackedVisit(requestId);
        pending.value = startVisit(pending.value, requestId);
        router.get(indexUrl, filterPayload(), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            ...callbacks,
            onStart: () => {
                busy.value = true;
            },
            onFinish: () => {
                busy.value = false;
                callbacks.onFinish();
            },
        });
    };

    const moveEngagement = ({ item, to }) => {
        if (!canMove() || movingIds.value.includes(item.id)) {
            return;
        }

        const requestId = ++lastRequestId;
        pending.value = startMove(pending.value, item.id, to, requestId);
        router.patch(
            statusUrl(item),
            { status: to },
            {
                async: true,
                preserveScroll: true,
                preserveState: true,
                ...trackedVisit(requestId, {
                    onSuccess: () => {
                        pending.value = succeedMove(
                            pending.value,
                            item.id,
                            requestId,
                        );
                    },
                    onError: (errors) => {
                        pending.value = failMove(
                            pending.value,
                            item.id,
                            requestId,
                        );
                        showFormError(errors);
                    },
                }),
            },
        );
    };

    watch(items, (nextItems) => {
        pending.value = reconcileWithProps(pending.value, nextItems);
    });

    return {
        applyFilters,
        busy,
        engagementItems,
        localStatusCounts,
        moveEngagement,
        movingIds,
    };
};
