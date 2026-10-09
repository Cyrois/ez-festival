<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Avatar } from '../../components/ui/avatar';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { Card, CardTitle } from '../../components/ui/card';
import { DataTable } from '../../components/ui/data-table';
import { Dialog } from '../../components/ui/dialog';
import { EmptyState } from '../../components/ui/empty-state';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { Radio } from '../../components/ui/radio';
import { Tooltip } from '../../components/ui/tooltip';
import { useFlashToast } from '../../composables/useFlashToast';
import { xsrfToken } from '../../lib/http';
import { mealDateLabel } from '../../lib/mealDates';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';

const props = defineProps({ event: { type: Object, required: true } });
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.meals') },
]);
const { showError, showFormError, showSuccess } = useFlashToast();
const search = ref('');
const people = ref(null);
const searching = ref(false);
const selected = ref(null);
const panel = ref(null);
const table = ref(null);
const tableContainer = ref(null);
const busy = ref(false);
const warning = ref(null);
const alert = ref('');
const highlightedAssignment = ref(null);
const overrideStep = ref('');
const overrideOptions = ref([]);
const overrideMealId = ref('');
const removal = ref(null);
let overrideRequest;
const overrideMeal = computed(() =>
    overrideOptions.value.find((meal) => meal.id === overrideMealId.value),
);
const overrideReason = (remove = false) =>
    panel.value?.is_locked || props.event.is_locked
        ? trans('events.read_only_locked')
        : trans(
              remove
                  ? 'meals.override.remove_read_only'
                  : 'meals.override.read_only',
          );
