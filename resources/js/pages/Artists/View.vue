<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import EngagementNoteLog from '../../components/notes/EngagementNoteLog.vue';
import { Avatar } from '../../components/ui/avatar';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import EngagementPeoplePanel from '../../components/people/EngagementPeoplePanel.vue';
import PassAssignmentsPanel from '../../components/credentials/PassAssignmentsPanel.vue';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { IconButton } from '../../components/ui/icon-button';
import { Input } from '../../components/ui/input';
import { LabelCombobox } from '../../components/ui/label-combobox';
import { Select } from '../../components/ui/select';
import { useFlashToast } from '../../composables/useFlashToast';
import { toastFormErrors } from '../../lib/fieldError';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    engagement: { type: Object, required: true },
    notes: { type: Array, required: true },
    event: { type: Object, required: true },
    types: { type: Array, required: true },
    labels: { type: Array, required: true },
    statuses: { type: Array, required: true },
    labelColors: { type: Array, required: true },
    passes: { type: Array, default: () => [] },
    canWrite: { type: Boolean, required: true },
});

const form = useForm({
    name: props.engagement.name,
    status: props.engagement.status,
    artist_type_id: props.engagement.artist_type_id ?? '',
    label_ids: props.engagement.labels.map((label) => label.id),
    new_labels: [],
});

const { showError, showFormError } = useFlashToast();

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.artists'), href: '/artists/advancing' },
    { label: trans('artists.advancing'), href: '/artists/advancing' },
    { label: trans('artists.view') },
]);

const readOnly = computed(() => !props.canWrite);
const labelError = computed(
    () =>
        form.errors.label_ids ||
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('new_labels.'),
        )?.[1] ||
        '',
);

const submit = () => {
    if (readOnly.value) {
        return;
    }
    form.put(`/artists/engagements/${props.engagement.id}`, {
        onError: (errors) =>
            toastFormErrors(form, errors, { showError, showFormError }),
    });
};
</script>

<template>
    <AppLayout
        :title="engagement.name"
        :breadcrumbs="breadcrumbs"
        back-href="/artists/advancing"
        :back-label="$t('artists.back_to_advancing')"
    >
        <div class="mx-auto max-w-6xl pb-24">
            <div class="mb-5 flex items-center gap-3.5">
                <Avatar
                    :name="engagement.name"
                    size="lg"
                />
                <h1 class="m-0 text-[26px] font-bold tracking-tight">
                    {{ engagement.name }}
                </h1>
            </div>
            <p class="mt-0 mb-5 text-sm text-muted">
                {{ $t('artists.view_lead', { name: event.name }) }}
            </p>

            <p
                v-if="readOnly"
                class="mb-4 flex items-center gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm text-charcoal"
                role="status"
            >
                <Icon :name="['fas', 'lock']" />
                {{ $t('artists.view_locked') }}
            </p>

            <div class="grid items-stretch gap-4 lg:grid-cols-2">
                <Card>
                    <h2 class="m-0 text-xl font-bold text-muted">
                        {{ $t('artists.details') }}
                    </h2>
                    <p class="mt-1 mb-4 text-xs text-muted">
                        {{ $t('artists.details_hint') }}
                    </p>
                    <form
                        id="artist-details"
                        class="space-y-4"
                        @submit.prevent="submit"
                    >
                        <FormField
                            v-slot="{ id, invalid }"
                            :label="$t('artists.name')"
                            :error="form.errors.name"
                            required
                        >
                            <Input
                                :id="id"
                                v-model="form.name"
                                :invalid="invalid"
                                :disabled="readOnly || form.processing"
                                maxlength="255"
                                required
                            />
                        </FormField>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormField
                                v-slot="{ id, invalid }"
                                :label="$t('artists.columns.status')"
                                :error="form.errors.status"
                            >
                                <Select
                                    :id="id"
                                    v-model="form.status"
                                    :invalid="invalid"
                                    :disabled="readOnly || form.processing"
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
                                    :disabled="readOnly || form.processing"
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
                                :disabled="readOnly || form.processing"
                                allow-create
                            />
                        </FormField>
                    </form>
                </Card>
                <Card class="flex flex-col">
                    <EngagementPeoplePanel
                        :people="engagement.people"
                        :base-path="`/artists/engagements/${engagement.id}`"
                        :can-write="canWrite"
                    />
                </Card>
            </div>

            <Card class="mt-4">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="m-0 text-xl font-bold text-muted">
                        {{ $t('artists.custom_fields') }}
                    </h2>
                    <IconButton
                        href="/settings/custom-fields"
                        :icon="['fas', 'gear']"
                        :label="$t('artists.custom_fields_manage')"
                    />
                </div>
                <div
                    class="mt-3 rounded-lg border border-dashed border-line bg-page p-5 text-center text-sm text-muted"
                >
                    {{ $t('artists.custom_fields_shell') }}
                </div>
            </Card>

            <Card
                id="passes"
                class="mt-4"
            >
                <PassAssignmentsPanel
                    :assignments="engagement.pass_assignments"
                    :people="engagement.people"
                    :passes="passes"
                    :base-path="`/artists/engagements/${engagement.id}`"
                    :can-write="canWrite"
                />
            </Card>

            <Card class="mt-4">
                <h2 class="m-0 text-xl font-bold text-muted">
                    {{ $t('artists.contracts_phase') }}
                </h2>
                <div
                    class="mt-3 rounded-lg border border-dashed border-line bg-page p-5 text-center text-sm text-muted"
                >
                    {{ $t('artists.contracts_phase') }}
                </div>
            </Card>

            <EngagementNoteLog
                class="mt-4"
                :notes="notes"
                :post-url="`/artists/engagements/${engagement.id}/notes`"
                :can-write="canWrite"
                :timezone="event.timezone"
                translation-namespace="artists"
            />

            <div
                v-if="!readOnly"
                class="fixed right-0 bottom-0 left-0 z-30 border-t border-line bg-ground/95 py-3 backdrop-blur lg:left-56"
            >
                <div class="container mx-auto px-4 md:px-6">
                    <div
                        class="mx-auto flex max-w-6xl items-center justify-between"
                    >
                        <Button
                            href="/artists/advancing"
                            variant="cancel"
                            :disabled="form.processing"
                        >
                            {{ $t('setup.actions.cancel') }}
                        </Button>
                        <Button
                            form="artist-details"
                            type="submit"
                            :loading="form.processing"
                        >
                            {{ $t('artists.save_details') }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
