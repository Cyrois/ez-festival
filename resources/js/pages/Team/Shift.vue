<script setup>
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { CustomDropdown } from '../../components/ui/custom-dropdown';
import { Dialog } from '../../components/ui/dialog';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { useFlashToast } from '../../composables/useFlashToast';
import { fieldError, toastFormErrors } from '../../lib/fieldError';
import AppLayout from '../../layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: { type: Object, required: true },
    shift: { type: Object, required: true },
    locations: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
});

const form = useForm({
    name: props.shift.name ?? '',
    location_id: props.shift.location_id,
    starts_at: props.shift.starts_at,
    ends_at: props.shift.ends_at,
});
const deleting = ref(false);
const deleteBusy = ref(false);
const { showError, showFormError } = useFlashToast();

const canWrite = computed(() => props.canManage && !props.event.is_locked);
const displayName = computed(
    () => props.shift.name || trans('team.scheduling.unnamed_shift'),
);
const locationItems = computed(() =>
    props.locations.map((location) => ({
        value: location.id,
        title: location.name,
    })),
);
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('team.title'), href: '/team/advancement' },
    {
        label: trans('nav.team.scheduling'),
        href: '/team/scheduling?tab=list',
    },
    { label: displayName.value },
]);

const submit = () => {
    if (!canWrite.value) return;

    form.put('/team/events/' + props.event.id + '/shifts/' + props.shift.id, {
        preserveScroll: true,
        onError: (errors) =>
            toastFormErrors(form, errors, { showError, showFormError }),
    });
};

const destroy = () => {
    if (!canWrite.value) return;

    deleteBusy.value = true;
    router.delete(
        '/team/events/' + props.event.id + '/shifts/' + props.shift.id,
        {
            onError: (errors) => showFormError(errors),
            onFinish: () => {
                deleteBusy.value = false;
            },
        },
    );
};
</script>

<template>
    <AppLayout
        :title="displayName"
        :breadcrumbs="breadcrumbs"
        back-href="/team/scheduling?tab=list"
        :back-label="$t('team.scheduling.actions.back')"
    >
        <div class="container mx-auto max-w-6xl pb-24">
            <header class="mb-5">
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ displayName }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('team.scheduling.shift_lead') }}
                </p>
            </header>

            <p
                v-if="event.is_locked"
                class="mb-4 flex items-center gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm text-charcoal"
                role="status"
            >
                <Icon :name="['fas', 'lock']" />
                {{ $t('team.scheduling.locked') }}
            </p>
            <p
                v-else-if="!canManage"
                class="mb-4 rounded-lg border border-line bg-page p-3 text-sm text-muted"
                role="status"
            >
                {{ $t('team.scheduling.no_permission') }}
            </p>

            <form @submit.prevent="submit">
                <Card>
                    <h2 class="m-0 mb-4 text-xl font-bold text-muted">
                        {{ $t('team.scheduling.shift_section') }}
                    </h2>

                    <dl
                        v-if="!canWrite"
                        class="space-y-4"
                    >
                        <div
                            v-for="field in [
                                'name',
                                'location',
                                'starts_at',
                                'ends_at',
                            ]"
                            :key="field"
                        >
                            <dt class="text-sm text-muted">
                                {{ $t(`team.scheduling.fields.${field}`) }}
                            </dt>
                            <dd class="mt-1">
                                {{
                                    field === 'location'
                                        ? locations.find(
                                              (location) =>
                                                  location.id ===
                                                  shift.location_id,
                                          )?.name
                                        : shift[field]
                                }}
                            </dd>
                        </div>
                    </dl>
                    <div
                        v-else
                        class="space-y-4"
                    >
                        <FormField
                            :label="$t('team.scheduling.fields.name')"
                            :error="fieldError(form, 'name')"
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
                                    :disabled="!canWrite || form.processing"
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
                                    :disabled="!canWrite || form.processing"
                                    :placeholder="
                                        $t(
                                            'team.scheduling.fields.location_placeholder',
                                        )
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
                                        :disabled="!canWrite || form.processing"
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
                                        :disabled="!canWrite || form.processing"
                                    />
                                </template>
                            </FormField>
                        </div>
                    </div>
                </Card>

                <div
                    v-if="canWrite"
                    class="mt-4 flex flex-col-reverse gap-3 sm:flex-row sm:justify-between"
                >
                    <Button
                        type="button"
                        variant="danger"
                        @click="deleting = true"
                    >
                        <Icon
                            :name="['fas', 'trash-can']"
                            size="sm"
                            class="mr-1.5"
                        />
                        {{ $t('team.scheduling.actions.delete') }}
                    </Button>
                    <Button
                        type="submit"
                        :loading="form.processing"
                    >
                        {{ $t('team.scheduling.actions.save') }}
                    </Button>
                </div>
            </form>
        </div>

        <Dialog
            v-model:open="deleting"
            :title="$t('team.scheduling.delete.title')"
            :description="
                $t('team.scheduling.delete.description', {
                    name: displayName,
                })
            "
            :confirm-label="$t('team.scheduling.actions.delete')"
            :cancel-label="$t('ui.dialog.cancel')"
            :busy="deleteBusy"
            @confirm="destroy"
        />
    </AppLayout>
</template>
