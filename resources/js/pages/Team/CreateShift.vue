<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import { Card } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { trans } from 'laravel-vue-i18n';
import { CustomDropdown } from '../../components/ui/custom-dropdown';
import { FormField } from '../../components/ui/form-field';
import { Input } from '../../components/ui/input';
import ShiftRoleSlots from '../../components/team/ShiftRoleSlots.vue';
import { useFlashToast } from '../../composables/useFlashToast';
import { fieldError, toastFormErrors } from '../../lib/fieldError';
import { shiftSlotPayload, shiftSlotErrors } from '../../lib/shiftRoleSlots';

const props = defineProps({
    event: { type: Object, required: true },
    locations: { type: Array, required: true },
    roles: { type: Array, required: true },
});
const form = useForm({
    name: '',
    location_id: props.locations[0]?.id ?? '',
    starts_at: '',
    ends_at: '',
    slots: [],
});
const slotErrors = ref({});
const { showError, showFormError } = useFlashToast();
const locationItems = computed(() =>
    props.locations.map((location) => ({
        value: location.id,
        title: location.name,
    })),
);
const submit = () => {
    if (form.processing) return;
    const submitted = [...form.slots];
    form.transform((data) => ({
        ...data,
        slots: shiftSlotPayload(data.slots),
    })).post('/team/events/' + props.event.id + '/shifts', {
        onError: (errors) => {
            slotErrors.value = shiftSlotErrors(submitted, errors);
            if (Object.keys(errors).some((key) => key.startsWith('slots'))) {
                showFormError(errors);
            } else {
                toastFormErrors(form, errors, { showError, showFormError });
            }
        },
    });
};
const clearSlotError = (key, field) => {
    if (slotErrors.value[key]) delete slotErrors.value[key][field];
};
</script>

<template>
    <AppLayout
        :title="$t('team.scheduling.actions.new')"
        :breadcrumbs="[
            { label: trans('app.name'), href: '/dashboard' },
            { label: trans('team.title'), href: '/team/advancement' },
            {
                label: trans('nav.team.scheduling'),
                href: '/team/scheduling?tab=list',
            },
            { label: trans('team.scheduling.actions.new') },
        ]"
        back-href="/team/scheduling?tab=list"
        :back-label="$t('team.scheduling.actions.back')"
    >
        <div class="container mx-auto max-w-6xl pb-24">
            <h1 class="mb-5 text-2xl font-bold tracking-tight">
                {{ $t('team.scheduling.actions.new') }}
            </h1>
            <p
                v-if="!locations.length"
                class="mb-4 text-sm text-muted"
                role="status"
            >
                {{ $t('team.scheduling.no_locations') }}
            </p>
            <form
                id="create-shift-form"
                class="space-y-5"
                novalidate
                @submit.prevent="submit"
            >
                <Card>
                    <div class="space-y-5">
                        <FormField
                            :label="$t('team.scheduling.fields.name')"
                            :error="fieldError(form, 'name')"
                            required
                        >
                            <template #default="{ id, invalid }">
                                <Input
                                    :id="id"
                                    v-model="form.name"
                                    :invalid="invalid"
                                    :placeholder="
                                        $t(
                                            'team.scheduling.fields.name_placeholder',
                                        )
                                    "
                                    :disabled="form.processing"
                                />
                            </template>
                        </FormField>

                        <FormField
                            :label="$t('team.scheduling.fields.location')"
                            :error="fieldError(form, 'location_id')"
                            required
                        >
                            <template #default="{ id, invalid }">
                                <CustomDropdown
                                    :id="id"
                                    v-model="form.location_id"
                                    :items="locationItems"
                                    :invalid="invalid"
                                    :disabled="form.processing"
                                    :placeholder="
                                        $t(
                                            'team.scheduling.fields.location_placeholder',
                                        )
                                    "
                                    :empty-text="
                                        $t('team.scheduling.no_locations')
                                    "
                                />
                            </template>
                        </FormField>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormField
                                :label="$t('team.scheduling.fields.start')"
                                :error="fieldError(form, 'starts_at')"
                                required
                            >
                                <template #default="{ id, invalid }">
                                    <Input
                                        :id="id"
                                        v-model="form.starts_at"
                                        type="datetime-local"
                                        :max="form.ends_at || undefined"
                                        :invalid="invalid"
                                        :disabled="form.processing"
                                    />
                                </template>
                            </FormField>
                            <FormField
                                :label="$t('team.scheduling.fields.end')"
                                :error="fieldError(form, 'ends_at')"
                                required
                            >
                                <template #default="{ id, invalid }">
                                    <Input
                                        :id="id"
                                        v-model="form.ends_at"
                                        type="datetime-local"
                                        :min="form.starts_at || undefined"
                                        :invalid="invalid"
                                        :disabled="form.processing"
                                    />
                                </template>
                            </FormField>
                        </div>
                    </div>
                </Card>
                <Card class="mt-4">
                    <ShiftRoleSlots
                        v-model="form.slots"
                        :roles="roles"
                        :errors="slotErrors"
                        :busy="form.processing"
                        :title="$t('team.scheduling.slots.create_title')"
                        @clear-error="clearSlotError"
                    />
                    <p
                        v-if="form.errors.slots"
                        class="text-sm text-danger"
                        role="alert"
                    >
                        {{ form.errors.slots }}
                    </p>
                </Card>
            </form>
        </div>
        <footer
            class="fixed right-0 bottom-0 left-0 z-20 border-t border-line bg-ground lg:left-[var(--app-sidebar-width)]"
        >
            <div class="container mx-auto px-4 md:px-6">
                <div
                    class="mx-auto flex max-w-6xl items-center justify-between gap-3 py-4"
                >
                    <Button
                        href="/team/scheduling?tab=list"
                        variant="cancel"
                        >{{ $t('ui.dialog.cancel') }}</Button
                    >
                    <Button
                        type="submit"
                        form="create-shift-form"
                        :loading="form.processing"
                        :disabled="form.processing || !locations.length"
                        >{{ $t('team.scheduling.actions.create') }}</Button
                    >
                </div>
            </div>
        </footer>
    </AppLayout>
</template>
