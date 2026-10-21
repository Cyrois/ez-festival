import { computed, onUnmounted, ref, shallowReactive, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { useFlashToast } from './useFlashToast';

export function useShiftHoursReportRequests({ eventId, selectedDate, active }) {
    const requests = shallowReactive(new Map());
    const openedShift = ref(null);
    const { showFormError } = useFlashToast();
    const dayKey = computed(() =>
        JSON.stringify(['day', eventId.value, selectedDate.value]),
    );
    const peopleKey = (shiftId) =>
        JSON.stringify(['people', eventId.value, shiftId]);
    const day = computed(() => requests.get(dayKey.value));
    const people = computed(() => requests.get(peopleKey(openedShift.value)));
    const dateError = computed(() =>
        !selectedDate.value
            ? trans('team.reports.day_required')
            : day.value?.errors?.date,
    );
    const rows = computed(() =>
        (day.value?.data?.shifts ?? []).map((shift) => {
            const loaded = requests.get(peopleKey(shift.shift_id))?.data;
            if (!loaded) return shift;
            return {
                ...shift,
                people_count: loaded.people.length,
                supervisor_name:
                    loaded.people.find((person) => person.is_supervisor)
                        ?.name ?? null,
                scheduled_minutes: loaded.people.reduce(
                    (total, person) => total + person.scheduled_minutes,
                    0,
                ),
            };
        }),
    );
    let mounted = true;
    const load = async (requestKey, endpoint, matches) => {
        if (['loading', 'loaded'].includes(requests.get(requestKey)?.status))
            return;
        const entry = { status: 'loading', controller: new AbortController() };
        requests.set(requestKey, entry);
        const current = () => mounted && requests.get(requestKey) === entry;
        try {
            const response = await fetch(endpoint, {
                headers: { Accept: 'application/json' },
                signal: entry.controller.signal,
            });
            if (!current()) return;
            if (response.status === 422) {
                const body = await response.json();
                if (!current()) return;
                const errors = Object.fromEntries(
                    Object.entries(body.errors ?? {}).map(
                        ([field, messages]) => [
                            field,
                            Array.isArray(messages) ? messages[0] : messages,
                        ],
                    ),
                );
                requests.set(requestKey, { status: 'invalid', errors });
                if (active.value && dayKey.value === requestKey)
                    showFormError(errors);
                return;
            }
            if (!response.ok) throw new Error('Shift report request failed');
            const { data } = await response.json();
            if (!current()) return;
            if (!matches(data))
                throw new Error('Shift report selection changed');
            requests.set(requestKey, { status: 'loaded', data });
        } catch (error) {
            if (current() && error.name !== 'AbortError')
                requests.set(requestKey, { status: 'failed' });
        }
    };
    const loadDay = () => {
        if (!active.value || !selectedDate.value) return;
        const event = eventId.value;
        const date = selectedDate.value;
        return load(
            dayKey.value,
            '/team/reports/shifts?' + new URLSearchParams({ date }),
            (data) =>
                data.event_id === event &&
                data.date === date &&
                Array.isArray(data.shifts),
        );
    };
    const loadPeople = () => {
        if (!active.value || openedShift.value === null) return;
        const event = eventId.value;
        const shift = openedShift.value;
        return load(
            peopleKey(shift),
            `/team/reports/shifts/${shift}/people`,
            (data) =>
                data.event_id === event &&
                data.shift_id === shift &&
                Array.isArray(data.people),
        );
    };
    const toggleShift = (shiftId) => {
        openedShift.value = openedShift.value === shiftId ? null : shiftId;
        loadPeople();
    };
    const clear = () => {
        for (const request of requests.values()) request.controller?.abort();
        requests.clear();
        openedShift.value = null;
    };
    watch(
        [eventId, selectedDate, active],
        (values, previous) => {
            if (previous.length && values[0] !== previous[0]) clear();
            else if (previous.length && values[1] !== previous[1])
                openedShift.value = null;
            loadDay();
        },
        { immediate: true },
    );
    onUnmounted(() => {
        mounted = false;
        clear();
    });
    return {
        dayKey,
        day,
        people,
        rows,
        openedShift,
        dateError,
        loadDay,
        loadPeople,
        toggleShift,
    };
}
