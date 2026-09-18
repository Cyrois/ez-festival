<script setup>
import SettingsLayout from '../../../layouts/SettingsLayout.vue';
import { Button } from '../../../components/ui/button';
import { Card } from '../../../components/ui/card';
import { FormField } from '../../../components/ui/form-field';
import { Input } from '../../../components/ui/input';
import { Select } from '../../../components/ui/select';
import { useFlashToast } from '../../../composables/useFlashToast';
import { fieldError, toastFormErrors } from '../../../lib/fieldError';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    timezones: {
        type: Array,
        required: true,
    },
});

const { showError, showSuccess, showFormError } = useFlashToast();

const form = useForm({
    name: '',
    starts_on: '',
    ends_on: '',
    timezone: 'America/Vancouver',
});

const breadcrumbs = computed(() => [
    {
        label: trans('app.name'),
        href: '/dashboard',
    },
    {
        label: trans('nav.settings'),
        href: '/settings/events',
    },
    {
        label: trans('settings.events.title'),
        href: '/settings/events',
    },
    {
        label: trans('settings.events.create.title'),
    },
]);

const submit = () => {
    form.post('/settings/events', {
        onSuccess: () => showSuccess(trans('settings.events.toast.created')),
        onError: (errors) =>
            toastFormErrors(form, errors, { showError, showFormError }),
    });
};
</script>

<template>
    <SettingsLayout
        :title="$t('settings.events.create.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="mb-6">
            <h1 class="m-0 text-2xl font-bold tracking-tight">
                {{ $t('settings.events.create.title') }}
            </h1>
            <p class="mt-1 mb-0 text-sm text-muted">
                {{ $t('settings.events.create.lead') }}
            </p>
        </div>

        <Card class="p-6">
            <form @submit.prevent="submit">
                <FormField
                    :label="$t('settings.events.fields.name')"
                    :error="fieldError(form, 'name')"
                    required
                    class="mb-4"
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="form.name"
                            type="text"
                            :invalid="invalid"
                            autocomplete="off"
                        />
                    </template>
                </FormField>

                <div class="mb-4 grid grid-cols-1 gap-3.5">
                    <FormField
                        :label="$t('settings.events.fields.starts_on')"
                        :error="fieldError(form, 'starts_on')"
                        required
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="form.starts_on"
                                type="date"
                                :invalid="invalid"
                            />
                        </template>
                    </FormField>
                    <FormField
                        :label="$t('settings.events.fields.ends_on')"
                        :error="fieldError(form, 'ends_on')"
                        required
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="form.ends_on"
                                type="date"
                                :invalid="invalid"
                            />
                        </template>
                    </FormField>
                </div>

                <FormField
                    :label="$t('settings.events.fields.timezone')"
                    :error="fieldError(form, 'timezone')"
                    required
                    class="mb-6"
                >
                    <template #default="{ id, invalid }">
                        <Select
                            :id="id"
                            v-model="form.timezone"
                            :invalid="invalid"
                        >
                            <option
                                v-for="timezone in props.timezones"
                                :key="timezone"
                                :value="timezone"
                            >
                                {{ timezone }}
                            </option>
                        </Select>
                    </template>
                </FormField>

                <div
                    class="flex flex-col gap-2 sm:flex-row sm:justify-end sm:gap-3"
                >
                    <Button
                        type="submit"
                        variant="primary"
                        class="min-h-11 w-full sm:w-auto"
                        :loading="form.processing"
                        :disabled="form.processing"
                    >
                        {{ $t('settings.events.actions.create') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-11 w-full sm:w-auto"
                        href="/settings/events"
                    >
                        {{ $t('settings.events.actions.cancel') }}
                    </Button>
                </div>
            </form>
        </Card>
    </SettingsLayout>
</template>
