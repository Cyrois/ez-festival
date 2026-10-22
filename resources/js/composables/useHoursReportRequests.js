import { computed, onUnmounted, shallowReactive, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { useFlashToast } from './useFlashToast';

// One page visit owns its cache, in-flight requests, and cancellation boundary.
export function useHoursReportRequests({
    eventId,
    employmentType,
    selectedGroup,
    startDate,
    endDate,
    groups,
    active = computed(() => true),
    scope = 'group',
}) {
    const { showFormError } = useFlashToast();
    const requests = shallowReactive(new Map());
    const key = computed(() =>
        JSON.stringify([
            eventId.value,
            selectedGroup.value,
            startDate.value,
            endDate.value,
            employmentType.value,
        ]),
    );
    const current = computed(() => requests.get(key.value));
    const report = computed(() => current.value?.data ?? null);
    const loading = computed(() => current.value?.status === 'loading');
    const failed = computed(() => current.value?.status === 'failed');
    const showingPeople = computed(() => selectedGroup.value !== '');
    const summary = computed(() => {
        if (report.value) return report.value.totals;
        if (!showingPeople.value) return null;
        const overview = requests.get(
            JSON.stringify([
                eventId.value,
                '',
                startDate.value,
                endDate.value,
                employmentType.value,
            ]),
        )?.data;
        return (
            overview?.[`${scope}s`].find(
                (group) => group[`${scope}_id`] === selectedGroup.value,
            ) ?? null
        );
    });
    const rangeErrors = computed(() => {
        if (startDate.value && !endDate.value)
            return { end_date: trans('team.reports.range_required') };
        if (endDate.value && !startDate.value)
            return { start_date: trans('team.reports.range_required') };
        if (startDate.value > endDate.value)
            return { end_date: trans('team.reports.range_order') };
        return {};
    });
    const errors = computed(() => ({
        ...rangeErrors.value,
        ...current.value?.errors,
    }));
    const rows = computed(
        () =>
            (showingPeople.value
                ? report.value?.people
                : report.value?.[`${scope}s`]) ?? [],
    );

    let mounted = true;
    const cancelPending = () => {
        for (const [requestKey, request] of requests) {
            if (request.status === 'loading') {
                requests.delete(requestKey);
                request.controller.abort();
            }
        }
    };
    const load = async () => {
        if (
            !active.value ||
            Object.keys(rangeErrors.value).length ||
            ['loading', 'loaded'].includes(current.value?.status)
        )
            return;
        const requestKey = key.value;
        const requestedEvent = eventId.value;
        const requestedGroup = selectedGroup.value;
        const requestedStart = startDate.value || null;
        const requestedEnd = endDate.value || null;
        const requestedEmployment = employmentType.value;
        const entry = { status: 'loading', controller: new AbortController() };
        requests.set(requestKey, entry);
        const isCurrentRequest = () =>
            mounted && requests.get(requestKey) === entry;
        try {
            const params = new URLSearchParams();
            if (requestedStart) {
                params.set('start_date', requestedStart);
                params.set('end_date', requestedEnd);
            }
            if (requestedEmployment !== 'all')
                params.set('employment_type', requestedEmployment);
            const endpoint =
                requestedGroup === ''
                    ? scope === 'location'
                        ? '/team/reports/locations'
                        : '/team/reports/data'
                    : `/team/reports/${scope}s/${requestedGroup}/people`;
            const response = await fetch(endpoint + '?' + params, {
                headers: { Accept: 'application/json' },
                signal: entry.controller.signal,
            });
            if (!isCurrentRequest()) return;
            if (response.status === 422) {
                const body = await response.json();
                if (!isCurrentRequest()) return;
                const validation = Object.fromEntries(
                    Object.entries(body.errors ?? {}).map(
                        ([field, messages]) => [
                            field,
                            Array.isArray(messages) ? messages[0] : messages,
                        ],
                    ),
                );
                requests.set(requestKey, {
                    status: 'invalid',
                    errors: validation,
                });
                if (key.value === requestKey) showFormError(validation);
                return;
            }
            if (!response.ok) throw new Error('Hours report request failed');
            const { data } = await response.json();
            if (!isCurrentRequest()) return;
            if (
                data.event_id !== requestedEvent ||
                data.employment_type !== requestedEmployment ||
                data.range.start_date !== requestedStart ||
                data.range.end_date !== requestedEnd ||
                (requestedGroup !== '' &&
                    data[scope][`${scope}_id`] !== requestedGroup)
            )
                throw new Error('Hours report selection changed');
            requests.set(requestKey, { status: 'loaded', data });
            if (
                scope === 'group' &&
                requestedGroup === '' &&
                requestedEmployment === 'all'
            )
                groups.value = data.groups;
        } catch (error) {
            if (isCurrentRequest() && error.name !== 'AbortError')
                requests.set(requestKey, { status: 'failed' });
        }
    };

    watch(
        [eventId, selectedGroup, startDate, endDate, employmentType, active],
        (values, previous) => {
            if (previous.length && values[0] !== previous[0]) {
                cancelPending();
                requests.clear();
                groups.value = [];
                selectedGroup.value = '';
            } else if (
                previous.length &&
                (values[2] !== previous[2] || values[3] !== previous[3])
            ) {
                cancelPending();
            }
            load();
        },
        { immediate: true },
    );
    onUnmounted(() => {
        mounted = false;
        cancelPending();
        requests.clear();
    });

    return {
        key,
        report,
        loading,
        failed,
        showingPeople,
        summary,
        rangeErrors,
        errors,
        rows,
        load,
    };
}
