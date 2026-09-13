<script setup>
import SetupLayout from '../../layouts/SetupLayout.vue';
import { Button } from '../../components/ui/button';
import { FormField } from '../../components/ui/form-field';
import { Input } from '../../components/ui/input';
import { Select } from '../../components/ui/select';
import { useFlashToast } from '../../composables/useFlashToast';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    organization: { type: Object, required: true },
    event: { type: Object, default: null },
    timezones: { type: Array, required: true },
    currentStep: { type: Number, required: true },
});

const { showError, showSuccess } = useFlashToast();

const form = useForm({
    name: props.event?.name ?? '',
    starts_on: props.event?.starts_on ?? '',
    ends_on: props.event?.ends_on ?? '',
    timezone: props.event?.timezone ?? 'America/Vancouver',
});

const fieldError = (key) => {
    const error = form.errors[key];
    if (!error) {
        return '';
    }

    const value = form[key];
    if (value === '' || value === null || value === undefined) {
        return trans('setup.errors.required');
    }

    return error;
};

const submit = () =>
    form.post('/setup/event', {
        onSuccess: () => showSuccess(trans('setup.toast.event_saved')),
        onError: () => showError(trans('setup.errors.required_fields')),
    });

const skip = () =>
    form.post('/setup/event/skip', {
        onError: (errors) => {
            const values = Object.values(errors ?? {});
            let first = values[0];
            if (Array.isArray(first)) {
                first = first[0];
            }
            showError(
                typeof first === 'string' && first
                    ? first
                    : trans('setup.errors.generic'),
            );
        },
    });
</script>

<template>
    <SetupLayout
        :title="$t('setup.event.title')"
        :current-step="currentStep"
        :organization-name="organization.name"
    >
        <div class="mb-5">
            <h1 class="m-0 mb-1.5 text-[28px] font-bold tracking-tight">
                {{ $t('setup.event.heading') }}
            </h1>
            <p class="m-0 text-sm leading-snug text-muted">
                {{ $t('setup.event.lead') }}
            </p>
        </div>

        <form @submit.prevent="submit">
            <div class="rounded-xl border border-line bg-ground px-6 py-6">
                <FormField
                    :label="$t('setup.event.name')"
                    :error="fieldError('name')"
                    required
                    class="mb-4"
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="form.name"
                            type="text"
                            :placeholder="$t('setup.event.name_placeholder')"
                            :invalid="invalid"
                            autocomplete="off"
                        />
                    </template>
                </FormField>

                <div class="mb-4 grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                    <FormField
                        :label="$t('setup.event.starts_on')"
                        :error="fieldError('starts_on')"
                        required
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="form.starts_on"
                                type="date"
                                :placeholder="
                                    $t('setup.event.starts_on_placeholder')
                                "
                                :invalid="invalid"
                            />
                        </template>
                    </FormField>
                    <FormField
                        :label="$t('setup.event.ends_on')"
                        :error="fieldError('ends_on')"
                        required
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="form.ends_on"
                                type="date"
                                :placeholder="
                                    $t('setup.event.ends_on_placeholder')
                                "
                                :invalid="invalid"
                            />
                        </template>
                    </FormField>
                </div>

                <FormField
                    :label="$t('setup.event.timezone')"
                    :error="fieldError('timezone')"
                    :hint="$t('setup.event.timezone_hint')"
                    required
                >
                    <template #default="{ id, invalid }">
                        <Select
                            :id="id"
                            v-model="form.timezone"
                            :invalid="invalid"
                        >
                            <option
                                v-for="tz in timezones"
                                :key="tz"
                                :value="tz"
                            >
                                {{ tz }}
                            </option>
                        </Select>
                    </template>
                </FormField>
            </div>

            <div class="mt-5 flex items-center justify-end gap-4">
                <Button
                    type="button"
                    variant="ghost"
                    class="text-secondary hover:bg-secondary-soft hover:text-secondary"
                    :disabled="form.processing"
                    @click="skip"
                >
                    {{ $t('setup.actions.skip') }}
                </Button>
                <Button
                    type="submit"
                    variant="primary"
                    :loading="form.processing"
                    :disabled="form.processing"
                >
                    {{ $t('setup.actions.save_continue') }}
                </Button>
            </div>
        </form>
    </SetupLayout>
</template>
