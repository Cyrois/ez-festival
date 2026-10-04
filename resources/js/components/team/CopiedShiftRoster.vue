<script setup>
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { CardTitle } from '../ui/card';
import { Avatar } from '../ui/avatar';
import { Badge } from '../ui/badge';
import { CustomDropdown } from '../ui/custom-dropdown';
import { FormField } from '../ui/form-field';
import { Input } from '../ui/input';
import { IconButton } from '../ui/icon-button';
import ShiftOverlapWarnings from './ShiftOverlapWarnings.vue';
import { copiedRoster, copiedHoursValid } from '../../lib/shiftCopy';

const props = defineProps({
    draft: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    busy: { type: Boolean, default: false },
});
const emit = defineEmits(['update', 'remove']);
const roster = computed(() => copiedRoster(props.draft));
const slotItems = computed(() => [
    { value: '', title: trans('team.scheduling.assignments.detached') },
    ...props.draft.slots.map((slot) => ({
        value: slot._key,
        title: slot.role_name,
    })),
]);
const modes = computed(() => [
    {
        value: 'full_shift',
        title: trans('team.scheduling.assignments.full_shift'),
    },
    { value: 'custom', title: trans('team.scheduling.assignments.custom') },
]);
const reasons = (row) => [
    ...(row.error ? [row.error] : []),
    ...Object.values(props.errors[row._key] ?? {}),
    ...(!copiedHoursValid(row, props.draft.starts_at, props.draft.ends_at)
        ? [
              trans('team.scheduling.assignments.errors.hours', {
                  from: props.draft.starts_at.replace('T', ' '),
                  to: props.draft.ends_at.replace('T', ' '),
              }),
          ]
        : []),
];
defineExpose({
    validate: () =>
        roster.value.assignments.every((row) => reasons(row).length === 0),
});
</script>

<template>
    <section>
        <CardTitle class="mb-2">{{
            $t('team.scheduling.assignments.roster')
        }}</CardTitle>
        <p class="m-0 text-sm text-muted">
            {{
                $t('team.scheduling.slots.filled', {
                    filled: roster.filled_count,
                    count: roster.total_needs,
                })
            }}
        </p>
        <p
            v-if="roster.extra_count"
            class="mt-1 text-sm text-warning"
        >
            {{
                $t(
                    roster.extra_count === 1
                        ? 'team.scheduling.assignments.extra_one'
                        : 'team.scheduling.assignments.extra_many',
                    { count: roster.extra_count },
                )
            }}
        </p>
        <p class="mt-2 text-sm text-muted">
            {{ $t('team.scheduling.copy.unsaved') }}
        </p>
        <ul class="m-0 list-none divide-y divide-line p-0">
            <li
                v-for="row in roster.assignments"
                :key="row._key"
                :data-copy-person="row._key"
                class="grid items-start gap-3 py-4 xl:grid-cols-[minmax(12rem,1fr)_9rem_14rem_14rem_2.75rem]"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <Avatar
                            :name="row.name"
                            size="sm"
                        />
                        <span class="font-semibold">{{ row.name }}</span>
                        <Badge
                            v-if="row.is_extra"
                            pill
                            variant="warning"
                            >{{
                                $t('team.scheduling.assignments.extra')
                            }}</Badge
                        >
                    </div>
                    <FormField
                        class="mt-2"
                        :label="$t('team.scheduling.slots.role')"
                    >
                        <template #default="{ id }">
                            <CustomDropdown
                                :id="id"
                                :model-value="
                                    draft.slots.some(
                                        (slot) => slot._key === row._slot_key,
                                    )
                                        ? row._slot_key
                                        : ''
                                "
                                :items="slotItems"
                                :disabled="busy"
                                @update:model-value="
                                    emit('update', row, '_slot_key', $event)
                                "
                            />
                        </template>
                    </FormField>
                    <p
                        v-if="
                            !draft.slots.some(
                                (slot) => slot._key === row._slot_key,
                            )
                        "
                        class="mt-1 text-sm text-muted"
                    >
                        {{ row.role_name }}
                    </p>
                    <ShiftOverlapWarnings :overlaps="row.overlaps" />
                    <p
                        v-for="reason in [...new Set(reasons(row))]"
                        :key="reason"
                        class="mt-2 text-sm text-danger"
                        role="alert"
                    >
                        {{ reason }}
                    </p>
                </div>
                <FormField :label="$t('team.scheduling.copy.hours')">
                    <template #default="{ id }">
                        <CustomDropdown
                            :id="id"
                            :model-value="row.hours_mode"
                            :items="modes"
                            :disabled="busy"
                            @update:model-value="
                                emit('update', row, 'hours_mode', $event)
                            "
                        />
                    </template>
                </FormField>
                <FormField :label="$t('team.scheduling.fields.start')">
                    <template #default="{ id }">
                        <Input
                            :id="id"
                            :model-value="row.starts_at"
                            type="datetime-local"
                            :invalid="
                                !copiedHoursValid(
                                    row,
                                    draft.starts_at,
                                    draft.ends_at,
                                )
                            "
                            :disabled="busy || row.hours_mode === 'full_shift'"
                            @update:model-value="
                                emit('update', row, 'starts_at', $event)
                            "
                        />
                    </template>
                </FormField>
                <FormField :label="$t('team.scheduling.fields.end')">
                    <template #default="{ id }">
                        <Input
                            :id="id"
                            :model-value="row.ends_at"
                            type="datetime-local"
                            :invalid="
                                !copiedHoursValid(
                                    row,
                                    draft.starts_at,
                                    draft.ends_at,
                                )
                            "
                            :disabled="busy || row.hours_mode === 'full_shift'"
                            @update:model-value="
                                emit('update', row, 'ends_at', $event)
                            "
                        />
                    </template>
                </FormField>
                <IconButton
                    :icon="['fas', 'trash-can']"
                    tone="delete"
                    class="h-10 w-10 justify-self-end xl:mt-6"
                    :label="
                        $t('team.scheduling.assignments.remove_person', {
                            name: row.name,
                        })
                    "
                    :disabled="busy"
                    @click="emit('remove', row)"
                />
            </li>
        </ul>
        <p
            v-if="!roster.assignments.length"
            class="mt-4 text-sm text-muted"
        >
            {{ $t('team.scheduling.copy.empty') }}
        </p>
    </section>
</template>
