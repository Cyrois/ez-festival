import { usePage } from '@inertiajs/vue3';
import { nextTick, onMounted, watch } from 'vue';

// State is only restored by an explicit return link, and stays in this browser tab.
export function useLandingState(name, fields = {}, applyFilters = () => {}) {
    const page = usePage();
    const scope = `${page.props.auth?.user?.id}:${page.props.activeEvent?.id ?? 'global'}`;
    const key = `landing:${scope}:${name}`;
    const restoring =
        new URL(page.url, 'http://navigation.local').searchParams.get(
            'restore',
        ) === '1';
    const read = (suffix) => {
        try {
            return JSON.parse(
                window.sessionStorage.getItem(`${key}:${suffix}`),
            );
        } catch {
            return null;
        }
    };
    const write = (suffix, value) => {
        try {
            window.sessionStorage.setItem(
                `${key}:${suffix}`,
                JSON.stringify(value),
            );
        } catch {
            // Navigation remains usable when browser storage is unavailable.
        }
    };
    if (restoring) {
        const saved = read('filters');
        for (const [field, value] of Object.entries(fields)) {
            if (
                saved &&
                Object.hasOwn(saved, field) &&
                Array.isArray(saved[field]) === Array.isArray(value.value) &&
                (typeof saved[field] === typeof value.value ||
                    (value.value === '' && typeof saved[field] === 'number'))
            ) {
                value.value = saved[field];
            }
        }
    }
    const saveFilters = () =>
        write(
            'filters',
            Object.fromEntries(
                Object.entries(fields).map(([field, value]) => [
                    field,
                    value.value,
                ]),
            ),
        );
    watch(Object.values(fields), saveFilters, { deep: true, flush: 'sync' });
    onMounted(async () => {
        await nextTick();
        if (restoring) applyFilters();
        saveFilters();
    });

    let loaded = false;
    return {
        stateSave: true,
        stateSaveCallback: (_settings, data) => write('table', data),
        stateLoadCallback: () => {
            if (!restoring || loaded) return null;
            loaded = true;
            return read('table');
        },
    };
}
