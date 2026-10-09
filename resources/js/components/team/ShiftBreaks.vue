<script setup>
import { overlappingBreak } from '../../lib/personalBreaks';
import { CardTitle } from '../ui/card';
import { computed, ref, useId, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { Button } from '../ui/button';
import { CustomDropdown } from '../ui/custom-dropdown';
import { FormField } from '../ui/form-field';
import { Icon } from '../ui/icon';
import { IconButton } from '../ui/icon-button';
import { Input } from '../ui/input';
import { Tooltip } from '../ui/tooltip';
import {
    newShiftBreak,
    shiftBreakDays,
    updateShiftBreak,
    validateShiftBreaks,
    wallMinutes,
} from '../../lib/shiftBreaks';

const props = defineProps({
    modelValue: { type: Array, required: true },
    options: { type: Object, required: true },
    startsAt: { type: String, default: '' },
    endsAt: { type: String, default: '' },
    errors: { type: Object, default: () => ({}) },
    collectionError: { type: String, default: '' },
    editable: { type: Boolean, default: true },
    personal: { type: Boolean, default: false },
    mass: { type: Boolean, default: false },
    people: { type: Array, default: () => [] },
    busy: { type: Boolean, default: false },
    disabledReason: { type: String, default: '' },
});
const emit = defineEmits([
    'update:modelValue',
    'mass-add',
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
const massError = ref('');
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
    return local
        ? trans(
              props.personal &&
                  local === 'team.scheduling.breaks.errors.containment'
                  ? 'team.scheduling.breaks.errors.personal_containment'
                  : local,
          )
        : (props.errors[row._key]?.[field] ?? '');
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
    massError.value = '';
    const updated = updateShiftBreak(row, field, value);
    if (
        props.personal &&
        ['_day', '_time', 'duration_minutes'].includes(field)
    ) {
        updated.shift_break_id = null;
        delete updated.shift_break_key;
    }
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
    if (!props.personal && Object.keys(pendingErrors.value).length) return;
    if (props.mass) {
        if (
            props.people.some((person) =>
                overlappingBreak(pending.value, person.breaks ?? []),
            )
        ) {
            massError.value = trans(
                'team.scheduling.breaks.errors.mass_conflict',
            );
            return;
        }
        if (!props.people.length) {
            massError.value = trans('team.scheduling.breaks.errors.no_people');
            return;
        }
        massError.value = '';
        emit('mass-add', { ...pending.value });
        pending.value = createPendingBreak();
        attemptedAdd.value = false;
        return;
    }
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
const formatStart = (row) => {
    if (!Number.isFinite(wallMinutes(row.starts_at)))
        return trans('team.scheduling.breaks.errors.start');
    return new Intl.DateTimeFormat('en-US', {
        ...(row._day !== props.startsAt.slice(0, 10) || days.value.length > 1
            ? { year: 'numeric', month: 'short', day: 'numeric' }
            : {}),
        hour: 'numeric',
        minute: '2-digit',
        timeZone: 'UTC',
    }).format(new Date(`${row.starts_at}Z`));
};
defineExpose({ validate: () => Object.keys(localErrors.value).length === 0 });
</script>

<template>
    <section class="@container space-y-4">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <CardTitle>
                    {{ $t('team.scheduling.breaks.title') }}
                </CardTitle>
                <Tooltip :label="$t('team.scheduling.breaks.paid_hours')">
                    <Icon
                        :name="['fas', 'circle-info']"
                        class="text-muted"
                        size="sm"
                    />
                    <template #content>
                        {{ $t('team.scheduling.breaks.paid_hours') }}
                    </template>
                </Tooltip>
            </div>
        </div>
        <p
            v-if="!personal"
            class="text-sm text-muted"
        >
            {{
                $t(
                    mass
                        ? 'team.scheduling.breaks.mass_description'
                        : 'team.scheduling.breaks.description',
                )
            }}
        </p>
        <template v-if="editable">
            <div
                v-for="row in mass ? [pending] : [pending, ...modelValue]"
                :key="row._key"
                :data-break-row="row._key"
                class="grid grid-cols-[minmax(0,8rem)_minmax(0,1fr)] gap-3 rounded-lg @min-[32rem]:grid-cols-[8rem_11rem_minmax(0,1fr)]"
                :class="
                    row._key === pending._key
                        ? 'items-start bg-page p-3 ring-1 ring-line ring-inset'
                        : 'items-center bg-page/50 px-3 py-2'
                "
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
                            :class="row._key !== pending._key ? 'h-8 px-2' : ''"
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
                                :class="
                                    row._key !== pending._key ? 'h-8 px-2' : ''
                                "
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
                                :class="
                                    row._key !== pending._key ? 'h-8 px-2' : ''
                                "
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
                    class="col-span-2 h-10 w-auto justify-self-end @min-[32rem]:col-span-1 @min-[32rem]:self-end"
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
                    class="col-span-2 h-8 w-8 justify-self-end bg-ground @min-[32rem]:col-span-1"
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
            <p
                v-if="mass || !days.length"
                class="m-0 text-xs text-muted"
            >
                {{
                    $t(
                        days.length
                            ? 'team.scheduling.breaks.mass_hint'
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
            v-if="!mass && !modelValue.length"
            class="m-0 text-sm text-muted"
        >
            {{ $t('team.scheduling.breaks.empty') }}
        </p>
        <p
            v-if="massError || collectionError"
            class="m-0 text-sm text-danger"
            role="alert"
        >
            {{ massError || collectionError }}
        </p>
    </section>
</template>
