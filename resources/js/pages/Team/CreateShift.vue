<script setup>
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { CustomDropdown } from '../../components/ui/custom-dropdown';
import { FormField } from '../../components/ui/form-field';
import { Input } from '../../components/ui/input';
import { useFlashToast } from '../../composables/useFlashToast';
import { fieldError, toastFormErrors } from '../../lib/fieldError';
import AppLayout from '../../layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: { type: Object, required: true },
    locations: { type: Array, required: true },
});

const form = useForm({
    name: '',
    location_id: props.locations[0]?.id ?? '',
    starts_at: '',
    ends_at: '',
});
const { showError, showFormError } = useFlashToast();

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
    { label: trans('team.scheduling.create.title') },
]);

const submit = () => {
    form.post('/team/events/' + props.event.id + '/shifts', {
        onError: (errors) =>
            toastFormErrors(form, errors, { showError, showFormError }),
    });
};
</script>

<template>
    <AppLayout
        :title="$t('team.scheduling.create.title')"
        :breadcrumbs="breadcrumbs"
        back-href="/team/scheduling?tab=list"
        :back-label="$t('team.scheduling.actions.back')"
    >
        <div class="container mx-auto max-w-6xl pb-24">
            <header class="mb-6">
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('team.scheduling.create.title') }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{
                        $t('team.scheduling.create.lead', {
                            event: event.name,
                        })
                    }}
                </p>
            </header>

            <Card>
                <form
                    class="space-y-5"
                    @submit.prevent="submit"
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
                                :empty-text="$t('team.scheduling.no_locations')"
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

                    <div
                        class="flex justify-end gap-2 border-t border-line pt-5"
                    >
                        <Button
                            href="/team/scheduling?tab=list"
                            variant="ghost"
                            :disabled="form.processing"
                        >
                            {{ $t('ui.dialog.cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            :loading="form.processing"
                            :disabled="locations.length === 0"
                        >
                            {{ $t('team.scheduling.actions.create') }}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
