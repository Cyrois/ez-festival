<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Button } from '../../components/ui/button';
import { Card, CardTitle } from '../../components/ui/card';
import { CustomDropdown } from '../../components/ui/custom-dropdown';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { useFlashToast } from '../../composables/useFlashToast';
import { toastFormErrors } from '../../lib/fieldError';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: { type: Object, required: true },
    meal: { type: Object, default: null },
    mealTypes: { type: Array, required: true },
    canWrite: { type: Boolean, required: true },
});
const creating = computed(() => props.meal === null);
const title = computed(() =>
    trans(creating.value ? 'meals.add' : 'meals.edit'),
);
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.meals'), href: '/meals' },
    { label: trans('meals.settings.title'), href: '/meals/settings' },
    { label: title.value },
]);
const form = useForm({
    name: props.meal?.name ?? '',
    meal_type_id: props.meal?.meal_type_id ?? '',
    date: props.meal?.date ?? '',
    starts_at: props.meal?.starts_at ?? '',
    ends_at: props.meal?.ends_at ?? '',
});
const type = computed(() =>
    props.mealTypes.find((item) => item.id === form.meal_type_id),
);
const typeItems = computed(() =>
    props.mealTypes.map((item) => ({ value: item.id, title: item.name })),
);
watch(
    () => form.meal_type_id,
    () => {
        if (creating.value && type.value) {
            form.starts_at = type.value.starts_at;
            form.ends_at = type.value.ends_at;
        }
    },
);
const typeHint = computed(() =>
    type.value
        ? trans(creating.value ? 'meals.window_filled' : 'meals.type_window', {
              type: type.value.name,
              start: type.value.starts_at,
              end: type.value.ends_at,
          })
        : '',
);
const { showError, showFormError } = useFlashToast();
const submit = () => {
    if (!props.canWrite || form.processing) return;
    const options = {
        onError: (errors) =>
            toastFormErrors(form, errors, { showError, showFormError }),
    };
    const url = `/events/${props.event.id}/meals`;
    if (creating.value) form.post(url, options);
    else form.put(`${url}/${props.meal.id}`, options);
};
</script>

<template>
    <AppLayout
        :title="title"
        :breadcrumbs="breadcrumbs"
        back-href="/meals/settings"
        :back-label="$t('meals.back_settings')"
    >
        <div class="container mx-auto max-w-6xl pb-24 xl:max-w-none">
            <h1 class="mb-5 text-2xl font-bold text-charcoal">{{ title }}</h1>
            <p
                v-if="event.is_locked"
                class="mb-5 rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-charcoal"
                role="status"
            >
                {{ $t('events.read_only_locked') }}
            </p>
            <p
                v-if="meal?.assigned_to_shifts"
                class="mb-5 rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-charcoal"
                role="status"
            >
                {{ $t('meals.errors.assigned_edit', { name: meal.name }) }}
            </p>
            <form
                id="meal-form"
                class="grid items-start gap-4 xl:grid-cols-2"
                novalidate
                @submit.prevent="submit"
            >
                <Card class="min-w-0">
                    <CardTitle class="mb-4">{{
                        $t('meals.details')
                    }}</CardTitle>
                    <div class="space-y-4">
                        <FormField
                            v-slot="{ id, invalid }"
                            :label="$t('meals.name')"
                            :error="form.errors.name"
                            :hint="$t('meals.name_hint')"
                            class="max-w-xl"
                            required
                        >
                            <Input
                                :id="id"
                                v-model="form.name"
                                maxlength="255"
                                :invalid="invalid"
                                :disabled="!canWrite || form.processing"
                                autocomplete="off"
                                required
                            />
                        </FormField>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormField
                                v-slot="{ id, invalid }"
                                :label="$t('meals.type')"
                                :error="form.errors.meal_type_id"
                                :hint="$t('meals.type_hint')"
                                required
                            >
                                <CustomDropdown
                                    :id="id"
                                    v-model="form.meal_type_id"
                                    :items="typeItems"
                                    :placeholder="$t('meals.pick_type')"
                                    :empty-text="$t('meals.types.empty')"
                                    :invalid="invalid"
                                    :disabled="!canWrite || form.processing"
                                    aria-required="true"
                                />
                            </FormField>
                            <FormField
                                v-slot="{ id, invalid }"
                                :label="$t('meals.date')"
                                :error="form.errors.date"
                                :hint="$t('meals.date_hint')"
                                required
                            >
                                <Input
                                    :id="id"
                                    v-model="form.date"
                                    type="date"
                                    :invalid="invalid"
                                    :disabled="!canWrite || form.processing"
                                    required
                                />
                            </FormField>
                        </div>
                    </div>
                </Card>
                <Card class="min-w-0">
                    <CardTitle class="mb-4">{{ $t('meals.window') }}</CardTitle>
                    <div class="grid grid-cols-2 gap-4">
                        <FormField
                            v-slot="{ id, invalid }"
                            :label="$t('meals.start')"
                            :error="form.errors.starts_at"
                            :hint="$t('meals.start_hint')"
                            required
                        >
                            <Input
                                :id="id"
                                v-model="form.starts_at"
                                type="time"
                                step="60"
                                :invalid="invalid"
                                :disabled="!canWrite || form.processing"
                                required
                            />
                        </FormField>
                        <FormField
                            v-slot="{ id, invalid }"
                            :label="$t('meals.end')"
                            :error="form.errors.ends_at"
                            :hint="$t('meals.overnight_hint')"
                            required
                        >
                            <Input
                                :id="id"
                                v-model="form.ends_at"
                                type="time"
                                step="60"
                                :invalid="invalid"
                                :disabled="!canWrite || form.processing"
                                required
                            />
                        </FormField>
                    </div>
                    <p
                        v-if="typeHint"
                        class="mt-3 text-xs text-muted"
                    >
                        {{ typeHint }}
                    </p>
                    <p
                        v-if="!creating && !meal.assigned_to_shifts"
                        class="mt-4 flex items-center gap-2 rounded-lg bg-page p-3 text-sm text-charcoal"
                    >
                        <Icon
                            :name="['fas', 'circle-info']"
                            class="text-muted"
                        />
                        {{ $t('meals.changes_apply') }}
                    </p>
                </Card>
            </form>
        </div>
        <div
            class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-ground py-4 lg:left-[var(--app-sidebar-width)]"
        >
            <div class="container mx-auto px-4 md:px-6">
                <div
                    class="mx-auto flex max-w-6xl items-center justify-between gap-3 xl:max-w-none"
                >
                    <Button
                        href="/meals/settings"
                        variant="cancel"
                        :disabled="form.processing"
                        >{{ $t('ui.dialog.cancel') }}</Button
                    >
                    <Button
                        type="submit"
                        form="meal-form"
                        :disabled="!canWrite || form.processing"
                        :loading="form.processing"
                        >{{
                            creating ? $t('meals.add') : $t('actions.save')
                        }}</Button
                    >
                </div>
            </div>
        </div>
    </AppLayout>
</template>
