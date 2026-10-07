<script setup>
import { computed, ref, watch } from 'vue';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';
import { CardTitle } from '../ui/card';
import { Button } from '../ui/button';
import { CustomDropdown } from '../ui/custom-dropdown';
import { FormField } from '../ui/form-field';
import { Icon } from '../ui/icon';
import { IconButton } from '../ui/icon-button';
import { Tag } from '../ui/tag';
import { Avatar } from '../ui/avatar';
import { draftShiftMeals, shiftMealOptions } from '../../lib/shiftMeals';
import { mealDateLabel } from '../../lib/mealDates';

const props = defineProps({
    modelValue: { type: Array, required: true },
    options: { type: Array, default: () => [] },
    people: { type: Array, default: () => [] },
    startsAt: { type: String, default: '' },
    endsAt: { type: String, default: '' },
    errors: { type: Object, default: () => ({}) },
    collectionError: { type: String, default: '' },
    editable: { type: Boolean, default: true },
    busy: { type: Boolean, default: false },
    disabledReason: { type: String, default: '' },
    canConfigure: { type: Boolean, default: false },
    copying: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'clear-error']);
const pendingMeal = ref('');
const pendingPeople = ref([]);
const attempted = ref(false);
// Remove selections when people leave the roster.
watch(
    () => props.people,
    (people) => {
        const keys = people.map((person) => person.id);
        pendingPeople.value = pendingPeople.value.filter((key) =>
            keys.includes(key),
        );
    },
);
const choices = computed(() =>
    shiftMealOptions(props.options, props.startsAt, props.endsAt),
);
const dayLabel = computed(() =>
    choices.value.days
        .map((day) => mealDateLabel(day, getActiveLanguage()))
        .join(', '),
);
const footer = computed(() =>
    trans('team.scheduling.meals.day_footer', { days: dayLabel.value }),
);
const serving = (meal) =>
    trans('team.scheduling.meals.serving', {
        type: meal.meal_type?.name ?? '',
        start: meal.starts_at,
        end: meal.ends_at,
    });
const mealItems = computed(() =>
    [...choices.value.suggested, ...choices.value.other].map((meal, index) => ({
        value: meal.id,
        title: meal.name,
        description: serving(meal),
        group: meal.suggested ? trans('team.scheduling.meals.suggested') : '',
        note:
            index === 0 && meal.suggested
                ? trans('team.scheduling.meals.closest')
                : '',
    })),
);
const hints = computed(() => ({
    [trans('team.scheduling.meals.suggested')]: trans(
        'team.scheduling.meals.suggestion_hint',
        {
            start: props.startsAt.slice(11),
            end: props.endsAt.slice(11),
            middle: new Date(
                (Date.parse(`${props.startsAt}Z`) +
                    Date.parse(`${props.endsAt}Z`)) /
                    2,
            )
                .toISOString()
                .slice(11, 16),
        },
    ),
}));
const peopleItems = computed(() =>
    props.people.map((person) => ({
        value: person.id,
        title: person.name,
        description: person.role_name,
    })),
);
const pendingReason = computed(() =>
    !props.editable
        ? props.disabledReason
        : !props.people.length
          ? trans('team.scheduling.meals.no_people')
          : '',
);
const changePeople = (row, keys) => {
    if (!props.editable || props.busy) return;
    emit(
        'update:modelValue',
        props.modelValue.map((value) =>
            value._key === row._key
                ? { ...value, assignment_keys: [...keys] }
                : value,
        ),
    );
    emit('clear-error');
};
const add = () => {
    if (!props.editable || props.busy || !props.people.length) return;
    attempted.value = true;
    const meal = props.options.find((value) => value.id === pendingMeal.value);
    if (
        !meal ||
        !pendingPeople.value.length ||
        !mealItems.value.some((item) => item.value === meal.id)
    )
        return;
    const existing = props.modelValue.find((row) => row.meal_id === meal.id);
    if (existing) {
        changePeople(existing, [
            ...new Set([...existing.assignment_keys, ...pendingPeople.value]),
        ]);
    } else {
        emit('update:modelValue', [
            ...props.modelValue,
            ...draftShiftMeals([
                {
                    meal_id: meal.id,
                    meal,
                    assignment_keys: pendingPeople.value,
                },
            ]),
        ]);
    }
    pendingMeal.value = '';
    pendingPeople.value = [];
    attempted.value = false;
    emit('clear-error');
};
const remove = (row) => {
    if (!props.editable || props.busy) return;
    emit(
        'update:modelValue',
        props.modelValue.filter((value) => value._key !== row._key),
    );
    emit('clear-error');
};
</script>

