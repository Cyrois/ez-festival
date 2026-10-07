<script setup>
import { Button } from '../ui/button';
import { Card, CardTitle } from '../ui/card';
import { DataTable } from '../ui/data-table';
import { Dialog } from '../ui/dialog';
import { EmptyState } from '../ui/empty-state';
import { Icon } from '../ui/icon';
import { Tag } from '../ui/tag';
import { useFlashToast } from '../../composables/useFlashToast';
import { mealDateLabel, mealNextDayLabel } from '../../lib/mealDates';
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: { type: Object, required: true },
    canEdit: { type: Boolean, required: true },
    count: { type: Number, required: true },
});
const canWrite = computed(() => props.canEdit && !props.event.is_locked);
const disabledReason = computed(() =>
    props.event.is_locked
        ? trans('events.read_only_locked')
        : !props.canEdit
          ? trans('meals.read_only')
          : '',
);
const table = ref(null);
const deleting = ref(null);
const dialogOpen = ref(false);
const deleteError = ref('');
const form = useForm({});
const { showFormError } = useFlashToast();
const dateLabel = (date) => mealDateLabel(date, getActiveLanguage());
const nextDay = (date) => mealNextDayLabel(date, getActiveLanguage());
const columns = computed(() => [
    {
        data: 'name',
        title: trans('meals.name'),
        render: { display: '#nameCell' },
    },
    {
        data: 'meal_type.name',
        title: trans('meals.type'),
        render: { display: '#typeCell' },
    },
    {
        data: 'date',
        title: trans('meals.date'),
        render: { display: '#dateCell' },
    },
    {
        data: 'starts_at',
        title: trans('meals.window'),
        render: { display: '#windowCell' },
    },
    {
        data: null,
        defaultContent: '',
        title: '',
        render: { display: '#actionsCell' },
    },
]);
const tableOptions = computed(() => ({
    serverSide: true,
    searching: false,
    ordering: false,
    lengthChange: false,
    pageLength: 25,
    layout: { topStart: null, topEnd: null },
    columnDefs: [{ targets: 4, className: 'text-right' }],
    language: { emptyTable: trans('meals.list_empty') },
}));
const openDelete = (meal) => {
    if (!canWrite.value || form.processing) return;
    deleteError.value = '';
    form.clearErrors();
    deleting.value = meal;
    dialogOpen.value = true;
};
const reload = (resetPaging = false) => table.value?.reload(resetPaging);
const destroy = () => {
    if (!canWrite.value || form.processing || !deleting.value) return;
    form.delete(`/events/${props.event.id}/meals/${deleting.value.id}`, {
        preserveScroll: true,
        onSuccess: async () => {
            dialogOpen.value = false;
            deleting.value = null;
            await nextTick();
            // Start at the first page so deleting the final row on a page cannot strand the list.
            reload(true);
        },
        onError: (errors) => {
            dialogOpen.value = false;
            deleteError.value = errors.meal || '';
            showFormError(errors);
        },
    });
};
const deleteDescription = computed(() =>
    deleting.value
        ? trans('meals.delete_description', {
              name: deleting.value.name,
              type: deleting.value.meal_type.name,
              date: dateLabel(deleting.value.date),
              start: deleting.value.starts_at,
              end: deleting.value.ends_at,
          })
        : '',
);
defineExpose({ reload });
</script>

<template>
    <Card>
        <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
            <div>
                <CardTitle>{{ $t('meals.title') }}</CardTitle>
                <p class="mt-1 text-sm text-muted">{{ $t('meals.hint') }}</p>
            </div>
            <span
                :title="disabledReason || undefined"
                :tabindex="!canWrite ? 0 : undefined"
                :aria-label="!canWrite ? disabledReason : undefined"
            >
                <Button
                    href="/meals/settings/meals/create"
                    :disabled="!canWrite"
                >
                    <Icon
                        :name="['fas', 'plus']"
                        size="sm"
                    />
                    {{ $t('meals.add') }}
                </Button>
            </span>
        </div>
        <div
            v-if="deleteError"
            class="mb-4 flex items-center gap-2 rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm text-danger"
            role="alert"
        >
            <Icon :name="['fas', 'circle-exclamation']" />
            <span class="flex-1">{{ deleteError }}</span>
            <Button
                variant="ghost"
                size="icon"
                class="text-danger"
                :aria-label="$t('ui.dialog.close')"
                @click="deleteError = ''"
                ><Icon :name="['fas', 'xmark']"
            /></Button>
        </div>
        <EmptyState
            v-if="count === 0"
            :title="$t('meals.list_empty')"
        />
        <DataTable
            v-else
            :key="event.id"
            ref="table"
            ajax="/meals/settings/meals"
            :columns="columns"
            :options="tableOptions"
        >
            <template #nameCell="{ rowData }">
                <span class="font-semibold">{{ rowData.name }}</span>
            </template>
            <template #typeCell="{ rowData }">
                <Tag :name="rowData.meal_type.name" />
            </template>
            <template #dateCell="{ rowData }">{{
                dateLabel(rowData.date)
            }}</template>
            <template #windowCell="{ rowData }">
                <span class="whitespace-nowrap"
                    >{{ rowData.starts_at }}–{{ rowData.ends_at }}</span
                >
                <span
                    v-if="rowData.ends_at < rowData.starts_at"
                    class="ml-1 text-xs whitespace-nowrap text-muted"
                    >{{
                        $t('meals.ends_day', { day: nextDay(rowData.date) })
                    }}</span
                >
            </template>
            <template #actionsCell="{ rowData }">
                <span
                    class="inline-flex gap-2"
                    :title="disabledReason || undefined"
                    :tabindex="!canWrite ? 0 : undefined"
                    :aria-label="!canWrite ? disabledReason : undefined"
                >
                    <span
                        :title="
                            rowData.assigned_to_shifts
                                ? $t('meals.errors.assigned_edit', {
                                      name: rowData.name,
                                  })
                                : undefined
                        "
                        :tabindex="
                            canWrite && rowData.assigned_to_shifts
                                ? 0
                                : undefined
                        "
                        :aria-label="
                            rowData.assigned_to_shifts
                                ? $t('meals.errors.assigned_edit', {
                                      name: rowData.name,
                                  })
                                : undefined
                        "
                    >
                        <Button
                            :href="`/meals/settings/meals/${rowData.id}/edit`"
                            variant="outline-secondary"
                            size="icon"
                            :disabled="
                                !canWrite ||
                                form.processing ||
                                rowData.assigned_to_shifts
                            "
                            :aria-label="
                                $t('meals.edit_name', { name: rowData.name })
                            "
                            ><Icon :name="['fas', 'pencil']"
                        /></Button>
                    </span>
                    <Button
                        variant="outline-danger"
                        size="icon"
                        :disabled="!canWrite || form.processing"
                        :aria-label="
                            $t('meals.delete_name', { name: rowData.name })
                        "
                        @click="openDelete(rowData)"
                        ><Icon :name="['fas', 'trash-can']"
                    /></Button>
                </span>
            </template>
        </DataTable>
        <Dialog
            :open="dialogOpen"
            :title="
                deleting
                    ? $t('meals.delete_title', { name: deleting.name })
                    : ''
            "
            :description="deleteDescription"
            :confirm-label="$t('meals.delete')"
            :busy="form.processing"
            :confirm-disabled="!canWrite"
            sectioned
            focus-trap
            @update:open="dialogOpen = $event"
            @confirm="destroy"
        />
    </Card>
</template>