const closeOverride = () => {
    overrideRequest?.abort();
    overrideStep.value = '';
    overrideMealId.value = '';
    overrideOptions.value = [];
    removal.value = null;
};
const overrideDescription = computed(() => {
    if (overrideStep.value === 'remove')
        return trans('meals.override.remove_confirm', {
            name: panel.value?.person.name,
            meal: removal.value?.name,
            time: removal.value?.override_at,
        });
    return trans('meals.override.pick');
});
let searchTimer;
let highlightTimer;
let refreshTimer;
let searchRequest;
let detailRequest;
let lookupVersion = 0;
let disposed = false;
const dateLabel = (date) => mealDateLabel(date, getActiveLanguage());
const disabledReason = computed(() =>
    panel.value?.is_locked || props.event.is_locked
        ? trans('events.read_only_locked')
        : trans('meals.claim.read_only'),
);
const emptyTable = (draw) => ({
    draw,
    recordsTotal: 0,
    recordsFiltered: 0,
    data: [],
});
const choose = (person) => {
    if (selected.value?.id === person.id) {
        table.value?.reload(false);
        return;
    }
    detailRequest?.abort();
    closeOverride();
    selected.value = person;
    panel.value = null;
    alert.value = '';
    warning.value = null;
    highlightedAssignment.value = null;
};
const lookup = async (page = 1, version = ++lookupVersion) => {
    if (disposed || version !== lookupVersion) return;
    window.clearTimeout(searchTimer);
    searchRequest?.abort();
    const controller = new AbortController();
    searchRequest = controller;
    searching.value = true;
    try {
        const params = new URLSearchParams({
            search: search.value.trim(),
            page,
        });
        const response = await fetch(`/meals/people?${params}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });
        if (!response.ok) throw new Error();
        const result = await response.json();
        if (disposed || version !== lookupVersion) return;
        people.value = result;
        if (result.matched_id !== null) {
            choose(
                result.people.find(
                    (person) => person.id === result.matched_id,
                ) ?? { id: result.matched_id },
            );
        }
    } catch (error) {
        if (
            !disposed &&
            version === lookupVersion &&
            error.name !== 'AbortError'
        )
            showError();
    } finally {
        if (version === lookupVersion) searching.value = false;
    }
};
watch(search, () => {
    window.clearTimeout(searchTimer);
    searchRequest?.abort();
    detailRequest?.abort();
    const version = ++lookupVersion;
    closeOverride();
    people.value = null;
    searching.value = false;
    selected.value = null;
    panel.value = null;
    alert.value = '';
    warning.value = null;
    if (search.value.trim())
        searchTimer = window.setTimeout(() => lookup(1, version), 250);
});
const loadMeals = async (data, callback) => {
    detailRequest?.abort();
    const controller = new AbortController();
    detailRequest = controller;
    const memberId = selected.value?.id;
    if (!memberId) return callback(emptyTable(data.draw));
    try {
        const params = new URLSearchParams({
            draw: data.draw,
            start: data.start,
            length: data.length,
        });
        const response = await fetch(`/meals/people/${memberId}?${params}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });
        if (!response.ok) {
            if (controller.signal.aborted || selected.value?.id !== memberId)
                return;
            if (selected.value?.id === memberId) {
                selected.value = null;
                panel.value = null;
            }
            throw new Error();
        }
        const result = await response.json();
        if (
            disposed ||
            selected.value?.id !== memberId ||
            controller.signal.aborted
        )
            return;
        panel.value = result;
        callback(result);
    } catch (error) {
        if (error.name !== 'AbortError' && !disposed) {
            callback(emptyTable(data.draw));
            showError();
        }
    }
};
const columns = computed(() => [
    {
        data: 'name',
        title: trans('meals.claim.columns.meal'),
        render: { display: '#nameCell' },
    },
    {
        data: 'type',
        title: trans('meals.type'),
        render: { display: '#typeCell' },
    },
    {
        data: 'shift_location',
        title: trans('meals.claim.columns.shift'),
        render: { display: '#shiftCell' },
    },
    {
        data: 'used',
        title: trans('meals.claim.columns.status'),
        render: { display: '#statusCell' },
    },
    {
        data: null,
        title: '',
        defaultContent: '',
        render: { display: '#actionsCell' },
    },
]);
const tableOptions = computed(() => ({
    serverSide: true,
    searching: false,
    ordering: false,
    lengthChange: false,
    pageLength: 25,
    info: false,
    layout: { topStart: null, topEnd: null, bottomStart: null },
    columnDefs: [{ targets: 4, className: 'text-right' }],
    createdRow: (row, meal) =>
        row.classList.toggle(
            'bg-success/10',
            meal.assignment_id === highlightedAssignment.value,
        ),
    language: { emptyTable: trans('meals.claim.no_meals') },
}));
const clearHighlight = () => {
    highlightedAssignment.value = null;
    tableContainer.value
        ?.querySelectorAll('tr')
        .forEach((row) => row.classList.remove('bg-success/10'));
};
const claim = async (meal, confirmed = false) => {
    if (!panel.value?.can_claim || meal.eligible_today === false || busy.value)
        return;
    const memberId = selected.value.id;
    busy.value = true;
    alert.value = '';
    try {
        const response = await fetch(
            `/events/${props.event.id}/meals/people/${memberId}/claims`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({
                    assignment_id: meal.assignment_id,
                    confirm_warning: confirmed,
                }),
            },
        );
        const result = await response.json();
        if (disposed) return;
        if (response.status === 409 && result.status === 'warning_required') {
            warning.value = { meal, message: result.message };
            return;
        }
        warning.value = null;
        if (response.ok) {
            highlightedAssignment.value = result.assignment_id;
            showSuccess(result.message);
            window.clearTimeout(highlightTimer);
            highlightTimer = window.setTimeout(clearHighlight, 3000);
        } else if (response.status === 409) alert.value = result.message;
        else if (response.status === 422) showFormError(result.errors);
        else showError(result.message);
    } catch {
        if (!disposed) showError();
    } finally {
        busy.value = false;
        if (!disposed && selected.value?.id === memberId)
            table.value?.reload(false);
    }
};
const unclaim = async (meal) => {
    if (
        !panel.value?.can_claim ||
        meal.eligible_today === false ||
        busy.value ||
        !meal.used
    )
        return;
    const memberId = selected.value.id;
    busy.value = true;
    alert.value = '';
    try {
        const response = await fetch(
            `/events/${props.event.id}/meals/people/${memberId}/claims`,
            {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({
                    assignment_id: meal.assignment_id,
                    claim_token: meal.claim_token,
                }),
            },
        );
        const result = await response.json();
        if (disposed) return;
        if (response.ok) {
            clearHighlight();
            showSuccess(result.message);
        } else if (response.status === 409) alert.value = result.message;
        else if (response.status === 422) showFormError(result.errors);
        else showError(result.message);
    } catch {
        if (!disposed) showError();
    } finally {
        busy.value = false;
        if (!disposed && selected.value?.id === memberId)
            table.value?.reload(false);
    }
};
const openOverride = async () => {
    if (!panel.value?.can_override || busy.value) return;
    const memberId = selected.value.id;
    closeOverride();
    overrideStep.value = 'pick';
    busy.value = true;
    const controller = new AbortController();
    overrideRequest = controller;
    try {
        const response = await fetch(
            `/events/${props.event.id}/meals/people/${memberId}/overrides`,
            {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            },
        );
        const result = await response.json();
        if (
            disposed ||
            controller.signal.aborted ||
            selected.value?.id !== memberId
        )
            return;
        if (response.ok) overrideOptions.value = result.data;
        else {
            closeOverride();
            showError(result.message);
        }
    } catch (error) {
        if (!disposed && error.name !== 'AbortError') {
            closeOverride();
            showError();
        }
    } finally {
        busy.value = false;
    }
};
const removeOverride = (meal) => {
    if (!panel.value?.can_remove_override || busy.value || meal.used) return;
    removal.value = meal;
    overrideStep.value = 'remove';
};
const saveOverride = async (claim = true) => {
    if (busy.value) return;
    const removing = overrideStep.value === 'remove';
    if (
        removing
            ? !panel.value?.can_remove_override
            : !panel.value?.can_override || !overrideMeal.value?.available
    )
        return;
    const memberId = selected.value.id;
    busy.value = true;
    alert.value = '';
    try {
        const response = await fetch(
            `/events/${props.event.id}/meals/people/${memberId}/overrides`,
            {
                method: removing ? 'DELETE' : 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify(
                    removing
                        ? {
                              assignment_id: removal.value.assignment_id,
                              confirmed: true,
                          }
                        : {
                              meal_id: overrideMeal.value.id,
                              claim,
                          },
                ),
            },
        );
        const result = await response.json();
        if (disposed) return;
        closeOverride();
        if (response.ok) {
            highlightedAssignment.value = removing
                ? null
                : result.assignment_id;
            showSuccess(result.message);
            window.clearTimeout(highlightTimer);
            highlightTimer = window.setTimeout(clearHighlight, 3000);
        } else if (response.status === 409) alert.value = result.message;
        else if (response.status === 422) showFormError(result.errors);
        else showError(result.message);
    } catch {
        if (!disposed) showError();
    } finally {
        busy.value = false;
        if (!disposed && selected.value?.id === memberId)
            table.value?.reload(false);
    }
};
onMounted(() => {
    refreshTimer = window.setInterval(() => {
        if (
            !busy.value &&
            !warning.value &&
            !overrideStep.value &&
            selected.value
        )
            table.value?.reload(false);
    }, 60000);
});
onUnmounted(() => {
    disposed = true;
    closeOverride();
    lookupVersion++;
    searchRequest?.abort();
    detailRequest?.abort();
    window.clearTimeout(searchTimer);
    window.clearTimeout(highlightTimer);
    window.clearInterval(refreshTimer);
});
</script>