<template>
    <div>
        <div class="mb-2 flex items-center justify-between gap-3">
            <CardTitle>{{ $t('team.scheduling.meals.title') }}</CardTitle>
            <span class="text-sm text-muted">{{
                $t('team.scheduling.meals.optional')
            }}</span>
        </div>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <p
                v-if="!copying"
                class="m-0 text-sm text-muted"
            >
                {{ $t('team.scheduling.meals.subtitle') }}
            </p>
            <Button
                v-if="canConfigure"
                href="/meals/settings"
                variant="ghost"
                size="sm"
            >
                <Icon :name="['fas', 'gear']" />{{
                    $t('team.scheduling.meals.configure')
                }}
            </Button>
        </div>
        <p
            v-if="copying"
            role="status"
            class="flex items-center gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm text-charcoal"
        >
            <Icon
                :name="['fas', 'triangle-exclamation']"
                class="text-warning"
            />{{ $t('team.scheduling.meals.not_copied') }}
        </p>
        <template v-else>
            <p
                v-if="collectionError"
                class="mb-2 text-sm text-danger"
                role="alert"
            >
                {{ collectionError }}
            </p>
            <div
                class="grid items-start gap-3 rounded-lg bg-page p-3 sm:grid-cols-[1fr_1fr_auto]"
            >
                <FormField
                    v-slot="{ id, invalid }"
                    :label="$t('team.scheduling.meals.meal')"
                    :error="
                        attempted && !pendingMeal
                            ? $t('team.scheduling.meals.errors.pick')
                            : ''
                    "
                    required
                >
                    <CustomDropdown
                        :id="id"
                        v-model="pendingMeal"
                        :items="mealItems"
                        :placeholder="$t('team.scheduling.meals.pick')"
                        :empty-text="$t('team.scheduling.meals.no_choices')"
                        :invalid="invalid"
                        :disabled="!editable || busy"
                        match-trigger-width
                        :footer="footer"
                        :group-hints="mealItems.length ? hints : {}"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    :label="$t('team.scheduling.meals.people')"
                    :error="
                        attempted && !pendingPeople.length
                            ? $t('team.scheduling.meals.errors.empty')
                            : ''
                    "
                    required
                >
                    <CustomDropdown
                        :id="id"
                        v-model="pendingPeople"
                        :items="peopleItems"
                        :placeholder="$t('team.scheduling.meals.pick_people')"
                        :invalid="invalid"
                        :disabled="!editable || busy"
                        multiple
                        :show-selected="false"
                        match-trigger-width
                        :action-label="$t('team.scheduling.meals.select_all')"
                        :search-placeholder="
                            $t('team.scheduling.meals.search_people')
                        "
                        @action="
                            pendingPeople = people.map((person) => person.id)
                        "
                    />
                </FormField>
                <span
                    class="sm:pt-6"
                    :title="pendingReason"
                    :tabindex="!editable || !people.length ? 0 : undefined"
                >
                    <Button
                        :disabled="!editable || busy || !people.length"
                        @click="add"
                        ><Icon :name="['fas', 'plus']" />{{
                            $t('team.scheduling.meals.add')
                        }}</Button
                    >
                </span>
                <div
                    v-if="pendingPeople.length"
                    class="flex min-w-0 flex-wrap gap-1 sm:col-span-3"
                >
                    <Tag
                        v-for="person in people.filter((person) =>
                            pendingPeople.includes(person.id),
                        )"
                        :key="person.id"
                        :name="person.name"
                        class="max-w-full"
                        :removable="editable && !busy"
                        :remove-label="
                            $t('dropdown.remove', { name: person.name })
                        "
                        @remove="
                            pendingPeople = pendingPeople.filter(
                                (id) => id !== person.id,
                            )
                        "
                    >
                        <template #leading>
                            <Avatar
                                :name="person.name"
                                size="xs"
                            />
                        </template>
                    </Tag>
                </div>
            </div>
            <div
                v-for="row in modelValue"
                :key="row._key"
                class="mt-3 grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3 rounded-lg border border-line p-3"
            >
                <div>
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <p class="m-0 text-sm font-semibold">
                            {{ row.meal.name }}
                        </p>
                        <p class="m-0 text-xs text-muted">
                            {{ serving(row.meal) }}
                        </p>
                    </div>
                    <p
                        v-if="errors[row._key]?.meal_id"
                        class="mt-1 text-xs text-danger"
                        role="alert"
                    >
                        {{ errors[row._key].meal_id }}
                    </p>
                </div>
                <div class="col-span-2 row-start-2 min-w-0">
                    <div class="flex flex-wrap gap-1">
                        <Tag
                            v-for="person in people.filter((person) =>
                                row.assignment_keys.includes(person.id),
                            )"
                            :key="person.id"
                            :name="person.name"
                            class="max-w-full"
                            :removable="editable && !busy"
                            :remove-label="
                                $t('dropdown.remove', { name: person.name })
                            "
                            @remove="
                                changePeople(
                                    row,
                                    row.assignment_keys.filter(
                                        (id) => id !== person.id,
                                    ),
                                )
                            "
                        >
                            <template #leading>
                                <Avatar
                                    :name="person.name"
                                    size="xs"
                                />
                            </template>
                        </Tag>
                    </div>
                    <p
                        v-if="
                            errors[row._key]?.assignment_keys ||
                            !row.assignment_keys.length
                        "
                        class="mt-1 text-xs text-danger"
                        role="alert"
                    >
                        {{
                            errors[row._key]?.assignment_keys ||
                            $t('team.scheduling.meals.errors.empty')
                        }}
                    </p>
                </div>
                <IconButton
                    v-if="editable"
                    class="col-start-2 row-start-1"
                    :label="
                        $t('team.scheduling.meals.remove', {
                            name: row.meal.name,
                        })
                    "
                    :disabled="busy"
                    :icon="['fas', 'trash-can']"
                    tone="delete"
                    @click="remove(row)"
                />
            </div>
            <p
                v-if="!modelValue.length"
                class="mt-3 text-sm text-muted"
            >
                {{ $t('team.scheduling.meals.empty') }}
            </p>
            <p class="mt-3 mb-0 text-xs text-muted">
                {{ $t('team.scheduling.meals.save_hint') }}
            </p>
        </template>
    </div>
</template>
