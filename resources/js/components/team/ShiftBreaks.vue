<script setup>
import { CardTitle } from '../ui/card';
import { computed, ref, useId, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { Button } from '../ui/button';
import { CustomDropdown } from '../ui/custom-dropdown';
import { FormField } from '../ui/form-field';
import { Icon } from '../ui/icon';
import { IconButton } from '../ui/icon-button';
import { Input } from '../ui/input';
import {
    newShiftBreak,
    shiftBreakDays,
    updateShiftBreak,
    validateShiftBreaks,
} from '../../lib/shiftBreaks';

const props = defineProps({
    modelValue: { type: Array, required: true },
    options: { type: Object, required: true },
    startsAt: { type: String, default: '' },
    endsAt: { type: String, default: '' },
    errors: { type: Object, default: () => ({}) },
    collectionError: { type: String, default: '' },
    editable: { type: Boolean, default: true },
    busy: { type: Boolean, default: false },
    disabledReason: { type: String, default: '' },
});
const emit = defineEmits([
    'update:modelValue',
    'clear-error',
    'clear-containment-errors',
]);
const reasonId = useId();
const days = computed(() => shiftBreakDays(props.startsAt, props.endsAt));
const createPendingBreak = () =>
    newShiftBreak(
        props.options.default_duration,
        props.startsAt.slice(0, 10),
        props.startsAt.slice(11, 16),
    );
const pending = ref(createPendingBreak());
const attemptedAdd = ref(false);
const formatDuration = (minutes) =>
    minutes === 60
        ? trans('team.scheduling.breaks.hour')
        : trans('team.scheduling.breaks.minutes', { minutes });
const durationItems = computed(() =>
    props.options.durations.map((minutes) => ({
        value: minutes,
        title: formatDuration(minutes),
    })),
);
const localErrors = computed(() =>
    validateShiftBreaks(
        props.modelValue,
        props.startsAt,
        props.endsAt,
        props.options.durations,
    ),
);
const pendingErrors = computed(() =>
    attemptedAdd.value
        ? (validateShiftBreaks(
              [...props.modelValue, pending.value],
              props.startsAt,
              props.endsAt,
              props.options.durations,
          )[pending.value._key] ?? {})
        : {},
);
const error = (row, field) => {
    const local =
        row._key === pending.value._key
            ? pendingErrors.value[field]
            : localErrors.value[row._key]?.[field];
    return local ? trans(local) : (props.errors[row._key]?.[field] ?? '');
};
const showDay = (row) =>
    days.value.length > 1 ||
    Boolean(row._day && !days.value.includes(row._day));
const dayItems = (row) => [
    ...(row._day && !days.value.includes(row._day)
        ? [{ value: row._day, title: row._day, disabled: true }]
        : []),
    ...days.value.map((day) => ({ value: day, title: day })),
];
watch(days, (values) => {
    // Only initialize a blank pending date. Saved/draft dates never move with shift changes.
    if (!pending.value._day)
        pending.value = updateShiftBreak(
            pending.value,
            '_day',
            values[0] ?? '',
        );
    emit('clear-containment-errors');
});
watch(
    () => props.startsAt,
    (value) => {
        if (!pending.value._time && value) {
            if (!pending.value._day)
                pending.value = updateShiftBreak(
                    pending.value,
                    '_day',
                    value.slice(0, 10),
                );
            pending.value = updateShiftBreak(
                pending.value,
                '_time',
                value.slice(11, 16),
            );
        }
    },
);
const update = (row, field, value) => {
    if (!props.editable || props.busy) return;
    const updated = updateShiftBreak(row, field, value);
    if (row._key === pending.value._key) pending.value = updated;
    else
        emit(
            'update:modelValue',
            props.modelValue.map((item) =>
                item._key === row._key ? updated : item,
            ),
        );
    emit(
        'clear-error',
        row._key,
        field === '_day' || field === '_time' ? 'starts_at' : field,
    );
    if (['_day', '_time', 'duration_minutes'].includes(field))
        emit('clear-containment-errors');
};
const add = () => {
    if (!props.editable || props.busy) return;
    attemptedAdd.value = true;
    if (Object.keys(pendingErrors.value).length) return;
    emit('update:modelValue', [...props.modelValue, { ...pending.value }]);
    pending.value = createPendingBreak();
    attemptedAdd.value = false;
};
const remove = (row) => {
    if (!props.editable || props.busy) return;
    emit(
        'update:modelValue',
        props.modelValue.filter((item) => item._key !== row._key),
    );
    emit('clear-containment-errors');
};
const formatStart = (row) =>
    new Intl.DateTimeFormat('en-US', {
        ...(row._day !== props.startsAt.slice(0, 10) || days.value.length > 1
            ? { year: 'numeric', month: 'short', day: 'numeric' }
            : {}),
        hour: 'numeric',
        minute: '2-digit',
        timeZone: 'UTC',
    }).format(new Date(`${row.starts_at}Z`));
defineExpose({ validate: () => Object.keys(localErrors.value).length === 0 });
</script>

<template>
    <section class="@container space-y-4">
        <div class="flex items-center justify-between gap-3">
            <CardTitle>
                {{ $t('team.scheduling.breaks.title') }}
            </CardTitle>
            <span class="text-sm text-muted">{{
                $t('team.scheduling.breaks.optional')
            }}</span>
        </div>
        <p class="m-0 text-sm text-muted">
            {{ $t('team.scheduling.breaks.description') }}
        </p>
        <template v-if="editable">
            <div
                v-for="row in [pending, ...modelValue]"
                :key="row._key"
                :data-break-row="row._key"
                class="grid grid-cols-[minmax(0,8rem)_minmax(0,1fr)] items-start gap-3 rounded-lg border border-line p-3 @min-[32rem]:grid-cols-[8rem_11rem_minmax(0,1fr)]"
                :class="row._key === pending._key ? 'bg-page' : 'bg-ground'"
            >
                <FormField
                    class="w-full max-w-32"
                    :label="
                        row._key === pending._key
                            ? $t('team.scheduling.breaks.length')
                            : ''
                    "
                    :required="row._key === pending._key"
                    :error="error(row, 'duration_minutes')"
                >
                    <template #default="{ id, invalid }">
                        <CustomDropdown
                            :id="id"
                            :model-value="row.duration_minutes"
                            :items="durationItems"
                            :aria-label="$t('team.scheduling.breaks.length')"
                            :invalid="invalid"
                            :disabled="busy"
                            @update:model-value="
                                update(row, 'duration_minutes', $event)
                            "
                        />
                    </template>
                </FormField>
                <div class="w-full max-w-44 space-y-2">
                    <FormField
                        :label="
                            row._key === pending._key
                                ? $t('team.scheduling.breaks.start')
                                : ''
                        "
                        :required="row._key === pending._key"
                        :error="error(row, 'starts_at')"
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                :model-value="row._time"
                                type="time"
                                :aria-label="$t('team.scheduling.breaks.start')"
                                :invalid="invalid"
                                :disabled="busy"
                                @update:model-value="
                                    update(row, '_time', $event)
                                "
                            />
                        </template>
                    </FormField>
                    <FormField
                        v-if="showDay(row)"
                        :label="$t('team.scheduling.breaks.day')"
                        required
                    >
                        <template #default="{ id }">
                            <CustomDropdown
                                :id="id"
                                :model-value="row._day"
                                :items="dayItems(row)"
                                :aria-label="$t('team.scheduling.breaks.day')"
                                :disabled="busy"
                                @update:model-value="
                                    update(row, '_day', $event)
                                "
                            />
                        </template>
                    </FormField>
                </div>
                <Button
                    v-if="row._key === pending._key"
                    type="button"
                    class="col-span-2 h-10 w-auto justify-self-end @min-[32rem]:col-span-1 @min-[32rem]:mt-6"
                    :disabled="busy || !days.length"
                    @click="add"
                    ><Icon :name="['fas', 'plus']" />{{
                        $t('team.scheduling.breaks.add')
                    }}</Button
                >
                <IconButton
                    v-else
                    :icon="['fas', 'trash-can']"
                    tone="delete"
                    class="col-span-2 h-10 w-10 justify-self-end @min-[32rem]:col-span-1"
                    :disabled="busy"
                    :label="$t('team.scheduling.breaks.remove')"
                    @click="remove(row)"
                />
                <p
                    v-if="error(row, 'id')"
                    class="col-span-2 m-0 text-xs text-danger @min-[32rem]:col-span-3"
                    role="alert"
                >
                    {{ error(row, 'id') }}
                </p>
            </div>
            <p class="m-0 text-xs text-muted">
                {{
                    $t(
                        days.length
                            ? 'team.scheduling.breaks.save_hint'
                            : 'team.scheduling.breaks.errors.shift_times',
                    )
                }}
            </p>
        </template>
        <template v-else>
            <span
                class="inline-flex rounded-lg outline-none focus:ring-2 focus:ring-primary"
                tabindex="0"
                :title="disabledReason"
                :aria-describedby="reasonId"
            >
                <Button
                    type="button"
                    class="pointer-events-none"
                    disabled
                    ><Icon :name="['fas', 'plus']" />{{
                        $t('team.scheduling.breaks.add')
                    }}</Button
                >
            </span>
            <span
                :id="reasonId"
                class="sr-only"
                >{{ disabledReason }}</span
            >
            <ul class="m-0 list-none space-y-2 p-0">
                <li
                    v-for="row in modelValue"
                    :key="row._key"
                    class="flex flex-wrap gap-3 rounded-lg border border-line p-3 text-sm"
                >
                    <span>{{ formatDuration(row.duration_minutes) }}</span>
                    <span>{{ formatStart(row) }}</span>
                </li>
            </ul>
        </template>
        <p
            v-if="!modelValue.length"
            class="m-0 text-sm text-muted"
        >
            {{ $t('team.scheduling.breaks.empty') }}
        </p>
        <p
            v-if="collectionError"
            class="m-0 text-sm text-danger"
            role="alert"
        >
            {{ collectionError }}
        </p>
    </section>
</template>
