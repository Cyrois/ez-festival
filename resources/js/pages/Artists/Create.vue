<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { FormField } from '../../components/ui/form-field';
import { Input } from '../../components/ui/input';
import { LabelCombobox } from '../../components/ui/label-combobox';
import { Select } from '../../components/ui/select';
import { useFlashToast } from '../../composables/useFlashToast';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: { type: Object, required: true },
    types: { type: Array, required: true },
    labels: { type: Array, required: true },
    statuses: { type: Array, required: true },
    labelColors: { type: Array, required: true },
});
const form = useForm({
    name: '',
    status: 'idea',
    artist_type_id: '',
    label_ids: [],
    new_labels: [],
});
const { showFormError } = useFlashToast();
const breadcrumbs = computed(() => [
    { label: trans('nav.artists'), href: '/artists/advancing' },
    { label: trans('artists.add') },
]);
const labelError = computed(
    () =>
        form.errors.label_ids ||
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('new_labels.'),
        )?.[1] ||
        '',
);
const submit = () =>
    form.post(`/events/${props.event.id}/artists`, { onError: showFormError });
</script>

<template>
    <AppLayout
        :title="$t('artists.add')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="max-w-2xl">
            <div class="mb-6">
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('artists.add') }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('artists.create_lead', { name: event.name }) }}
                </p>
            </div>
            <Card>
                <form
                    class="space-y-5"
                    @submit.prevent="submit"
                >
                    <FormField
                        v-slot="{ id, invalid }"
                        :label="$t('artists.name')"
                        :error="form.errors.name"
                        :hint="$t('artists.name_hint')"
                        required
                    >
                        <Input
                            :id="id"
                            v-model="form.name"
                            :invalid="invalid"
                            maxlength="255"
                            required
                            autofocus
                        />
                    </FormField>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <FormField
                            v-slot="{ id, invalid }"
                            :label="$t('artists.columns.status')"
                            :error="form.errors.status"
                        >
                            <Select
                                :id="id"
                                v-model="form.status"
                                :invalid="invalid"
                            >
                                <option
                                    v-for="status in statuses"
                                    :key="status"
                                    :value="status"
                                >
                                    {{ $t(`artists.status.${status}`) }}
                                </option>
                            </Select>
                        </FormField>
                        <FormField
                            v-slot="{ id, invalid }"
                            :label="$t('artists.columns.type')"
                            :error="form.errors.artist_type_id"
                        >
                            <Select
                                :id="id"
                                v-model="form.artist_type_id"
                                :invalid="invalid"
                            >
                                <option value="">
                                    {{ $t('artists.type_optional') }}
                                </option>
                                <option
                                    v-for="type in types"
                                    :key="type.id"
                                    :value="type.id"
                                >
                                    {{ type.name }}
                                </option>
                            </Select>
                        </FormField>
                    </div>
                    <FormField
                        v-slot="{ id, invalid }"
                        :label="$t('artists.columns.labels')"
                        :error="labelError"
                        :hint="$t('artists.labels_hint')"
                    >
                        <LabelCombobox
                            :id="id"
                            v-model="form.label_ids"
                            v-model:new-labels="form.new_labels"
                            :labels="labels"
                            :colors="labelColors"
                            :invalid="invalid"
                            :disabled="form.processing"
                            allow-create
                        />
                    </FormField>
                    <div
                        class="flex justify-end gap-2 border-t border-line pt-5"
                    >
                        <Button
                            href="/artists/advancing"
                            variant="ghost"
                            :disabled="form.processing"
                            >{{ $t('setup.actions.cancel') }}</Button
                        >
                        <Button
                            type="submit"
                            :loading="form.processing"
                            >{{ $t('artists.save') }}</Button
                        >
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