<template>
    <AppLayout
        :title="$t('nav.meals')"
        :breadcrumbs="breadcrumbs"
        full-width-content
    >
        <div class="container mx-auto flex max-w-none flex-col gap-5">
            <header class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="m-0 text-2xl font-bold text-charcoal">
                    {{ $t('nav.meals') }}
                </h1>
                <Button
                    href="/meals/settings"
                    variant="outline"
                    ><Icon
                        :name="['fas', 'gear']"
                        size="sm"
                    />{{ $t('meals.settings.title') }}</Button
                >
            </header>
            <p
                v-if="event.is_locked || panel?.is_locked"
                role="status"
                class="rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-charcoal"
            >
                {{ $t('events.read_only_locked') }}
            </p>
            <div
                class="grid items-start gap-4 xl:grid-cols-[minmax(18rem,3fr)_minmax(0,7fr)]"
            >
                <Card>
                    <CardTitle class="mb-4">{{
                        $t('meals.claim.find')
                    }}</CardTitle>
                    <form
                        role="search"
                        @submit.prevent="lookup()"
                    >
                        <div class="relative">
                            <Icon
                                :name="['fas', 'magnifying-glass']"
                                class="pointer-events-none absolute top-3.5 left-3 text-muted"
                                size="sm"
                            />
                            <Input
                                v-model="search"
                                type="search"
                                class="pr-10 pl-9"
                                :aria-label="$t('meals.claim.search')"
                                :placeholder="$t('meals.claim.search')"
                                :disabled="busy"
                                maxlength="255"
                                autocomplete="off"
                            />
                            <Icon
                                :name="['fas', 'barcode']"
                                class="pointer-events-none absolute top-3.5 right-3 text-muted"
                                size="sm"
                            />
                        </div>
                        <p
                            v-if="panel && people?.matched_id === selected?.id"
                            class="mt-2 text-xs text-primary"
                            role="status"
                        >
                            {{
                                $t('meals.claim.matched', {
                                    code: search.trim(),
                                    name: panel.person.name,
                                })
                            }}
                        </p>
                        <p
                            v-else
                            class="mt-2 text-xs text-muted"
                        >
                            {{ $t('meals.claim.hint') }}
                        </p>
                    </form>
                    <p
                        v-if="searching"
                        role="status"
                        class="mt-4 text-sm text-muted"
                    >
                        {{ $t('data_table.loading') }}
                    </p>
                    <div
                        v-else-if="people?.people.length"
                        class="mt-4 overflow-hidden rounded-lg border border-line"
                    >
                        <Button
                            v-for="person in people.people"
                            :key="person.id"
                            variant="ghost"
                            class="flex h-auto w-full justify-start gap-3 rounded-none border-x-0 border-t-0 border-b border-line p-3 text-left last:border-b-0"
                            :class="
                                selected?.id === person.id
                                    ? 'bg-primary-soft'
                                    : ''
                            "
                            :aria-pressed="selected?.id === person.id"
                            :disabled="busy"
                            @click="choose(person)"
                        >
                            <Avatar
                                :name="person.name"
                                size="sm"
                            />
                            <span class="min-w-0 flex-1"
                                ><span class="block truncate font-semibold">{{
                                    person.name
                                }}</span
                                ><span
                                    class="block text-xs font-normal text-muted"
                                    >{{ person.type
                                    }}<template v-if="person.codes.length">
                                        ·
                                        {{
                                            $t('meals.claim.wristband', {
                                                code: person.codes.join(', '),
                                            })
                                        }}</template
                                    ></span
                                ></span
                            >
                            <Icon
                                :name="['fas', 'chevron-right']"
                                size="sm"
                                class="text-muted"
                            />
                        </Button>
                    </div>
                    <EmptyState
                        v-else-if="people && search.trim()"
                        class="mt-4"
                        :title="
                            $t('meals.claim.no_results', {
                                search: search.trim(),
                            })
                        "
                    />
                    <p
                        v-if="people?.total"
                        class="mt-3 text-xs text-muted"
                        role="status"
                    >
                        {{
                            $t(
                                people.total === 1
                                    ? 'meals.claim.person_count_one'
                                    : 'meals.claim.person_count',
                                { count: people.total },
                            )
                        }}
                    </p>
                    <div
                        v-if="people?.last_page > 1"
                        class="mt-3 flex items-center justify-between gap-2"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="busy || people.page <= 1"
                            @click="lookup(people.page - 1)"
                            >{{ $t('data_table.pagination.previous') }}</Button
                        >
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="busy || people.page >= people.last_page"
                            @click="lookup(people.page + 1)"
                            >{{ $t('data_table.pagination.next') }}</Button
                        >
                    </div>
                </Card>
                <Card
                    v-if="selected"
                    class="min-w-0"
                >
                    <div
                        v-if="panel"
                        class="mb-4 flex flex-wrap items-start justify-between gap-4"
                    >
                        <div class="flex items-center gap-3">
                            <Avatar
                                :name="panel.person.name"
                                size="lg"
                            />
                            <div>
                                <CardTitle>{{ panel.person.name }}</CardTitle>
                                <p class="mt-1 text-sm text-muted">
                                    {{ panel.person.type }} ·
                                    {{ panel.person.status }}
                                </p>
                            </div>
                        </div>
                        <div class="ml-auto flex items-start">
                            <Tooltip
                                v-if="!panel.can_override"
                                :label="overrideReason()"
                                ><Button
                                    variant="ghost"
                                    disabled
                                    >{{ $t('meals.override.action') }}</Button
                                >
                                <template #content>{{
                                    overrideReason()
                                }}</template>
                            </Tooltip>
                            <Button
                                v-else
                                variant="ghost"
                                :disabled="busy"
                                @click="openOverride"
                                ><Icon :name="['fas', 'plus']" />{{
                                    $t('meals.override.action')
                                }}</Button
                            >
                        </div>
                    </div>
                    <div
                        v-if="panel?.counts.length"
                        class="mb-4 flex flex-wrap gap-2"
                    >
                        <span
                            v-for="count in panel.counts"
                            :key="`${count.date}-${count.type_id}`"
                            class="rounded-full border border-line bg-page px-3 py-1 text-xs text-charcoal"
                            ><strong>{{ count.type }}:</strong>
                            {{
                                $t(
                                    count.date === panel.today
                                        ? 'meals.claim.count'
                                        : 'meals.claim.carry_count',
                                    {
                                        total: count.total,
                                        left: count.left,
                                        day: dateLabel(count.date),
                                    },
                                )
                            }}<template v-if="count.overrides"
                                >,
                                {{
                                    $t(
                                        count.overrides === 1
                                            ? 'meals.override.count_one'
                                            : 'meals.override.count_many',
                                        { count: count.overrides },
                                    )
                                }}</template
                            ></span
                        >
                    </div>
                    <div
                        v-if="alert"
                        class="mb-4 flex items-center gap-2 rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm text-danger"
                        role="alert"
                    >
                        <Icon :name="['fas', 'circle-exclamation']" /><span
                            class="flex-1"
                            >{{ alert }}</span
                        ><Button
                            variant="ghost"
                            size="icon"
                            :aria-label="$t('ui.dialog.close')"
                            @click="alert = ''"
                            ><Icon :name="['fas', 'xmark']"
                        /></Button>
                    </div>
                    <div
                        ref="tableContainer"
                        class="overflow-x-auto"
                        :class="
                            panel && panel.recordsTotal <= 25
                                ? '[&_.dt-paging]:hidden'
                                : ''
                        "
                    >
                        <DataTable
                            :key="selected.id"
                            ref="table"
                            :ajax="loadMeals"
                            :columns="columns"
                            :options="tableOptions"
                        >
                            <template #nameCell="{ rowData }"
                                ><span class="font-semibold">{{
                                    rowData.name
                                }}</span
                                ><span class="mt-1 block text-xs text-muted">{{
                                    dateLabel(rowData.date)
                                }}</span></template
                            >
                            <template #typeCell="{ rowData }"
                                ><Badge pill>{{
                                    rowData.type
                                }}</Badge></template
                            >
                            <template #shiftCell="{ rowData }"
                                ><span
                                    v-if="rowData.is_override"
                                    class="text-muted"
                                    >{{ $t('meals.override.no_shift') }}</span
                                ><span
                                    v-else-if="rowData.source_shift_id !== null"
                                    class="whitespace-nowrap"
                                    >{{ rowData.shift_location }},
                                    {{
                                        dateLabel(
                                            rowData.shift_start?.slice(0, 10),
                                        )
                                    }}
                                    {{ rowData.shift_start?.slice(11) }}–{{
                                        rowData.shift_end?.slice(11)
                                    }}</span
                                ><span
                                    v-else
                                    class="text-muted"
                                    >{{
                                        $t('meals.claim.direct_assignment')
                                    }}</span
                                ></template
                            >
                            <template #statusCell="{ rowData }"
                                ><span
                                    v-if="rowData.used"
                                    class="inline-flex items-center gap-2 whitespace-nowrap text-success"
                                    ><Icon
                                        :name="['fas', 'check']"
                                        size="sm"
                                    />{{
                                        $t('meals.claim.used', {
                                            time: rowData.used_at,
                                        })
                                    }}</span
                                ><span
                                    v-else
                                    class="whitespace-nowrap text-muted"
                                    >{{ $t('meals.claim.not_used') }}</span
                                ><Badge
                                    v-if="rowData.is_override"
                                    pill
                                    class="ml-2"
                                    >{{
                                        $t('meals.override.origin', {
                                            time: rowData.override_at,
                                        })
                                    }}</Badge
                                ></template
                            >
                            <template #actionsCell="{ rowData }"
                                ><Tooltip
                                    v-if="rowData.eligible_today === false"
                                    :label="$t('meals.claim.outside_day')"
                                    ><Button
                                        variant="ghost"
                                        disabled
                                        >{{
                                            $t(
                                                rowData.used
                                                    ? 'meals.claim.unclaim'
                                                    : 'meals.claim.action',
                                            )
                                        }}</Button
                                    ><template #content>{{
                                        $t('meals.claim.outside_day')
                                    }}</template></Tooltip
                                ><template v-else
                                    ><Tooltip
                                        v-if="
                                            rowData.is_override &&
                                            !rowData.used &&
                                            !panel?.can_remove_override
                                        "
                                        :label="overrideReason(true)"
                                        ><Button
                                            variant="outline"
                                            class="mr-2"
                                            disabled
                                            >{{
                                                $t('meals.override.remove')
                                            }}</Button
                                        >
                                        <template #content>{{
                                            overrideReason(true)
                                        }}</template> </Tooltip
                                    ><Button
                                        v-else-if="
                                            rowData.is_override && !rowData.used
                                        "
                                        variant="outline"
                                        class="mr-2"
                                        :disabled="busy"
                                        @click="removeOverride(rowData)"
                                        >{{
                                            $t('meals.override.remove')
                                        }}</Button
                                    ><template v-if="rowData.used"
                                        ><Tooltip
                                            v-if="!panel?.can_claim"
                                            :label="disabledReason"
                                            ><Button
                                                variant="outline"
                                                disabled
                                                >{{
                                                    $t('meals.claim.unclaim')
                                                }}</Button
                                            ><template #content>{{
                                                disabledReason
                                            }}</template></Tooltip
                                        ><Button
                                            v-else
                                            variant="outline"
                                            :disabled="busy"
                                            @click="unclaim(rowData)"
                                            >{{
                                                $t('meals.claim.unclaim')
                                            }}</Button
                                        ></template
                                    ><template v-else
                                        ><Tooltip
                                            v-if="!panel?.can_claim"
                                            :label="disabledReason"
                                            ><Button disabled>{{
                                                $t('meals.claim.action')
                                            }}</Button
                                            ><template #content>{{
                                                disabledReason
                                            }}</template></Tooltip
                                        ><Button
                                            v-else
                                            :disabled="busy"
                                            @click="claim(rowData)"
                                            >{{
                                                $t('meals.claim.action')
                                            }}</Button
                                        ></template
                                    ></template
                                ></template
                            >
                        </DataTable>
                    </div>
                </Card>
                <EmptyState
                    v-else
                    :title="$t('meals.claim.pick_person')"
                    ><template #icon
                        ><Icon
                            :name="['fas', 'utensils']"
                            size="lg" /></template
                ></EmptyState>
            </div>
            <Dialog
                :open="overrideStep !== ''"
                :title="
                    overrideStep === 'pick'
                        ? $t('meals.override.title', {
                              name: panel?.person.name,
                          })
                        : ''
                "
                :description="overrideDescription"
                :sectioned="overrideStep === 'pick'"
                :cancel-align-start="overrideStep === 'pick'"
                :confirm-label="
                    $t(
                        overrideStep === 'remove'
                            ? 'meals.override.remove'
                            : 'meals.override.give_and_claim',
                    )
                "
                :confirm-variant="
                    overrideStep === 'remove' ? 'danger' : 'primary'
                "
                :confirm-disabled="
                    overrideStep === 'pick' && !overrideMeal?.available
                "
                :busy="busy"
                focus-trap
                @update:open="
                    (open) => {
                        if (!open) closeOverride();
                    }
                "
                @confirm="saveOverride(true)"
            >
                <template #footer-actions>
                    <Button
                        v-if="overrideStep === 'pick'"
                        variant="outline"
                        class="min-h-11 w-full sm:w-auto"
                        :disabled="busy || !overrideMeal?.available"
                        @click="saveOverride(false)"
                    >
                        {{ $t('meals.override.give') }}
                    </Button>
                </template>
                <div
                    v-if="overrideStep === 'pick'"
                    class="mt-4 flex flex-col gap-2"
                >
                    <p
                        v-if="busy"
                        class="text-sm text-muted"
                    >
                        {{ $t('meals.override.loading') }}
                    </p>
                    <p
                        v-else-if="!overrideOptions.length"
                        class="text-sm text-muted"
                    >
                        {{ $t('meals.override.no_meals') }}
                    </p>
                    <div
                        v-for="meal in overrideOptions"
                        :key="meal.id"
                        class="rounded-lg border border-line p-3"
                        :class="{ 'bg-page text-muted': !meal.available }"
                    >
                        <Radio
                            v-model="overrideMealId"
                            name="meal-override"
                            :value="meal.id"
                            :disabled="!meal.available || busy"
                            :label="
                                $t('meals.override.option', {
                                    meal: meal.name,
                                    type: meal.type,
                                    start: meal.starts_at,
                                    end: meal.ends_at,
                                })
                            "
                        />
                        <p
                            v-if="meal.date !== panel?.today"
                            class="mt-1 text-xs text-muted"
                        >
                            {{ dateLabel(meal.date) }}
                        </p>
                        <p
                            v-if="!meal.available"
                            class="mt-1 text-xs text-muted"
                        >
                            {{
                                $t('meals.override.unused', {
                                    name: panel?.person.name,
                                    type: meal.type,
                                })
                            }}
                        </p>
                    </div>
                </div>
            </Dialog>
            <Dialog
                :open="warning !== null"
                :description="warning?.message || ''"
                :confirm-label="$t('meals.claim.anyway')"
                :cancel-label="$t('ui.dialog.cancel')"
                confirm-variant="primary"
                :busy="busy"
                focus-trap
                @update:open="
                    (open) => {
                        if (!open) warning = null;
                    }
                "
                @confirm="claim(warning.meal, true)"
            />
        </div>
    </AppLayout>
</template>
